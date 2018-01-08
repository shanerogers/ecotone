<?php

namespace SimplyCodedSoftware\Messaging\Handler\Gateway\Poller;

use SimplyCodedSoftware\Messaging\Handler\Gateway\ReplySender;
use SimplyCodedSoftware\Messaging\Message;
use SimplyCodedSoftware\Messaging\PollableChannel;
use SimplyCodedSoftware\Messaging\Support\MessageBuilder;

/**
 * Class TimeoutChannelReplySender
 * @package SimplyCodedSoftware\Messaging\Handler\Gateway\Poller
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class TimeoutChannelReplySender implements ReplySender
{
    const MICROSECOND_TO_MILLI_SECOND = 1000;
    /**
     * @var PollableChannel
     */
    private $replyChannel;
    /**
     * @var int
     */
    private $millisecondsTimeout;

    /**
     * ReceivePoller constructor.
     * @param PollableChannel $replyChannel
     * @param int $millisecondsTimeout
     */
    public function __construct(PollableChannel $replyChannel, int $millisecondsTimeout)
    {
        $this->replyChannel = $replyChannel;
        $this->millisecondsTimeout = $millisecondsTimeout;
    }

    /**
     * @inheritDoc
     */
    public function addErrorChannel(MessageBuilder $messageBuilder): MessageBuilder
    {
        return $messageBuilder
                    ->setErrorChannel($this->replyChannel);
    }

    /**
     * @inheritDoc
     */
    public function receiveReply(): ?Message
    {
        $message = null;
        $startingTimestamp = $this->currentMillisecond();

        while (($this->currentMillisecond() - $startingTimestamp) <= $this->millisecondsTimeout && is_null($message)) {
            $message = $this->replyChannel->receive();
        }

        return $message;
    }

    /**
     * @return float
     */
    private function currentMillisecond(): float
    {
        return microtime(true) * self::MICROSECOND_TO_MILLI_SECOND;
    }

    /**
     * @inheritDoc
     */
    public function hasReply(): bool
    {
        return true;
    }
}