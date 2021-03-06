<?php
declare(strict_types=1);

namespace Ecotone\Messaging\Config\Annotation\ModuleConfiguration;

use Ecotone\Messaging\Annotation\MessageGateway;
use Ecotone\Messaging\Annotation\MessageEndpoint;
use Ecotone\Messaging\Annotation\ModuleAnnotation;
use Ecotone\Messaging\Annotation\Parameter\Header;
use Ecotone\Messaging\Annotation\Parameter\Headers;
use Ecotone\Messaging\Annotation\Parameter\HeaderValue;
use Ecotone\Messaging\Annotation\Parameter\Payload;
use Ecotone\Messaging\Config\Annotation\AnnotationModule;
use Ecotone\Messaging\Config\Annotation\AnnotationRegistrationService;
use Ecotone\Messaging\Config\Configuration;
use Ecotone\Messaging\Config\ConfigurationException;
use Ecotone\Messaging\Config\ModuleReferenceSearchService;
use Ecotone\Messaging\Handler\Gateway\GatewayBuilder;
use Ecotone\Messaging\Handler\Gateway\GatewayProxyBuilder;
use Ecotone\Messaging\Handler\Gateway\ParameterToMessageConverter\GatewayHeaderExpressionBuilder;
use Ecotone\Messaging\Handler\Gateway\ParameterToMessageConverter\GatewayHeadersBuilder;
use Ecotone\Messaging\Handler\Gateway\ParameterToMessageConverter\GatewayHeaderBuilder;
use Ecotone\Messaging\Handler\Gateway\ParameterToMessageConverter\GatewayHeaderValueBuilder;
use Ecotone\Messaging\Handler\Gateway\ParameterToMessageConverter\GatewayPayloadBuilder;
use Ecotone\Messaging\Handler\Gateway\ParameterToMessageConverter\GatewayPayloadExpressionBuilder;

/**
 * Class AnnotationGatewayConfiguration
 * @package Ecotone\Messaging\Config\Annotation\AnnotationToBuilder
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 * @ModuleAnnotation()
 */
class GatewayModule extends NoExternalConfigurationModule implements AnnotationModule
{
    public const MODULE_NAME = 'gatewayModule';

    /**
     * @var GatewayBuilder[]
     */
    private $gatewayBuilders = [];

    /**
     * AnnotationGatewayConfiguration constructor.
     *
     * @param GatewayBuilder[] $gatewayBuilders
     */
    private function __construct(array $gatewayBuilders)
    {
        $this->gatewayBuilders = $gatewayBuilders;
    }

    /**
     * @inheritDoc
     */
    public static function create(AnnotationRegistrationService $annotationRegistrationService): AnnotationModule
    {
        $gatewayBuilders = [];
        foreach ($annotationRegistrationService->findRegistrationsFor(MessageEndpoint::class, MessageGateway::class) as $annotationRegistration) {
            /** @var \Ecotone\Messaging\Annotation\MessageGateway $annotation */
            $annotation = $annotationRegistration->getAnnotationForMethod();

            $parameterConverters = [];
            foreach ($annotation->parameterConverters as $parameterToMessage) {
                if ($parameterToMessage instanceof Payload) {
                    if ($parameterToMessage->expression) {
                        $parameterConverters[] = GatewayPayloadExpressionBuilder::create($parameterToMessage->parameterName, $parameterToMessage->expression);
                    } else {
                        $parameterConverters[] = GatewayPayloadBuilder::create($parameterToMessage->parameterName);
                    }
                } else if ($parameterToMessage instanceof Header) {
                    if ($parameterToMessage->expression) {
                        throw ConfigurationException::create("@Header annotation for Gateway ({$annotationRegistration->getReferenceName()}) cannot be used with expression");
                    }else {
                        $parameterConverters[] = GatewayHeaderBuilder::create($parameterToMessage->parameterName, $parameterToMessage->headerName);
                    }
                } else if ($parameterToMessage instanceof Headers) {
                    $parameterConverters[] = GatewayHeadersBuilder::create($parameterToMessage->parameterName);
                } else if ($parameterToMessage instanceof HeaderValue) {
                    $parameterConverters[] = GatewayHeaderValueBuilder::create($parameterToMessage->headerName, $parameterToMessage->headerValue);
                }else {
                    $converterClass = get_class($parameterToMessage);
                    throw new \InvalidArgumentException("Not known converters for gateway {$converterClass} for {$annotationRegistration->getClassName()}::{$annotationRegistration->getMethodName()}. Have you registered converter starting with name @MessageGateway(...) e.g. @MessageGatewayHeader?");
                }
            }

            $gatewayBuilders[] = GatewayProxyBuilder::create($annotationRegistration->getReferenceName(), $annotationRegistration->getClassName(), $annotationRegistration->getMethodName(), $annotation->requestChannel)
                ->withErrorChannel($annotation->errorChannel)
                ->withParameterConverters($parameterConverters)
                ->withRequiredInterceptorNames($annotation->requiredInterceptorNames)
                ->withReplyMillisecondTimeout($annotation->replyTimeoutInMilliseconds);
        }

        return new self($gatewayBuilders);
    }

    /**
     * @inheritDoc
     */
    public function canHandle($extensionObject): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return self::MODULE_NAME;
    }

    /**
     * @inheritDoc
     */
    public function prepare(Configuration $configuration, array $extensionObjects, ModuleReferenceSearchService $moduleReferenceSearchService): void
    {
        foreach ($this->gatewayBuilders as $gatewayBuilder) {
            $configuration->registerGatewayBuilder($gatewayBuilder);
        }
    }
}