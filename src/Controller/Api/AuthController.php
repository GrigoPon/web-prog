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
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Auth", description: "Авторизация и регистрация")]
class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    #[OA\Post(
        summary: "Регистрация нового пользователя",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", example: "123456"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Успешная регистрация",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "id", type: "integer", example: 1),
                        new OA\Property(property: "email", type: "string", example: "user@example.com"),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Ошибка валидации (некорректный email или короткий пароль)",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "errors",
                            type: "object",
                            additionalProperties: new OA\AdditionalProperties(type: "string")
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: "Email уже существует",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "error", type: "string", example: "Email already exists")
                    ]
                )
            )
        ]
    )]
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
        $user->setPassword($data['password']);


        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorsArray = [];
            foreach ($errors as $error) {
                $errorsArray[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorsArray], 400);
        }


        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
        if ($existingUser) {
            return $this->json(['error' => 'Email already exists'], 409);
        }


        $user->setPassword($passwordHasher->hashPassword($user, $user->getPassword()));

        $em->persist($user);
        $em->flush();

        $message = new UserRegisteredMessage(
            $user->getId(),
            $user->getEmail(),
            new \DateTimeImmutable()
        );
        $messageBus->dispatch($message);

        return $this->json(['id' => $user->getId(), 'email' => $user->getEmail()], 201);
    }

    #[Route('/api/session-check', name: 'api_session_check', methods: ['GET'])]
    #[OA\Get(
        summary: "Проверить, авторизован ли пользователь",
        responses: [
            new OA\Response(
                response: 200,
                description: "Статус сессии",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "logged_in", type: "boolean", example: true),
                        new OA\Property(property: "email", type: "string", example: "user@example.com"),
                    ],
                    required: ["logged_in"]
                )
            )
        ]
    )]
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

        throw new \LogicException('form_login должен обработать запрос до этого метода.');
    }



    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Logout должен быть обработан firewall\'ом.');
    }



}
