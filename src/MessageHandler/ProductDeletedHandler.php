<?php
namespace App\MessageHandler;

use App\Message\ProductDeletedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProductDeletedHandler
{
    public function __invoke(ProductDeletedMessage $message): void
    {
        error_log(sprintf(
            "[RabbitMQ] Товар удалён: ID=%d, название='%s', количество=%d, пользователь=%d, время=%s\n",
            $message->productId,
            $message->name,
            $message->quantity,
            $message->userId,
            $message->deletedAt->format('Y-m-d H:i:s')
        ));
    }
}
