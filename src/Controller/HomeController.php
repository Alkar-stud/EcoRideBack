<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController{

    #[Route('/', name: 'app_')]
    public function indexHome(): Response
    {
        return new Response('Bienvenue sur EcoRide !<br> Vous avez l\'API <a href="/api">ici</a>') ;
    }

    #[Route('/api', name: 'app_api_')]
    public function index(): Response
    {
        return new Response('Bienvenue sur l\'API d\'EcoRide !<br> Vous avez la doc <a href="/api/doc/">ici</a>') ;
    }
}
