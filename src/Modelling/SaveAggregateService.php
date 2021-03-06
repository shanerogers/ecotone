<?php

namespace Ecotone\Modelling;

use Ecotone\Messaging\Handler\Enricher\PropertyEditorAccessor;
use Ecotone\Messaging\Handler\Enricher\PropertyPath;
use Ecotone\Messaging\Handler\Enricher\PropertyReaderAccessor;
use Ecotone\Messaging\Handler\NonProxyGateway;
use Ecotone\Messaging\Handler\TypeDescriptor;
use Ecotone\Messaging\Message;
use Ecotone\Messaging\MessageHeaders;
use Ecotone\Messaging\Support\Assert;
use Ecotone\Messaging\Support\InvalidArgumentException;
use Ecotone\Messaging\Support\MessageBuilder;
use Ecotone\Modelling\LazyEventBus\LazyEventBus;

/**
 * Class SaveAggregateService
 * @package Ecotone\Modelling
 * @author  Dariusz Gafka <dgafka.mail@gmail.com>
 */
class SaveAggregateService
{
    /**
     * @var StandardRepository|EventSourcedRepository
     */
    private $aggregateRepository;
    /**
     * @var PropertyReaderAccessor
     */
    private $propertyReaderAccessor;
    /**
     * @var NonProxyGateway|LazyEventBus
     */
    private $lazyEventBus;
    /**
     * @var string|null
     */
    private $eventMethod;
    /**
     * @var PropertyEditorAccessor
     */
    private $propertyEditorAccessor;
    /**
     * @var array|null
     */
    private $versionMapping;
    /**
     * @var string
     */
    private $aggregateClassName;

    /**
     * SaveAggregateService constructor.
     * @param string $aggregateClassName
     * @param object|StandardRepository|EventSourcedRepository $aggregateRepository
     * @param PropertyEditorAccessor $propertyEditorAccessor
     * @param PropertyReaderAccessor $propertyReaderAccessor
     * @param NonProxyGateway $lazyEventBus
     * @param string|null $eventMethod
     * @param array|null $versionMapping
     */
    public function __construct(string $aggregateClassName, object $aggregateRepository, PropertyEditorAccessor $propertyEditorAccessor, PropertyReaderAccessor $propertyReaderAccessor, NonProxyGateway $lazyEventBus, ?string $eventMethod, ?array $versionMapping)
    {
        $this->aggregateRepository = $aggregateRepository;
        $this->propertyReaderAccessor = $propertyReaderAccessor;
        $this->lazyEventBus = $lazyEventBus;
        $this->eventMethod = $eventMethod;
        $this->propertyEditorAccessor = $propertyEditorAccessor;
        $this->versionMapping = $versionMapping;
        $this->aggregateClassName = $aggregateClassName;
    }

    /**
     * @param Message $message
     *
     * @return Message
     * @throws \ReflectionException
     * @throws \Ecotone\Messaging\MessagingException
     * @throws \Ecotone\Messaging\Support\InvalidArgumentException
     */
    public function save(Message $message) : ?Message
    {
        $aggregate = $message->getHeaders()->get(AggregateMessage::AGGREGATE_OBJECT);
        $events = [];

        if ($this->aggregateRepository instanceof EventSourcedRepository) {
            $events = $message->getPayload();
            Assert::isIterable($events, "Return value Event Sourced Aggregate {$this->aggregateClassName} must return iterable events");
        }elseif ($this->eventMethod) {
            $events = call_user_func([$aggregate, $this->eventMethod]);
        }
        foreach ($events as $event) {
            if (!is_object($event)) {
                $typeDescriptor = TypeDescriptor::createFromVariable($event);
                throw InvalidArgumentException::create("Events return by after calling {$this->aggregateClassName}:{$message->getHeaders()->get(AggregateMessage::METHOD_NAME)} must all be objects, {$typeDescriptor->toString()} given");
            }
        }

        $nextVersion = null;
        if ($this->versionMapping) {
            $aggregatePropertyName = reset($this->versionMapping);
            $nextVersion = $this->propertyReaderAccessor->getPropertyValue(
                PropertyPath::createWith($aggregatePropertyName),
                $aggregate
            );
            $nextVersion = is_null($nextVersion) ? 1 : $nextVersion + 1;

            $this->propertyEditorAccessor->enrichDataWith(
                PropertyPath::createWith($aggregatePropertyName),
                $aggregate,
                $nextVersion,
                $message,
                null
            );
        }

        $aggregateIds = [];
        foreach ($message->getHeaders()->get(AggregateMessage::AGGREGATE_ID) as $aggregateIdName => $aggregateIdValue) {
            $id = $this->propertyReaderAccessor->getPropertyValue(PropertyPath::createWith($aggregateIdName), $aggregate);

            if (!$id) {
                throw NoCorrectIdentifierDefinedException::create("{$this->aggregateClassName} after calling {$message->getHeaders()->get(AggregateMessage::METHOD_NAME)} has no identifier assigned. Are you sure you have set the state correctly?");
            }

            $aggregateIds[$aggregateIdName] = $id;
        }

        if ($message->getHeaders()->get(AggregateMessage::IS_FACTORY_METHOD)) {
            $message =
                MessageBuilder::fromMessage($message)
                    ->setPayload($aggregateIds)
                    ->build();
        }
        $version = null;
        if ($nextVersion && $message->getHeaders()->containsKey(AggregateMessage::TARGET_VERSION)) {
            $expectedVersion = $message->getHeaders()->get(AggregateMessage::TARGET_VERSION);
            if ($expectedVersion && $nextVersion != $expectedVersion + 1) {
                throw AggregateVersionMismatchException::create("Aggregate version is different");
            }
        }

        /** @var Message $callingMessage */
        $callingMessage = $message->getHeaders()->get(AggregateMessage::CALLING_MESSAGE);
        $metadata = $callingMessage->getHeaders()->headers();
        unset($metadata[MessageHeaders::REPLY_CHANNEL]);

        $this->aggregateRepository->save($aggregateIds, $this->aggregateRepository instanceof EventSourcedRepository ? $events : $aggregate, $metadata, $nextVersion);

        foreach ($events as $event) {
            $this->lazyEventBus->execute([$event, $metadata]);
        }

        return MessageBuilder::fromMessage($message)
                ->build();
    }
}