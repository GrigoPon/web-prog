<?php

namespace App\Tests\MessageHandler;

use App\Message\ProductDeletedMessage;
use App\MessageHandler\ProductDeletedHandler;
use PHPUnit\Framework\TestCase;

class ProductDeletedHandlerTest extends TestCase
{
    public function testInvokeWritesToErrorLog(): void
    {
        $this->expectOutputRegex('/\[RabbitMQ\] Товар удалён: ID=1, название=\'Deleted\', количество=3, пользователь=2/');

        $message = new ProductDeletedMessage(
            productId: 1,
            name: 'Deleted',
            quantity: 3,
            userId: 2,
            deletedAt: new \DateTimeImmutable('2025-01-01 12:00:00')
        );

        $handler = new ProductDeletedHandler();
        $handler($message);
    }
}
