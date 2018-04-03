<?php

namespace SimplyCodedSoftware\IntegrationMessaging\Handler\Enricher\Setter;

use SimplyCodedSoftware\IntegrationMessaging\Handler\Enricher\DataSetter;
use SimplyCodedSoftware\IntegrationMessaging\Handler\Enricher\PropertyPath;
use SimplyCodedSoftware\IntegrationMessaging\Handler\Enricher\Setter;
use SimplyCodedSoftware\IntegrationMessaging\Handler\ExpressionEvaluationService;
use SimplyCodedSoftware\IntegrationMessaging\Message;

/**
 * Class ExpressionHeaderSetter
 * @package SimplyCodedSoftware\IntegrationMessaging\Handler\Enricher\Setter
 * @author  Dariusz Gafka <dgafka.mail@gmail.com>
 */
class ExpressionHeaderSetter implements Setter
{
    /**
     * @var ExpressionEvaluationService
     */
    private $expressionEvaluationService;
    /**
     * @var PropertyPath
     */
    private $propertyPath;
    /**
     * @var string
     */
    private $expression;
    /**
     * @var DataSetter
     */
    private $dataSetter;

    /**
     * ExpressionSetter constructor.
     *
     * @param ExpressionEvaluationService $expressionEvaluationService
     * @param DataSetter                  $dataSetter
     * @param PropertyPath                $propertyPath
     * @param string                      $expression
     */
    public function __construct(ExpressionEvaluationService $expressionEvaluationService, DataSetter $dataSetter, PropertyPath $propertyPath, string $expression)
    {
        $this->expressionEvaluationService = $expressionEvaluationService;
        $this->propertyPath                = $propertyPath;
        $this->expression                  = $expression;
        $this->dataSetter = $dataSetter;
    }

    /**
     * @inheritDoc
     */
    public function evaluate(Message $enrichMessage, ?Message $replyMessage)
    {
        $dataToEnrich = $this->expressionEvaluationService->evaluate(
            $this->expression, [
            "payload" => $replyMessage->getPayload(),
            "headers" => $replyMessage->getHeaders()->headers()
        ]);

        return $this->dataSetter->enrichDataWith($this->propertyPath, $enrichMessage->getHeaders()->headers(), $dataToEnrich);
    }

    /**
     * @inheritDoc
     */
    public function isPayloadSetter(): bool
    {
        return false;
    }
}