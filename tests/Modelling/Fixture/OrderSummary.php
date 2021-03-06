<?php

namespace Test\Ecotone\Modelling\Fixture;

use Ecotone\Messaging\Annotation\MessageEndpoint;
use Ecotone\Modelling\Annotation\QueryHandler;

/**
 * @MessageEndpoint()
 */
class OrderSummary
{
    /**
     * @QueryHandler(ignoreMessage=true)
     */
    public function getOrders(GetOrdersQuery $query) : array
    {
        //return orders
    }
}