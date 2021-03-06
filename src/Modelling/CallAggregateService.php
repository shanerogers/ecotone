<?php

namespace Ecotone\Modelling;

use Ecotone\Messaging\Handler\ChannelResolver;
use Ecotone\Messaging\Handler\MessageHandlingException;
use Ecotone\Messaging\Handler\ParameterConverter;
use Ecotone\Messaging\Handler\ParameterConverterBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\AroundMethodInterceptor;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInvoker;
use Ecotone\Messaging\Handler\ReferenceSearchService;
use Ecotone\Messaging\Message;
use Ecotone\Messaging\Support\Assert;
use Ecotone\Messaging\Support\MessageBuilder;

/**
 * Class CallAggregateService
 * @package Ecotone\Modelling
 * @author  Dariusz Gafka <dgafka.mail@gmail.com>
 */
class CallAggregateService
{
    /**
     * @var array|ParameterConverterBuilder[]
     */
    private $messageToParameterConverters;
    /**
     * @var ChannelResolver
     */
    private $channelResolver;
    /**
     * @var ReferenceSearchService
     */
    private $referenceSearchService;
    /**
     * @var array|AroundMethodInterceptor[]
     */
    private $aroundMethodInterceptors;
    /**
     * @var bool
     */
    private $withFactoryRedirectOnFoundMethodName;
    /**
     * @var array|ParameterConverterBuilder[]
     */
    private $withFactoryRedirectOnFoundParameterConverters;
    /**
     * @var string|null
     */
    private $eventSourcedFactoryMethod;
    /**
     * @var bool
     */
    private $isCommandHandler;

    /**
     * ServiceCallToAggregateAdapter constructor.
     *
     * @param ChannelResolver $channelResolver
     * @param array|ParameterConverterBuilder[] $messageToParameterConverters
     * @param AroundMethodInterceptor[] $aroundMethodInterceptors
     * @param ReferenceSearchService $referenceSearchService
     * @param string $withFactoryRedirectOnFoundMethodName
     * @param ParameterConverterBuilder[] $withFactoryRedirectOnFoundParameterConverters
     * @param string|null $eventSourcedFactoryMethod
     * @param bool $isCommand
     * @throws \Ecotone\Messaging\MessagingException
     */
    public function __construct(ChannelResolver $channelResolver, array $messageToParameterConverters, array $aroundMethodInterceptors, ReferenceSearchService $referenceSearchService,
                                string $withFactoryRedirectOnFoundMethodName, array $withFactoryRedirectOnFoundParameterConverters, ?string $eventSourcedFactoryMethod, bool $isCommand
    )
    {
        Assert::allInstanceOfType($messageToParameterConverters, ParameterConverter::class);

        $this->messageToParameterConverters = $messageToParameterConverters;
        $this->channelResolver = $channelResolver;
        $this->referenceSearchService = $referenceSearchService;
        $this->aroundMethodInterceptors = $aroundMethodInterceptors;
        $this->withFactoryRedirectOnFoundMethodName = $withFactoryRedirectOnFoundMethodName;
        $this->withFactoryRedirectOnFoundParameterConverters = $withFactoryRedirectOnFoundParameterConverters;
        $this->eventSourcedFactoryMethod = $eventSourcedFactoryMethod;
        $this->isCommandHandler = $isCommand;
    }

    public function call(Message $message) : ?Message
    {
        $aggregate = $message->getHeaders()->containsKey(AggregateMessage::AGGREGATE_OBJECT)
                            ? $message->getHeaders()->get(AggregateMessage::AGGREGATE_OBJECT)
                            : null;

        if ($aggregate && $this->withFactoryRedirectOnFoundMethodName) {
            $methodInvoker = MethodInvoker::createWithBuiltParameterConverters(
                $aggregate,
                $this->withFactoryRedirectOnFoundMethodName,
                $this->withFactoryRedirectOnFoundParameterConverters,
                $this->referenceSearchService,
                $this->aroundMethodInterceptors,
                [],
                true
            );
        }else {
            $methodInvoker = MethodInvoker::createWithBuiltParameterConverters(
                $aggregate ? $aggregate : $message->getHeaders()->get(AggregateMessage::CLASS_NAME),
                $message->getHeaders()->get(AggregateMessage::METHOD_NAME),
                $this->messageToParameterConverters,
                $this->referenceSearchService,
                $this->aroundMethodInterceptors,
                [],
                true
            );
        }

        $resultMessage = MessageBuilder::fromMessage($message);
        $result = $methodInvoker->processMessage($message);

        if (!$aggregate) {
            if ($message->getHeaders()->get(AggregateMessage::IS_EVENT_SOURCED)) {
                $resultMessage = $resultMessage
                    ->setHeader(AggregateMessage::AGGREGATE_OBJECT, call_user_func([$message->getHeaders()->get(AggregateMessage::CLASS_NAME), $this->eventSourcedFactoryMethod], $result));
            }else {
                $resultMessage = $resultMessage
                    ->setHeader(AggregateMessage::AGGREGATE_OBJECT, $result);
            }
        }

        if (!is_null($result)) {
            $resultMessage = $resultMessage
                ->setPayload($result);
        }

        if ($this->isCommandHandler || !is_null($result)) {
            return $resultMessage
                ->build();
        }

        return null;
    }
}