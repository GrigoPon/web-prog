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
    public function index(EntityManagerInterface $em, #[CurrentUser] User $user): JsonResponse
    {
        $products = $em->getRepository(Product::class)->findBy(['owner' => $user]);
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
        return $this->json($product, 200);
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
