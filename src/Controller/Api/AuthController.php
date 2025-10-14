<?php // src/Controller/Api/AuthController.php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $data['password'])
        );

        $em->persist($user);
        $em->flush();

        return $this->json(['id' => $user->getId(), 'email' => $user->getEmail()], 201);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);

        if (!$user || !password_verify($data['password'], $user->getPassword())) {
            return $this->json(['error' => 'Invalid credentials'], 401);
        }

        $token = bin2hex(random_bytes(32));
        $user->setApiToken($token);
        $em->persist($user); // ← ЭТО ОБЯЗАТЕЛЬНО!
        $em->flush();

        return $this->json(['token' => $token]);
    }
}
