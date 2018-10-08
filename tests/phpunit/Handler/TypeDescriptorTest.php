<?php
declare(strict_types=1);

namespace Test\SimplyCodedSoftware\IntegrationMessaging\Handler;
use PHPUnit\Framework\TestCase;
use SimplyCodedSoftware\IntegrationMessaging\Handler\TypeDefinitionException;
use SimplyCodedSoftware\IntegrationMessaging\Handler\TypeDescriptor;

/**
 * Class TypeDescriptorTest
 * @package Test\SimplyCodedSoftware\IntegrationMessaging\Handler
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class TypeDescriptorTest extends TestCase
{
    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_guessing_type_hint_from_compound_type_and_array_of_scalar_type()
    {
        $typeDescription = TypeDescriptor::create(TypeDescriptor::ARRAY, false, "array<string>");

        $this->assertEquals(
            'array<string>',
            $typeDescription->getTypeHint()
        );
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_throwing_exception_if_doc_block_type_is_incorrect()
    {
        $this->expectException(TypeDefinitionException::class);

        TypeDescriptor::create(TypeDescriptor::ARRAY, false, "array<bla>");
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_throwing_exception_if_type_hint_is_incorrect()
    {
        $this->expectException(TypeDefinitionException::class);

        TypeDescriptor::create("bla", false, TypeDescriptor::ARRAY);
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_passing_incompatible_scalar_type_hint_and_compound_doc_block_type()
    {
        $this->expectException(TypeDefinitionException::class);

        TypeDescriptor::create(TypeDescriptor::STRING, false, TypeDescriptor::ARRAY);
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_passing_incompatible_compound_type_hint_and_scalar_doc_block_type()
    {
        $this->expectException(TypeDefinitionException::class);

        TypeDescriptor::create(TypeDescriptor::ARRAY, false, TypeDescriptor::INTEGER);
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_passing_incompatible_resource_type_hint_and_scalar_doc_block_type()
    {
        $this->expectException(TypeDefinitionException::class);

        TypeDescriptor::create(TypeDescriptor::RESOURCE, false, TypeDescriptor::INTEGER);
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_passing_incompatible_scalar_type_hint_and_resource_doc_block_type()
    {
        $this->expectException(TypeDefinitionException::class);

        TypeDescriptor::create(TypeDescriptor::INTEGER, false, TypeDescriptor::RESOURCE);
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_passing_incompatible_resource_hint_and_compound_doc_block_type()
    {
        $this->expectException(TypeDefinitionException::class);

        TypeDescriptor::create(TypeDescriptor::RESOURCE, false, TypeDescriptor::ARRAY);
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_passing_incompatible_compound_hint_and_resource_doc_block_type()
    {
        $this->expectException(TypeDefinitionException::class);

        TypeDescriptor::create(TypeDescriptor::ITERABLE, false, TypeDescriptor::RESOURCE);
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_converting_doc_block_array_type_to_generic()
    {
        $this->assertEquals(
            "array<\stdClass>",
            TypeDescriptor::create(TypeDescriptor::ITERABLE, false, "\stdClass[]")->getTypeHint()
        );
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_throwing_exception_on_incompatible_class_type_hint_and_array_doc_block()
    {
        $this->expectException(TypeDefinitionException::class);

        TypeDescriptor::create(\stdClass::class, false, "array<\stdClass>");
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_throwing_exception_on_incompatible_array_type_hint_and_class_doc_block()
    {
        $this->expectException(TypeDefinitionException::class);

        TypeDescriptor::create(TypeDescriptor::ARRAY, false, \stdClass::class);
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_choosing_doc_block_type_hint_over_compound()
    {
        $this->assertEquals(
            "array<\stdClass>",
            TypeDescriptor::create(TypeDescriptor::ARRAY, false, "array<\stdClass>")->getTypeHint()
        );
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_choosing_doc_block_collection_type_hint_over_compound()
    {
        $this->assertEquals(
            "\ArrayCollection<\stdClass>",
            TypeDescriptor::create(TypeDescriptor::ITERABLE, false, "\ArrayCollection<\stdClass>")->getTypeHint()
        );
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_choosing_doc_block_class_type_over_class_type_hint()
    {
        $this->assertEquals(
            "\\" . \stdClass::class,
            TypeDescriptor::create(\Countable::class, false, \stdClass::class)->getTypeHint()
        );
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_picking_class_from_doc_block_if_type_hint_is_compound_object()
    {
        $this->assertEquals(
            "\\" . \stdClass::class,
            TypeDescriptor::create(TypeDescriptor::OBJECT, false, \stdClass::class)->getTypeHint()
        );
    }

    /**
     * @throws TypeDefinitionException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_choosing_first_type_if_union_doc_block_type_hint()
    {
        $this->assertEquals(
            "\\" . \stdClass::class,
            TypeDescriptor::create(TypeDescriptor::OBJECT, false, "\stdClass|\Countable")->getTypeHint()
        );
    }
}