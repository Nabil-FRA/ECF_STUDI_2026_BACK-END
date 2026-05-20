<?php

namespace App\Controller\Api;

use App\Entity\Allergene;
use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\Menu;
use App\Entity\MenuImage;
use App\Entity\Plat;
use App\Entity\Utilisateur;
use App\Repository\MenuRepository;
use App\Repository\RegimeRepository;
use App\Repository\RoleRepository;
use App\Repository\ThemeRepository;
use App\Repository\UtilisateurRepository;
use App\Service\MongoDbService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Administration')]
class AdminApiController extends AbstractController
{
    // =========================================================
    // UTILISATEURS
    // =========================================================

    #[Route('/utilisateurs', name: 'api_admin_utilisateurs', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste des utilisateurs',
        description: 'Retourne tous les utilisateurs sauf les administrateurs.',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des utilisateurs')
        ]
    )]
    public function utilisateurs(UtilisateurRepository $repo): JsonResponse
    {
        $users = $repo->findAll();
        $result = [];

        foreach ($users as $u) {
            if ($u->getRole() && $u->getRole()->getLibelle() === 'administrateur') {
                continue;
            }
            $result[] = [
                'id' => $u->getId(),
                'email' => $u->getEmail(),
                'nom' => $u->getNom(),
                'prenom' => $u->getPrenom(),
                'telephone' => $u->getTelephone(),
                'ville' => $u->getVille(),
                'role' => $u->getRole() ? $u->getRole()->getLibelle() : null,
            ];
        }

        return $this->json(['success' => true, 'utilisateurs' => $result]);
    }

    #[Route('/employes', name: 'api_admin_create_employe', methods: ['POST'])]
    #[OA\Post(
        summary: 'Créer un employé',
        description: 'Crée un nouveau compte employé. Un email de bienvenue est envoyé automatiquement.',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'nom', 'prenom', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'employe@viteetgourmand.fr'),
                    new OA\Property(property: 'nom', type: 'string', example: 'Martin'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Sophie'),
                    new OA\Property(property: 'password', type: 'string', description: 'Min 10 car., 1 maj, 1 min, 1 chiffre, 1 spécial', example: 'EmployeMdp2024!')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Employé créé'),
            new OA\Response(response: 400, description: 'Champs manquants / email invalide / mot de passe trop faible'),
            new OA\Response(response: 409, description: 'Email déjà utilisé'),
            new OA\Response(response: 500, description: 'Rôle employé introuvable en base')
        ]
    )]
    public function createEmploye(
        Request $request,
        EntityManagerInterface $em,
        RoleRepository $roleRepository,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $email = strip_tags(trim($data['email'] ?? ''));
        $nom = strip_tags(trim($data['nom'] ?? ''));
        $prenom = strip_tags(trim($data['prenom'] ?? ''));
        $password = $data['password'] ?? '';

        if (empty($email) || empty($nom) || empty($prenom) || empty($password)) {
            return $this->json(['success' => false, 'message' => 'Tous les champs sont obligatoires.'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['success' => false, 'message' => 'Email invalide.'], 400);
        }

        if (strlen($password) < 10 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password)
            || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            return $this->json(['success' => false, 'message' => 'Mot de passe trop faible.'], 400);
        }

        $existing = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
        if ($existing) {
            return $this->json(['success' => false, 'message' => 'Email déjà utilisé.'], 409);
        }

        $roleEmploye = $roleRepository->findOneBy(['libelle' => 'employe']);
        if (!$roleEmploye) {
            return $this->json(['success' => false, 'message' => 'Rôle employé introuvable.'], 500);
        }

        $user = new Utilisateur();
        $user->setEmail($email);
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setRole($roleEmploye);
        $user->setRoles(['ROLE_EMPLOYE']);

        $em->persist($user);
        $em->flush();

        try {
            $emailMsg = (new Email())
                ->from('maxnabil2ait@gmail.com')
                ->to($email)
                ->subject('Votre compte employé Vite & Gourmand')
                ->html('<h2>Bienvenue dans l\'équipe !</h2><p>Un compte a été créé pour vous.</p>');
            $mailer->send($emailMsg);
        } catch (\Exception $e) {}

        return $this->json(['success' => true, 'message' => 'Employé créé.'], 201);
    }

    #[Route('/utilisateurs/{id}/toggle', name: 'api_admin_toggle_user', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Activer / désactiver un compte',
        description: 'Bascule un compte entre actif et désactivé. Ne fonctionne pas sur les comptes administrateurs.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Compte activé ou désactivé'),
            new OA\Response(response: 403, description: 'Impossible de modifier un admin')
        ]
    )]
    public function toggleUser(
        Utilisateur $user,
        EntityManagerInterface $em,
        RoleRepository $roleRepository
    ): JsonResponse {
        if ($user->getRole() && $user->getRole()->getLibelle() === 'administrateur') {
            return $this->json(['success' => false, 'message' => 'Impossible de modifier un admin.'], 403);
        }

        if ($user->getRole() && $user->getRole()->getLibelle() === 'desactive') {
            $roleEmploye = $roleRepository->findOneBy(['libelle' => 'employe']);
            if ($roleEmploye) {
                $user->setRole($roleEmploye);
                $user->setRoles(['ROLE_EMPLOYE']);
            }
            $em->flush();
            return $this->json(['success' => true, 'message' => 'Compte réactivé.']);
        }

        $roleDesactive = $roleRepository->findOneBy(['libelle' => 'desactive']);
        if (!$roleDesactive) {
            $roleDesactive = new \App\Entity\Role();
            $roleDesactive->setLibelle('desactive');
            $em->persist($roleDesactive);
        }

        $user->setRole($roleDesactive);
        $user->setRoles([]);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Compte désactivé.']);
    }

    // =========================================================
    // COMMANDES
    // =========================================================

    #[Route('/commandes', name: 'api_admin_commandes', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste des commandes',
        description: 'Retourne toutes les commandes avec filtres optionnels.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'statut', in: 'query', required: false, description: 'Filtrer par statut (insensible à la casse)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'dateDebut', in: 'query', required: false, description: 'Date de prestation minimum', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'dateFin', in: 'query', required: false, description: 'Date de prestation maximum', schema: new OA\Schema(type: 'string', format: 'date'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des commandes')
        ]
    )]
    public function commandes(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $qb = $em->getRepository(Commande::class)->createQueryBuilder('c')
            ->leftJoin('c.utilisateur', 'u')
            ->leftJoin('c.menu', 'm')
            ->orderBy('c.date_commande', 'DESC');

        $statut = strtolower(trim($request->query->get('statut', '')));
        if ($statut !== '') {
            $qb->andWhere('LOWER(c.statut) = :statut')->setParameter('statut', $statut);
        }

        $dateDebut = $request->query->get('dateDebut', '');
        if ($dateDebut) {
            try {
                $qb->andWhere('c.date_prestation >= :dateDebut')
                   ->setParameter('dateDebut', new \DateTime($dateDebut));
            } catch (\Exception $e) {}
        }

        $dateFin = $request->query->get('dateFin', '');
        if ($dateFin) {
            try {
                $qb->andWhere('c.date_prestation <= :dateFin')
                   ->setParameter('dateFin', (new \DateTime($dateFin))->setTime(23, 59, 59));
            } catch (\Exception $e) {}
        }

        $result = [];
        foreach ($qb->getQuery()->getResult() as $c) {
            $result[] = [
                'id'               => $c->getId(),
                'numero_commande'  => $c->getNumeroCommande(),
                'date_commande'    => $c->getDateCommande()?->format('Y-m-d H:i'),
                'date_prestation'  => $c->getDatePrestation()?->format('Y-m-d'),
                'lieu_prestation'  => $c->getLieuPrestation() ?? '',
                'nombre_personne'  => $c->getNombrePersonne(),
                'prix_total'       => $c->getPrixMenu() + $c->getPrixLivraison(),
                'statut'           => $c->getStatut(),
                'client_email'     => $c->getUtilisateur() ? $c->getUtilisateur()->getEmail() : '',
                'menu_titre'       => $c->getMenu() ? $c->getMenu()->getTitre() : '',
            ];
        }

        return $this->json(['success' => true, 'commandes' => $result]);
    }

    // =========================================================
    // MENUS
    // =========================================================

    #[Route('/menus', name: 'api_admin_menu_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Créer un menu',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['titre', 'prix_par_personne'],
                properties: [
                    new OA\Property(property: 'titre', type: 'string', example: 'Menu Prestige'),
                    new OA\Property(property: 'prix_par_personne', type: 'number', example: 35.50),
                    new OA\Property(property: 'nombre_personne_minimum', type: 'integer', example: 10),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'conditions', type: 'string'),
                    new OA\Property(property: 'quantite_restante', type: 'integer'),
                    new OA\Property(property: 'theme', type: 'string', description: 'Libellé du thème'),
                    new OA\Property(property: 'regime', type: 'string', description: 'Libellé du régime')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Menu créé'),
            new OA\Response(response: 400, description: 'Titre ou prix manquant')
        ]
    )]
    public function createMenu(
        Request $request,
        EntityManagerInterface $em,
        ThemeRepository $themeRepo,
        RegimeRepository $regimeRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        return $this->saveMenu(null, $data, $em, $themeRepo, $regimeRepo);
    }

    #[Route('/menus/{id}', name: 'api_admin_menu_update', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Modifier un menu',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'titre', type: 'string'),
                    new OA\Property(property: 'prix_par_personne', type: 'number'),
                    new OA\Property(property: 'nombre_personne_minimum', type: 'integer'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'conditions', type: 'string'),
                    new OA\Property(property: 'quantite_restante', type: 'integer'),
                    new OA\Property(property: 'theme', type: 'string'),
                    new OA\Property(property: 'regime', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Menu modifié'),
            new OA\Response(response: 404, description: 'Menu introuvable')
        ]
    )]
    public function updateMenu(
        Menu $menu,
        Request $request,
        EntityManagerInterface $em,
        ThemeRepository $themeRepo,
        RegimeRepository $regimeRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        return $this->saveMenu($menu, $data, $em, $themeRepo, $regimeRepo);
    }

    #[Route('/menus/{id}', name: 'api_admin_menu_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Supprimer un menu',
        description: 'Supprime un menu. Impossible si des commandes y sont associées.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Menu supprimé'),
            new OA\Response(response: 400, description: 'Commandes associées')
        ]
    )]
    public function deleteMenu(Menu $menu, EntityManagerInterface $em): JsonResponse
    {
        if ($menu->getCommandes()->count() > 0) {
            return $this->json(['success' => false, 'message' => 'Ce menu a des commandes associées.'], 400);
        }
        $em->remove($menu);
        $em->flush();
        return $this->json(['success' => true, 'message' => 'Menu supprimé.']);
    }

    private function saveMenu(
        ?Menu $menu,
        array $data,
        EntityManagerInterface $em,
        ThemeRepository $themeRepo,
        RegimeRepository $regimeRepo
    ): JsonResponse {
        $titre = strip_tags(trim($data['titre'] ?? ''));
        $prix  = isset($data['prix_par_personne']) ? (float) $data['prix_par_personne'] : null;
        $minP  = isset($data['nombre_personne_minimum']) ? (int) $data['nombre_personne_minimum'] : null;

        if (empty($titre) || $prix === null || $prix <= 0) {
            return $this->json(['success' => false, 'message' => 'Titre et prix obligatoires.'], 400);
        }

        if ($menu === null) {
            $menu = new Menu();
        }

        $menu->setTitre($titre);
        $menu->setPrixParPersonne($prix);

        if ($minP !== null && $minP > 0) {
            $menu->setNombrePersonneMinimum($minP);
        }

        if (isset($data['description'])) {
            $menu->setDescription(strip_tags(trim($data['description'])));
        }

        if (isset($data['conditions'])) {
            $menu->setConditions(strip_tags(trim($data['conditions'])));
        }

        if (isset($data['quantite_restante'])) {
            $menu->setQuantiteRestante((int) $data['quantite_restante']);
        }

        if (!empty($data['theme'])) {
            $theme = $themeRepo->findOneBy(['libelle' => $data['theme']]);
            if ($theme) {
                $menu->setTheme($theme);
            }
        }

        if (!empty($data['regime'])) {
            $regime = $regimeRepo->findOneBy(['libelle' => $data['regime']]);
            if ($regime) {
                $menu->setRegime($regime);
            }
        }

        $em->persist($menu);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Menu enregistré.', 'id' => $menu->getId()]);
    }

    // =========================================================
    // IMAGES DE MENUS
    // =========================================================

    #[Route('/menus/{id}/images', name: 'api_admin_menu_image_add', methods: ['POST'])]
    #[OA\Post(
        summary: 'Ajouter une image à un menu',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['url_image'],
                properties: [
                    new OA\Property(property: 'url_image', type: 'string', format: 'url', example: 'https://example.com/image.jpg')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Image ajoutée'),
            new OA\Response(response: 400, description: 'URL invalide')
        ]
    )]
    public function addMenuImage(
        Menu $menu,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $url = strip_tags(trim($data['url_image'] ?? ''));

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->json(['success' => false, 'message' => 'URL invalide.'], 400);
        }

        $image = new MenuImage();
        $image->setUrlImage($url);
        $image->setMenu($menu);

        $em->persist($image);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Image ajoutée.',
            'image' => ['id' => $image->getId(), 'url_image' => $image->getUrlImage()]
        ], 201);
    }

    #[Route('/menus/{menuId}/images/{imageId}', name: 'api_admin_menu_image_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Supprimer une image d\'un menu',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'menuId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'imageId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Image supprimée'),
            new OA\Response(response: 404, description: 'Image introuvable')
        ]
    )]
    public function deleteMenuImage(
        int $menuId,
        int $imageId,
        EntityManagerInterface $em
    ): JsonResponse {
        $image = $em->getRepository(MenuImage::class)->find($imageId);

        if (!$image || $image->getMenu()->getId() !== $menuId) {
            return $this->json(['success' => false, 'message' => 'Image introuvable.'], 404);
        }

        $em->remove($image);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Image supprimée.']);
    }

    // =========================================================
    // PLATS
    // =========================================================

    #[Route('/menus/{id}/plats', name: 'api_admin_menu_plat_add', methods: ['POST'])]
    #[OA\Post(
        summary: 'Ajouter un plat à un menu',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['titre_plat'],
                properties: [
                    new OA\Property(property: 'titre_plat', type: 'string', example: 'Filet de boeuf sauce bordelaise'),
                    new OA\Property(property: 'allergenes', type: 'array', items: new OA\Items(type: 'integer'), description: 'IDs des allergènes')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Plat ajouté'),
            new OA\Response(response: 400, description: 'Titre manquant')
        ]
    )]
    public function addMenuPlat(
        Menu $menu,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $titre = strip_tags(trim($data['titre_plat'] ?? ''));

        if (empty($titre)) {
            return $this->json(['success' => false, 'message' => 'Titre du plat obligatoire.'], 400);
        }

        $plat = new Plat();
        $plat->setTitrePlat($titre);

        if (!empty($data['allergenes']) && is_array($data['allergenes'])) {
            foreach ($data['allergenes'] as $allergeneId) {
                $allergene = $em->getRepository(Allergene::class)->find((int) $allergeneId);
                if ($allergene) {
                    $plat->addAllergene($allergene);
                }
            }
        }

        $em->persist($plat);
        $menu->addPlat($plat);
        $em->flush();

        $allergenesList = [];
        foreach ($plat->getAllergenes() as $a) {
            $allergenesList[] = ['id' => $a->getId(), 'libelle' => $a->getLIBELLE()];
        }

        return $this->json([
            'success' => true,
            'plat' => [
                'id'        => $plat->getId(),
                'titre_plat' => $plat->getTitrePlat(),
                'allergenes' => $allergenesList,
            ]
        ], 201);
    }

    #[Route('/menus/{menuId}/plats/{platId}', name: 'api_admin_menu_plat_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Retirer un plat d\'un menu',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'menuId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'platId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Plat retiré'),
            new OA\Response(response: 404, description: 'Menu ou plat introuvable')
        ]
    )]
    public function deleteMenuPlat(
        int $menuId,
        int $platId,
        EntityManagerInterface $em
    ): JsonResponse {
        $menu = $em->getRepository(Menu::class)->find($menuId);
        $plat = $em->getRepository(Plat::class)->find($platId);

        if (!$menu || !$plat) {
            return $this->json(['success' => false, 'message' => 'Menu ou plat introuvable.'], 404);
        }

        $menu->removePlat($plat);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Plat retiré du menu.']);
    }

    #[Route('/menus/{menuId}/plats/{platId}', name: 'api_admin_menu_plat_update', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Modifier un plat',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'menuId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'platId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'titre_plat', type: 'string'),
                    new OA\Property(property: 'allergenes', type: 'array', items: new OA\Items(type: 'integer'), description: 'Remplace tous les allergènes')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Plat modifié'),
            new OA\Response(response: 404, description: 'Plat introuvable')
        ]
    )]
    public function updateMenuPlat(
        int $menuId,
        int $platId,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $plat = $em->getRepository(Plat::class)->find($platId);

        if (!$plat) {
            return $this->json(['success' => false, 'message' => 'Plat introuvable.'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!empty($data['titre_plat'])) {
            $titre = strip_tags(trim($data['titre_plat']));
            if (!empty($titre)) {
                $plat->setTitrePlat($titre);
            }
        }

        if (array_key_exists('allergenes', $data) && is_array($data['allergenes'])) {
            foreach ($plat->getAllergenes() as $a) {
                $plat->removeAllergene($a);
            }
            foreach ($data['allergenes'] as $allergeneId) {
                $allergene = $em->getRepository(Allergene::class)->find((int) $allergeneId);
                if ($allergene) {
                    $plat->addAllergene($allergene);
                }
            }
        }

        $em->flush();

        $allergenesList = [];
        foreach ($plat->getAllergenes() as $a) {
            $allergenesList[] = ['id' => $a->getId(), 'libelle' => $a->getLIBELLE()];
        }

        return $this->json([
            'success' => true,
            'plat' => [
                'id'         => $plat->getId(),
                'titre_plat' => $plat->getTitrePlat(),
                'allergenes' => $allergenesList,
            ]
        ]);
    }

    // =========================================================
    // AVIS
    // =========================================================

    #[Route('/avis', name: 'api_admin_avis', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste de tous les avis',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des avis')
        ]
    )]
    public function avisList(EntityManagerInterface $em): JsonResponse
    {
        $avisList = $em->getRepository(Avis::class)->findBy([], ['id' => 'DESC']);

        $result = [];
        foreach ($avisList as $a) {
            $result[] = [
                'id'          => $a->getId(),
                'note'        => $a->getNote(),
                'description' => $a->getDescription() ?? '',
                'statut'      => $a->getStatut(),
                'client'      => $a->getUtilisateur()
                    ? $a->getUtilisateur()->getPrenom() . ' ' . $a->getUtilisateur()->getNom()
                    : '',
                'commande'    => $a->getCommande() ? $a->getCommande()->getNumeroCommande() : '',
            ];
        }

        return $this->json(['success' => true, 'avis' => $result]);
    }

    #[Route('/avis/{id}/statut', name: 'api_admin_avis_statut', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Valider ou refuser un avis',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['statut'],
                properties: [
                    new OA\Property(property: 'statut', type: 'string', enum: ['validé', 'refusé'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Avis mis à jour'),
            new OA\Response(response: 400, description: 'Statut invalide')
        ]
    )]
    public function updateAvisStatut(
        Avis $avis,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $statut = trim($data['statut'] ?? '');

        if (!in_array($statut, ['validé', 'refusé'])) {
            return $this->json(['success' => false, 'message' => 'Statut invalide.'], 400);
        }

        $avis->setStatut($statut);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Avis ' . $statut . '.']);
    }

    // =========================================================
    // STATISTIQUES
    // =========================================================

    #[Route('/stats', name: 'api_admin_stats', methods: ['GET'])]
    #[OA\Get(
        summary: 'Statistiques',
        description: 'Retourne les statistiques globales : nombre d\'utilisateurs, menus, chiffre d\'affaires et commandes par menu. Données agrégées depuis MongoDB.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'menu', in: 'query', required: false, description: 'Filtrer par titre de menu', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date_debut', in: 'query', required: false, description: 'Date de début (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_fin', in: 'query', required: false, description: 'Date de fin (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Statistiques avec CA, commandes par menu, etc.')
        ]
    )]
    public function stats(
        Request $request,
        MongoDbService $mongoDbService,
        UtilisateurRepository $utilisateurRepo,
        MenuRepository $menuRepo
    ): JsonResponse {
        $menuTitre = $request->query->get('menu');
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');

        $caParMenu = $mongoDbService->getChiffreAffaires(
            $menuTitre ?: null,
            $dateDebut ?: null,
            $dateFin ?: null
        );

        $commandesParMenu = $mongoDbService->getCommandesParMenu();
        $menusList = $mongoDbService->getMenusList();

        $nbUtilisateurs = count($utilisateurRepo->findAll());
        $nbMenus = count($menuRepo->findAll());

        $totalCA = 0.0;
        $totalCommandes = 0;
        foreach ($caParMenu as $ligne) {
            $totalCA += $ligne['chiffre_affaires'] ?? 0;
            $totalCommandes += $ligne['total_commandes'] ?? 0;
        }

        return $this->json([
            'success'            => true,
            'utilisateurs'       => $nbUtilisateurs,
            'nb_menus'           => $nbMenus,
            'chiffre_affaires'   => [
                'total'            => round($totalCA, 2),
                'chiffre_affaires' => round($totalCA, 2),
                'nombre_commandes' => $totalCommandes,
                'nb_commandes'     => $totalCommandes,
                'detail_par_menu'  => $caParMenu,
            ],
            'commandes_par_menu' => $commandesParMenu,
            'menus_list'         => $menusList,
        ]);
    }
}
