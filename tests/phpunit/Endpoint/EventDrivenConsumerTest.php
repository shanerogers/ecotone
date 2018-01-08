<?php

namespace Test\SimplyCodedSoftware\Messaging\Endpoint;

use Fixture\Handler\NoReturnMessageHandler;
use SimplyCodedSoftware\Messaging\Channel\DirectChannel;
use SimplyCodedSoftware\Messaging\Channel\MessageDispatchingException;
use SimplyCodedSoftware\Messaging\Endpoint\EventDrivenConsumer;
use SimplyCodedSoftware\Messaging\Support\MessageBuilder;
use Test\SimplyCodedSoftware\Messaging\MessagingTest;

/**
 * Class EventDrivenConsumerTest
 * @package SimplyCodedSoftware\Messaging\Endpoint
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class EventDrivenConsumerTest extends MessagingTest
{
    public function test_starting_consumer()
    {
        $directChannel = DirectChannel::create();
        $handler = NoReturnMessageHandler::create();
        $eventDrivenConsumer = new EventDrivenConsumer('some', $directChannel, $handler);

        $eventDrivenConsumer->start();

        $directChannel->send(MessageBuilder::withPayload('test')->build());

        $this->assertTrue($handler->wasCalled(), "Handler for event driven consumer was not called");
        $this->assertTrue($eventDrivenConsumer->isRunning(), "Event driven consumer should be running");
    }

    public function test_stopping_consumer()
    {
        $directChannel = DirectChannel::create();
        $handler = NoReturnMessageHandler::create();
        $eventDrivenConsumer = new EventDrivenConsumer('some', $directChannel, $handler);

        $eventDrivenConsumer->start();
        $eventDrivenConsumer->stop();

        $this->expectException(MessageDispatchingException::class);

        $directChannel->send(MessageBuilder::withPayload('test')->build());
        $this->assertFalse($eventDrivenConsumer->isRunning(), "Event driven consumer should not be running after");
    }

    public function test_naming_and_configuration()
    {
        $directChannel = DirectChannel::create();
        $handler = NoReturnMessageHandler::create();
        $eventDrivenConsumer = new EventDrivenConsumer('some', $directChannel, $handler);

        $this->assertEquals("some", $eventDrivenConsumer->getConsumerName());
        $this->assertEquals("", $eventDrivenConsumer->getMissingConfiguration());
        $this->assertFalse($eventDrivenConsumer->isMissingConfiguration(), "Configuration should not be missing");
    }
}