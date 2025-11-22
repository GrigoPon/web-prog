<?php
namespace App\Message;

class ProductUpdatedMessage
{
    public function __construct(
        public int $productId,
        public string $name,
        public int $quantity,
        public int $userId,
        public \DateTimeImmutable $updatedAt
    ) {}
}
