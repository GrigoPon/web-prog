<?php

namespace App\Tests\MessageHandler;

use App\Message\ProductCreatedMessage;
use App\MessageHandler\ProductCreatedHandler;
use PHPUnit\Framework\TestCase;

class ProductCreatedHandlerTest extends TestCase
{
    public function testInvokeWritesToErrorLog(): void
    {
        $this->expectOutputRegex('/\[RabbitMQ\] Товар создан: ID=1, название=\'Test\', количество=10, пользователь=2/');

        $message = new ProductCreatedMessage(
            productId: 1,
            name: 'Test',
            quantity: 10,
            userId: 2,
            createdAt: new \DateTimeImmutable('2025-01-01 12:00:00')
        );

        $handler = new ProductCreatedHandler();
        $handler($message);
    }
}
