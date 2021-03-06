<?php
declare(strict_types=1);

namespace Test\Ecotone\Messaging\Fixture\Annotation\Interceptor;

use Ecotone\Messaging\Annotation\Interceptor\Around;
use Ecotone\Messaging\Annotation\Interceptor\MethodInterceptor;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInvocation;

/**
 * Class CalculatingService
 * @package Test\Ecotone\Messaging\Fixture\Service
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 * @MethodInterceptor(referenceName="calculatingService")
 */
class CalculatingServiceInterceptorExample
{
    /**
     * @var int
     */
    private $secondValueForMathOperations;
    /**
     * @var bool
     */
    private $wasCalled = false;

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
     */
    public function result(MethodInvocation $methodInvocation, int $amount) : int
    {
        return $amount;
    }

    /**
     * @param MethodInvocation $methodInvocation
     * @param int $amount
     * @Around(precedence=2, pointcut=CalculatingServiceInterceptorExample::class)
     * @return mixed
     */
    public function sum(MethodInvocation $methodInvocation, int $amount)
    {
        $result = $amount + $this->secondValueForMathOperations;

        $methodInvocation->replaceArgument("amount", $result);
        return $methodInvocation->proceed();
    }

    /**
     * @param MethodInvocation $methodInvocation
     * @return integer
     */
    public function sumAfterCalling(MethodInvocation $methodInvocation) : int
    {
        $result = $methodInvocation->proceed();

        return $this->secondValueForMathOperations + $result;
    }

    /**
     * @param MethodInvocation $methodInvocation
     * @param int $amount
     * @return int
     * @Around()
     */
    public function subtract(MethodInvocation $methodInvocation, int $amount) : int
    {
        $result = $amount - $this->secondValueForMathOperations;

        $methodInvocation->replaceArgument("amount", $result);
        return $methodInvocation->proceed();
    }

    /**
     * @param MethodInvocation $methodInvocation
     * @param int $amount
     * @return int
     * @Around(precedence=2, pointcut=CalculatingServiceInterceptorExample::class)
     */
    public function multiply(MethodInvocation $methodInvocation, int $amount) : int
    {
        $result = $amount * $this->secondValueForMathOperations;

        $methodInvocation->replaceArgument("amount", $result);
        return $methodInvocation->proceed();
    }

    /**
     * @return bool
     */
    public function isWasCalled(): bool
    {
        return $this->wasCalled;
    }
}