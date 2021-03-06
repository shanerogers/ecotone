<?php
declare(strict_types=1);

namespace Ecotone\Messaging\Config;

/**
 * Interface ExternalConfiguration
 * @package Ecotone\Messaging\Config
 * @author  Dariusz Gafka <dgafka.mail@gmail.com>
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
     * @param Configuration                $configuration
     * @param object[]                     $extensionObjects
     * @param ModuleReferenceSearchService $moduleReferenceSearchService
     *
     * @return void
     */
    public function prepare(Configuration $configuration, array $extensionObjects, ModuleReferenceSearchService $moduleReferenceSearchService): void;

    /**
     * @param $extensionObject
     *
     * @return bool
     */
    public function canHandle($extensionObject): bool;

    /**
     * Which will be available during build configure phase
     *
     * @return RequiredReference[]|OptionalReference[]|string[]
     */
    public function getRelatedReferences(): array;
}