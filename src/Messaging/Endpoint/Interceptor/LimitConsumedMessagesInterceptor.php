<?php
declare(strict_types=1);

namespace Ecotone\Messaging\Endpoint\Interceptor;

use Ecotone\Messaging\Endpoint\ConsumerInterceptor;

/**
 * Class LimitConsumedMessagesExtension
 * @package Ecotone\Messaging\Endpoint\Extension
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class LimitConsumedMessagesInterceptor implements ConsumerInterceptor
{
    /**
     * @var bool
     */
    private $shouldBeStopped = false;

    /**
     * @var int
     */
    private $currentSentMessages = 0;

    /**
     * @var int
     */
    private $messageLimit;

    /**
     * LimitConsumedMessagesInterceptor constructor.
     * @param int $messageLimit
     */
    public function __construct(int $messageLimit)
    {
        $this->messageLimit = $messageLimit;
    }

    /**
     * @inheritDoc
     */
    public function onStartup(): void
    {
        $this->currentSentMessages = 0;
        $this->shouldBeStopped = false;
        return;
    }

    /**
     * @inheritDoc
     */
    public function preRun(): void
    {
        return;
    }

    /**
     * @inheritDoc
     */
    public function shouldBeThrown(\Throwable $exception) : bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function shouldBeStopped(): bool
    {
        return $this->shouldBeStopped;
    }

    /**
     * @inheritDoc
     */
    public function postRun(): void
    {
        return;
    }

    /**
     * @inheritDoc
     */
    public function postSend(): void
    {
        $this->currentSentMessages++;

        $this->shouldBeStopped = $this->currentSentMessages >= $this->messageLimit;
    }
}