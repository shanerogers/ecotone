<?php

namespace Test\Ecotone\Messaging\Fixture\Handler\Processor\Interceptor;

use Ecotone\Messaging\Annotation\Interceptor\Around;
use Ecotone\Messaging\Annotation\Interceptor\MethodInterceptor;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInvocation;

/**
 * Class CallWithUnorderedClassInvocationInterceptorExample
 * @package Test\Ecotone\Messaging\Fixture\Handler\Processor\Interceptor
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 * @MethodInterceptor()
 */
class CallWithUnorderedClassInvocationInterceptorExample extends BaseInterceptorExample
{
    /**
     * @param MethodInvocation $methodInvocation
     * @param int $test
     * @param \stdClass $stdClass
     * @return mixed
     * @Around()
     */
    public function callWithUnorderedClassInvocation(MethodInvocation $methodInvocation, int $test, \stdClass $stdClass)
    {
        return $methodInvocation->proceed();
    }
}