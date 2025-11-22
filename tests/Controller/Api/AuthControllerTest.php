<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class AuthControllerTest extends WebTestCase
{
//    protected function tearDown(): void
//    {
//        $client = static::createClient();
//        $em = $client->getContainer()->get(EntityManagerInterface::class);
//
//        foreach (['test@example.com', 'test2@example.com', 'duplicate@example.com'] as $email) {
//            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
//            if ($user) {
//                $em->remove($user);
//                $em->flush();
//            }
//        }
//    }

    #[RunInSeparateProcess]
    public function testRegisterValidUser(): void
    {
        $client = static::createClient(); // ← ОБЯЗАТЕЛЬНО ПЕРВЫМ
        $client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['email' => 'test@example.com', 'password' => 'password123']));
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('test@example.com', $data['email']);
    }

    #[RunInSeparateProcess]
    public function testRegisterInvalidEmail(): void
    {
        $client = static::createClient(); // ← первым!
        $client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['email' => 'invalid-email', 'password' => 'password123']));
        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Некорректный email', $data['errors']['email'] ?? '');
    }

    #[RunInSeparateProcess]
    public function testRegisterShortPassword(): void
    {
        $client = static::createClient(); // ← первым!
        $client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['email' => 'test2@example.com', 'password' => '123']));
        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('не менее 6', $data['errors']['password'] ?? '');
    }

    #[RunInSeparateProcess]
    public function testRegisterDuplicateEmail(): void
    {
        $client = static::createClient(); // ← первым!
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');

        // Создаём первого
        $user1 = new User();
        $user1->setEmail('duplicate@example.com');
        $user1->setPassword($passwordHasher->hashPassword($user1, 'password123'));
        $em->persist($user1);
        $em->flush();

        // Пытаемся создать второго
        $client->request('POST', '/api/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['email' => 'duplicate@example.com', 'password' => 'password123']));
        $this->assertResponseStatusCodeSame(409);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Email already exists', $data['error'] ?? '');
    }
}
