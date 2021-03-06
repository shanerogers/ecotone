<?php

namespace Test\Ecotone\Modelling\Fixture\Saga;

use Ecotone\Modelling\Annotation\Aggregate;
use Ecotone\Modelling\Annotation\AggregateIdentifier;
use Ecotone\Modelling\Annotation\CommandHandler;
use Ecotone\Modelling\Annotation\EventHandler;
use Test\Ecotone\Modelling\Fixture\Saga\OrderWasPlacedEvent;
use Test\Ecotone\Modelling\Fixture\Saga\PaymentWasDoneEvent;

/**
 * Class Article
 * @package Test\Ecotone\Modelling\Fixture\Blog
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 * @Aggregate()
 */
class OrderFulfilment
{
    /**
     * @var string
     * @AggregateIdentifier()
     */
    private $orderId;
    /**
     * @var string
     */
    private $status;

    /**
     * Article constructor.
     *
     * @param string $orderId
     */
    private function __construct(string $orderId)
    {
        $this->orderId  = $orderId;
        $this->status = "new";
    }

    /**
     * @EventHandler()
     */
    public static function createWith(string $orderId) : self
    {
        return new self($orderId);
    }

    /**
     * @EventHandler(identifierMetadataMapping={"orderId":"paymentId"})
     */
    public function finishOrder(PaymentWasDoneEvent $event) : void
    {
        $this->status = "done";
    }

    public function getId() : string
    {
        return $this->orderId;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }
}