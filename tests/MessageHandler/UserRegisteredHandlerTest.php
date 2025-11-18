<?php

namespace App\Tests\MessageHandler;

use App\Message\UserRegisteredMessage;
use App\MessageHandler\UserRegisteredHandler;
use PHPUnit\Framework\TestCase;

class UserRegisteredHandlerTest extends TestCase
{
    public function testInvokeWritesToErrorLog(): void
    {
        // Перехватываем вывод error_log()
        $this->expectOutputRegex('/\[RabbitMQ\] Пользователь зарегистрирован/');

        $message = new UserRegisteredMessage(
            userId: 1,
            email: 'test@example.com',
            registeredAt: new \DateTimeImmutable('2025-01-01 12:00:00')
        );

        $handler = new UserRegisteredHandler();
        $handler($message); // вызывает __invoke
    }
}
