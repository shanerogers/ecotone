<?php
declare(strict_types=1);

namespace SimplyCodedSoftware\IntegrationMessaging\Scheduling;

use Cron\CronExpression;
use SimplyCodedSoftware\IntegrationMessaging\Support\InvalidArgumentException;

/**
 * Class CronTrigger
 * @package SimplyCodedSoftware\IntegrationMessaging\Scheduling
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class CronTrigger implements Trigger
{
    /**
     * @var string
     */
    private $cronExpression;

    /**
     * CronTrigger constructor.
     * @param string $cronExpression
     * @throws InvalidArgumentException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    private function __construct(string $cronExpression)
    {
        $this->initialize($cronExpression);
    }

    /**
     * @param string $cronExpression
     * @return CronTrigger
     * @throws InvalidArgumentException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public static function createWith(string $cronExpression) : self
    {
        return new self($cronExpression);
    }

    /**
     * @inheritDoc
     */
    public function nextExecutionTime(Clock $clock, TriggerContext $triggerContext): int
    {
        $cron = CronExpression::factory($this->cronExpression);

        if (!$triggerContext->lastActualExecutionTime() && $triggerContext->lastScheduledTime()) {
            return $triggerContext->lastScheduledTime();
        }
        if ($this->hasScheduledButNotYetExecuted($triggerContext)) {
            return $triggerContext->lastScheduledTime();
        }

        $dateTime = new \DateTime("now", new \DateTimeZone("UTC"));
        $dateTime->setTimestamp($clock->unixTimeInMilliseconds() / 1000);

        $nextExecutionTime = $cron->getNextRunDate($dateTime, 0, true, "UTC")->getTimestamp();
        if ($nextExecutionTime < $dateTime->getTimestamp()) {
            $nextExecutionTime = $cron->getNextRunDate($dateTime, 1, true, "UTC")->getTimestamp();
        }

        return $nextExecutionTime * 1000;
    }

    /**
     * @param string $cronExpression
     * @throws InvalidArgumentException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    private function initialize(string $cronExpression) : void
    {
        if (!CronExpression::isValidExpression($cronExpression)) {
            throw InvalidArgumentException::create("Passed cron expression {$cronExpression} is not correct");
        }

        $this->cronExpression = $cronExpression;
    }

    /**
     * @param TriggerContext $triggerContext
     * @return bool
     */
    private function hasScheduledButNotYetExecuted(TriggerContext $triggerContext): bool
    {
        return
            ($triggerContext->lastActualExecutionTime() && $triggerContext->lastScheduledTime())
            &&
            ($triggerContext->lastActualExecutionTime() < $triggerContext->lastScheduledTime());
    }
}