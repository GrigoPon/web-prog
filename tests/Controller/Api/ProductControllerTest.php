<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProductControllerTest extends WebTestCase
{
    private const TEST_EMAIL = 'product_test@example.com';

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

        // Логинимся (form data, НЕ JSON!)
        $client->request('POST', '/login', [
            'email' => self::TEST_EMAIL,
            'password' => 'password123',
        ]);
        $this->assertTrue($client->getResponse()->isRedirect());

        // Создаём товар (теперь авторизованы)
        $client->request(
            'POST',
            '/api/products',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Test Product', 'quantity' => 10])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Получаем список
        $client->request('GET', '/api/products');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Test Product', $client->getResponse()->getContent());
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

        // Логинимся
        $client->request('POST', '/login', [
            'email' => self::TEST_EMAIL,
            'password' => 'password123',
        ]);
        $this->assertTrue($client->getResponse()->isRedirect());

        // Создаём товар
        $client->request(
            'POST',
            '/api/products',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Update Test', 'quantity' => 5])
        );
        $data = json_decode($client->getResponse()->getContent(), true);
        $productId = $data['id'];

        // Обновляем
        $client->request(
            'PUT',
            "/api/products/$productId",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Updated Product', 'quantity' => 20])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
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

        // Логинимся
        $client->request('POST', '/login', [
            'email' => self::TEST_EMAIL,
            'password' => 'password123',
        ]);
        $this->assertTrue($client->getResponse()->isRedirect());

        // Создаём товар
        $client->request(
            'POST',
            '/api/products',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Delete Test', 'quantity' => 1])
        );
        $data = json_decode($client->getResponse()->getContent(), true);
        $productId = $data['id'];

        // Удаляем
        $client->request('DELETE', "/api/products/$productId");
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Проверяем отсутствие
        $client->request('GET', '/api/products');
        $response = $client->getResponse()->getContent();
        $this->assertStringNotContainsString('Delete Test', $response);
    }
}
