<?php

namespace Messaging;

/**
 * Interface Clock
 * @package Messaging
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
interface Clock
{
    /**
     * @return int
     */
    public function getCurrentTimestamp() : int;
}