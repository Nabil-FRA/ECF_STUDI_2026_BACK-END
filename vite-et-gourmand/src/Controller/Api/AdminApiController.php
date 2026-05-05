<?php

namespace App\Controller\Api;

use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\Menu;
use App\Entity\MenuImage;
use App\Entity\Utilisateur;
use App\Repository\MenuRepository;
use App\Repository\RegimeRepository;
use App\Repository\RoleRepository;
use App\Repository\ThemeRepository;
use App\Repository\UtilisateurRepository;
use App\Service\MongoDbService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller API pour l'espace administrateur.
 * 
 * SÉCURITÉ :
 * - Toutes les routes nécessitent ROLE_ADMIN
 * - L'admin ne peut pas créer d'autre admin via l'API
 * - Sanitisation de toutes les entrées
 * - Validation mot de passe fort
 */
#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminApiController extends AbstractController
{
    /**
     * GET /api/admin/utilisateurs - Liste des utilisateurs (sauf admin)
     */
    #[Route('/utilisateurs', name: 'api_admin_utilisateurs', methods: ['GET'])]
    public function utilisateurs(UtilisateurRepository $repo): JsonResponse
    {
        $users = $repo->findAll();
        $result = [];

        foreach ($users as $u) {
            // Ne pas lister les administrateurs
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

    /**
     * POST /api/admin/employes - Créer un employé
     */
    #[Route('/employes', name: 'api_admin_create_employe', methods: ['POST'])]
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

        // Validation mot de passe fort
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
                ->from('noreply@viteetgourmand.fr')
                ->to($email)
                ->subject('Votre compte employé Vite & Gourmand')
                ->html('<h2>Bienvenue dans l\'équipe !</h2><p>Un compte a été créé pour vous.</p>');
            $mailer->send($emailMsg);
        } catch (\Exception $e) {}

        return $this->json(['success' => true, 'message' => 'Employé créé.'], 201);
    }

    /**
     * PUT /api/admin/utilisateurs/{id}/toggle - Activer/désactiver un compte
     */
    #[Route('/utilisateurs/{id}/toggle', name: 'api_admin_toggle_user', methods: ['PUT'])]
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
        if ($roleDesactive) {
            $user->setRole($roleDesactive);
            $user->setRoles([]);
        }
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Compte désactivé.']);
    }

    /**
     * GET /api/admin/commandes - Liste toutes les commandes (avec filtres optionnels)
     */
    #[Route('/commandes', name: 'api_admin_commandes', methods: ['GET'])]
    public function commandes(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $qb = $em->getRepository(Commande::class)->createQueryBuilder('c')
            ->leftJoin('c.utilisateur', 'u')
            ->leftJoin('c.menu', 'm')
            ->orderBy('c.date_commande', 'DESC');

        // Filtre statut insensible à la casse (LOWER des deux côtés)
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

    /**
     * POST /api/admin/menus - Créer un menu
     */
    #[Route('/menus', name: 'api_admin_menu_create', methods: ['POST'])]
    public function createMenu(
        Request $request,
        EntityManagerInterface $em,
        ThemeRepository $themeRepo,
        RegimeRepository $regimeRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        return $this->saveMenu(null, $data, $em, $themeRepo, $regimeRepo);
    }

    /**
     * PUT /api/admin/menus/{id} - Modifier un menu
     */
    #[Route('/menus/{id}', name: 'api_admin_menu_update', methods: ['PUT'])]
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

    /**
     * DELETE /api/admin/menus/{id} - Supprimer un menu
     */
    #[Route('/menus/{id}', name: 'api_admin_menu_delete', methods: ['DELETE'])]
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

        // Thème
        if (!empty($data['theme'])) {
            $theme = $themeRepo->findOneBy(['libelle' => $data['theme']]);
            if ($theme) {
                $menu->setTheme($theme);
            }
        }

        // Régime
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

    /**
     * POST /api/admin/menus/{id}/images - Ajouter une image (URL) à un menu
     */
    #[Route('/menus/{id}/images', name: 'api_admin_menu_image_add', methods: ['POST'])]
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

    /**
     * DELETE /api/admin/menus/{menuId}/images/{imageId} - Supprimer une image d'un menu
     */
    #[Route('/menus/{menuId}/images/{imageId}', name: 'api_admin_menu_image_delete', methods: ['DELETE'])]
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

    /**
     * GET /api/admin/avis - Liste de tous les avis
     */
    #[Route('/avis', name: 'api_admin_avis', methods: ['GET'])]
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

    /**
     * PUT /api/admin/avis/{id}/statut - Valider ou refuser un avis
     */
    #[Route('/avis/{id}/statut', name: 'api_admin_avis_statut', methods: ['PUT'])]
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

    /**
     * GET /api/admin/stats - Statistiques (chiffre d'affaires)
     */
    #[Route('/stats', name: 'api_admin_stats', methods: ['GET'])]
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

        // Compteurs depuis MySQL (sources fiables)
        $nbUtilisateurs = count($utilisateurRepo->findAll());
        $nbMenus = count($menuRepo->findAll());

        // Calcul des totaux globaux depuis les données MongoDB
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
