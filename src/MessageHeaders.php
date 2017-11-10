<?php

namespace Messaging;

use Messaging\InvalidMessageHeaderException;
use Messaging\MessageHeaderDoesNotExistsException;
use Ramsey\Uuid\Uuid;

/**
 * Class MessageHeaders
 * @package Messaging
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class MessageHeaders
{
    /**
     * An identifier for this message instance. Changes each time a message is mutated.
     */
    const MESSAGE_ID = 'id';
    /**
     * Used to correlate two or more messages.
     */
    const MESSAGE_CORRELATION_ID = 'correlationId';
    /**
     * Used to point parent message
     */
    const CAUSATION_MESSAGE_ID = 'parentId';
    /**
     * content-type values are parsed as media types, e.g., application/json or text/plain;charset=UTF-8
     */
    const CONTENT_TYPE = 'contentType';
    /**
     * The time the message was created. Changes each time a message is mutated.
     */
    const TIMESTAMP = 'timestamp';
    /**
     * A channel to which errors will be sent. It must represent a name from registry of a class implementing MessageChannel
     */
    const REPLY_CHANNEL = 'replyChannel';
    /**
     * A channel to which errors will be sent. It must represent a name from registry of a class implementing MessageChannel
     */
    const ERROR_CHANNEL = 'errorChannel';
    /**
     * Usually a sequence number with a group of messages with a SEQUENCE_SIZE
     */
    const SEQUENCE_NUMBER = 'sequenceNumber';
    /**
     * The number of messages within a group of correlated messages.
     */
    const SEQUENCE_SIZE = 'sequenceSize';
    /**
     * Indicates when a message is expired
     */
    const EXPIRATION_DATE = 'expirationDate';
    /**
     * Message priority; for example within a PriorityChannel
     */
    const PRIORITY = 'priority';
    /**
     * True if a message was detected as a duplicate by an idempotent receiver interceptor
     */
    const DUPLICATE_MESSAGE = 'duplicateMessage';

    /**
     * @var array|string[]
     */
    private $headers;


    /**
     * MessageHeaders constructor.
     * @param array $headers
     */
    final private function __construct(array $headers)
    {
        $this->initialize($headers);
    }

    /**
     * @param int $timestamp
     * @return MessageHeaders|static
     */
    final public static function createEmpty(int $timestamp) : self
    {
        return static::createMessageHeadersWith([], $timestamp);
    }

    /**
     * @param int $timestamp
     * @param array|string[] $headers
     * @return MessageHeaders|static
     */
    final public static function create(int $timestamp, array $headers) : self
    {
        return static::createMessageHeadersWith($headers, $timestamp);
    }

    /**
     * @return array|string[]
     */
    final public function headers() : array
    {
        return $this->headers;
    }

    /**
     * @param string $headerName
     * @return bool
     */
    final public function containsKey(string $headerName) : bool
    {
        return array_key_exists($headerName, $this->headers);
    }

    /**
     * @param string $value
     * @return bool
     */
    final public function containsValue(string $value) : bool
    {
        return in_array($value, $this->headers);
    }

    /**
     * @param string $headerName
     * @return mixed
     * @throws \Messaging\MessagingException
     */
    final public function get(string $headerName)
    {
        if (!$this->containsKey($headerName)) {
            throw MessageHeaderDoesNotExistsException::create("Header with name {$headerName} does not exists");
        }

        return $this->headers[$headerName];
    }

    /**
     * @return int
     */
    final public function size() : int
    {
        return count($this->headers());
    }

    /**
     * @param MessageHeaders $messageHeaders
     * @return bool
     */
    final public function equals(MessageHeaders $messageHeaders) : bool
    {
        return $this == $messageHeaders;
    }

    /**
     * @return mixed
     */
    final public function getReplyChannel()
    {
        return $this->get(self::REPLY_CHANNEL);
    }

    /**
     * @return mixed
     */
    final public function getErrorChannel()
    {
        return $this->get(self::ERROR_CHANNEL);
    }

    /**
     * @param string $messageId
     * @return bool
     */
    final public function hasMessageId(string $messageId) : bool
    {
        return $this->get(self::MESSAGE_ID) === $messageId;
    }

    /**
     * @param string $headerName
     * @param $headerValue
     */
    final protected function changeHeader(string $headerName, $headerValue) : void
    {
        $this->headers[$headerName] = $headerValue;
    }

    /**
     * @param array|string[] $headers
     * @throws \Messaging\MessagingException
     */
    final private function initialize(array $headers) : void
    {
        foreach ($headers as $headerName => $headerValue) {
            if (!$headerName) {
                throw InvalidMessageHeaderException::create("Passed empty header name");
            }
        }

        $this->headers = $headers;
    }

    /**
     * @param array $headers
     * @param $timestamp
     * @return MessageHeaders
     */
    final private static function createMessageHeadersWith(array $headers, $timestamp): MessageHeaders
    {
        return new static(array_merge($headers, [
            self::MESSAGE_ID => Uuid::uuid4()->toString(),
            self::TIMESTAMP => $timestamp
        ]));
    }
}