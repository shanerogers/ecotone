<?php

namespace Ecotone\Modelling\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;
use Ecotone\Messaging\Annotation\InputOutputEndpointAnnotation;

/**
 * Class QueryHandler
 * @package Ecotone\Modelling\Annotation
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 * @Annotation
 * @Target({"METHOD"})
 */
class QueryHandler extends InputOutputEndpointAnnotation
{
    /**
     * @var array
     */
    public $parameterConverters = [];
    /**
     * if endpoint is not interested in message, set to true.
     * inputChannelName must be defined to connect with external channels
     *
     * @var string
     */
    public $ignoreMessage = false;
    /**
     * Does the handler allow for same command class name
     *
     * @var bool
     */
    public $mustBeUnique = true;
}