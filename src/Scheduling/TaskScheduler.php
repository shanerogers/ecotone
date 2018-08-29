<?php
declare(strict_types=1);

namespace SimplyCodedSoftware\IntegrationMessaging\Scheduling;

/**
 * Interface TaskScheduler
 * @package SimplyCodedSoftware\IntegrationMessaging\Scheduling
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
interface TaskScheduler
{
    /**
     * @param TaskExecutor $taskExecutor
     * @param Trigger $trigger
     */
    public function schedule(TaskExecutor $taskExecutor, Trigger $trigger): void;
}