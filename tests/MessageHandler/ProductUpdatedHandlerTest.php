<?php

namespace App\Tests\MessageHandler;

use App\Message\ProductUpdatedMessage;
use App\MessageHandler\ProductUpdatedHandler;
use PHPUnit\Framework\TestCase;

class ProductUpdatedHandlerTest extends TestCase
{
    public function testInvokeWritesToErrorLog(): void
    {
        $this->expectOutputRegex('/\[RabbitMQ\] Товар обновлён: ID=1, название=\'Updated\', количество=5, пользователь=2/');

        $message = new ProductUpdatedMessage(
            productId: 1,
            name: 'Updated',
            quantity: 5,
            userId: 2,
            updatedAt: new \DateTimeImmutable('2025-01-01 12:00:00')
        );

        $handler = new ProductUpdatedHandler();
        $handler($message);
    }
}
