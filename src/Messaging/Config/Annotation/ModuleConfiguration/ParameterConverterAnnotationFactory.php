<?php
declare(strict_types=1);

namespace Ecotone\Messaging\Config\Annotation\ModuleConfiguration;

use Ecotone\Messaging\Annotation\Parameter\Expression;
use Ecotone\Messaging\Annotation\Parameter\Header;
use Ecotone\Messaging\Annotation\Parameter\Headers;
use Ecotone\Messaging\Annotation\Parameter\MessageParameter;
use Ecotone\Messaging\Annotation\Parameter\Payload;
use Ecotone\Messaging\Annotation\Parameter\Reference;
use Ecotone\Messaging\Annotation\Parameter\Value;
use Ecotone\Messaging\Config\Annotation\AnnotationRegistration;
use Ecotone\Messaging\Handler\InterfaceParameter;
use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\AllHeadersBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\HeaderExpressionBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\HeaderBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\MessageConverterBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\PayloadBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\PayloadExpressionBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\ReferenceBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\ValueBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\ConverterBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInvoker;
use Ecotone\Messaging\MessagingException;
use Ecotone\Messaging\Support\InvalidArgumentException;

/**
 * Class ParameterConverterAnnotationFactory
 * @package Ecotone\Messaging\Config\Annotation
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class ParameterConverterAnnotationFactory
{
    private function __construct()
    {
    }

    /**
     * @return ParameterConverterAnnotationFactory
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * @param InterfaceToCall|null $relatedClassInterface
     * @param array $parameterConverterAnnotations
     *
     * @return array
     * @throws \Ecotone\Messaging\MessagingException
     * @throws \Ecotone\Messaging\Support\InvalidArgumentException
     */
    public function createParameterConverters(?InterfaceToCall $relatedClassInterface, array $parameterConverterAnnotations): array
    {
        $parameterConverters = [];

        foreach ($parameterConverterAnnotations as $parameterConverterAnnotation) {
            if ($parameterConverterAnnotation instanceof Header) {
                if ($parameterConverterAnnotation->expression) {
                    $parameterConverters[] = HeaderExpressionBuilder::create(
                        $parameterConverterAnnotation->parameterName,
                        $parameterConverterAnnotation->headerName,
                        $parameterConverterAnnotation->expression,
                        $parameterConverterAnnotation->isRequired
                    );
                }else if ($parameterConverterAnnotation->isRequired) {
                    $parameterConverters[] = HeaderBuilder::create($parameterConverterAnnotation->parameterName, $parameterConverterAnnotation->headerName);
                } else {
                    $parameterConverters[] = HeaderBuilder::createOptional($parameterConverterAnnotation->parameterName, $parameterConverterAnnotation->headerName);
                }
            } else if ($parameterConverterAnnotation instanceof Payload) {
                if ($parameterConverterAnnotation->expression) {
                    $parameterConverters[] = PayloadExpressionBuilder::create($parameterConverterAnnotation->parameterName, $parameterConverterAnnotation->expression);
                }else {
                    $parameterConverters[] = PayloadBuilder::create($parameterConverterAnnotation->parameterName);
                }
            } else if ($parameterConverterAnnotation instanceof MessageParameter) {
                $parameterConverters[] = MessageConverterBuilder::create($parameterConverterAnnotation->parameterName);
            } else if ($parameterConverterAnnotation instanceof Reference) {
                if ($parameterConverterAnnotation->referenceName) {
                    $parameterConverters[] = ReferenceBuilder::create($parameterConverterAnnotation->parameterName, $parameterConverterAnnotation->referenceName);
                }elseif ($relatedClassInterface) {
                    $parameterConverters[] = ReferenceBuilder::createFromParameterTypeHint($parameterConverterAnnotation->parameterName, $relatedClassInterface);
                }else {
                    $parameterConverters[] = ReferenceBuilder::createWithDynamicResolve($parameterConverterAnnotation->parameterName);
                }
            } else if ($parameterConverterAnnotation instanceof Headers) {
                $parameterConverters[] = AllHeadersBuilder::createWith($parameterConverterAnnotation->parameterName);
            }
        }

        return $parameterConverters;
    }

    /**
     * @param InterfaceToCall $relatedClassInterface
     * @param array $methodParameterConverterBuilders
     * @param AnnotationRegistration $registration
     *
     * @param bool $ignoreMessage
     * @return array
     * @throws InvalidArgumentException
     * @throws MessagingException
     */
    public function createParameterConvertersWithReferences(InterfaceToCall $relatedClassInterface, array $methodParameterConverterBuilders, AnnotationRegistration $registration, bool $ignoreMessage): array
    {
        $methodParameterConverterBuilders = $this->createParameterConverters($relatedClassInterface, $methodParameterConverterBuilders);

        if ($ignoreMessage) {
            if ($relatedClassInterface->hasNoParameters()) {
                return [];
            }

            if ($relatedClassInterface->getFirstParameter()->getTypeDescriptor()->isNonCollectionArray() && !self::hasParameterConverterFor($methodParameterConverterBuilders, $relatedClassInterface->getFirstParameter())) {
                $methodParameterConverterBuilders[] = AllHeadersBuilder::createWith($relatedClassInterface->getFirstParameterName());
            }

            foreach ($relatedClassInterface->getInterfaceParameters() as $interfaceParameter) {
                if ($this->hasParameterConverterFor($methodParameterConverterBuilders, $interfaceParameter)) {
                    continue;
                }

                $methodParameterConverterBuilders[] = ReferenceBuilder::create($interfaceParameter->getName(), $interfaceParameter->getTypeHint());
            }
        }

        if (!$methodParameterConverterBuilders) {
            $methodParameterConverterBuilders = MethodInvoker::createDefaultMethodParameters($relatedClassInterface, $methodParameterConverterBuilders, false);
        }

        foreach ($relatedClassInterface->getInterfaceParameters() as $interfaceParameter) {
            if ($this->hasParameterConverterFor($methodParameterConverterBuilders, $interfaceParameter)) {
                continue;
            }

            $methodParameterConverterBuilders[] = ReferenceBuilder::create($interfaceParameter->getName(), $interfaceParameter->getTypeHint());
        }

        return $methodParameterConverterBuilders;
    }

    /**
     * @param $methodParameterConverterBuilders
     * @param InterfaceParameter $interfaceParameter
     * @return bool
     */
    private function hasParameterConverterFor($methodParameterConverterBuilders, InterfaceParameter $interfaceParameter): bool
    {
        foreach ($methodParameterConverterBuilders as $methodParameterConverterBuilder) {
            if ($methodParameterConverterBuilder->isHandling($interfaceParameter)) {
                return true;
            }
        }
        return false;
    }
}