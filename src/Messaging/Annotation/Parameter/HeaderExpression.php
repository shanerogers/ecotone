<?php
declare(strict_types=1);


namespace Ecotone\Messaging\Annotation\Parameter;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * Class HeaderExpression
 * @package Ecotone\Messaging\Annotation\Parameter
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 * @Annotation
 */
class HeaderExpression
{
    /**
     * @var string
     * @Required()
     */
    public $headerName;
    /**
     * @var string
     * @Required()
     */
    public $expression;
}