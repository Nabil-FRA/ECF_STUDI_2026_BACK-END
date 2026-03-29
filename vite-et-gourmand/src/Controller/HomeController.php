<?php

namespace App\Controller;

use App\Repository\AvisRepository;
use App\Repository\HoraireRepository;
use App\Repository\PlatRepository;
use App\Repository\RegimeRepository;
use App\Repository\AllergeneRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        AvisRepository $avisRepository
    ): Response {
        // Récupérer uniquement les avis validés
        $avisValides = $avisRepository->findBy(
            ['statut' => 'validé'],
            ['id' => 'DESC']
        );

        return $this->render('home/index.html.twig', [
            'avis' => $avisValides,
        ]);
    }

    #[Route('/api/plats', name: 'api_plats', methods: ['GET'])]
    public function getPlatsFiltres(Request $request, PlatRepository $platRepository): JsonResponse
    {
        $regimeId = $request->query->get('regime');
        $allergeneId = $request->query->get('allergene');

        $tousLesPlats = $platRepository->findAll();
        $platsFiltres = [];

        foreach ($tousLesPlats as $plat) {
            $garderLePlat = true;

            if (!empty($regimeId)) {
                $matchRegime = false;
                foreach ($plat->getMenus() as $menu) {
                    if ($menu->getRegime() && $menu->getRegime()->getId() == $regimeId) {
                        $matchRegime = true;
                        break;
                    }
                }
                if (!$matchRegime) {
                    $garderLePlat = false;
                }
            }

            if ($garderLePlat && !empty($allergeneId)) {
                foreach ($plat->getAllergenes() as $allergene) {
                    if ($allergene->getId() == $allergeneId) {
                        $garderLePlat = false;
                        break;
                    }
                }
            }

            if ($garderLePlat) {
                $platsFiltres[] = [
                    'id' => $plat->getId(),
                    'titre' => $plat->getTitrePlat(),
                    'photo' => $plat->getPhoto(),
                ];
            }
        }

        return $this->json($platsFiltres);
    }
}
