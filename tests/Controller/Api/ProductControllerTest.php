<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProductControllerTest extends WebTestCase
{
    private const TEST_EMAIL = 'product_test@example.com';

    // Выполняется ПОСЛЕ КАЖДОГО теста
    protected function tearDown(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => self::TEST_EMAIL]);
        if ($user) {
            $em->remove($user);
            $em->flush();
        }
    }

    public function testCreateAndListProduct(): void
    {
        $client = static::createClient();

        // Создаём пользователя
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');
        $user = new User();
        $user->setEmail(self::TEST_EMAIL);
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $em->persist($user);
        $em->flush();

        // Логинимся
        $client->request('POST', '/login', [
            'email' => self::TEST_EMAIL,
            'password' => 'password123',
        ]);
        $this->assertTrue($client->getResponse()->isRedirect());

        // Дальнейшие действия...
        $client->request('POST', '/api/products', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => 'Test', 'quantity' => 10]));
        $this->assertResponseStatusCodeSame(201);
        $client->request('GET', '/api/products');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Test', $client->getResponse()->getContent());
    }

    public function testUpdateProduct(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');
        $user = new User();
        $user->setEmail(self::TEST_EMAIL);
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $em->persist($user);
        $em->flush();

        $client->request('POST', '/login', [
            'email' => self::TEST_EMAIL,
            'password' => 'password123',
        ]);
        $this->assertTrue($client->getResponse()->isRedirect());

        $client->request('POST', '/api/products', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => 'Test', 'quantity' => 5]));
        $data = json_decode($client->getResponse()->getContent(), true);
        $id = $data['id'];

        $client->request('PUT', "/api/products/$id", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => 'Updated', 'quantity' => 10]));
        $this->assertResponseStatusCodeSame(200);
    }

    public function testDeleteProduct(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');
        $user = new User();
        $user->setEmail(self::TEST_EMAIL);
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $em->persist($user);
        $em->flush();

        $client->request('POST', '/login', [
            'email' => self::TEST_EMAIL,
            'password' => 'password123',
        ]);
        $this->assertTrue($client->getResponse()->isRedirect());

        $client->request('POST', '/api/products', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => 'ToDelete', 'quantity' => 1]));
        $data = json_decode($client->getResponse()->getContent(), true);
        $id = $data['id'];

        $client->request('DELETE', "/api/products/$id");
        $this->assertResponseStatusCodeSame(204);
    }
}
