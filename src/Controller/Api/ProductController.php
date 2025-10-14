<?php // src/Controller/Api/ProductController.php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Entity\Stock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    #[Route('/api/products', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $products = $em->getRepository(Product::class)->findAll();
        return $this->json($products);
    }

    #[Route('/api/products', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $product = new Product();
        $product->setName($data['name']);
        $product->setDescription($data['description'] ?? '');

        $stock = new Stock();
        $stock->setQuantity($data['quantity'] ?? 0);
        $stock->setProduct($product);

        $em->persist($product);
        $em->persist($stock);
        $em->flush();

        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'quantity' => $stock->getQuantity()
        ], 201);
    }
}
