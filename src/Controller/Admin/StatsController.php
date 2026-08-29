<?php

namespace App\Controller\Admin;

use App\Service\MongoDbService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/stats')]
#[IsGranted('ROLE_ADMIN')]
class StatsController extends AbstractController
{
    #[Route('/', name: 'admin_stats')]
    public function index(MongoDbService $mongoDbService): Response
    {
        $commandesParMenu = $mongoDbService->getCommandesParMenu();
        $menusList = $mongoDbService->getMenusList();

        return $this->render('admin/stats.html.twig', [
            'commandesParMenu' => $commandesParMenu,
            'menusList' => $menusList,
        ]);
    }

    #[Route('/api/chiffre-affaires', name: 'admin_stats_ca', methods: ['GET'])]
    public function chiffreAffaires(Request $request, MongoDbService $mongoDbService): JsonResponse
    {
        $menuTitre = $request->query->get('menu');
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');

        $data = $mongoDbService->getChiffreAffaires(
            $menuTitre ?: null,
            $dateDebut ?: null,
            $dateFin ?: null
        );

        return $this->json($data);
    }
}
