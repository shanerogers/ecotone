<?php
declare(strict_types=1);

namespace Test\Ecotone\Messaging\Unit\Config\Annotation\ModuleConfiguration;

use Doctrine\Common\Annotations\AnnotationException;
use Ecotone\Messaging\Config\Annotation\InMemoryAnnotationRegistrationService;
use Ecotone\Messaging\Config\Annotation\ModuleConfiguration\AsyncModule;
use Ecotone\Messaging\Config\ConfigurationException;
use Ecotone\Messaging\Config\ModuleReferenceSearchService;
use Ecotone\Messaging\Handler\TypeDefinitionException;
use Ecotone\Messaging\MessagingException;
use ReflectionException;
use Test\Ecotone\Messaging\Fixture\Annotation\Async\AsyncClassExample;
use Test\Ecotone\Messaging\Fixture\Annotation\Async\AsyncCommandHandlerWithoutIdExample;
use Test\Ecotone\Messaging\Fixture\Annotation\Async\AsyncEventHandlerExample;
use Test\Ecotone\Messaging\Fixture\Annotation\Async\AsyncEventHandlerWithoutIdExample;
use Test\Ecotone\Messaging\Fixture\Annotation\Async\AsyncMethodExample;
use Test\Ecotone\Messaging\Fixture\Annotation\Async\AsyncQueryHandlerExample;

/**
 * Class ConverterModuleTest
 * @package Test\Ecotone\Messaging\Unit\Config\Annotation\ModuleConfiguration
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class AsyncModuleTest extends AnnotationConfigurationTest
{
    /**
     * @throws AnnotationException
     * @throws ReflectionException
     * @throws TypeDefinitionException
     * @throws MessagingException
     */
    public function test_registering_async_channel_for_method()
    {
        $annotationConfiguration = AsyncModule::create(
            InMemoryAnnotationRegistrationService::createEmpty()
                ->registerClassWithAnnotations(AsyncMethodExample::class)
        );
        $configuration = $this->createMessagingSystemConfiguration();
        $annotationConfiguration->prepare($configuration, [], ModuleReferenceSearchService::createEmpty());

        $this->assertEquals(
            $this->createMessagingSystemConfiguration()
                ->registerAsynchronousEndpoint("asyncChannel", "asyncServiceActivator"),
            $configuration
        );
    }

    /**
     * @throws AnnotationException
     * @throws ReflectionException
     * @throws TypeDefinitionException
     * @throws MessagingException
     */
    public function test_registering_async_channel_for_whole_class()
    {
        $annotationConfiguration = AsyncModule::create(
            InMemoryAnnotationRegistrationService::createEmpty()
                ->registerClassWithAnnotations(AsyncClassExample::class)
        );
        $configuration = $this->createMessagingSystemConfiguration();
        $annotationConfiguration->prepare($configuration, [], ModuleReferenceSearchService::createEmpty());

        $this->assertEquals(
            $this->createMessagingSystemConfiguration()
                ->registerAsynchronousEndpoint("asyncChannel1", "asyncServiceActivator1")
                ->registerAsynchronousEndpoint("asyncChannel2", "asyncServiceActivator2"),
            $configuration
        );
    }

    /**
     * @throws AnnotationException
     * @throws ReflectionException
     * @throws TypeDefinitionException
     * @throws MessagingException
     */
    public function test_registering_event_handler()
    {
        $annotationConfiguration = AsyncModule::create(
            InMemoryAnnotationRegistrationService::createEmpty()
                ->registerClassWithAnnotations(AsyncEventHandlerExample::class)
        );
        $configuration = $this->createMessagingSystemConfiguration();
        $annotationConfiguration->prepare($configuration, [], ModuleReferenceSearchService::createEmpty());

        $this->assertEquals(
            $this->createMessagingSystemConfiguration()
                ->registerAsynchronousEndpoint("asyncChannel", "asyncEvent"),
            $configuration
        );
    }

    /**
     * @throws AnnotationException
     * @throws ReflectionException
     * @throws TypeDefinitionException
     * @throws MessagingException
     */
    public function test_ignoring_query_handler_as_async()
    {
        $annotationConfiguration = AsyncModule::create(
            InMemoryAnnotationRegistrationService::createEmpty()
                ->registerClassWithAnnotations(AsyncQueryHandlerExample::class)
        );
        $configuration = $this->createMessagingSystemConfiguration();
        $annotationConfiguration->prepare($configuration, [], ModuleReferenceSearchService::createEmpty());

        $this->assertEquals(
            $this->createMessagingSystemConfiguration(),
            $configuration
        );
    }

    /**
     * @throws AnnotationException
     * @throws ReflectionException
     * @throws TypeDefinitionException
     * @throws MessagingException
     */
    public function test_throwing_exception_if_using_generated_id_for_event_handler()
    {
        $this->expectException(ConfigurationException::class);

        AsyncModule::create(
            InMemoryAnnotationRegistrationService::createEmpty()
                ->registerClassWithAnnotations(AsyncEventHandlerWithoutIdExample::class)
        );
    }

    /**
     * @throws AnnotationException
     * @throws ReflectionException
     * @throws TypeDefinitionException
     * @throws MessagingException
     */
    public function test_throwing_exception_if_using_generated_id_for_command_handler()
    {
        $this->expectException(ConfigurationException::class);

        AsyncModule::create(
            InMemoryAnnotationRegistrationService::createEmpty()
                ->registerClassWithAnnotations(AsyncCommandHandlerWithoutIdExample::class)
        );
    }
}