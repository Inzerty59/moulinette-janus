<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GeiqController extends AbstractController
{
    #[Route('/geiq', name: 'app_geiq')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->render('geiq/index.html.twig');
    }
}
