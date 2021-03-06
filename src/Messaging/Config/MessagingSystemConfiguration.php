<?php
declare(strict_types=1);

namespace Ecotone\Messaging\Config;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Ecotone\Messaging\Annotation\PollableEndpoint;
use Ecotone\Messaging\Annotation\WithRequiredReferenceNameList;
use Ecotone\Messaging\Channel\ChannelInterceptorBuilder;
use Ecotone\Messaging\Channel\MessageChannelBuilder;
use Ecotone\Messaging\Channel\SimpleMessageChannelBuilder;
use Ecotone\Messaging\Config\Annotation\AnnotationModuleRetrievingService;
use Ecotone\Messaging\Config\Annotation\FileSystemAnnotationRegistrationService;
use Ecotone\Messaging\Config\Annotation\AutoloadFileNamespaceParser;
use Ecotone\Messaging\Config\BeforeSend\BeforeSendChannelInterceptorBuilder;
use Ecotone\Messaging\Conversion\AutoCollectionConversionService;
use Ecotone\Messaging\Conversion\ConversionService;
use Ecotone\Messaging\Conversion\ConverterBuilder;
use Ecotone\Messaging\Endpoint\ChannelAdapterConsumerBuilder;
use Ecotone\Messaging\Endpoint\MessageHandlerConsumerBuilder;
use Ecotone\Messaging\Endpoint\PollingMetadata;
use Ecotone\Messaging\Handler\Bridge\Bridge;
use Ecotone\Messaging\Handler\Bridge\BridgeBuilder;
use Ecotone\Messaging\Handler\Chain\ChainMessageHandlerBuilder;
use Ecotone\Messaging\Handler\ErrorHandler\RetryTemplateBuilder;
use Ecotone\Messaging\Handler\Gateway\GatewayBuilder;
use Ecotone\Messaging\Handler\Gateway\ProxyFactory;
use Ecotone\Messaging\Handler\InMemoryReferenceSearchService;
use Ecotone\Messaging\Handler\InterceptedEndpoint;
use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\InterfaceToCallRegistry;
use Ecotone\Messaging\Handler\MessageHandlerBuilder;
use Ecotone\Messaging\Handler\MessageHandlerBuilderWithOutputChannel;
use Ecotone\Messaging\Handler\MessageHandlerBuilderWithParameterConverters;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\AroundInterceptorReference;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\InterceptorWithPointCut;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInterceptor;
use Ecotone\Messaging\Handler\ReferenceNotFoundException;
use Ecotone\Messaging\Handler\ReferenceSearchService;
use Ecotone\Messaging\Handler\ServiceActivator\ServiceActivatorBuilder;
use Ecotone\Messaging\Handler\Transformer\TransformerBuilder;
use Ecotone\Messaging\Handler\Type;
use Ecotone\Messaging\MessageHeaders;
use Ecotone\Messaging\MessagingException;
use Ecotone\Messaging\Support\Assert;
use Ecotone\Messaging\Support\InvalidArgumentException;
use Ecotone\Modelling\CommandBus;
use Ecotone\Modelling\EventBus;
use Ecotone\Modelling\LazyEventBus\LazyEventPublishing;
use Exception;
use Ramsey\Uuid\Uuid;
use ReflectionException;

/**
 * Class Configuration
 * @package Ecotone\Messaging\Config
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
final class MessagingSystemConfiguration implements Configuration
{
    /**
     * @var MessageChannelBuilder[]
     */
    private $channelBuilders = [];
    /**
     * @var MessageChannelBuilder[]
     */
    private $defaultChannelBuilders = [];
    /**
     * @var ChannelInterceptorBuilder[]
     */
    private $channelInterceptorBuilders = [];
    /**
     * @var MessageHandlerBuilder[]
     */
    private $messageHandlerBuilders = [];
    /**
     * @var PollingMetadata[]
     */
    private $pollingMetadata = [];
    /**
     * @var array|GatewayBuilder[]
     */
    private $gatewayBuilders = [];
    /**
     * @var MessageHandlerConsumerBuilder[]
     */
    private $consumerFactories = [];
    /**
     * @var ChannelAdapterConsumerBuilder[]
     */
    private $channelAdapters = [];
    /**
     * @var MethodInterceptor[]
     */
    private $beforeSendInterceptors = [];
    /**
     * @var MethodInterceptor[]
     */
    private $beforeCallMethodInterceptors = [];
    /**
     * @var AroundInterceptorReference[]
     */
    private $aroundMethodInterceptors = [];
    /**
     * @var MethodInterceptor[]
     */
    private $afterCallMethodInterceptors = [];
    /**
     * @var string[]
     */
    private $requiredReferences = [];
    /**
     * @var string[]
     */
    private $optionalReferences = [];
    /**
     * @var ConverterBuilder[]
     */
    private $converterBuilders = [];
    /**
     * @var string[]
     */
    private $messageConverterReferenceNames = [];
    /**
     * @var InterfaceToCall[]
     */
    private $interfacesToCall = [];
    /**
     * @var ModuleReferenceSearchService
     */
    private $moduleReferenceSearchService;
    /**
     * @var bool
     */
    private $isLazyConfiguration;
    /**
     * @var array
     */
    private $asynchronousEndpoints = [];
    /**
     * @var string[]
     */
    private $gatewayClassesToGenerateProxies = [];
    /**
     * @var string
     */
    private $rootPathToSearchConfigurationFor;
    /**
     * @var ApplicationConfiguration
     */
    private $applicationConfiguration;
    /**
     * @var string[]
     */
    private $requiredConsumerEndpointIds = [];

    /**
     * Only one instance at time
     *
     * Configuration constructor.
     * @param string|null $rootPathToSearchConfigurationFor
     * @param ModuleRetrievingService $moduleConfigurationRetrievingService
     * @param object[] $extensionObjects
     * @param ReferenceTypeFromNameResolver $referenceTypeFromNameResolver
     * @param ApplicationConfiguration $applicationConfiguration
     * @throws AnnotationException
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws MessagingException
     * @throws ReflectionException
     */
    private function __construct(?string $rootPathToSearchConfigurationFor, ModuleRetrievingService $moduleConfigurationRetrievingService, array $extensionObjects, ReferenceTypeFromNameResolver $referenceTypeFromNameResolver, ApplicationConfiguration $applicationConfiguration)
    {
        $extensionApplicationConfiguration = [];
        foreach ($extensionObjects as $extensionObject) {
            if ($extensionObject instanceof ApplicationConfiguration) {
                $extensionApplicationConfiguration[] = $extensionObject;
            }
        }
        $applicationConfiguration = $applicationConfiguration->mergeWith($extensionApplicationConfiguration);
        if (!$applicationConfiguration->getConnectionRetryTemplate()) {
            if ($applicationConfiguration->isProductionConfiguration()) {
                $applicationConfiguration->withConnectionRetryTemplate(
                    RetryTemplateBuilder::exponentialBackoff(1000, 3)
                        ->maxRetryAttempts(5)
                );
            }else {
                $applicationConfiguration->withConnectionRetryTemplate(
                    RetryTemplateBuilder::exponentialBackoff(100, 3)
                        ->maxRetryAttempts(3)
                );
            }
        }

        $this->isLazyConfiguration = !$applicationConfiguration->isFailingFast();
        $this->rootPathToSearchConfigurationFor = $rootPathToSearchConfigurationFor;
        $this->applicationConfiguration = $applicationConfiguration;

        $extensionObjects = array_filter($extensionObjects, function($extensionObject){
            if (is_null($extensionObject)) {
                return false;
            }

            return !($extensionObject instanceof ApplicationConfiguration);
        });
        $extensionObjects[] = $applicationConfiguration;
        $this->initialize($moduleConfigurationRetrievingService, $extensionObjects, $referenceTypeFromNameResolver, $applicationConfiguration->getCacheDirectoryPath() ? ProxyFactory::createWithCache($applicationConfiguration->getCacheDirectoryPath()) : ProxyFactory::createNoCache(), $applicationConfiguration);
    }

    private function initialize(ModuleRetrievingService $moduleConfigurationRetrievingService, array $extensionObjects, ReferenceTypeFromNameResolver $referenceTypeFromNameResolver, ProxyFactory $proxyFactory, ApplicationConfiguration $applicationConfiguration): void
    {
        $moduleReferenceSearchService = ModuleReferenceSearchService::createEmpty();
        $moduleReferenceSearchService->store(ProxyFactory::REFERENCE_NAME, $proxyFactory);

        $modules = $moduleConfigurationRetrievingService->findAllModuleConfigurations();
        $moduleExtensions = [];

        foreach ($modules as $module) {
            $this->requireReferences($module->getRelatedReferences());

            $moduleExtensions[$module->getName()] = [];
            foreach ($extensionObjects as $extensionObject) {
                if ($module->canHandle($extensionObject)) {
                    $moduleExtensions[$module->getName()][] = $extensionObject;
                }
            }
        }

        foreach ($modules as $module) {
            $module->prepare(
                $this,
                $moduleExtensions[$module->getName()],
                $moduleReferenceSearchService
            );
        }
        $interfaceToCallRegistry = InterfaceToCallRegistry::createWith($referenceTypeFromNameResolver);

        $this->prepareAndOptimizeConfiguration($interfaceToCallRegistry, $applicationConfiguration);
        $proxyFactory->warmUpCacheFor($this->gatewayClassesToGenerateProxies);
        $this->gatewayClassesToGenerateProxies = [];

        $this->interfacesToCall = array_unique($this->interfacesToCall);
        $this->moduleReferenceSearchService = $moduleReferenceSearchService;
    }

    /**
     * @param string[] $referenceNames
     * @return Configuration
     */
    public function requireReferences(array $referenceNames): Configuration
    {
        foreach ($referenceNames as $referenceName) {
            $isRequired = true;
            if ($referenceName instanceof RequiredReference) {
                $referenceName = $referenceName->getReferenceName();
            } elseif ($referenceName instanceof OptionalReference) {
                $isRequired = false;
                $referenceName = $referenceName->getReferenceName();
            }

            if (in_array($referenceName, [InterfaceToCallRegistry::REFERENCE_NAME, ConversionService::REFERENCE_NAME, ProxyFactory::REFERENCE_NAME])) {
                continue;
            }

            if ($referenceName) {
                if ($isRequired) {
                    $this->requiredReferences[] = $referenceName;
                } else {
                    $this->optionalReferences[] = $referenceName;
                }
            }
        }

        $this->requiredReferences = array_unique($this->requiredReferences);

        return $this;
    }

    /**
     * @param InterfaceToCallRegistry $interfaceToCallRegistry
     * @param ApplicationConfiguration $applicationConfiguration
     * @throws AnnotationException
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws MessagingException
     * @throws ReflectionException
     */
    private function prepareAndOptimizeConfiguration(InterfaceToCallRegistry $interfaceToCallRegistry, ApplicationConfiguration $applicationConfiguration): void
    {
        $pollableEndpointAnnotations = [new PollableEndpoint()];
        foreach ($this->channelAdapters as $channelAdapter) {
            $channelAdapter->withEndpointAnnotations(array_merge($channelAdapter->getEndpointAnnotations(), $pollableEndpointAnnotations));
        }
        /** @var BeforeSendChannelInterceptorBuilder[] $beforeSendInterceptors */
        $beforeSendInterceptors = [];
        foreach ($this->messageHandlerBuilders as $messageHandlerBuilder) {
            if ($messageHandlerBuilder instanceof MessageHandlerBuilderWithOutputChannel) {
                if ($this->beforeSendInterceptors) {
                    $interceptorWithPointCuts = $this->getRelatedInterceptors($this->beforeSendInterceptors, $messageHandlerBuilder->getInterceptedInterface($interfaceToCallRegistry), $messageHandlerBuilder->getEndpointAnnotations(), $messageHandlerBuilder->getRequiredInterceptorNames());
                    foreach ($interceptorWithPointCuts as $interceptorReference) {
                        $beforeSendInterceptors[] = new BeforeSendChannelInterceptorBuilder($messageHandlerBuilder->getInputMessageChannelName(), $interceptorReference);
                    }
                }
            }
        }

        $beforeSendInterceptors = array_unique($beforeSendInterceptors);
        foreach ($beforeSendInterceptors as $beforeSendInterceptor) {
            $this->registerChannelInterceptor($beforeSendInterceptor);
        }

        $this->configureAsynchronousEndpoints();
        $this->configureDefaultMessageChannels();
        foreach ($this->messageHandlerBuilders as $messageHandlerBuilder) {
            if ($this->channelBuilders[$messageHandlerBuilder->getInputMessageChannelName()]->isPollable()) {
                $messageHandlerBuilder->withEndpointAnnotations(array_merge($messageHandlerBuilder->getEndpointAnnotations(), [new PollableEndpoint()]));
            }
        }

        $this->resolveRequiredReferences($interfaceToCallRegistry,
            array_map(function (MethodInterceptor $methodInterceptor) {
                return $methodInterceptor->getInterceptingObject();
            }, $this->beforeCallMethodInterceptors)
        );
        foreach ($this->aroundMethodInterceptors as $aroundInterceptorReference) {
            $this->interfacesToCall[] = $aroundInterceptorReference->getInterceptingInterface($interfaceToCallRegistry);
        }
        $this->resolveRequiredReferences($interfaceToCallRegistry,
            array_map(function (MethodInterceptor $methodInterceptor) {
                return $methodInterceptor->getInterceptingObject();
            }, $this->afterCallMethodInterceptors)
        );
        foreach ($this->messageHandlerBuilders as $messageHandlerBuilder) {
            if ($this->channelBuilders[$messageHandlerBuilder->getInputMessageChannelName()]->isPollable() && $messageHandlerBuilder instanceof InterceptedEndpoint) {
                $messageHandlerBuilder->withEndpointAnnotations(array_merge($messageHandlerBuilder->getEndpointAnnotations(), $pollableEndpointAnnotations));
            }
        }
        $this->configureInterceptors($interfaceToCallRegistry);
        $this->resolveRequiredReferences($interfaceToCallRegistry, $this->messageHandlerBuilders);
        $this->resolveRequiredReferences($interfaceToCallRegistry, $this->gatewayBuilders);
        $this->resolveRequiredReferences($interfaceToCallRegistry, $this->channelAdapters);

        foreach ($this->messageHandlerBuilders as $messageHandlerBuilder) {
            $this->addDefaultPollingConfiguration($messageHandlerBuilder->getEndpointId());
        }
        foreach ($this->channelAdapters as $channelAdapter) {
            $this->addDefaultPollingConfiguration($channelAdapter->getEndpointId());
        }

        foreach ($this->requiredConsumerEndpointIds as $requiredConsumerEndpointId) {
            if (!array_key_exists($requiredConsumerEndpointId, $this->messageHandlerBuilders) && !array_key_exists($requiredConsumerEndpointId, $this->channelAdapters)) {
                throw ConfigurationException::create("Consumer with id {$requiredConsumerEndpointId} has no configuration defined. Define consumer configuration and retry.");
            }
        }
    }

    /**
     * @return void
     */
    private function configureAsynchronousEndpoints(): void
    {
        $messageHandlerBuilders = $this->messageHandlerBuilders;
        $asynchronousChannels = [];

        foreach ($this->asynchronousEndpoints as $targetEndpointId => $asynchronousMessageChannel) {
            $foundEndpoint = false;
            $asynchronousChannels[] = $asynchronousMessageChannel;
            foreach ($this->messageHandlerBuilders as $messageHandlerBuilder) {
                if ($messageHandlerBuilder->getEndpointId() === $targetEndpointId) {
                    $targetChannelName = $messageHandlerBuilder->getInputMessageChannelName() . ".async";
                    $messageHandlerBuilders[] = TransformerBuilder::createHeaderEnricher([
                        CommandBus::CHANNEL_NAME_BY_NAME => null,
                        CommandBus::CHANNEL_NAME_BY_OBJECT => null,
                        EventBus::CHANNEL_NAME_BY_OBJECT => null,
                        EventBus::CHANNEL_NAME_BY_NAME => null,
                        MessageHeaders::REPLY_CHANNEL => null,
                        MessageHeaders::ROUTING_SLIP => $targetChannelName
                    ])
                        ->withEndpointId($targetChannelName)
                        ->withInputChannelName($messageHandlerBuilder->getInputMessageChannelName())
                        ->withOutputMessageChannel($asynchronousMessageChannel);
                    $messageHandlerBuilder->withInputChannelName($targetChannelName);

                    if (array_key_exists($messageHandlerBuilder->getEndpointId(), $this->pollingMetadata)) {
                        $this->pollingMetadata[$targetChannelName] = $this->pollingMetadata[$messageHandlerBuilder->getEndpointId()];
                        unset($this->pollingMetadata[$messageHandlerBuilder->getEndpointId()]);
                    }
                    $foundEndpoint = true;
                    break;
                }
            }

            if (!$foundEndpoint) {
                throw ConfigurationException::create("Registered asynchronous endpoint for not existing id {$targetEndpointId}");
            }
        }

        foreach (array_unique($asynchronousChannels) as $asynchronousChannel) {
            //        needed for correct around intercepting, otherwise requestReply is outside of around interceptor scope
            $bridgeBuilder = ChainMessageHandlerBuilder::create()
                ->chain(ServiceActivatorBuilder::createWithDirectReference(new Bridge(), "handle"))
                ->chain(ServiceActivatorBuilder::createWithDirectReference(new Bridge(), "handle"));
            $messageHandlerBuilders[] = $bridgeBuilder
                ->withEndpointId($asynchronousChannel)
                ->withInputChannelName($asynchronousChannel);
        }

        $this->messageHandlerBuilders = $messageHandlerBuilders;
        $this->asynchronousEndpoints = [];
    }

    public function requireConsumer(string $endpointId): Configuration
    {
        $this->requiredConsumerEndpointIds[] = $endpointId;

        return $this;
    }


    /**
     * @return void
     */
    private function configureDefaultMessageChannels(): void
    {
        foreach ($this->messageHandlerBuilders as $messageHandlerBuilder) {
            if (!array_key_exists($messageHandlerBuilder->getInputMessageChannelName(), $this->channelBuilders)) {
                if (array_key_exists($messageHandlerBuilder->getInputMessageChannelName(), $this->defaultChannelBuilders)) {
                    $this->channelBuilders[$messageHandlerBuilder->getInputMessageChannelName()] = $this->defaultChannelBuilders[$messageHandlerBuilder->getInputMessageChannelName()];
                } else {
                    $this->channelBuilders[$messageHandlerBuilder->getInputMessageChannelName()] = SimpleMessageChannelBuilder::createDirectMessageChannel($messageHandlerBuilder->getInputMessageChannelName());
                }
            }
        }

        foreach ($this->defaultChannelBuilders as $name => $defaultChannelBuilder) {
            if (!array_key_exists($name, $this->channelBuilders)) {
                $this->channelBuilders[$name] = $defaultChannelBuilder;
            }
        }
    }

    /**
     * @param InterfaceToCallRegistry $interfaceToCallRegistry
     * @param MessageHandlerBuilder[]|InterceptedEndpoint[] $interceptedEndpoints
     */
    public function resolveRequiredReferences(InterfaceToCallRegistry $interfaceToCallRegistry, array $interceptedEndpoints): void
    {
        foreach ($interceptedEndpoints as $interceptedEndpoint) {
            $relatedInterfaces = $interceptedEndpoint->resolveRelatedInterfaces($interfaceToCallRegistry);

            foreach ($relatedInterfaces as $relatedInterface) {
                foreach ($relatedInterface->getMethodAnnotations() as $methodAnnotation) {
                    if ($methodAnnotation instanceof WithRequiredReferenceNameList) {
                        $this->requireReferences($methodAnnotation->getRequiredReferenceNameList());
                    }
                }
                foreach ($relatedInterface->getClassAnnotations() as $classAnnotation) {
                    if ($classAnnotation instanceof WithRequiredReferenceNameList) {
                        $this->requireReferences($classAnnotation->getRequiredReferenceNameList());
                    }
                }
            }

            if ($interceptedEndpoint instanceof InterceptedEndpoint) {
                $this->interfacesToCall[] = $interceptedEndpoint->getInterceptedInterface($interfaceToCallRegistry);
            }
            $this->interfacesToCall = array_merge($this->interfacesToCall, $relatedInterfaces);
        }
    }

    /**
     * @param InterfaceToCallRegistry $interfaceRegistry
     * @return void
     * @throws MessagingException
     */
    private function configureInterceptors(InterfaceToCallRegistry $interfaceRegistry): void
    {
        foreach ($this->messageHandlerBuilders as $messageHandlerBuilder) {
            if ($messageHandlerBuilder instanceof MessageHandlerBuilderWithOutputChannel) {
                $aroundInterceptors = [];
                $beforeCallInterceptors = [];
                $afterCallInterceptors = [];
                if ($this->beforeCallMethodInterceptors) {
                    $beforeCallInterceptors = $this->getRelatedInterceptors($this->beforeCallMethodInterceptors, $messageHandlerBuilder->getInterceptedInterface($interfaceRegistry), $messageHandlerBuilder->getEndpointAnnotations(), $messageHandlerBuilder->getRequiredInterceptorNames());
                }
                if ($this->aroundMethodInterceptors) {
                    $aroundInterceptors = $this->getRelatedInterceptors($this->aroundMethodInterceptors, $messageHandlerBuilder->getInterceptedInterface($interfaceRegistry), $messageHandlerBuilder->getEndpointAnnotations(), $messageHandlerBuilder->getRequiredInterceptorNames());
                }
                if ($this->afterCallMethodInterceptors) {
                    $afterCallInterceptors = $this->getRelatedInterceptors($this->afterCallMethodInterceptors, $messageHandlerBuilder->getInterceptedInterface($interfaceRegistry), $messageHandlerBuilder->getEndpointAnnotations(), $messageHandlerBuilder->getRequiredInterceptorNames());
                }

                foreach ($aroundInterceptors as $aroundInterceptorReference) {
                    $messageHandlerBuilder->addAroundInterceptor($aroundInterceptorReference);
                }
                if ($beforeCallInterceptors || $afterCallInterceptors) {
                    $messageHandlerBuilderToUse = ChainMessageHandlerBuilder::create()
                        ->withEndpointId($messageHandlerBuilder->getEndpointId())
                        ->withInputChannelName($messageHandlerBuilder->getInputMessageChannelName())
                        ->withOutputMessageChannel($messageHandlerBuilder->getOutputMessageChannelName());

                    foreach ($beforeCallInterceptors as $beforeCallInterceptor) {
                        $messageHandlerBuilderToUse->chain($beforeCallInterceptor->getInterceptingObject());
                    }
                    $messageHandlerBuilderToUse->chain($messageHandlerBuilder);
                    foreach ($afterCallInterceptors as $afterCallInterceptor) {
                        $messageHandlerBuilderToUse->chain($afterCallInterceptor->getInterceptingObject());
                    }

                    $this->messageHandlerBuilders[$messageHandlerBuilder->getEndpointId()] = $messageHandlerBuilderToUse;
                }
            }
        }

        foreach ($this->gatewayBuilders as $gatewayBuilder) {
            $aroundInterceptors = [];
            $beforeCallInterceptors = [];
            $afterCallInterceptors = [];
            if ($this->beforeCallMethodInterceptors) {
                $beforeCallInterceptors = $this->getRelatedInterceptors($this->beforeCallMethodInterceptors, $gatewayBuilder->getInterceptedInterface($interfaceRegistry), $gatewayBuilder->getEndpointAnnotations(), $gatewayBuilder->getRequiredInterceptorNames());
            }
            if ($this->aroundMethodInterceptors) {
                $aroundInterceptors = $this->getRelatedInterceptors($this->aroundMethodInterceptors, $gatewayBuilder->getInterceptedInterface($interfaceRegistry), $gatewayBuilder->getEndpointAnnotations(), $gatewayBuilder->getRequiredInterceptorNames());
            }
            if ($this->afterCallMethodInterceptors) {
                $afterCallInterceptors = $this->getRelatedInterceptors($this->afterCallMethodInterceptors, $gatewayBuilder->getInterceptedInterface($interfaceRegistry), $gatewayBuilder->getEndpointAnnotations(), $gatewayBuilder->getRequiredInterceptorNames());
            }

            foreach ($aroundInterceptors as $aroundInterceptor) {
                $gatewayBuilder->addAroundInterceptor($aroundInterceptor);
            }
            foreach ($beforeCallInterceptors as $beforeCallInterceptor) {
                $gatewayBuilder->addBeforeInterceptor($beforeCallInterceptor);
            }
            foreach ($afterCallInterceptors as $afterCallInterceptor) {
                $gatewayBuilder->addAfterInterceptor($afterCallInterceptor);
            }
        }

        foreach ($this->channelAdapters as $channelAdapter) {
            $aroundInterceptors = [];
            $beforeCallInterceptors = [];
            $afterCallInterceptors = [];
            if ($this->beforeCallMethodInterceptors) {
                $beforeCallInterceptors = $this->getRelatedInterceptors($this->beforeCallMethodInterceptors, $channelAdapter->getInterceptedInterface($interfaceRegistry), $channelAdapter->getEndpointAnnotations(), $channelAdapter->getRequiredInterceptorNames());
            }
            if ($this->aroundMethodInterceptors) {
                $aroundInterceptors = $this->getRelatedInterceptors($this->aroundMethodInterceptors, $channelAdapter->getInterceptedInterface($interfaceRegistry), $channelAdapter->getEndpointAnnotations(), $channelAdapter->getRequiredInterceptorNames());
            }
            if ($this->afterCallMethodInterceptors) {
                $afterCallInterceptors = $this->getRelatedInterceptors($this->afterCallMethodInterceptors, $channelAdapter->getInterceptedInterface($interfaceRegistry), $channelAdapter->getEndpointAnnotations(), $channelAdapter->getRequiredInterceptorNames());
            }

            foreach ($aroundInterceptors as $aroundInterceptor) {
                $channelAdapter->addAroundInterceptor($aroundInterceptor);
            }
            foreach ($beforeCallInterceptors as $beforeCallInterceptor) {
                $channelAdapter->addBeforeInterceptor($beforeCallInterceptor);
            }
            foreach ($afterCallInterceptors as $afterCallInterceptor) {
                $channelAdapter->addAfterInterceptor($afterCallInterceptor);
            }
        }

        $this->beforeCallMethodInterceptors = [];
        $this->aroundMethodInterceptors = [];
        $this->afterCallMethodInterceptors = [];
    }

    /**
     * @param InterceptorWithPointCut[] $interceptors
     * @param InterfaceToCall $interceptedInterface
     * @param object[] $endpointAnnotations
     * @param string[] $requiredInterceptorNames
     * @return InterceptorWithPointCut[]|AroundInterceptorReference[]|MessageHandlerBuilderWithOutputChannel[]
     * @throws MessagingException
     */
    private function getRelatedInterceptors($interceptors, InterfaceToCall $interceptedInterface, iterable $endpointAnnotations, iterable $requiredInterceptorNames): iterable
    {
        $relatedInterceptors = [];
        foreach ($requiredInterceptorNames as $requiredInterceptorName) {
            if (!$this->doesInterceptorWithNameExists($requiredInterceptorName)) {
                throw ConfigurationException::create("Can't find interceptor with name {$requiredInterceptorName} for {$interceptedInterface}");
            }
        }

        foreach ($interceptors as $interceptor) {
            foreach ($requiredInterceptorNames as $requiredInterceptorName) {
                if ($interceptor->hasName($requiredInterceptorName)) {
                    $relatedInterceptors[] = $interceptor;
                    break;
                }
            }

            if ($interceptor->doesItCutWith($interceptedInterface, $endpointAnnotations)) {
                $relatedInterceptors[] = $interceptor->addInterceptedInterfaceToCall($interceptedInterface, $endpointAnnotations);
            }
        }

        return array_unique($relatedInterceptors);
    }

    /**
     * @param string $name
     * @return bool
     */
    private function doesInterceptorWithNameExists(string $name): bool
    {
        /** @var InterceptorWithPointCut $interceptor */
        foreach (array_merge($this->aroundMethodInterceptors, $this->beforeCallMethodInterceptors, $this->afterCallMethodInterceptors) as $interceptor) {
            if ($interceptor->hasName($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ModuleRetrievingService $moduleConfigurationRetrievingService
     * @return Configuration
     * @throws AnnotationException
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws MessagingException
     * @throws ReflectionException
     */
    public static function prepareWithDefaults(ModuleRetrievingService $moduleConfigurationRetrievingService): Configuration
    {
        return new self(null, $moduleConfigurationRetrievingService, $moduleConfigurationRetrievingService->findAllExtensionObjects(), InMemoryReferenceTypeFromNameResolver::createEmpty(), ApplicationConfiguration::createWithDefaults());
    }

    /**
     * @param string $rootPathToSearchConfigurationFor
     * @param ReferenceTypeFromNameResolver $referenceTypeFromNameResolver
     * @param ApplicationConfiguration $applicationConfiguration
     * @return Configuration
     * @throws AnnotationException
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws MessagingException
     * @throws ReflectionException
     */
    public static function prepare(string $rootPathToSearchConfigurationFor, ReferenceTypeFromNameResolver $referenceTypeFromNameResolver, ApplicationConfiguration $applicationConfiguration): Configuration
    {
        self::registerAnnotationAutoloader($rootPathToSearchConfigurationFor);

        $cachedVersion = self::getCachedVersion($applicationConfiguration);
        if ($cachedVersion) {
            return $cachedVersion;
        }

        return self::prepareWithModuleRetrievingService(
            $rootPathToSearchConfigurationFor,
            new AnnotationModuleRetrievingService(
                new FileSystemAnnotationRegistrationService(
                    new AnnotationReader(),
                    new AutoloadFileNamespaceParser(),
                    realpath($rootPathToSearchConfigurationFor),
                    $applicationConfiguration->getNamespaces(),
                    $applicationConfiguration->getEnvironment(),
                    $applicationConfiguration->getLoadedCatalog()
                )
            ),
            $referenceTypeFromNameResolver,
            $applicationConfiguration
        );
    }

    /**
     * @param string|null $rootPathToSearchConfigurationFor
     * @throws InvalidArgumentException
     * @throws MessagingException
     */
    private static function registerAnnotationAutoloader(?string $rootPathToSearchConfigurationFor): void
    {
        if ($rootPathToSearchConfigurationFor) {
            $path = $rootPathToSearchConfigurationFor . '/vendor/autoload.php';
            Assert::isTrue(file_exists($path), "Can't find autoload file on {$path}. Is autoload generated correctly?");
            $loader = require $path;
            AnnotationRegistry::registerLoader(array($loader, "loadClass"));
        }
    }

    private static function getCachedVersion(ApplicationConfiguration $applicationConfiguration): ?MessagingSystemConfiguration
    {
        if (!$applicationConfiguration->getCacheDirectoryPath()) {
            return null;
        }

        $messagingSystemCachePath = $applicationConfiguration->getCacheDirectoryPath() . DIRECTORY_SEPARATOR . "messaging_system";
        if (file_exists($messagingSystemCachePath)) {
            return unserialize(file_get_contents($messagingSystemCachePath));
        }

        return null;
    }

    /**
     * @param string|null $rootProjectDirectoryPath
     * @param ModuleRetrievingService $moduleConfigurationRetrievingService
     * @param ReferenceTypeFromNameResolver $referenceTypeFromNameResolver
     * @param ApplicationConfiguration $applicationConfiguration
     * @return Configuration
     * @throws AnnotationException
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws MessagingException
     * @throws ReflectionException
     */
    public static function prepareWithModuleRetrievingService(?string $rootProjectDirectoryPath, ModuleRetrievingService $moduleConfigurationRetrievingService, ReferenceTypeFromNameResolver $referenceTypeFromNameResolver, ApplicationConfiguration $applicationConfiguration): Configuration
    {
        $cacheDirectoryPath = $applicationConfiguration->getCacheDirectoryPath();

        $cachedVersion = self::getCachedVersion($applicationConfiguration);
        if ($cachedVersion) {
            return $cachedVersion;
        }

        if ($cacheDirectoryPath) {
            @mkdir($cacheDirectoryPath, 0777, true);
            Assert::isTrue(is_writable($cacheDirectoryPath), "Not enough permissions to write into cache directory {$cacheDirectoryPath}");
            self::cleanCache($applicationConfiguration);
        }

        $messagingSystemConfiguration = new self($rootProjectDirectoryPath, $moduleConfigurationRetrievingService, $moduleConfigurationRetrievingService->findAllExtensionObjects(), $referenceTypeFromNameResolver, $applicationConfiguration);

        if ($cacheDirectoryPath) {
            $serializedMessagingSystemConfiguration = serialize($messagingSystemConfiguration);
            file_put_contents($cacheDirectoryPath . DIRECTORY_SEPARATOR . "messaging_system", $serializedMessagingSystemConfiguration);
        }

        return $messagingSystemConfiguration;
    }

    /**
     * @param ApplicationConfiguration $applicationConfiguration
     * @throws InvalidArgumentException
     * @throws MessagingException
     */
    public static function cleanCache(ApplicationConfiguration $applicationConfiguration): void
    {
        if ($applicationConfiguration->getCacheDirectoryPath()) {
            if (!is_dir($applicationConfiguration->getCacheDirectoryPath())) {
                @mkdir($applicationConfiguration->getCacheDirectoryPath(), 0777, true);
            }
            Assert::isTrue(is_writable($applicationConfiguration->getCacheDirectoryPath()), "Not enough permissions to write into cache directory {$applicationConfiguration->getCacheDirectoryPath()}");

            Assert::isFalse(is_file($applicationConfiguration->getCacheDirectoryPath()), "Cache directory is file, should be directory");

            self::deleteFiles($applicationConfiguration->getCacheDirectoryPath() . DIRECTORY_SEPARATOR, false);
        }
    }

    private static function deleteFiles(string $target, bool $deleteDirectory)
    {
        if (is_dir($target)) {
            $files = glob($target . '*', GLOB_MARK);

            foreach ($files as $file) {
                self::deleteFiles($file, true);
            }

            if ($deleteDirectory) {
                rmdir($target);
            }
        } elseif (is_file($target)) {
            unlink($target);
        }
    }

    /**
     * @inheritDoc
     */
    public function isLazyLoaded(): bool
    {
        return $this->isLazyConfiguration;
    }

    /**
     * @param PollingMetadata $pollingMetadata
     * @return Configuration
     */
    public function registerPollingMetadata(PollingMetadata $pollingMetadata): Configuration
    {
        $this->pollingMetadata[$pollingMetadata->getEndpointId()] = $pollingMetadata;

        return $this;
    }

    /**
     * @param MethodInterceptor $methodInterceptor
     * @return Configuration
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function registerBeforeSendInterceptor(MethodInterceptor $methodInterceptor): Configuration
    {
        $this->checkIfInterceptorIsCorrect($methodInterceptor);

        $interceptingObject = $methodInterceptor->getInterceptingObject();
        if ($interceptingObject instanceof ServiceActivatorBuilder) {
            $interceptingObject->withPassThroughMessageOnVoidInterface(true);
        }

        $this->beforeSendInterceptors[] = $methodInterceptor;
        $this->beforeSendInterceptors = $this->orderMethodInterceptors($this->beforeSendInterceptors);
        $this->requireReferences($methodInterceptor->getMessageHandler()->getRequiredReferenceNames());

        return $this;
    }

    /**
     * @param MethodInterceptor $methodInterceptor
     * @throws ConfigurationException
     * @throws MessagingException
     */
    private function checkIfInterceptorIsCorrect(MethodInterceptor $methodInterceptor): void
    {
        if ($methodInterceptor->getMessageHandler()->getEndpointId()) {
            throw ConfigurationException::create("Interceptor {$methodInterceptor} should not contain EndpointId");
        }
        if ($methodInterceptor->getMessageHandler()->getInputMessageChannelName()) {
            throw ConfigurationException::create("Interceptor {$methodInterceptor} should not contain input channel. Interceptor is wired by endpoint id");
        }
        if ($methodInterceptor->getMessageHandler()->getOutputMessageChannelName()) {
            throw ConfigurationException::create("Interceptor {$methodInterceptor} should not contain output channel. Interceptor is wired by endpoint id");
        }
    }

    /**
     * @param MessageHandlerBuilderWithOutputChannel[] $methodInterceptors
     * @return array
     */
    private function orderMethodInterceptors(array $methodInterceptors): array
    {
        usort($methodInterceptors, function (MethodInterceptor $methodInterceptor, MethodInterceptor $toCompare) {
            if ($methodInterceptor->getPrecedence() === $toCompare->getPrecedence()) {
                return 0;
            }

            if ($methodInterceptor->getPrecedence() > $toCompare->getPrecedence()) {
                return 1;
            }

            return -1;
        });

        return $methodInterceptors;
    }

    /**
     * @param MethodInterceptor $methodInterceptor
     * @return Configuration
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function registerBeforeMethodInterceptor(MethodInterceptor $methodInterceptor): Configuration
    {
        $this->checkIfInterceptorIsCorrect($methodInterceptor);

        $interceptingObject = $methodInterceptor->getInterceptingObject();
        if ($interceptingObject instanceof ServiceActivatorBuilder) {
            $interceptingObject->withPassThroughMessageOnVoidInterface(true);
        }

        $this->beforeCallMethodInterceptors[] = $methodInterceptor;
        $this->beforeCallMethodInterceptors = $this->orderMethodInterceptors($this->beforeCallMethodInterceptors);
        $this->requireReferences($methodInterceptor->getMessageHandler()->getRequiredReferenceNames());

        return $this;
    }

    /**
     * @param MethodInterceptor $methodInterceptor
     * @return Configuration
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function registerAfterMethodInterceptor(MethodInterceptor $methodInterceptor): Configuration
    {
        $this->checkIfInterceptorIsCorrect($methodInterceptor);

        if ($methodInterceptor->getInterceptingObject() instanceof ServiceActivatorBuilder) {
            $methodInterceptor->getInterceptingObject()->withPassThroughMessageOnVoidInterface(true);
        }

        $this->afterCallMethodInterceptors[] = $methodInterceptor;
        $this->afterCallMethodInterceptors = $this->orderMethodInterceptors($this->afterCallMethodInterceptors);
        $this->requireReferences($methodInterceptor->getMessageHandler()->getRequiredReferenceNames());

        return $this;
    }

    /**
     * @param AroundInterceptorReference $aroundInterceptorReference
     * @return Configuration
     */
    public function registerAroundMethodInterceptor(AroundInterceptorReference $aroundInterceptorReference): Configuration
    {
        $this->aroundMethodInterceptors[] = $aroundInterceptorReference;
        $this->requireReferences($aroundInterceptorReference->getRequiredReferenceNames());


        return $this;
    }

    /**
     * @inheritDoc
     */
    public function registerAsynchronousEndpoint(string $asynchronousChannelName, string $targetEndpointId): Configuration
    {
        $this->asynchronousEndpoints[$targetEndpointId] = $asynchronousChannelName;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function registerChannelInterceptor(ChannelInterceptorBuilder $channelInterceptorBuilder): Configuration
    {
        $this->channelInterceptorBuilders[$channelInterceptorBuilder->getPrecedence()][] = $channelInterceptorBuilder;
        $this->requireReferences($channelInterceptorBuilder->getRequiredReferenceNames());

        return $this;
    }

    /**
     * @param MessageHandlerBuilder $messageHandlerBuilder
     * @return Configuration
     * @throws ConfigurationException
     * @throws Exception
     * @throws MessagingException
     */
    public function registerMessageHandler(MessageHandlerBuilder $messageHandlerBuilder): Configuration
    {
        Assert::notNullAndEmpty($messageHandlerBuilder->getInputMessageChannelName(), "Lack information about input message channel for {$messageHandlerBuilder}");
        if (is_null($messageHandlerBuilder->getEndpointId()) || $messageHandlerBuilder->getEndpointId() === "") {
            $messageHandlerBuilder->withEndpointId(Uuid::uuid4()->toString());
        }
        if (array_key_exists($messageHandlerBuilder->getEndpointId(), $this->messageHandlerBuilders)) {
            throw ConfigurationException::create("Trying to register endpoints with same id {$messageHandlerBuilder->getEndpointId()}. {$messageHandlerBuilder} and {$this->messageHandlerBuilders[$messageHandlerBuilder->getEndpointId()]}");
        }
        if ($messageHandlerBuilder->getInputMessageChannelName() === $messageHandlerBuilder->getEndpointId()) {
            throw ConfigurationException::create("Can't register message handler {$messageHandlerBuilder} with same endpointId as inputChannelName.");
        }

        $this->requireReferences($messageHandlerBuilder->getRequiredReferenceNames());

        if ($messageHandlerBuilder instanceof MessageHandlerBuilderWithParameterConverters) {
            foreach ($messageHandlerBuilder->getParameterConverters() as $parameterConverter) {
                $this->requireReferences($parameterConverter->getRequiredReferences());
            }
        }
        if ($messageHandlerBuilder instanceof MessageHandlerBuilderWithOutputChannel) {
            foreach ($messageHandlerBuilder->getEndpointAnnotations() as $endpointAnnotation) {
                if ($endpointAnnotation instanceof WithRequiredReferenceNameList) {
                    $this->requireReferences($endpointAnnotation->getRequiredReferenceNameList());
                }
            }
        }

        $this->messageHandlerBuilders[$messageHandlerBuilder->getEndpointId()] = $messageHandlerBuilder;
        $this->verifyEndpointAndChannelNameUniqueness();

        return $this;
    }

    /**
     * @param MessageChannelBuilder $messageChannelBuilder
     * @return Configuration
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function registerMessageChannel(MessageChannelBuilder $messageChannelBuilder): Configuration
    {
        if (array_key_exists($messageChannelBuilder->getMessageChannelName(), $this->channelBuilders)) {
            throw ConfigurationException::create("Trying to register message channel with name `{$messageChannelBuilder->getMessageChannelName()}` twice.");
        }

        $this->channelBuilders[$messageChannelBuilder->getMessageChannelName()] = $messageChannelBuilder;
        $this->requireReferences($messageChannelBuilder->getRequiredReferenceNames());
        $this->verifyEndpointAndChannelNameUniqueness();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function registerDefaultChannelFor(MessageChannelBuilder $messageChannelBuilder): Configuration
    {
        $this->defaultChannelBuilders[$messageChannelBuilder->getMessageChannelName()] = $messageChannelBuilder;
        $this->requireReferences($messageChannelBuilder->getRequiredReferenceNames());
        $this->verifyEndpointAndChannelNameUniqueness();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function registerConsumer(ChannelAdapterConsumerBuilder $consumerBuilder): Configuration
    {
        if (array_key_exists($consumerBuilder->getEndpointId(), $this->channelAdapters)) {
            throw ConfigurationException::create("Trying to register consumers under same endpoint id {$consumerBuilder->getEndpointId()}. Change the name of one of them.");
        }

        $this->channelAdapters[$consumerBuilder->getEndpointId()] = $consumerBuilder;
        $this->requireReferences($consumerBuilder->getRequiredReferences());

        return $this;
    }

    /**
     * @param GatewayBuilder $gatewayBuilder
     * @return Configuration
     */
    public function registerGatewayBuilder(GatewayBuilder $gatewayBuilder): Configuration
    {
        $this->gatewayBuilders[] = $gatewayBuilder;
        $this->requireReferences($gatewayBuilder->getRequiredReferences());
        $this->gatewayClassesToGenerateProxies[] = $gatewayBuilder->getInterfaceName();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function registerConsumerFactory(MessageHandlerConsumerBuilder $consumerFactory): Configuration
    {
        $this->consumerFactories[] = $consumerFactory;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getRequiredReferences(): array
    {
        return $this->requiredReferences;
    }

    /**
     * @return string[]
     */
    public function getOptionalReferences(): array
    {
        return $this->optionalReferences;
    }

    /**
     * @inheritDoc
     */
    public function registerRelatedInterfaces(array $relatedInterfaces): Configuration
    {
        $this->interfacesToCall = array_merge($this->interfacesToCall, $relatedInterfaces);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getRegisteredGateways(): array
    {
        return $this->gatewayBuilders;
    }

    /**
     * @inheritDoc
     */
    public function registerInternalGateway(Type $interfaceName): Configuration
    {
        Assert::isTrue($interfaceName->isClassOrInterface(), "Passed internal gateway must be class, passed: {$interfaceName->toString()}");

        $this->gatewayClassesToGenerateProxies[] = $interfaceName->toString();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function registerConverter(ConverterBuilder $converterBuilder): Configuration
    {
        $this->converterBuilders[] = $converterBuilder;
        $this->requireReferences($converterBuilder->getRequiredReferences());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function registerMessageConverter(string $referenceName): Configuration
    {
        $this->messageConverterReferenceNames[] = $referenceName;

        return $this;
    }

    /**
     * Initialize messaging system from current configuration
     *
     * @param ReferenceSearchService $referenceSearchService
     * @return ConfiguredMessagingSystem
     * @throws AnnotationException
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     * @throws MessagingException
     * @throws ReflectionException
     */
    public function buildMessagingSystemFromConfiguration(ReferenceSearchService $referenceSearchService): ConfiguredMessagingSystem
    {
        self::registerAnnotationAutoloader($this->rootPathToSearchConfigurationFor);
        $interfaceToCallRegistry = InterfaceToCallRegistry::createWithInterfaces($this->interfacesToCall, $this->isLazyConfiguration, $referenceSearchService);
        if (!$this->isLazyConfiguration) {
            $this->prepareAndOptimizeConfiguration($interfaceToCallRegistry, $this->applicationConfiguration);
        }

        $converters = [];
        foreach ($this->converterBuilders as $converterBuilder) {
            $converters[] = $converterBuilder->build($referenceSearchService);
        }
        $referenceSearchService = $this->prepareReferenceSearchServiceWithInternalReferences($referenceSearchService, $converters, $interfaceToCallRegistry);
        /** @var ProxyFactory $proxyFactory */
        $proxyFactory = $referenceSearchService->get(ProxyFactory::REFERENCE_NAME);
        $proxyFactory->warmUpCacheFor($this->gatewayClassesToGenerateProxies);
        spl_autoload_register($proxyFactory->getConfiguration()->getProxyAutoloader());

        $channelInterceptorsByImportance = $this->channelInterceptorBuilders;
        arsort($channelInterceptorsByImportance);
        $channelInterceptorsByChannelName = [];
        foreach ($channelInterceptorsByImportance as $channelInterceptors) {
            /** @var ChannelInterceptorBuilder $channelInterceptor */
            foreach ($channelInterceptors as $channelInterceptor) {
                $channelInterceptorsByChannelName[$channelInterceptor->relatedChannelName()][] = $channelInterceptor;
            }
        }

        /** @var GatewayBuilder[][] $preparedGateways */
        $preparedGateways = [];
        foreach ($this->gatewayBuilders as $gatewayBuilder) {
            $preparedGateways[$gatewayBuilder->getReferenceName()][] = $gatewayBuilder->withMessageConverters($this->messageConverterReferenceNames);
        }

        return MessagingSystem::createFrom(
            $referenceSearchService,
            $this->channelBuilders,
            $channelInterceptorsByChannelName,
            $preparedGateways,
            $this->consumerFactories,
            $this->pollingMetadata,
            $this->messageHandlerBuilders,
            $this->channelAdapters,
            $this->isLazyConfiguration
        );
    }

    /**
     * @param ReferenceSearchService $referenceSearchService
     * @param array $converters
     * @param InterfaceToCallRegistry $interfaceToCallRegistry
     * @return InMemoryReferenceSearchService|ReferenceSearchService
     * @throws MessagingException
     * @throws ReferenceNotFoundException
     */
    private function prepareReferenceSearchServiceWithInternalReferences(ReferenceSearchService $referenceSearchService, array $converters, InterfaceToCallRegistry $interfaceToCallRegistry)
    {
        return InMemoryReferenceSearchService::createWithReferenceService($referenceSearchService,
            array_merge(
                $this->moduleReferenceSearchService->getAllRegisteredReferences(),
                [
                    ConversionService::REFERENCE_NAME => AutoCollectionConversionService::createWith($converters),
                    InterfaceToCallRegistry::REFERENCE_NAME => $interfaceToCallRegistry,
                    ApplicationConfiguration::class => $this->applicationConfiguration
                ]
            )
        );
    }

    /**
     * Only one instance at time
     *
     * @internal
     */
    private function __clone()
    {

    }

    /**
     * @throws MessagingException
     */
    private function verifyEndpointAndChannelNameUniqueness(): void
    {
        foreach ($this->messageHandlerBuilders as $messageHandlerBuilder) {
            foreach ($this->channelBuilders as $channelBuilder) {
                if ($messageHandlerBuilder->getEndpointId() === $channelBuilder->getMessageChannelName()) {
                    throw ConfigurationException::create("Endpoint id should not be the same as existing channel name. Got {$messageHandlerBuilder} which use endpoint id same as existing channel name {$channelBuilder->getMessageChannelName()}");
                }
            }
            foreach ($this->defaultChannelBuilders as $channelBuilder) {
                if ($messageHandlerBuilder->getEndpointId() === $channelBuilder->getMessageChannelName()) {
                    throw ConfigurationException::create("Endpoint id should not be the same as existing channel name. Got {$messageHandlerBuilder} which use endpoint id same as existing channel name {$channelBuilder->getMessageChannelName()}");
                }
            }
        }
    }

    private function addDefaultPollingConfiguration($endpointId): void
    {
        $pollingMetadata = PollingMetadata::create((string)$endpointId);
        if (array_key_exists($endpointId, $this->pollingMetadata)) {
            $pollingMetadata = $this->pollingMetadata[$endpointId];
        }

        if ($this->applicationConfiguration->getDefaultErrorChannel() && !$pollingMetadata->getErrorChannelName()) {
            $pollingMetadata = $pollingMetadata
                ->setErrorChannelName($this->applicationConfiguration->getDefaultErrorChannel());
        }
        if ($this->applicationConfiguration->getDefaultMemoryLimitInMegabytes() && !$pollingMetadata->getMemoryLimitInMegabytes()) {
            $pollingMetadata = $pollingMetadata
                ->setMemoryLimitInMegaBytes($this->applicationConfiguration->getDefaultMemoryLimitInMegabytes());
        }
        if ($this->applicationConfiguration->getConnectionRetryTemplate() && !$pollingMetadata->getConnectionRetryTemplate()) {
            $pollingMetadata = $pollingMetadata
                ->setConnectionRetryTemplate($this->applicationConfiguration->getConnectionRetryTemplate());
        }

        $this->pollingMetadata[$endpointId] = $pollingMetadata;
    }
}