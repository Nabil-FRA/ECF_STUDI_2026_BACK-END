<?php

namespace App\Controller;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use App\Repository\RegimeRepository;
use App\Repository\ThemeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MenuController extends AbstractController
{
    /**
     * Route publique : affiche la page avec tous les menus + les filtres
     * URL : /menus
     */
    #[Route('/menus', name: 'app_menus')]
    public function index(
        MenuRepository $menuRepository,
        RegimeRepository $regimeRepository,
        ThemeRepository $themeRepository
    ): Response {
        return $this->render('menu/index.html.twig', [
            'menus' => $menuRepository->findAll(),
            'regimes' => $regimeRepository->findAll(),
            'themes' => $themeRepository->findAll(),
        ]);
    }

    /**
     * Route API appelée par le JavaScript (AJAX)
     * Le JS envoie les filtres dans l'URL, par exemple :
     * /api/menus?regime=3&theme=5&prix_max=40&prix_min=20&nb_personnes=6
     *
     * PHP récupère ces paramètres, filtre les menus, et renvoie du JSON
     */
    #[Route('/api/menus', name: 'api_menus', methods: ['GET'])]
    public function getMenusFiltres(Request $request, MenuRepository $menuRepository): JsonResponse
    {
        // 1. Récupérer les valeurs envoyées par le JavaScript
        $regimeId = $request->query->get('regime');
        $themeId = $request->query->get('theme');
        $prixMax = $request->query->get('prix_max');
        $prixMin = $request->query->get('prix_min');
        $nbPersonnes = $request->query->get('nb_personnes');

        // 2. Récupérer tous les menus de la base
        $tousLesMenus = $menuRepository->findAll();
        $menusFiltres = [];

        // 3. Parcourir chaque menu et appliquer les filtres
        foreach ($tousLesMenus as $menu) {
            $garder = true;

            // FILTRE RÉGIME : on vérifie si le menu a le bon régime
            // Exemple : le visiteur choisit "Végétarien" (id=4)
            // On compare avec le regime_id du menu
            if (!empty($regimeId)) {
                if (!$menu->getRegime() || $menu->getRegime()->getId() != $regimeId) {
                    $garder = false;
                }
            }

            // FILTRE THÈME : même logique
            // Exemple : le visiteur choisit "Noël" (id=3)
            if ($garder && !empty($themeId)) {
                if (!$menu->getTheme() || $menu->getTheme()->getId() != $themeId) {
                    $garder = false;
                }
            }

            // FILTRE PRIX MAXIMUM : on exclut les menus trop chers
            // Exemple : le visiteur met 40€ max → un menu à 45.6€ disparaît
            if ($garder && !empty($prixMax)) {
                if ($menu->getPrixParPersonne() > (float) $prixMax) {
                    $garder = false;
                }
            }

            // FILTRE PRIX MINIMUM : on exclut les menus trop bon marché
            // Exemple : le visiteur met 30€ min → un menu à 25€ disparaît
            if ($garder && !empty($prixMin)) {
                if ($menu->getPrixParPersonne() < (float) $prixMin) {
                    $garder = false;
                }
            }

            // FILTRE NOMBRE DE PERSONNES : on garde les menus dont le minimum
            // est inférieur ou égal au nombre demandé
            // Exemple : visiteur veut 6 personnes → menu avec min 10 disparaît
            if ($garder && !empty($nbPersonnes)) {
                if ($menu->getNombrePersonneMinimum() > (int) $nbPersonnes) {
                    $garder = false;
                }
            }

            // 4. Si le menu a passé TOUS les filtres, on le prépare pour le JSON
            if ($garder) {
                $menusFiltres[] = [
                    'id' => $menu->getId(),
                    'titre' => $menu->getTitre(),
                    'description' => $menu->getDescription(),
                    'prix_par_personne' => $menu->getPrixParPersonne(),
                    'nombre_personne_minimum' => $menu->getNombrePersonneMinimum(),
                    'quantite_restante' => $menu->getQuantiteRestante(),
                    'theme' => $menu->getTheme() ? $menu->getTheme()->getLibelle() : null,
                    'regime' => $menu->getRegime() ? $menu->getRegime()->getLibelle() : null,
                ];
            }
        }

        // 5. Renvoyer le tableau au format JSON pour que le JavaScript le reçoive
        return $this->json($menusFiltres);
    }

    /**
     * Route publique : affiche le détail d'un menu
     * URL : /menu/2 (où 2 est l'id du menu)
     * Symfony récupère automatiquement le Menu depuis l'id grâce au ParamConverter
     */
    #[Route('/menu/{id}', name: 'app_menu_detail')]
    public function detail(Menu $menu): Response
    {
        return $this->render('menu/detail.html.twig', [
            'menu' => $menu,
        ]);
    }
}
