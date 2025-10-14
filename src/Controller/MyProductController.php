<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MyProductController extends AbstractController
{
    #[Route('/my-products', name: 'my_products')]
    public function index(): Response
    {
        return $this->render('my_products.html');
    }
}
