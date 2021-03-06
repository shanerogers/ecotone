<?php

namespace Ecotone\Modelling\Config;

use Ecotone\Messaging\Annotation\MessageEndpoint;
use Ecotone\Messaging\Annotation\ModuleAnnotation;
use Ecotone\Messaging\Config\Annotation\AnnotationModule;
use Ecotone\Messaging\Config\Annotation\AnnotationRegistration;
use Ecotone\Messaging\Config\Annotation\AnnotationRegistrationService;
use Ecotone\Messaging\Config\Configuration;
use Ecotone\Messaging\Config\ConfigurationException;
use Ecotone\Messaging\Config\ModuleReferenceSearchService;
use Ecotone\Messaging\Handler\TypeDescriptor;
use Ecotone\Modelling\Annotation\Aggregate;
use Ecotone\Modelling\Annotation\CommandHandler;
use Ecotone\Modelling\Annotation\EventHandler;
use Ecotone\Modelling\Annotation\QueryHandler;

/**
 * Class AggregateMessageRouterModule
 * @package Ecotone\Modelling\Config
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 * @ModuleAnnotation()
 */
class ModellingMessageRouterModule implements AnnotationModule
{
    const MODULE_NAME = self::class;

    /**
     * @var BusRouterBuilder
     */
    private $commandBusByObject;
    /**
     * @var BusRouterBuilder
     */
    private $queryBusByObject;
    /**
     * @var BusRouterBuilder
     */
    private $eventBusByObject;
    /**
     * @var BusRouterBuilder
     */
    private $commandBusByName;
    /**
     * @var BusRouterBuilder
     */
    private $queryBusByName;
    /**
     * @var BusRouterBuilder
     */
    private $eventBusByName;

    /**
     * AggregateMessageRouterModule constructor.
     *
     * @param BusRouterBuilder $commandBusByObject
     * @param BusRouterBuilder $commandBusByName
     * @param BusRouterBuilder $queryBusByObject
     * @param BusRouterBuilder $queryBusByName
     * @param BusRouterBuilder $eventBusByObject
     * @param BusRouterBuilder $eventBusByName
     */
    public function __construct(BusRouterBuilder $commandBusByObject, BusRouterBuilder $commandBusByName, BusRouterBuilder $queryBusByObject, BusRouterBuilder $queryBusByName, BusRouterBuilder $eventBusByObject, BusRouterBuilder $eventBusByName)
    {
        $this->commandBusByObject = $commandBusByObject;
        $this->queryBusByObject   = $queryBusByObject;
        $this->eventBusByObject   = $eventBusByObject;
        $this->commandBusByName = $commandBusByName;
        $this->queryBusByName = $queryBusByName;
        $this->eventBusByName = $eventBusByName;
    }

    /**
     * @inheritDoc
     */
    public static function create(AnnotationRegistrationService $annotationRegistrationService)
    {
        return new self(
            BusRouterBuilder::createCommandBusByObject(self::getCommandBusByObjectMapping($annotationRegistrationService)),
            BusRouterBuilder::createCommandBusByName(self::getCommandBusByNamesMapping($annotationRegistrationService)),
            BusRouterBuilder::createQueryBusByObject(self::getQueryBusByObjectsMapping($annotationRegistrationService)),
            BusRouterBuilder::createQueryBusByName(self::getQueryBusByNamesMapping($annotationRegistrationService)),
            BusRouterBuilder::createEventBusByObject(self::getEventBusByObjectsMapping($annotationRegistrationService)),
            BusRouterBuilder::createEventBusByName(self::getEventBusByNamesMapping($annotationRegistrationService))
        );
    }

    /**
     * @inheritDoc
     */
    public function prepare(Configuration $configuration, array $extensionObjects, ModuleReferenceSearchService $moduleReferenceSearchService): void
    {
        $configuration
            ->registerMessageHandler($this->commandBusByObject)
            ->registerMessageHandler($this->commandBusByName)
            ->registerMessageHandler($this->queryBusByObject)
            ->registerMessageHandler($this->queryBusByName)
            ->registerMessageHandler($this->eventBusByObject)
            ->registerMessageHandler($this->eventBusByName);
    }

    public static function getCommandBusByObjectMapping(AnnotationRegistrationService $annotationRegistrationService): array
    {
        $uniqueChannels = [];
        $objectCommandHandlers = [];
        foreach ($annotationRegistrationService->findRegistrationsFor(Aggregate::class, CommandHandler::class) as $registration) {
            $classChannel = ModellingHandlerModule::getClassChannelFor($registration);
            if ($classChannel) {
                $objectCommandHandlers[$classChannel][] = ModellingHandlerModule::getHandlerChannel($registration);
                $uniqueChannels[$classChannel][] = $registration;
            }
        }
        foreach ($annotationRegistrationService->findRegistrationsFor(MessageEndpoint::class, CommandHandler::class) as $registration) {
            $classChannel = ModellingHandlerModule::getClassChannelFor($registration);
            if ($classChannel) {
                $objectCommandHandlers[$classChannel][] = ModellingHandlerModule::getHandlerChannel($registration);
                $uniqueChannels[$classChannel][] = $registration;
            }
        }

        self::verifyUniqueness($uniqueChannels);

        return $objectCommandHandlers;
    }

    public static function getCommandBusByNamesMapping(AnnotationRegistrationService $annotationRegistrationService): array
    {
        $uniqueChannels = [];
        $namedCommandHandlers = [];
        foreach ($annotationRegistrationService->findRegistrationsFor(Aggregate::class, CommandHandler::class) as $registration) {
            self::verifyInputChannel($registration);

            $targetMessageChannel = ModellingHandlerModule::getHandlerChannel($registration);
            $namedChannel = ModellingHandlerModule::getNamedMessageChannelFor($registration);
            if ($namedChannel) {
                $namedCommandHandlers[$namedChannel][] = $targetMessageChannel;
                $uniqueChannels[$namedChannel][] = $registration;
            }
        }
        foreach ($annotationRegistrationService->findRegistrationsFor(MessageEndpoint::class, CommandHandler::class) as $registration) {
            self::verifyInputChannel($registration);

            $targetMessageChannel = ModellingHandlerModule::getHandlerChannel($registration);
            $namedChannel = ModellingHandlerModule::getNamedMessageChannelFor($registration);
            if ($namedChannel) {
                $namedCommandHandlers[$namedChannel][] = $targetMessageChannel;
                $uniqueChannels[$namedChannel][] = $registration;
            }
        }

        self::verifyUniqueness($uniqueChannels);

        return $namedCommandHandlers;
    }

    public static function getQueryBusByObjectsMapping(AnnotationRegistrationService $annotationRegistrationService): array
    {
        $uniqueChannels = [];
        $objectQueryHandlers = [];
        foreach ($annotationRegistrationService->findRegistrationsFor(Aggregate::class, QueryHandler::class) as $registration) {
            self::verifyInputChannel($registration);

            $classChannel = ModellingHandlerModule::getClassChannelFor($registration);
            if ($classChannel) {
                $objectQueryHandlers[$classChannel][] = ModellingHandlerModule::getHandlerChannel($registration);
                $uniqueChannels[$classChannel][] = $registration;
            }
        }
        foreach ($annotationRegistrationService->findRegistrationsFor(MessageEndpoint::class, QueryHandler::class) as $registration) {
            self::verifyInputChannel($registration);

            $classChannel = ModellingHandlerModule::getClassChannelFor($registration);
            if ($classChannel) {
                $objectQueryHandlers[$classChannel][] = ModellingHandlerModule::getHandlerChannel($registration);
                $uniqueChannels[$classChannel][] = $registration;
            }
        }

        self::verifyUniqueness($uniqueChannels);

        return $objectQueryHandlers;
    }

    public static function getQueryBusByNamesMapping(AnnotationRegistrationService $annotationRegistrationService): array
    {
        $uniqueChannels = [];
        $namedQueryHandlers = [];
        foreach ($annotationRegistrationService->findRegistrationsFor(Aggregate::class, QueryHandler::class) as $registration) {
            self::verifyInputChannel($registration);

            $namedChannel = ModellingHandlerModule::getNamedMessageChannelFor($registration);
            if ($namedChannel) {
                $namedQueryHandlers[$namedChannel][] = ModellingHandlerModule::getHandlerChannel($registration);
                $uniqueChannels[$namedChannel][] = $registration;
            }
        }
        foreach ($annotationRegistrationService->findRegistrationsFor(MessageEndpoint::class, QueryHandler::class) as $registration) {
            self::verifyInputChannel($registration);

            $namedChannel = ModellingHandlerModule::getNamedMessageChannelFor($registration);
            if ($namedChannel) {
                $namedQueryHandlers[$namedChannel][] = ModellingHandlerModule::getHandlerChannel($registration);
                $uniqueChannels[$namedChannel][] = $registration;
            }
        }

        self::verifyUniqueness($uniqueChannels);

        return $namedQueryHandlers;
    }

    public static function getEventBusByObjectsMapping(AnnotationRegistrationService $annotationRegistrationService): array
    {
        $objectEventHandlers = [];
        foreach ($annotationRegistrationService->findRegistrationsFor(Aggregate::class, EventHandler::class) as $registration) {
            self::verifyInputChannel($registration);

            $classChannel = ModellingHandlerModule::getClassChannelFor($registration);
            if ($classChannel) {
                $objectEventHandlers[$classChannel][] = ModellingHandlerModule::getHandlerChannel($registration);
            }
        }
        foreach ($annotationRegistrationService->findRegistrationsFor(MessageEndpoint::class, EventHandler::class) as $registration) {
            self::verifyInputChannel($registration);

            $classChannel = ModellingHandlerModule::getClassChannelFor($registration);
            if ($classChannel) {
                $objectEventHandlers[$classChannel][] = ModellingHandlerModule::getHandlerChannel($registration);
            }
        }

        return $objectEventHandlers;
    }

    public static function getEventBusByNamesMapping(AnnotationRegistrationService $annotationRegistrationService): array
    {
        $namedEventHandlers = [];
        foreach ($annotationRegistrationService->findRegistrationsFor(MessageEndpoint::class, EventHandler::class) as $registration) {
            if (ModellingHandlerModule::getNamedMessageChannelFor($registration)) {
                $namedEventHandlers[ModellingHandlerModule::getNamedMessageChannelFor($registration)][] = $targetMessageChannel = ModellingHandlerModule::getHandlerChannel($registration);
            }
        }
        foreach ($annotationRegistrationService->findRegistrationsFor(Aggregate::class, EventHandler::class) as $registration) {
            if (ModellingHandlerModule::getNamedMessageChannelFor($registration)) {
                $namedEventHandlers[ModellingHandlerModule::getNamedMessageChannelFor($registration)][] = $targetMessageChannel = ModellingHandlerModule::getHandlerChannel($registration);
            }
        }
        return $namedEventHandlers;
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
    public function canHandle($extensionObject): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getRelatedReferences(): array
    {
        return [];
    }

    private static function verifyInputChannel(AnnotationRegistration $annotationRegistration) : void
    {
        if (!ModellingHandlerModule::getNamedMessageChannelFor($annotationRegistration) && !ModellingHandlerModule::getClassChannelFor($annotationRegistration)) {
            throw ConfigurationException::create("Handler {$annotationRegistration->getClassName()}:{$annotationRegistration->getMethodName()} has no input channel information. Configure inputChannelName or type hint first argument as class");
        }
    }

    /**
     * @param array $uniqueChannels
     * @throws \Ecotone\Messaging\MessagingException
     */
    private static function verifyUniqueness(array $uniqueChannels): void
    {
        foreach ($uniqueChannels as $uniqueChannelName => $registrations) {
            $isUnique = true;
            $combinedRegistrationNames = "";
            if (count($registrations) === 1) {
                continue;
            }

            /** @var AnnotationRegistration $registration */
            foreach ($registrations as $registration) {
                /** @var CommandHandler|QueryHandler $method */
                $method = $registration->getAnnotationForMethod();
                $combinedRegistrationNames .= " {$registration->getClassName()}:{$registration->getMethodName()}";

                if ($method->mustBeUnique) {
                    $isUnique = false;
                }
            }

            if (!$isUnique) {
                throw ConfigurationException::create("Channel name `{$uniqueChannelName}` should be unique, but is used in multiple handlers:{$combinedRegistrationNames}");
            }
        }
    }
}