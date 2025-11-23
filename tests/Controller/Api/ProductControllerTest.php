<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProductControllerTest extends WebTestCase
{
    private function createAuthenticatedClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();

        // Создаём пользователя
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');

        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $em->persist($user);
        $em->flush();

        // Авторизуем напрямую
        $session = $client->getContainer()->get('session');
        $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, 'main', $user->getRoles());
        $session->set('_security_main', serialize($token));
        $session->save();

        $cookie = new \Symfony\Component\BrowserKit\Cookie($session->getName(), $session->getId());
        $client->getCookieJar()->set($cookie);

        return $client;
    }

    public function testCreateAndListProduct(): void
    {
        $client = $this->createAuthenticatedClient();

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
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Test', 'quantity' => 5]));
        $data = json_decode($client->getResponse()->getContent(), true);
        $id = $data['id'];

        $client->request('PUT', "/api/products/$id", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Updated', 'quantity' => 20]));
        $this->assertResponseStatusCodeSame(200);
    }

    public function testDeleteProduct(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'ToDelete', 'quantity' => 1]));
        $data = json_decode($client->getResponse()->getContent(), true);
        $id = $data['id'];

        $client->request('DELETE', "/api/products/$id");
        $this->assertResponseStatusCodeSame(204);
    }
}
