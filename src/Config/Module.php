<?php
declare(strict_types=1);

namespace SimplyCodedSoftware\IntegrationMessaging\Config;

use SimplyCodedSoftware\IntegrationMessaging\Handler\ReferenceSearchService;

/**
 * Interface ExternalConfiguration
 * @package SimplyCodedSoftware\IntegrationMessaging\Config
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
interface Module
{
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * In here you can register all message handlers, gateways, message channels
     *
     * @param Configuration         $configuration
     * @param object[]     $extensionObjects
     *
     * @return void
     */
    public function prepare(Configuration $configuration, array $extensionObjects) : void;

    /**
     * @param $extensionObject
     * @return bool
     */
    public function canHandle($extensionObject) : bool;

    /**
     * Which will be available during build configure phase
     *
     * @return RequiredReference[]
     */
    public function getRequiredReferences(): array;

    /**
     * Runs during configuration phase, when all handlers must be already defined
     *
     * @param ReferenceSearchService $referenceSearchService
     *
     * @return void
     */
    public function afterConfigure(ReferenceSearchService $referenceSearchService): void;
}