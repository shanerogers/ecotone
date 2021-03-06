<?php
declare(strict_types=1);

namespace Test\Ecotone\Messaging\Fixture\Annotation\Interceptor;

use Ecotone\Messaging\Annotation\Interceptor\Around;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInvocation;

/**
 * Class CalculatingService
 * @package Test\Ecotone\Messaging\Fixture\Service
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class SummingInterceptorExample
{
    /**
     * @var int
     */
    private $secondValueForMathOperations;

    /**
     * @param int $secondValueForMathOperations
     * @return CalculatingServiceInterceptorExample
     */
    public static function create(int $secondValueForMathOperations) : self
    {
        $calculatingService = new self();
        $calculatingService->secondValueForMathOperations = $secondValueForMathOperations;

        return $calculatingService;
    }

    /**
     * @param MethodInvocation $methodInvocation
     * @param int $amount
     * @return int
     * @Around(precedence=4)
     */
    public function sum(MethodInvocation $methodInvocation, int $amount) : int
    {
        $result = $amount + $this->secondValueForMathOperations;

        $methodInvocation->replaceArgument("amount", $result);
        return $methodInvocation->proceed();
    }
}