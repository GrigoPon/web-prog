<?php // src/Controller/Api/ProductController.php

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
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ProductController extends AbstractController
{
    #[Route('/api/products', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        #[CurrentUser] User $user
    ): JsonResponse {
        $qb = $em->createQueryBuilder();
        $qb
            ->select('p')
            ->from(Product::class, 'p')
            ->leftJoin('p.stocks', 's')
            ->where('p.owner = :user')
            ->groupBy('p.id') // ← обязательно!
            ->setParameter('user', $user);

        $name = $request->query->get('name');
        $minQuantity = $request->query->get('minQuantity');
        $maxQuantity = $request->query->get('maxQuantity');
        $inStock = $request->query->get('inStock');

        if ($name) {
            $qb->andWhere('p.name LIKE :name')
                ->setParameter('name', '%' . $name . '%');
        }

        // Если есть ЛЮБОЙ фильтр по остаткам — накладываем условия на stock
        if ($inStock === 'true' || ($minQuantity !== null && is_numeric($minQuantity)) || ($maxQuantity !== null && is_numeric($maxQuantity))) {
            // Требуем, чтобы stock существовал (INNER JOIN по факту)
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
        error_log('inStock: ' . var_export($inStock, true));
        error_log('name: ' . var_export($name, true));
        error_log('minQuantity: ' . var_export($minQuantity, true));
        error_log('Raw query string: ' . $request->server->get('QUERY_STRING', ''));
        error_log('All query params: ' . var_export($request->query->all(), true));
        $products = $qb->getQuery()->getResult();
        return $this->json($products, context: ['groups' => 'product:read']);
    }

    #[Route('/api/products', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
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

        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'quantity' => $stock->getQuantity()
        ], 201);
    }

    #[Route('/api/products/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse {
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

        //ОСТАТОК
        $stock = $product->getStocks()->first();
        if ($stock && isset($data['quantity'])) {
            $stock->setQuantity($data['quantity']);
        }
        $em->flush();
        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'quantity' => $stock ? $stock->getQuantity() : 0
        ], 200);
    }

    #[Route('/api/products/{id}', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse {
        $product = $em->getRepository(Product::class)->find($id);
        $stock = $product->getStocks()->first();

        if (!$product) {
            return $this->json(['error' => 'product not found'], 404);
        }

        if ($product->getOwner() !== $user) {
            throw new AccessDeniedHttpException('You cannot delete this product');
        }
        $em->remove($stock);
        $em->remove($product);
        $em->flush();
        return $this->json(null, 204);
    }
}
