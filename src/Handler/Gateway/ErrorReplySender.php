<?php

namespace SimplyCodedSoftware\Messaging\Handler\Gateway;

use SimplyCodedSoftware\Messaging\Handler\MessageHandlingException;
use SimplyCodedSoftware\Messaging\Message;
use SimplyCodedSoftware\Messaging\Support\ErrorMessage;
use SimplyCodedSoftware\Messaging\Support\MessageBuilder;

/**
 * Class ErrorReplySender
 * @package SimplyCodedSoftware\Messaging\Handler\Gateway
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 * @internal
 */
class ErrorReplySender implements ReplySender
{
    /**
     * @var ReplySender
     */
    private $replySender;

    /**
     * @inheritDoc
     */
    public function addErrorChannel(MessageBuilder $messageBuilder): MessageBuilder
    {
        return $this->replySender->addErrorChannel($messageBuilder);
    }

    /**
     * ErrorReplySender constructor.
     * @param ReplySender $replySender
     */
    private function __construct(ReplySender $replySender)
    {
        $this->replySender = $replySender;
    }

    /**
     * @param ReplySender $replySender
     * @return ErrorReplySender
     */
    public static function create(ReplySender $replySender) : self
    {
        return new self($replySender);
    }

    /**
     * @inheritDoc
     */
    public function receiveReply(): ?Message
    {
        $replyMessage = $this->replySender->receiveReply();

        if (is_null($replyMessage)) {
            return null;
        }
        if ($replyMessage instanceof ErrorMessage) {
            throw MessageHandlingException::fromErrorMessage($replyMessage);
        }

        return $replyMessage;
    }

    /**
     * @inheritDoc
     */
    public function hasReply(): bool
    {
        return $this->replySender->hasReply();
    }
}