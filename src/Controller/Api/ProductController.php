<?php
// src/Controller/Api/ProductController.php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Entity\Stock;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use App\Message\ProductCreatedMessage;
use App\Message\ProductDeletedMessage;
use App\Message\ProductUpdatedMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use OpenApi\Attributes as OA;


#[OA\Tag(name: "Products", description: "Управление товарами на складе")]
class ProductController extends AbstractController
{

    #[Route('/api/products', methods: ['GET'])]
    #[OA\Get(
        summary: "Получить список товаров",
        parameters: [
            new OA\Parameter(name: "name", in: "query", schema: new OA\Schema(type: "string"), description: "Фильтр по названию (частичное совпадение)"),
            new OA\Parameter(name: "minQuantity", in: "query", schema: new OA\Schema(type: "integer"), description: "Минимальное количество"),
            new OA\Parameter(name: "maxQuantity", in: "query", schema: new OA\Schema(type: "integer"), description: "Максимальное количество"),
            new OA\Parameter(name: "inStock", in: "query", schema: new OA\Schema(type: "boolean"), description: "Только товары в наличии (quantity > 0)"),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Список товаров",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/Product")
                )
            ),
            new OA\Response(response: 401, description: "Неавторизован")
        ]
    )]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $cacheKey = 'products_user_' . $user->getId() . '_' . md5($request->getQueryString());
        $products = $cache->get($cacheKey, function (ItemInterface $item) use ($request, $em, $user) {
            $item->tag('products_user_' . $user->getId());
            $item->expiresAfter(300);

            $qb = $em->createQueryBuilder();
            $qb
                ->select('p', 's') // ← ИСПРАВЛЕНО: добавлено 's'
                ->from(Product::class, 'p')
                ->leftJoin('p.stocks', 's')
                ->where('p.owner = :user')
                ->groupBy('p.id, s.id') // ← ИСПРАВЛЕНО: добавлено 's.id'
                ->setParameter('user', $user);

            $name = $request->query->get('name');
            $minQuantity = $request->query->get('minQuantity');
            $maxQuantity = $request->query->get('maxQuantity');
            $inStock = $request->query->get('inStock');

            if ($name) {
                $qb->andWhere('p.name LIKE :name')
                    ->setParameter('name', '%' . $name . '%');
            }

            if ($inStock === 'true' || ($minQuantity !== null && is_numeric($minQuantity)) || ($maxQuantity !== null && is_numeric($maxQuantity))) {
                $qb->andWhere('s.id IS NOT NULL');

                if ($inStock === 'true') {
                    $qb->andWhere('s.quantity > 0');
                }
                if ($minQuantity !== null && is_numeric($minQuantity)) {
                    $qb->andWhere('s.quantity >= :minQuantity')
                        ->setParameter('minQuantity', (int) $minQuantity);
                }
                if ($maxQuantity !== null && is_numeric($maxQuantity)) {
                    $qb->andWhere('s.quantity <= :maxQuantity')
                        ->setParameter('maxQuantity', (int) $maxQuantity);
                }
            }

            return $qb->getQuery()->getResult();
        });

        return $this->json($products, context: ['groups' => 'product:read']);
    }

    #[Route('/api/products', methods: ['POST'])]
    #[OA\Post(
        summary: "Создать новый товар",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "quantity"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Ноутбук"),
                    new OA\Property(property: "description", type: "string", example: "Мощный игровой ноутбук"),
                    new OA\Property(property: "quantity", type: "integer", example: 10),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Товар создан",
                content: new OA\JsonContent(ref: "#/components/schemas/Product")
            ),
            new OA\Response(response: 401, description: "Неавторизован")
        ]
    )]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        MessageBusInterface $messageBus,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);

        $product = new Product();
        $product->setName($data['name']);
        $product->setDescription($data['description'] ?? '');
        $product->setOwner($user);

        $stock = new Stock();
        $stock->setQuantity($data['quantity'] ?? 0);
        $stock->setProduct($product);

        $em->persist($product);
        $em->persist($stock);
        $em->flush();


        $cache->invalidateTags(['products_user_' . $user->getId()]);

        // Отправляем сообщение
        $message = new ProductCreatedMessage(
            $product->getId(),
            $product->getName(),
            $stock->getQuantity(),
            $user->getId(),
            new \DateTimeImmutable()
        );
        $messageBus->dispatch($message);

        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'quantity' => $stock->getQuantity()
        ], 201);
    }

    #[Route('/api/products/{id}', methods: ['PUT'])]
    #[OA\Put(
        summary: "Обновить товар",
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Обновлённый ноутбук"),
                    new OA\Property(property: "description", type: "string", example: "Обновлённое описание"),
                    new OA\Property(property: "quantity", type: "integer", example: 5),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Товар обновлён",
                content: new OA\JsonContent(ref: "#/components/schemas/Product")
            ),
            new OA\Response(response: 401, description: "Неавторизован"),
            new OA\Response(response: 404, description: "Товар не найден")
        ]
    )]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        MessageBusInterface $messageBus,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $product = $em->getRepository(Product::class)->find($id);

        if (!$product) {
            return $this->json(['error' => 'product not found'], 404);
        }

        if ($product->getOwner() !== $user) {
            throw new AccessDeniedHttpException('You cannot edit this product');
        }

        $data = json_decode($request->getContent(), true);
        $product->setName($data['name']);
        $product->setDescription($data['description'] ?? '');

        $stock = $product->getStocks()->first();
        if ($stock && isset($data['quantity'])) {
            $stock->setQuantity($data['quantity']);
        }

        $em->flush();

        // 🔔 Отправляем сообщение
        $message = new ProductUpdatedMessage(
            $product->getId(),
            $product->getName(),
            $stock?->getQuantity() ?? 0,
            $user->getId(),
            new \DateTimeImmutable()
        );
        $messageBus->dispatch($message);

        $cache->invalidateTags(['products_user_' . $user->getId()]);

        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'quantity' => $stock ? $stock->getQuantity() : 0
        ], 200);
    }

    #[Route('/api/products/{id}', methods: ['DELETE'])]
    #[OA\Delete(
        summary: "Удалить товар",
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Товар удалён"),
            new OA\Response(response: 401, description: "Неавторизован"),
            new OA\Response(response: 404, description: "Товар не найден")
        ]
    )]
    public function delete(
        int $id,
        EntityManagerInterface $em,
        MessageBusInterface $messageBus,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $product = $em->getRepository(Product::class)->find($id);

        if (!$product) {
            return $this->json(['error' => 'product not found'], 404);
        }

        if ($product->getOwner() !== $user) {
            throw new AccessDeniedHttpException('You cannot delete this product');
        }

        $productId = $product->getId();
        $name = $product->getName();
        $stock = $product->getStocks()->first();
        $quantity = $stock?->getQuantity() ?? 0;

        // Отправляем сообщение ПЕРЕД удалением (пока данные ещё есть)
        $message = new ProductDeletedMessage(
            $productId,
            $name,
            $quantity,
            $user->getId(),
            new \DateTimeImmutable()
        );
        $messageBus->dispatch($message);


        if ($stock) {
            $em->remove($stock);
        }
        $em->remove($product);
        $em->flush();



        $cache->invalidateTags(['products_user_' . $user->getId()]);

        return $this->json(null, 204);
    }
}
