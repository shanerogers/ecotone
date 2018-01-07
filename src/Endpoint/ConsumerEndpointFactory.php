<?php

namespace Messaging\Endpoint;

use Messaging\Handler\ChannelResolver;
use Messaging\Handler\MessageHandlerBuilder;
use Messaging\MessageChannel;
use Messaging\MessageHandler;
use Messaging\PollableChannel;
use Messaging\SubscribableChannel;

/**
 * Class ConsumerEndpointFactory - Responsible for creating consumers from builders
 * @package Messaging\Config
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class ConsumerEndpointFactory
{
    /**
     * @var ChannelResolver
     */
    private $channelResolver;
    /**
     * @var PollableConsumerFactory
     */
    private $pollableFactory;

    /**
     * ConsumerEndpointFactory constructor.
     * @param ChannelResolver $channelResolver
     * @param PollableConsumerFactory $pollableFactory
     */
    public function __construct(ChannelResolver $channelResolver, PollableConsumerFactory $pollableFactory)
    {
        $this->channelResolver = $channelResolver;
        $this->pollableFactory = $pollableFactory;
    }

    /**
     * @param MessageHandlerBuilder $messageHandlerBuilder
     * @return ConsumerLifecycle
     */
    public function create(MessageHandlerBuilder $messageHandlerBuilder) : ConsumerLifecycle
    {
        $inputMessageChannel = $this->channelResolver->resolve($messageHandlerBuilder->getInputMessageChannelName());
        $messageHandlerBuilder = $messageHandlerBuilder->setChannelResolver($this->channelResolver);

        if ($inputMessageChannel instanceof SubscribableChannel) {
            return new EventDrivenConsumer($messageHandlerBuilder->messageHandlerName(), $inputMessageChannel, $messageHandlerBuilder->build());
        }elseif ($inputMessageChannel instanceof PollableChannel) {
            return $this->pollableFactory->create($messageHandlerBuilder->messageHandlerName(), $inputMessageChannel, $messageHandlerBuilder->build());
        }
    }
}