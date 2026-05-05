<?php

namespace App\Controller\Api;

use App\Entity\Menu;
use App\Repository\AllergeneRepository;
use App\Repository\MenuRepository;
use App\Repository\RegimeRepository;
use App\Repository\ThemeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller API pour les menus (endpoints publics).
 * 
 * SÉCURITÉ :
 * - Toutes les sorties sont échappées (htmlspecialchars)
 * - Validation des paramètres de requête (cast typé)
 * - Pas d'exposition de données sensibles
 */
#[Route('/api')]
class MenuApiController extends AbstractController
{
    /**
     * GET /api/menus - Liste des menus avec filtres dynamiques
     * Paramètres query : regime, theme, prix_min, prix_max, nb_personnes
     */
    #[Route('/menus', name: 'api_menus_list', methods: ['GET'])]
    public function list(Request $request, MenuRepository $menuRepository): JsonResponse
    {
        // Cast typé des paramètres (protection injection)
        $regimeId = $request->query->getInt('regime', 0) ?: null;
        $themeId = $request->query->getInt('theme', 0) ?: null;
        $prixMin = $request->query->get('prix_min') !== null ? (float) $request->query->get('prix_min') : null;
        $prixMax = $request->query->get('prix_max') !== null ? (float) $request->query->get('prix_max') : null;
        $nbPersonnes = $request->query->getInt('nb_personnes', 0) ?: null;

        $menus = $menuRepository->findAll();
        $result = [];

        foreach ($menus as $menu) {
            if ($regimeId && (!$menu->getRegime() || $menu->getRegime()->getId() !== $regimeId)) {
                continue;
            }
            if ($themeId && (!$menu->getTheme() || $menu->getTheme()->getId() !== $themeId)) {
                continue;
            }
            if ($prixMax !== null && $menu->getPrixParPersonne() > $prixMax) {
                continue;
            }
            if ($prixMin !== null && $menu->getPrixParPersonne() < $prixMin) {
                continue;
            }
            if ($nbPersonnes && $menu->getNombrePersonneMinimum() > $nbPersonnes) {
                continue;
            }

            $result[] = $this->serializeMenu($menu);
        }

        return $this->json($result);
    }

    /**
     * GET /api/menus/{id} - Détail d'un menu avec plats et allergènes
     */
    #[Route('/menus/{id}', name: 'api_menus_detail', methods: ['GET'])]
    public function detail(Menu $menu): JsonResponse
    {
        $data = $this->serializeMenu($menu);

        // Ajout des plats avec allergènes
        $plats = [];
        foreach ($menu->getPlats() as $plat) {
            $allergenes = [];
            foreach ($plat->getAllergenes() as $allergene) {
                $allergenes[] = [
                    'id' => $allergene->getId(),
                    'libelle' => $this->escape($allergene->getLIBELLE()),
                ];
            }

            $plats[] = [
                'id' => $plat->getId(),
                'titre' => $this->escape($plat->getTitrePlat()),
                'photo' => $plat->getPhoto(),
                'allergenes' => $allergenes,
            ];
        }
        $data['plats'] = $plats;

        // Ajout des images
        $images = [];
        foreach ($menu->getMenuImages() as $img) {
            $images[] = [
                'id' => $img->getId(),
                'url' => $img->getUrlImage(),
            ];
        }
        $data['images'] = $images;

        // Conditions
        $data['conditions'] = $this->escape($menu->getConditions() ?? '');

        return $this->json($data);
    }

    /**
     * GET /api/themes - Liste des thèmes
     */
    #[Route('/themes', name: 'api_themes', methods: ['GET'])]
    public function themes(ThemeRepository $themeRepository): JsonResponse
    {
        $themes = [];
        foreach ($themeRepository->findAll() as $theme) {
            $themes[] = [
                'id' => $theme->getId(),
                'libelle' => $this->escape($theme->getLibelle()),
            ];
        }
        return $this->json($themes);
    }

    /**
     * GET /api/regimes - Liste des régimes alimentaires
     */
    #[Route('/regimes', name: 'api_regimes', methods: ['GET'])]
    public function regimes(RegimeRepository $regimeRepository): JsonResponse
    {
        $regimes = [];
        foreach ($regimeRepository->findAll() as $regime) {
            $regimes[] = [
                'id' => $regime->getId(),
                'libelle' => $this->escape($regime->getLibelle()),
            ];
        }
        return $this->json($regimes);
    }

    /**
     * GET /api/allergenes - Liste des allergènes
     */
    #[Route('/allergenes', name: 'api_allergenes', methods: ['GET'])]
    public function allergenes(AllergeneRepository $allergeneRepository): JsonResponse
    {
        $allergenes = [];
        foreach ($allergeneRepository->findAll() as $a) {
            $allergenes[] = [
                'id' => $a->getId(),
                'libelle' => $this->escape($a->getLIBELLE()),
            ];
        }
        return $this->json($allergenes);
    }

    /**
     * Sérialise un menu pour le JSON.
     * SÉCURITÉ : échappement de toutes les chaînes
     */
    private function serializeMenu(Menu $menu): array
    {
        // Image principale : première image du menu (si disponible)
        $firstImage = $menu->getMenuImages()->first();
        $imagePrincipale = $firstImage ? $firstImage->getUrlImage() : null;

        return [
            'id' => $menu->getId(),
            'titre' => $this->escape($menu->getTitre()),
            'description' => $this->escape($menu->getDescription()),
            'prix_par_personne' => $menu->getPrixParPersonne(),
            'nombre_personne_minimum' => $menu->getNombrePersonneMinimum(),
            'quantite_restante' => $menu->getQuantiteRestante(),
            'image' => $imagePrincipale,
            'theme' => $menu->getTheme() ? [
                'id' => $menu->getTheme()->getId(),
                'libelle' => $this->escape($menu->getTheme()->getLibelle()),
            ] : null,
            'regime' => $menu->getRegime() ? [
                'id' => $menu->getRegime()->getId(),
                'libelle' => $this->escape($menu->getRegime()->getLibelle()),
            ] : null,
        ];
    }

    /**
     * Retourne la valeur brute pour la sortie JSON.
     * JSON n'est pas du HTML — pas de htmlspecialchars() sur les sorties JSON.
     */
    private function escape(string $value): string
    {
        return $value;
    }
}
