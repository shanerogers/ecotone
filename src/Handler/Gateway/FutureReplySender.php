<?php

namespace SimplyCodedSoftware\Messaging\Handler\Gateway;

use SimplyCodedSoftware\Messaging\Future;

/**
 * Class FutureReplySender
 * @package SimplyCodedSoftware\Messaging\Handler\Gateway
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 * @internal
 */
class FutureReplySender implements Future
{
    /**
     * @var ReplySender
     */
    private $replySender;

    /**
     * FutureReplySender constructor.
     * @param ReplySender $replySender
     */
    private function __construct(ReplySender $replySender)
    {
        $this->replySender = $replySender;
    }

    /**
     * @param ReplySender $replySender
     * @return FutureReplySender
     */
    public static function create(ReplySender $replySender) : self
    {
        return new self($replySender);
    }

    /**
     * @inheritDoc
     */
    public function resolve()
    {
        $message = $this->replySender->receiveReply();

        return $message ? $message->getPayload() : null;
    }
}