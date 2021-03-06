<?php


namespace Test\Ecotone\Modelling\Fixture\Annotation\QueryHandler\Service;

use Ecotone\Messaging\Annotation\MessageEndpoint;
use Ecotone\Modelling\Annotation\QueryHandler;

/**
 * @MessageEndpoint()
 */
class ServiceQueryHandlersWithNotUniqueClass
{
    /**
     * @QueryHandler()
     */
    public function execute1(\stdClass $class) : int
    {

    }

    /**
     * @QueryHandler()
     */
    public function execute2(\stdClass $class) : int
    {

    }
}