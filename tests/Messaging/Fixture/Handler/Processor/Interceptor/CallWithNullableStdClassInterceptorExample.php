<?php

namespace Test\Ecotone\Messaging\Fixture\Handler\Processor\Interceptor;

use Ecotone\Messaging\Annotation\Interceptor\Around;
use Ecotone\Messaging\Annotation\Interceptor\MethodInterceptor;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInvocation;

/**
 * Class CallWithNullableStdClassInterceptorExample
 * @package Test\Ecotone\Messaging\Fixture\Handler\Processor\Interceptor
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 * @MethodInterceptor()
 */
class CallWithNullableStdClassInterceptorExample extends BaseInterceptorExample
{
    /**
     * @param MethodInvocation $methodInvocation
     * @param \stdClass|null $stdClass
     * @return \stdClass|null
     * @Around()
     */
    public function callWithNullableStdClass(MethodInvocation $methodInvocation, ?\stdClass $stdClass)
    {
        return $stdClass;
    }
}