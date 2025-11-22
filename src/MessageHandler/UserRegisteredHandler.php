<?php
namespace App\MessageHandler;

use App\Message\UserRegisteredMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UserRegisteredHandler
{
    public function __invoke(UserRegisteredMessage $message): void
    {
        error_log(sprintf(
            "[RabbitMQ] Пользователь зарегистрирован: ID=%d, email=%s, время=%s\n",
            $message->userId,
            $message->email,
            $message->registeredAt->format('Y-m-d H:i:s')
        ));
    }
}
