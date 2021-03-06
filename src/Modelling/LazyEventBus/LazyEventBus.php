<?php


namespace Ecotone\Modelling\LazyEventBus;

use Ecotone\Messaging\Annotation\MessageGateway;
use Ecotone\Messaging\Annotation\MessageEndpoint;
use Ecotone\Messaging\Annotation\Parameter\Headers;
use Ecotone\Messaging\Annotation\Parameter\Payload;

/**
 * Interface LazyEventBus
 * @package Ecotone\Modelling
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 * @MessageEndpoint()
 */
interface LazyEventBus
{
    const CHANNEL_NAME = "ecotone.modelling.bus.lazy_event";

    /**
     * Lazy send, it will be published after command handler is done
     *
     * @param object $event instance of command
     *
     * @return mixed
     *
     * @MessageGateway(requestChannel=LazyEventBus::CHANNEL_NAME)
     */
    public function send(object $event);

    /**
     * Lazy send, it will be published after command handler is done
     *
     * @param object $event instance of command
     * @param array  $metadata
     *
     * @return mixed
     *
     * @MessageGateway(
     *     requestChannel=LazyEventBus::CHANNEL_NAME,
     *     parameterConverters={
     *         @Payload(parameterName="event"),
     *         @Headers(parameterName="metadata")
     *     }
     * )
     */
    public function sendWithMetadata(object $event, array $metadata);
}