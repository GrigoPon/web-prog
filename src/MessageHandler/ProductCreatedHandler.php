<?php

namespace App\MessageHandler;

use App\Message\ProductCreatedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProductCreatedHandler
{
    public function __invoke(ProductCreatedMessage $message): void
    {
        error_log(sprintf(
            "[RabbitMQ] Товар создан: ID=%d, название='%s', количество=%d, пользователь=%d, время=%s\n",
            $message->productId,
            $message->name,
            $message->quantity,
            $message->userId,
            $message->createdAt->format('Y-m-d H:i:s')
        ));
    }
}
