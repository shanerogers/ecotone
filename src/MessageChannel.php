<?php

namespace SimplyCodedSoftware\Messaging;

/**
 * Interface MessageChannel
 * @package SimplyCodedSoftware\Messaging
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
interface MessageChannel
{
    /**
     * Send message to this channel
     *
     * @param Message $message
     * @return void
     */
    public function send(Message $message) : void;
}