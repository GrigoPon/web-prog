<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class ProductControllerTest extends WebTestCase
{
    private const TEST_EMAIL = 'product_test@example.com';

//    protected function tearDown(): void
//    {
//        $client = static::createClient();
//        $em = $client->getContainer()->get(EntityManagerInterface::class);
//        $user = $em->getRepository(User::class)->findOneBy(['email' => self::TEST_EMAIL]);
//        if ($user) {
//            $em->remove($user);
//            $em->flush();
//        }
//    }

    #[RunInSeparateProcess]
    private function loginClient($client): void
    {
        $client->request('POST', '/login', [
            'email' => self::TEST_EMAIL,
            'password' => 'password123',
        ]);
        $this->assertTrue($client->getResponse()->isRedirect());
    }

    #[RunInSeparateProcess]
    public function testCreateAndListProduct(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');

        // Создаём пользователя, если ещё не создан
        $user = $em->getRepository(User::class)->findOneBy(['email' => self::TEST_EMAIL]);
        if (!$user) {
            $user = new User();
            $user->setEmail(self::TEST_EMAIL);
            $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
            $em->persist($user);
            $em->flush();
        }

        $this->loginClient($client);

        // Создаём товар
        $client->request(
            'POST',
            '/api/products',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Test Product', 'quantity' => 10])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Test Product', $data['name']);
        $this->assertSame(10, $data['quantity']);

        // Получаем список
        $client->request('GET', '/api/products');
        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('Test Product', $content);
    }

    #[RunInSeparateProcess]
    public function testUpdateProduct(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');

        $user = $em->getRepository(User::class)->findOneBy(['email' => self::TEST_EMAIL]);
        if (!$user) {
            $user = new User();
            $user->setEmail(self::TEST_EMAIL);
            $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
            $em->persist($user);
            $em->flush();
        }

        $this->loginClient($client);

        // Создаём товар
        $client->request('POST', '/api/products', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => 'Update Test', 'quantity' => 5]));
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
        $updated = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Updated Product', $updated['name']);
        $this->assertSame(20, $updated['quantity']);
    }

    #[RunInSeparateProcess]
    public function testDeleteProduct(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');

        $user = $em->getRepository(User::class)->findOneBy(['email' => self::TEST_EMAIL]);
        if (!$user) {
            $user = new User();
            $user->setEmail(self::TEST_EMAIL);
            $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
            $em->persist($user);
            $em->flush();
        }

        $this->loginClient($client);

        // Создаём товар
        $client->request('POST', '/api/products', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => 'Delete Test', 'quantity' => 1]));
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
