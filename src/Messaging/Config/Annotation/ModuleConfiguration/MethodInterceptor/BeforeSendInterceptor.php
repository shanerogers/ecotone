<?php
declare(strict_types=1);


namespace Ecotone\Messaging\Config\Annotation\ModuleConfiguration\MethodInterceptor;

use Ecotone\Messaging\Channel\ChannelInterceptor;
use Ecotone\Messaging\Endpoint\InboundGatewayEntrypoint;
use Ecotone\Messaging\Handler\NonProxyGateway;
use Ecotone\Messaging\Message;
use Ecotone\Messaging\MessageChannel;

/**
 * Class BeforeSendInterceptor
 * @package Ecotone\Messaging\Config\Annotation\ModuleConfiguration\MethodInterceptor
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class BeforeSendInterceptor implements ChannelInterceptor
{
    /**
     * @var NonProxyGateway
     */
    private $entrypointGateway;

    /**
     * BeforeSendInterceptor constructor.
     * @param NonProxyGateway $entrypointGateway
     */
    public function __construct(NonProxyGateway $entrypointGateway)
    {
        $this->entrypointGateway = $entrypointGateway;
    }

    /**
     * @inheritDoc
     */
    public function preSend(Message $message, MessageChannel $messageChannel): ?Message
    {
        return $this->entrypointGateway->execute([$message]);
    }

    /**
     * @inheritDoc
     */
    public function postSend(Message $message, MessageChannel $messageChannel): void
    {
        return;
    }

    /**
     * @inheritDoc
     */
    public function afterSendCompletion(Message $message, MessageChannel $messageChannel, ?\Throwable $exception): void
    {
        return;
    }

    /**
     * @inheritDoc
     */
    public function preReceive(MessageChannel $messageChannel): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function postReceive(Message $message, MessageChannel $messageChannel): ?Message
    {
        return $message;
    }

    /**
     * @inheritDoc
     */
    public function afterReceiveCompletion(?Message $message, MessageChannel $messageChannel, ?\Throwable $exception): void
    {
        return;
    }

}