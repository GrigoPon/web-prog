<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProductControllerTest extends WebTestCase
{
    private function generateTestEmail(): string
    {
        return 'test_' . uniqid() . '@example.com';
    }

    public function testCreateAndListProduct(): void
    {
        $client = static::createClient();
        $email = $this->generateTestEmail();

        // Создаём пользователя
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $em->persist($user);
        $em->flush();

        // Логинимся
        $client->request('POST', '/login', [
            'email' => $email,
            'password' => 'password123',
        ]);
        $this->assertTrue($client->getResponse()->isRedirect());

        // Создаём товар
        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Test', 'quantity' => 10]));
        $this->assertResponseStatusCodeSame(201);

        $client->request('GET', '/api/products');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Test', $client->getResponse()->getContent());
    }

    public function testUpdateProduct(): void
    {
        $client = static::createClient();
        $email = $this->generateTestEmail();

        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $em->persist($user);
        $em->flush();

        $client->request('POST', '/login', [
            'email' => $email,
            'password' => 'password123',
        ]);
        $this->assertTrue($client->getResponse()->isRedirect());

        // Создаём и обновляем
        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Update', 'quantity' => 5]));

        $data = json_decode($client->getResponse()->getContent(), true);
        $id = $data['id'];

        $client->request('PUT', "/api/products/$id", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Updated', 'quantity' => 10]));
        $this->assertResponseStatusCodeSame(200);
    }

    public function testDeleteProduct(): void
    {
        $client = static::createClient();
        $email = $this->generateTestEmail();

        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $em->persist($user);
        $em->flush();

        $client->request('POST', '/login', [
            'email' => $email,
            'password' => 'password123',
        ]);
        $this->assertTrue($client->getResponse()->isRedirect());

        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'ToDelete', 'quantity' => 1]));
        $data = json_decode($client->getResponse()->getContent(), true);
        $id = $data['id'];

        $client->request('DELETE', "/api/products/$id");
        $this->assertResponseStatusCodeSame(204);
    }
}
