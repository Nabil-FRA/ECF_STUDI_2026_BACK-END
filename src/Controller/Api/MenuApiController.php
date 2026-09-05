<?php

namespace App\Controller\Api;

use App\Entity\Menu;
use App\Repository\AllergeneRepository;
use App\Repository\MenuRepository;
use App\Repository\RegimeRepository;
use App\Repository\ThemeRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
#[OA\Tag(name: 'Menus (public)')]
class MenuApiController extends AbstractController
{
    #[Route('/menus', name: 'api_menus_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste des menus',
        description: 'Retourne tous les menus avec possibilité de filtrer par régime, thème, prix et nombre de personnes.',
        parameters: [
            new OA\Parameter(name: 'regime', in: 'query', required: false, description: 'ID du régime alimentaire', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'theme', in: 'query', required: false, description: 'ID du thème', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'prix_min', in: 'query', required: false, description: 'Prix minimum par personne', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'prix_max', in: 'query', required: false, description: 'Prix maximum par personne', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'nb_personnes', in: 'query', required: false, description: 'Nombre de personnes (filtre sur le minimum requis)', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des menus filtrés')
        ]
    )]
    public function list(Request $request, MenuRepository $menuRepository): JsonResponse
    {
        $regimeId = $this->filtreEntier($request, 'regime');
        $themeId = $this->filtreEntier($request, 'theme');
        $nbPersonnes = $this->filtreEntier($request, 'nb_personnes');
        $prixMin = $this->filtreDecimal($request, 'prix_min');
        $prixMax = $this->filtreDecimal($request, 'prix_max');

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

    #[Route('/menus/{id}', name: 'api_menus_detail', methods: ['GET'])]
    #[OA\Get(
        summary: 'Détail d\'un menu',
        description: 'Retourne le détail complet d\'un menu : informations, plats avec allergènes, images et conditions.',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID du menu', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail du menu avec plats, allergènes et images'),
            new OA\Response(response: 404, description: 'Menu introuvable')
        ]
    )]
    public function detail(Menu $menu): JsonResponse
    {
        $data = $this->serializeMenu($menu);

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

        $images = [];
        foreach ($menu->getMenuImages() as $img) {
            $images[] = [
                'id' => $img->getId(),
                'url' => $img->getUrlImage(),
            ];
        }
        $data['images'] = $images;

        $data['conditions'] = $this->escape($menu->getConditions() ?? '');

        return $this->json($data);
    }

    #[Route('/themes', name: 'api_themes', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste des thèmes',
        description: 'Retourne tous les thèmes de menus disponibles.',
        responses: [
            new OA\Response(response: 200, description: 'Liste des thèmes')
        ]
    )]
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

    #[Route('/regimes', name: 'api_regimes', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste des régimes alimentaires',
        description: 'Retourne tous les régimes alimentaires disponibles (végétarien, sans gluten, etc.).',
        responses: [
            new OA\Response(response: 200, description: 'Liste des régimes')
        ]
    )]
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

    #[Route('/allergenes', name: 'api_allergenes', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste des allergènes',
        description: 'Retourne tous les allergènes référencés (gluten, lactose, arachides, etc.).',
        responses: [
            new OA\Response(response: 200, description: 'Liste des allergènes')
        ]
    )]
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
     * Filtre optionnel de type entier.
     *
     * InputBag::getInt() leve une BadRequestException, donc un 400, des que la
     * valeur n'est pas un entier : un client qui se trompe sur un filtre
     * facultatif faisait echouer toute la requete. Ici une valeur inexploitable
     * est simplement ignoree.
     */
    private function filtreEntier(Request $request, string $cle): ?int
    {
        $valeur = $request->query->all()[$cle] ?? null;

        if (!is_scalar($valeur) || !ctype_digit((string) $valeur)) {
            return null;
        }

        return (int) $valeur ?: null;
    }

    /** Filtre optionnel de type decimal, meme principe. */
    private function filtreDecimal(Request $request, string $cle): ?float
    {
        $valeur = $request->query->all()[$cle] ?? null;

        return (is_scalar($valeur) && is_numeric($valeur)) ? (float) $valeur : null;
    }

    private function serializeMenu(Menu $menu): array
    {
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

    private function escape(string $value): string
    {
        return $value;
    }
}
