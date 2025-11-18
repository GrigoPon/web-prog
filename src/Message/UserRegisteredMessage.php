<?php
namespace App\Message;

class UserRegisteredMessage
{
    public function __construct(
        public int $userId,
        public string $email,
        public \DateTimeImmutable $registeredAt
    ) {}
}
