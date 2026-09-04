<?php

namespace App\Controller\Api;

use App\Entity\Avis;
use App\Entity\Commande;
use App\Repository\MenuRepository;
use App\Security\ApiTokenAuthenticator;
use App\Service\MongoDbService;
use App\Service\TarificationService;
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

#[Route('/api/user')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Utilisateur')]
class UserApiController extends AbstractController
{
    #[Route('/profile', name: 'api_user_profile', methods: ['GET'])]
    #[OA\Get(
        summary: 'Mon profil',
        description: 'Retourne les informations du profil de l\'utilisateur connecté.',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Profil utilisateur'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function profile(): JsonResponse
    {
        $user = $this->getUser();
        return $this->json([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'telephone' => $user->getTelephone(),
                'ville' => $user->getVille(),
                'pays' => $user->getPays(),
                'adresse_postale' => $user->getAdressePostale(),
                'role' => $user->getRole() ? $user->getRole()->getLibelle() : null,
            ],
        ]);
    }

    #[Route('/profile', name: 'api_user_profile_update', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Modifier mon profil',
        description: 'Met à jour les informations personnelles. Seuls les champs envoyés sont modifiés.',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'telephone', type: 'string', example: '06 12 34 56 78'),
                    new OA\Property(property: 'ville', type: 'string', example: 'Bordeaux'),
                    new OA\Property(property: 'pays', type: 'string', example: 'France'),
                    new OA\Property(property: 'adresse_postale', type: 'string', example: '12 rue de la Paix, 33000 Bordeaux')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour'),
            new OA\Response(response: 400, description: 'Téléphone invalide'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function updateProfile(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (isset($data['nom'])) {
            $user->setNom($this->sanitize($data['nom']));
        }
        if (isset($data['prenom'])) {
            $user->setPrenom($this->sanitize($data['prenom']));
        }
        if (isset($data['telephone'])) {
            $tel = $this->sanitize($data['telephone']);
            if (!empty($tel) && !preg_match('/^[\d\s\+\-\.()]{6,20}$/', $tel)) {
                return $this->json(['success' => false, 'message' => 'Téléphone invalide.'], 400);
            }
            $user->setTelephone($tel ?: null);
        }
        if (isset($data['ville'])) {
            $user->setVille($this->sanitize($data['ville']) ?: null);
        }
        if (isset($data['pays'])) {
            $user->setPays($this->sanitize($data['pays']) ?: null);
        }
        if (isset($data['adresse_postale'])) {
            $user->setAdressePostale($this->sanitize($data['adresse_postale']) ?: null);
        }

        $em->flush();

        return $this->json(['success' => true, 'message' => 'Profil mis à jour.']);
    }

    #[Route('/commandes', name: 'api_user_commandes', methods: ['GET'])]
    #[OA\Get(
        summary: 'Mes commandes',
        description: 'Retourne toutes les commandes de l\'utilisateur connecté, triées par date décroissante.',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des commandes'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function commandes(): JsonResponse
    {
        $user = $this->getUser();
        $result = [];

        foreach ($user->getCommandes() as $commande) {
            $result[] = $this->serializeCommande($commande);
        }

        usort($result, fn($a, $b) => strcmp($b['date_commande'] ?? '', $a['date_commande'] ?? ''));

        return $this->json(['success' => true, 'commandes' => $result]);
    }

    #[Route('/commandes/{id}', name: 'api_user_commande_detail', methods: ['GET'])]
    #[OA\Get(
        summary: 'Détail d\'une commande',
        description: 'Retourne le détail d\'une commande avec son suivi (historique des statuts depuis MongoDB).',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail de la commande avec suivi'),
            new OA\Response(response: 403, description: 'Commande d\'un autre utilisateur'),
            new OA\Response(response: 404, description: 'Commande introuvable')
        ]
    )]
    public function commandeDetail(Commande $commande, MongoDbService $mongoDbService): JsonResponse
    {
        if ($commande->getUtilisateur() !== $this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Accès interdit.'], 403);
        }

        $suivi = $mongoDbService->getSuivi($commande->getNumeroCommande());

        $data = $this->serializeCommande($commande);
        $data['suivi'] = $suivi;

        return $this->json(['success' => true, 'commande' => $data]);
    }

    #[Route('/commandes', name: 'api_user_commande_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Créer une commande',
        description: 'Crée une nouvelle commande. Le prix est calculé automatiquement (réduction 10% si +5 personnes au-dessus du minimum). Livraison gratuite à Bordeaux, sinon forfait 5€ + 0.59€/km (le forfait reste dû si la distance n\'est pas fournie). Zone : Gironde uniquement (CP 33xxx).',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['menu_id', 'nombre_personne', 'date_prestation', 'lieu_prestation', 'heure_livraison'],
                properties: [
                    new OA\Property(property: 'menu_id', type: 'integer', example: 1),
                    new OA\Property(property: 'nombre_personne', type: 'integer', example: 15),
                    new OA\Property(property: 'date_prestation', type: 'string', format: 'date', example: '2026-07-15'),
                    new OA\Property(property: 'lieu_prestation', type: 'string', example: '25 rue Sainte-Catherine, 33000 Bordeaux'),
                    new OA\Property(property: 'heure_livraison', type: 'string', example: '12:00'),
                    new OA\Property(property: 'pret_materiel', type: 'boolean', example: true),
                    new OA\Property(property: 'distance_km', type: 'integer', description: 'Distance depuis Bordeaux en km. Ignorée pour une adresse bordelaise, requise ailleurs pour la part kilométrique.', example: 12)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Commande créée avec succès'),
            new OA\Response(response: 400, description: 'Validation échouée (champs manquants, minimum personnes, date passée, hors zone)'),
            new OA\Response(response: 404, description: 'Menu introuvable')
        ]
    )]
    public function createCommande(
        Request $request,
        MenuRepository $menuRepository,
        EntityManagerInterface $em,
        MongoDbService $mongoDbService,
        MailerInterface $mailer,
        TarificationService $tarification
    ): JsonResponse {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $menuId = (int) ($data['menu_id'] ?? 0);
        $nbPersonnes = (int) ($data['nombre_personne'] ?? 0);
        $datePrestation = $this->sanitize($data['date_prestation'] ?? '');
        $lieuPrestation = $this->sanitize($data['lieu_prestation'] ?? '');
        $heureLivraison = $this->sanitize($data['heure_livraison'] ?? '');
        $pretMateriel = (bool) ($data['pret_materiel'] ?? false);
        $distanceKm = (int) ($data['distance_km'] ?? 0);

        if ($menuId <= 0 || $nbPersonnes <= 0 || empty($datePrestation) || empty($lieuPrestation) || empty($heureLivraison)) {
            return $this->json([
                'success' => false,
                'message' => 'Tous les champs obligatoires doivent être remplis.'
            ], 400);
        }

        $menu = $menuRepository->find($menuId);
        if (!$menu) {
            return $this->json(['success' => false, 'message' => 'Menu introuvable.'], 404);
        }

        if ($nbPersonnes < $menu->getNombrePersonneMinimum()) {
            return $this->json([
                'success' => false,
                'message' => 'Minimum ' . $menu->getNombrePersonneMinimum() . ' personnes pour ce menu.'
            ], 400);
        }

        if ($menu->getQuantiteRestante() !== null && $menu->getQuantiteRestante() <= 0) {
            return $this->json(['success' => false, 'message' => 'Ce menu n\'est plus disponible.'], 400);
        }

        $datePrest = \DateTime::createFromFormat('Y-m-d', $datePrestation);
        if (!$datePrest || $datePrest < new \DateTime('today')) {
            return $this->json(['success' => false, 'message' => 'Date de prestation invalide.'], 400);
        }

        if ($tarification->estHorsZoneDeLivraison($lieuPrestation)) {
            return $this->json([
                'success' => false,
                'message' => 'Nous livrons uniquement en Gironde (département 33). Votre adresse est hors de notre zone de livraison.'
            ], 400);
        }

        // La distance ne sert qu'en dehors de la zone gratuite.
        if ($tarification->estZoneGratuite($lieuPrestation)) {
            $distanceKm = null;
        }

        $prix = $tarification->calculer($menu, $nbPersonnes, $lieuPrestation, $distanceKm);
        $prixMenu = $prix['prix_menu'];
        $prixLivraison = $prix['prix_livraison'];

        $numeroCommande = 'CMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

        $commande = new Commande();
        $commande->setNumeroCommande($numeroCommande);
        $commande->setDateCommande(new \DateTime());
        $commande->setDatePrestation($datePrest);
        $commande->setLieuPrestation($lieuPrestation);
        $commande->setHeureLivraison($heureLivraison);
        $commande->setNombrePersonne($nbPersonnes);
        $commande->setDistanceKm($distanceKm);
        $commande->setPrixMenu($prixMenu);
        $commande->setPrixLivraison($prixLivraison);
        $commande->setStatut('en cours');
        $commande->setPretMateriel($pretMateriel);
        $commande->setRestitutionMateriel(false);
        $commande->setUtilisateur($user);
        $commande->setMenu($menu);

        if ($menu->getQuantiteRestante() !== null) {
            $menu->setQuantiteRestante($menu->getQuantiteRestante() - 1);
        }

        $em->persist($commande);
        $em->flush();

        $mongoDbService->syncCommande([
            'numero_commande' => $numeroCommande,
            'menu_titre' => $menu->getTitre(),
            'nombre_personne' => $nbPersonnes,
            'prix_menu' => $prixMenu,
            'prix_livraison' => $prixLivraison,
            'prix_total' => $prix['prix_total'],
            'date_commande' => date('Y-m-d'),
            'statut' => 'en cours',
            'client_email' => $user->getEmail(),
        ]);
        $mongoDbService->ajouterSuivi($numeroCommande, 'en cours');

        try {
            $total = $prix['prix_total'];
            $emailMsg = (new Email())
                ->from('maxnabil2ait@gmail.com')
                ->to($user->getEmail())
                ->subject('Confirmation de commande ' . $numeroCommande)
                ->html(
                    '<h2>Commande confirmée !</h2>' .
                    '<p>Bonjour ' . htmlspecialchars($user->getPrenom()) . ',</p>' .
                    '<p>Votre commande <strong>' . $numeroCommande . '</strong> a bien été enregistrée.</p>' .
                    '<p><strong>Total : ' . number_format($total, 2, ',', ' ') . ' €</strong></p>'
                );
            $mailer->send($emailMsg);
        } catch (\Exception $e) {}

        return $this->json([
            'success' => true,
            'message' => 'Commande créée.',
            'commande' => $this->serializeCommande($commande),
        ], 201);
    }

    #[Route('/commandes/{id}', name: 'api_user_commande_update', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Modifier une commande',
        description: 'Modifie une commande existante. Uniquement possible si le statut est « en cours ». Le menu ne peut pas être changé.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nombre_personne', type: 'integer', example: 20),
                    new OA\Property(property: 'date_prestation', type: 'string', format: 'date', example: '2026-08-01'),
                    new OA\Property(property: 'lieu_prestation', type: 'string', example: '10 place Gambetta, 33000 Bordeaux'),
                    new OA\Property(property: 'heure_livraison', type: 'string', example: '13:00'),
                    new OA\Property(property: 'pret_materiel', type: 'boolean'),
                    new OA\Property(property: 'distance_km', type: 'integer', description: 'Facultatif : la distance enregistrée à la commande est réutilisée si elle est omise.')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Commande modifiée'),
            new OA\Response(response: 400, description: 'Modification impossible (statut != en cours) / validation échouée'),
            new OA\Response(response: 403, description: 'Commande d\'un autre utilisateur')
        ]
    )]
    public function updateCommande(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em,
        MongoDbService $mongoDbService,
        TarificationService $tarification
    ): JsonResponse {
        if ($commande->getUtilisateur() !== $this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Accès interdit.'], 403);
        }

        if ($commande->getStatut() !== 'en cours') {
            return $this->json([
                'success' => false,
                'message' => 'Modification impossible : la commande a déjà été acceptée.'
            ], 400);
        }

        $data = json_decode($request->getContent(), true);
        $menu = $commande->getMenu();

        if (isset($data['nombre_personne'])) {
            $nbPersonnes = (int) $data['nombre_personne'];
            if ($nbPersonnes < $menu->getNombrePersonneMinimum()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Minimum ' . $menu->getNombrePersonneMinimum() . ' personnes pour ce menu.'
                ], 400);
            }
            $commande->setNombrePersonne($nbPersonnes);
        }

        if (isset($data['date_prestation'])) {
            $datePrest = \DateTime::createFromFormat('Y-m-d', $this->sanitize($data['date_prestation']));
            if (!$datePrest || $datePrest < new \DateTime('today')) {
                return $this->json(['success' => false, 'message' => 'Date de prestation invalide.'], 400);
            }
            $commande->setDatePrestation($datePrest);
        }

        if (isset($data['lieu_prestation'])) {
            $lieu = $this->sanitize($data['lieu_prestation']);

            if ($tarification->estHorsZoneDeLivraison($lieu)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Nous livrons uniquement en Gironde (département 33). Votre adresse est hors de notre zone de livraison.'
                ], 400);
            }

            $commande->setLieuPrestation($lieu);
        }

        if (isset($data['distance_km'])) {
            $commande->setDistanceKm((int) $data['distance_km']);
        }

        if (isset($data['heure_livraison'])) {
            $commande->setHeureLivraison($this->sanitize($data['heure_livraison']));
        }

        if (isset($data['pret_materiel'])) {
            $commande->setPretMateriel((bool) $data['pret_materiel']);
        }

        // Recalcul unique à partir de l'état final de la commande : la distance
        // enregistrée est réutilisée si le client ne la renvoie pas, ce qui
        // évite de perdre la part kilométrique à chaque modification.
        if ($tarification->estZoneGratuite($commande->getLieuPrestation())) {
            $commande->setDistanceKm(null);
        }

        $prix = $tarification->calculer(
            $menu,
            $commande->getNombrePersonne(),
            $commande->getLieuPrestation(),
            $commande->getDistanceKm()
        );
        $commande->setPrixMenu($prix['prix_menu']);
        $commande->setPrixLivraison($prix['prix_livraison']);

        $em->flush();

        $mongoDbService->syncCommande([
            'numero_commande' => $commande->getNumeroCommande(),
            'menu_titre'      => $menu->getTitre(),
            'nombre_personne' => $commande->getNombrePersonne(),
            'prix_menu'       => $commande->getPrixMenu(),
            'prix_livraison'  => $commande->getPrixLivraison(),
            'prix_total'      => $commande->getPrixTotal(),
            'date_commande'   => $commande->getDateCommande()?->format('Y-m-d') ?? date('Y-m-d'),
            'statut'          => $commande->getStatut(),
            'client_email'    => $commande->getUtilisateur()->getEmail(),
        ]);

        return $this->json([
            'success'  => true,
            'message'  => 'Commande modifiée.',
            'commande' => $this->serializeCommande($commande),
        ]);
    }

    #[Route('/commandes/{id}/annuler', name: 'api_user_commande_annuler', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Annuler une commande',
        description: 'Annule une commande. Uniquement possible si le statut est « en cours ». La quantité restante du menu est restaurée.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Commande annulée'),
            new OA\Response(response: 400, description: 'Annulation impossible (statut != en cours)'),
            new OA\Response(response: 403, description: 'Commande d\'un autre utilisateur')
        ]
    )]
    public function annulerCommande(
        Commande $commande,
        EntityManagerInterface $em,
        MongoDbService $mongoDbService
    ): JsonResponse {
        if ($commande->getUtilisateur() !== $this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Accès interdit.'], 403);
        }

        if ($commande->getStatut() !== 'en cours') {
            return $this->json(['success' => false, 'message' => 'Cette commande ne peut plus être annulée.'], 400);
        }

        $menu = $commande->getMenu();
        if ($menu->getQuantiteRestante() !== null) {
            $menu->setQuantiteRestante($menu->getQuantiteRestante() + 1);
        }

        $commande->setStatut('annulée');
        $em->flush();

        $mongoDbService->syncCommande([
            'numero_commande' => $commande->getNumeroCommande(),
            'menu_titre' => $menu->getTitre(),
            'nombre_personne' => $commande->getNombrePersonne(),
            'prix_menu' => $commande->getPrixMenu(),
            'prix_livraison' => $commande->getPrixLivraison(),
            'prix_total' => $commande->getPrixTotal(),
            'date_commande' => $commande->getDateCommande() ? $commande->getDateCommande()->format('Y-m-d') : date('Y-m-d'),
            'statut' => 'annulée',
            'client_email' => $commande->getUtilisateur()->getEmail(),
        ]);
        $mongoDbService->ajouterSuivi($commande->getNumeroCommande(), 'annulée');

        return $this->json(['success' => true, 'message' => 'Commande annulée.']);
    }

    #[Route('/commandes/{id}/avis', name: 'api_user_avis', methods: ['POST'])]
    #[OA\Post(
        summary: 'Donner un avis',
        description: 'Dépose un avis sur une commande terminée. Un seul avis par commande. L\'avis est soumis à validation par un employé/admin.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID de la commande', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['note'],
                properties: [
                    new OA\Property(property: 'note', type: 'integer', minimum: 1, maximum: 5, example: 4),
                    new OA\Property(property: 'description', type: 'string', maxLength: 2000, example: 'Excellent service, plats délicieux !')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Avis déposé (statut : en attente de validation)'),
            new OA\Response(response: 400, description: 'Commande non terminée / avis déjà déposé / note invalide'),
            new OA\Response(response: 403, description: 'Commande d\'un autre utilisateur')
        ]
    )]
    public function donnerAvis(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($commande->getUtilisateur() !== $this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Accès interdit.'], 403);
        }

        if ($commande->getStatut() !== 'terminée') {
            return $this->json(['success' => false, 'message' => 'Avis uniquement sur commande terminée.'], 400);
        }

        foreach ($commande->getAvis() as $existing) {
            if ($existing->getUtilisateur() === $this->getUser()) {
                return $this->json(['success' => false, 'message' => 'Vous avez déjà donné un avis.'], 400);
            }
        }

        $data = json_decode($request->getContent(), true);
        $note = (int) ($data['note'] ?? 0);
        $description = $this->sanitize($data['description'] ?? '');

        if ($note < 1 || $note > 5) {
            return $this->json(['success' => false, 'message' => 'Note entre 1 et 5.'], 400);
        }

        if (mb_strlen($description) > 2000) {
            return $this->json(['success' => false, 'message' => 'Description trop longue (max 2000 car.).'], 400);
        }

        $avis = new Avis();
        $avis->setNote($note);
        $avis->setDescription($description ?: null);
        $avis->setStatut('en attente');
        $avis->setUtilisateur($this->getUser());
        $avis->setCommande($commande);

        $em->persist($avis);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Merci pour votre avis !'], 201);
    }

    #[Route('/password', name: 'api_user_password', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Changer mon mot de passe',
        description: 'Change le mot de passe. Nécessite le mot de passe actuel. Le nouveau doit respecter la politique de sécurité.',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'new_password'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', example: 'AncienMdp2024!'),
                    new OA\Property(property: 'new_password', type: 'string', description: 'Min 10 car., 1 maj, 1 min, 1 chiffre, 1 spécial', example: 'NouveauMdp2024!')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe modifié'),
            new OA\Response(response: 400, description: 'Champs manquants / mot de passe trop faible'),
            new OA\Response(response: 401, description: 'Mot de passe actuel incorrect')
        ]
    )]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $currentPassword = $data['current_password'] ?? '';
        $newPassword     = $data['new_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            return $this->json(['success' => false, 'message' => 'Champs obligatoires manquants.'], 400);
        }

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->json(['success' => false, 'message' => 'Mot de passe actuel incorrect.'], 401);
        }

        if (strlen($newPassword) < 10
            || !preg_match('/[A-Z]/', $newPassword)
            || !preg_match('/[a-z]/', $newPassword)
            || !preg_match('/[0-9]/', $newPassword)
            || !preg_match('/[^A-Za-z0-9]/', $newPassword)
        ) {
            return $this->json([
                'success' => false,
                'message' => 'Le mot de passe doit contenir 10 caractères minimum, 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial.'
            ], 400);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Mot de passe modifié.']);
    }

    #[Route('/account', name: 'api_user_delete_account', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Supprimer mon compte',
        description: 'Supprime définitivement le compte, ses commandes et ses avis. Impossible s\'il reste des commandes actives (ni annulées, ni terminées).',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Compte supprimé'),
            new OA\Response(response: 400, description: 'Commandes en cours empêchent la suppression')
        ]
    )]
    public function deleteAccount(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        $commandesActives = $user->getCommandes()->filter(function($c) {
            return !in_array($c->getStatut(), ['annulée', 'terminée']);
        });

        if ($commandesActives->count() > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de supprimer le compte : vous avez des commandes en cours.'
            ], 400);
        }

        foreach ($user->getAvis() as $avi) {
            $em->remove($avi);
        }

        foreach ($user->getCommandes() as $commande) {
            $em->remove($commande);
        }

        $em->remove($user);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Compte supprimé.']);
    }

    private function serializeCommande(Commande $commande): array
    {
        return [
            'id' => $commande->getId(),
            'numero_commande' => $commande->getNumeroCommande(),
            'date_commande' => $commande->getDateCommande()?->format('Y-m-d H:i'),
            'date_prestation' => $commande->getDatePrestation()?->format('Y-m-d'),
            'lieu_prestation' => $commande->getLieuPrestation() ?? '',
            'heure_livraison' => $commande->getHeureLivraison(),
            'nombre_personne' => $commande->getNombrePersonne(),
            'prix_menu' => $commande->getPrixMenu(),
            'prix_livraison' => $commande->getPrixLivraison(),
            'prix_total' => $commande->getPrixTotal(),
            'statut' => $commande->getStatut(),
            'pret_materiel' => $commande->isPretMateriel(),
            'restitution_materiel' => $commande->isRestitutionMateriel(),
            'menu' => $commande->getMenu() ? [
                'id' => $commande->getMenu()->getId(),
                'titre' => $commande->getMenu()->getTitre(),
            ] : null,
            'avis_depose' => $commande->getAvis()->count() > 0,
        ];
    }

    private function sanitize(string $input): string
    {
        return strip_tags(trim($input));
    }
}
