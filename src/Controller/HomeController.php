<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/')]
class HomeController extends AbstractController
{
    #[Route(path: '/', name: 'app_home_index')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_status_list');
    }
}