<?php
declare(strict_types=1);

namespace SimplyCodedSoftware\IntegrationMessaging\Scheduling;

/**
 * Class SimpleTriggerContext
 * @package SimplyCodedSoftware\IntegrationMessaging\Scheduling
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class SimpleTriggerContext implements TriggerContext
{
    /**
     * @var int|null
     */
    private $lastScheduledExecutionTime;
    /**
     * @var int|null
     */
    private $lastActualExecutionTime;

    /**
     * SimpleTriggerContext constructor.
     * @param int|null $lastScheduledExecutionTime
     * @param int|null $lastActualExecutionTime
     */
    private function __construct(?int $lastScheduledExecutionTime, ?int $lastActualExecutionTime)
    {
        $this->lastScheduledExecutionTime = $lastScheduledExecutionTime;
        $this->lastActualExecutionTime = $lastActualExecutionTime;
    }

    /**
     * @return SimpleTriggerContext
     */
    public static function createEmpty() : self
    {
        return new self(null, null);
    }

    /**
     * @param int|null $lastScheduledExecutionTime
     * @param int|null $lastActualExecutionTime
     * @return SimpleTriggerContext
     */
    public static function createWith(?int $lastScheduledExecutionTime, ?int $lastActualExecutionTime) : self
    {
        return new self($lastScheduledExecutionTime, $lastActualExecutionTime);
    }

    /**
     * @param int $lastScheduledExecutionTime
     * @return SimpleTriggerContext
     */
    public function withLastScheduledExecutionTime(int $lastScheduledExecutionTime): self
    {
        $this->lastScheduledExecutionTime = $lastScheduledExecutionTime;

        return self::createWith($lastScheduledExecutionTime, $this->lastActualExecutionTime());
    }

    /**
     * @param int $lastActualExecutionTime
     * @return SimpleTriggerContext
     */
    public function withLastActualExecutionTime(int $lastActualExecutionTime): self
    {
        $this->lastActualExecutionTime = $lastActualExecutionTime;

        return self::createWith($this->lastScheduledExecutionTime, $lastActualExecutionTime);
    }

    /**
     * @inheritDoc
     */
    public function lastScheduledTime(): ?int
    {
        return $this->lastScheduledExecutionTime;
    }

    /**
     * @inheritDoc
     */
    public function lastActualExecutionTime(): ?int
    {
        return $this->lastActualExecutionTime;
    }
}