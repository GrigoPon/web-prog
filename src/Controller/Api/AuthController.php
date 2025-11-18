<?php
// src/Controller/Api/AuthController.php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use App\Message\UserRegisteredMessage;
use Symfony\Component\Messenger\MessageBusInterface;

class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        MessageBusInterface $messageBus,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $data['password'])
        );

        // 🔍 Валидация
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorsArray = [];
            foreach ($errors as $error) {
                $errorsArray[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorsArray], 400);
        }

        // Проверка дубликата email (после валидации)
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
        if ($existingUser) {
            return $this->json(['error' => 'Email already exists'], 409);
        }

        $em->persist($user);
        $em->flush();

        // Отправляем сообщение
        $message = new UserRegisteredMessage(
            $user->getId(),
            $user->getEmail(),
            new \DateTimeImmutable()
        );
        $messageBus->dispatch($message);

        return $this->json(['id' => $user->getId(), 'email' => $user->getEmail()], 201);
    }

    #[Route('/api/session-check', name: 'api_session_check', methods: ['GET'])]
    public function sessionCheck(): JsonResponse
    {
        $user = $this->getUser();
        if ($user) {
            return $this->json([
                'logged_in' => true,
                'email' => $user->getEmail(),
            ]);
        }

        return $this->json(['logged_in' => false]);
    }
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): never
    {
        // Этот метод НИКОГДА не должен быть вызван!
        // Если сюда попали — значит, аутентификация не сработала.
        throw new \LogicException('form_login должен обработать запрос до этого метода.');
    }



    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Logout должен быть обработан firewall\'ом.');
    }



}
