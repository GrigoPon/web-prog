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

        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');

        // Уникальный email
        $email = 'test_' . uniqid() . '@example.com';
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $em->persist($user);
        $em->flush();

        // Авторизация через сессию
        $session = $client->getContainer()->get('session');
        $firewallName = 'main';
        $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, $firewallName, $user->getRoles());
        $session->set('_security_' . $firewallName, serialize($token));
        $session->save();

        $cookie = new \Symfony\Component\BrowserKit\Cookie($session->getName(), $session->getId());
        $client->getCookieJar()->set($cookie);

        return $client;
    }

    public function testCreateAndListProduct(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'name' => 'Test Product',
            'quantity' => 10
        ]));
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('GET', '/api/products');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Test Product', $client->getResponse()->getContent());
    }

    public function testUpdateProduct(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'name' => 'Update Test',
            'quantity' => 5
        ]));
        $data = json_decode($client->getResponse()->getContent(), true);
        $productId = $data['id'];

        $client->request('PUT', "/api/products/$productId", [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'name' => 'Updated Product',
            'quantity' => 20
        ]));
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testDeleteProduct(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'name' => 'Delete Test',
            'quantity' => 1
        ]));
        $data = json_decode($client->getResponse()->getContent(), true);
        $productId = $data['id'];

        $client->request('DELETE', "/api/products/$productId");
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', '/api/products');
        $response = $client->getResponse()->getContent();
        $this->assertStringNotContainsString('Delete Test', $response);
    }
}
