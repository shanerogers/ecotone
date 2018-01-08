<?php

namespace SimplyCodedSoftware\Messaging\Handler\Processor\MethodInvoker;

use SimplyCodedSoftware\Messaging\Handler\MethodArgument;
use SimplyCodedSoftware\Messaging\Message;

/**
 * Class PayloadArgument
 * @package SimplyCodedSoftware\Messaging\Handler\ServiceActivator
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class PayloadArgument implements MethodArgument
{
    /**
     * @var string
     */
    private $argumentName;

    /**
     * PayloadArgument constructor.
     * @param string $argumentName
     */
    private function __construct(string $argumentName)
    {
        $this->argumentName = $argumentName;
    }

    public static function create(string $argumentName)
    {
        return new self($argumentName);
    }

    /**
     * @inheritDoc
     */
    public function getFrom(Message $message)
    {
        return $message->getPayload();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->argumentName;
    }
}