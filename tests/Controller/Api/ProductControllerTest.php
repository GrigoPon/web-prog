<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProductControllerTest extends WebTestCase
{
    private const TEST_EMAIL = 'product_test@example.com';

//    // Создаём пользователя ОДИН РАЗ перед всеми тестами
//    public static function setUpBeforeClass(): void
//    {
//        $client = static::createClient();
//        $em = $client->getContainer()->get(EntityManagerInterface::class);
//        $passwordHasher = $client->getContainer()->get('security.password_hasher');
//
//        // Удаляем, если существует
//        $existing = $em->getRepository(User::class)->findOneBy(['email' => self::TEST_EMAIL]);
//        if ($existing) {
//            $em->remove($existing);
//            $em->flush();
//        }
//
//        // Создаём нового
//        $user = new User();
//        $user->setEmail(self::TEST_EMAIL);
//        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
//        $em->persist($user);
//        $em->flush();
//    }

    // Удаляем пользователя после всех тестов
    public static function tearDownAfterClass(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => self::TEST_EMAIL]);
        if ($user) {
            $em->remove($user);
            $em->flush();
        }
    }

    // Логинимся в каждом тесте (но пользователь уже создан)
    private function loginClient($client): void
    {
        $client->request('POST', '/login', [
            'email' => self::TEST_EMAIL,
            'password' => 'password123',
        ]);
        $this->assertTrue($client->getResponse()->isRedirect());
    }

    public function testCreateAndListProduct(): void
    {
        $client = static::createClient();
        $this->loginClient($client);

        // Создаём товар
        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Test Product', 'quantity' => 10]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Test Product', $data['name']);
        $this->assertSame(10, $data['quantity']);

        // Получаем список
        $client->request('GET', '/api/products');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Test Product', $client->getResponse()->getContent());
    }

    public function testUpdateProduct(): void
    {
        $client = static::createClient();
        $this->loginClient($client);

        // Создаём товар
        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Update Test', 'quantity' => 5]));

        $data = json_decode($client->getResponse()->getContent(), true);
        $productId = $data['id'];

        // Обновляем
        $client->request('PUT', "/api/products/$productId", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Updated Product', 'quantity' => 20]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $updated = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Updated Product', $updated['name']);
        $this->assertSame(20, $updated['quantity']);
    }

    public function testDeleteProduct(): void
    {
        $client = static::createClient();
        $this->loginClient($client);

        // Создаём товар
        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Delete Test', 'quantity' => 1]));

        $data = json_decode($client->getResponse()->getContent(), true);
        $productId = $data['id'];

        // Удаляем
        $client->request('DELETE', "/api/products/$productId");
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Проверяем, что товара нет
        $client->request('GET', '/api/products');
        $response = $client->getResponse()->getContent();
        $this->assertStringNotContainsString('Delete Test', $response);
    }
}
