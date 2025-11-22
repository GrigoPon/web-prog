<?php
namespace App\MessageHandler;

use App\Message\ProductUpdatedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProductUpdatedHandler
{
    public function __invoke(ProductUpdatedMessage $message): void
    {
        error_log(sprintf(
            "[RabbitMQ] Товар обновлён: ID=%d, название='%s', количество=%d, пользователь=%d, время=%s\n",
            $message->productId,
            $message->name,
            $message->quantity,
            $message->userId,
            $message->updatedAt->format('Y-m-d H:i:s')
        ));
    }
}
