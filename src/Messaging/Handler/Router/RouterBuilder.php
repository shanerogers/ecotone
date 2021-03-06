<?php declare(strict_types=1);

namespace Ecotone\Messaging\Handler\Router;

use Ecotone\Messaging\Config\ReferenceTypeFromNameResolver;
use Ecotone\Messaging\Handler\ChannelResolver;
use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\InterfaceToCallRegistry;
use Ecotone\Messaging\Handler\MessageHandlerBuilder;
use Ecotone\Messaging\Handler\MessageHandlerBuilderWithParameterConverters;
use Ecotone\Messaging\Handler\ParameterConverter;
use Ecotone\Messaging\Handler\ParameterConverterBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInvoker;
use Ecotone\Messaging\Handler\ReferenceSearchService;
use Ecotone\Messaging\MessageHandler;
use Ecotone\Messaging\Support\Assert;

/**
 * Class RouterBuilder
 * @package Ecotone\Messaging\Handler\Router
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class RouterBuilder implements MessageHandlerBuilderWithParameterConverters
{
    /**
     * @var string
     */
    private $inputMessageChannelName;
    /**
     * @var string
     */
    private $objectToInvokeReference;
    /**
     * @var object
     */
    private $directObjectToInvoke;
    /**
     * @var string
     */
    private $methodName;
    /**
     * @var array|ParameterConverterBuilder[]
     */
    private $methodParameterConverters = [];
    /**
     * @var bool
     */
    private $resolutionRequired = true;
    /**
     * @var string[]
     */
    private $requiredReferenceNames = [];
    /**
     * @var string|null
     */
    private $defaultResolution = null;
    /**
     * @var bool
     */
    private $applySequence = false;
    /**
     * @var string
     */
    private $endpointId = "";

    /**
     * RouterBuilder constructor.
     * @param string $objectToInvokeReference
     * @param string $methodName
     */
    private function __construct(string $objectToInvokeReference, string $methodName)
    {
        $this->objectToInvokeReference = $objectToInvokeReference;
        $this->methodName = $methodName;

        if ($objectToInvokeReference) {
            $this->requiredReferenceNames[] = $objectToInvokeReference;
        }
    }

    /**
     * @param string $objectToInvokeReference
     * @param string $methodName
     * @return RouterBuilder
     */
    public static function create(string $objectToInvokeReference, string $methodName) : self
    {
        return new self($objectToInvokeReference, $methodName);
    }

    /**
     * @param array $typeToChannelMapping
     * @return RouterBuilder
     * @throws \Ecotone\Messaging\MessagingException
     */
    public static function createPayloadTypeRouter(array $typeToChannelMapping) : self
    {
        $routerBuilder = self::create("", 'route');
        $routerBuilder->setObjectToInvoke(PayloadTypeRouter::create($typeToChannelMapping));

        return $routerBuilder;
    }

    /**
     * @inheritDoc
     */
    public function resolveRelatedInterfaces(InterfaceToCallRegistry $interfaceToCallRegistry) : iterable
    {
        return [$this->directObjectToInvoke
                ? $interfaceToCallRegistry->getFor($this->directObjectToInvoke, $this->methodName)
                : $interfaceToCallRegistry->getForReferenceName($this->objectToInvokeReference, $this->methodName)];
    }

    /**
     * @param $customRouterObject
     * @param string $methodName
     * @return RouterBuilder
     * @throws \Ecotone\Messaging\MessagingException
     */
    public static function createRouterFromObject($customRouterObject, string $methodName) : self
    {
        Assert::isObject($customRouterObject, "Custom router must be object");

        $routerBuilder = self::create( "", $methodName);
        $routerBuilder->setObjectToInvoke($customRouterObject);

        return $routerBuilder;
    }

    /**
     * @return RouterBuilder
     * @throws \Ecotone\Messaging\MessagingException
     */
    public static function createPayloadTypeRouterByClassName() : self
    {
        $routerBuilder = self::create("", 'route');
        $routerBuilder->setObjectToInvoke(PayloadTypeRouter::createWithRoutingByClass());

        return $routerBuilder;
    }

    /**
     * @param array  $recipientLists
     *
     * @return RouterBuilder
     * @throws \Ecotone\Messaging\MessagingException
     */
    public static function createRecipientListRouter(array $recipientLists) : self
    {
        $routerBuilder = self::create( "", 'route');
        $routerBuilder->setObjectToInvoke(new RecipientListRouter($recipientLists));

        return $routerBuilder;
    }

    /**
     * @param string $headerName
     * @param array $headerValueToChannelMapping
     * @return RouterBuilder
     * @throws \Ecotone\Messaging\MessagingException
     */
    public static function createHeaderValueRouter(string $headerName, array $headerValueToChannelMapping) : self
    {
        $routerBuilder = self::create("", 'route');
        $routerBuilder->setObjectToInvoke(HeaderValueRouter::create($headerName, $headerValueToChannelMapping));

        return $routerBuilder;
    }

    /**
     * @inheritDoc
     */
    public function getRequiredReferenceNames(): array
    {
        $requiredReferenceNames = $this->requiredReferenceNames;
        $requiredReferenceNames[] = $this->objectToInvokeReference;

        return $requiredReferenceNames;
    }

    /**
     * @inheritDoc
     */
    public function withMethodParameterConverters(array $methodParameterConverterBuilders) : self
    {
        Assert::allInstanceOfType($methodParameterConverterBuilders, ParameterConverterBuilder::class);

        $this->methodParameterConverters = $methodParameterConverterBuilders;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getParameterConverters(): array
    {
        return $this->methodParameterConverters;
    }

    /**
     * @inheritDoc
     */
    public function withInputChannelName(string $inputChannelName): self
    {
        $this->inputMessageChannelName = $inputChannelName;

        return $this;
    }

    /**
     * @param string $channelName
     * @return RouterBuilder
     */
    public function withDefaultResolutionChannel(string $channelName) : self
    {
        $this->defaultResolution = $channelName;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function build(ChannelResolver $channelResolver, ReferenceSearchService $referenceSearchService) : MessageHandler
    {
        $objectToInvoke = $this->directObjectToInvoke ? $this->directObjectToInvoke : $referenceSearchService->get($this->objectToInvokeReference);

        return Router::create(
            $channelResolver,
            MethodInvoker::createWith($objectToInvoke, $this->methodName, $this->methodParameterConverters, $referenceSearchService),
            $this->resolutionRequired,
            $this->defaultResolution,
            $this->applySequence
        );
    }

    /**
     * @param bool $resolutionRequired
     * @return RouterBuilder
     */
    public function setResolutionRequired(bool $resolutionRequired) : self
    {
        $this->resolutionRequired = $resolutionRequired;

        return $this;
    }

    /**
     * @param bool $applySequence
     *
     * @return RouterBuilder
     */
    public function withApplySequence(bool $applySequence) : self
    {
        $this->applySequence = $applySequence;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getInputMessageChannelName(): string
    {
        return $this->inputMessageChannelName;
    }

    /**
     * @inheritDoc
     */
    public function getEndpointId(): ?string
    {
        return $this->endpointId;
    }

    /**
     * @inheritDoc
     */
    public function withEndpointId(string $endpointId)
    {
        $this->endpointId = $endpointId;

        return $this;
    }

    /**
     * @param object $objectToInvoke
     * @throws \Ecotone\Messaging\MessagingException
     */
    private function setObjectToInvoke($objectToInvoke) : void
    {
        Assert::isObject($objectToInvoke, "Object to invoke in router must be object");

        $this->directObjectToInvoke = $objectToInvoke;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf("Router for input channel `%s` with name `%s`", $this->inputMessageChannelName, $this->getEndpointId());
    }
}