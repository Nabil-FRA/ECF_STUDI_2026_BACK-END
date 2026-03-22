<?php

namespace App\Controller;

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
        PlatRepository $platRepository,
        RegimeRepository $regimeRepository,
        AllergeneRepository $allergeneRepository
    ): Response {
        return $this->render('home/index.html.twig', [
            'plats' => $platRepository->findAll(),
            'regimes' => $regimeRepository->findAll(),
            'allergenes' => $allergeneRepository->findAll(),
        ]);
    }

    #[Route('/api/plats', name: 'api_plats', methods: ['GET'])]
    public function getPlatsFiltres(Request $request, PlatRepository $platRepository): JsonResponse
    {
        // 1. Récupération des IDs envoyés par l'AJAX
        $regimeId = $request->query->get('regime');
        $allergeneId = $request->query->get('allergene');

        // 2. On récupère TOUS les plats pour faire le tri en PHP
        $tousLesPlats = $platRepository->findAll();
        $platsFiltres = [];

        foreach ($tousLesPlats as $plat) {
            $garderLePlat = true;

            // --- FILTRE RÉGIME (VIA LE MENU) ---
            if (!empty($regimeId)) {
                $matchRegime = false;

                // Un plat peut appartenir à plusieurs menus
                // On vérifie si UN de ces menus a le régime demandé
                foreach ($plat->getMenus() as $menu) {
                    if ($menu->getRegime() && $menu->getRegime()->getId() == $regimeId) {
                        $matchRegime = true;
                        break; // On a trouvé un menu correspondant, c'est bon pour ce plat
                    }
                }

                if (!$matchRegime) {
                    $garderLePlat = false;
                }
            }

            // --- FILTRE ALLERGÈNE (DIRECT) ---
            if ($garderLePlat && !empty($allergeneId)) {
                foreach ($plat->getAllergenes() as $allergene) {
                    if ($allergene->getId() == $allergeneId) {
                        $garderLePlat = false; // Le plat contient l'allergène à exclure
                        break;
                    }
                }
            }

            // 3. Si le plat a passé les deux filtres, on l'ajoute au résultat
            if ($garderLePlat) {
                $platsFiltres[] = [
                    'id' => $plat->getId(),
                    'titre' => $plat->getTitrePlat(),
                    'photo' => $plat->getPhoto(), // Maintenant ça fonctionnera
                ];
            }
        }

        return $this->json($platsFiltres);
    }
}
