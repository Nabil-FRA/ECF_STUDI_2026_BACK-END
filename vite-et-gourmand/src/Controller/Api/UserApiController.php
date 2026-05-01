<?php

namespace App\Controller\Api;

use App\Entity\Avis;
use App\Entity\Commande;
use App\Repository\MenuRepository;
use App\Security\ApiTokenAuthenticator;
use App\Service\MongoDbService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller API pour l'espace utilisateur (routes protégées).
 * 
 * SÉCURITÉ :
 * - Toutes les routes nécessitent ROLE_USER
 * - Vérification propriétaire pour chaque commande
 * - Sanitisation des entrées
 * - Validation métier (statut commande, etc.)
 */
#[Route('/api/user')]
#[IsGranted('ROLE_USER')]
class UserApiController extends AbstractController
{
    /**
     * GET /api/user/profile - Profil de l'utilisateur connecté
     */
    #[Route('/profile', name: 'api_user_profile', methods: ['GET'])]
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

    /**
     * PUT /api/user/profile - Modifier le profil
     */
    #[Route('/profile', name: 'api_user_profile_update', methods: ['PUT'])]
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

    /**
     * GET /api/user/commandes - Mes commandes
     */
    #[Route('/commandes', name: 'api_user_commandes', methods: ['GET'])]
    public function commandes(): JsonResponse
    {
        $user = $this->getUser();
        $result = [];

        foreach ($user->getCommandes() as $commande) {
            $result[] = $this->serializeCommande($commande);
        }

        // Trier par date décroissante
        usort($result, fn($a, $b) => strcmp($b['date_commande'] ?? '', $a['date_commande'] ?? ''));

        return $this->json(['success' => true, 'commandes' => $result]);
    }

    /**
     * GET /api/user/commandes/{id} - Détail d'une commande
     */
    #[Route('/commandes/{id}', name: 'api_user_commande_detail', methods: ['GET'])]
    public function commandeDetail(Commande $commande, MongoDbService $mongoDbService): JsonResponse
    {
        // SÉCURITÉ : vérification du propriétaire
        if ($commande->getUtilisateur() !== $this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Accès interdit.'], 403);
        }

        $suivi = $mongoDbService->getSuivi($commande->getNumeroCommande());

        $data = $this->serializeCommande($commande);
        $data['suivi'] = $suivi;

        return $this->json(['success' => true, 'commande' => $data]);
    }

    /**
     * POST /api/user/commandes - Créer une commande
     */
    #[Route('/commandes', name: 'api_user_commande_create', methods: ['POST'])]
    public function createCommande(
        Request $request,
        MenuRepository $menuRepository,
        EntityManagerInterface $em,
        MongoDbService $mongoDbService,
        MailerInterface $mailer
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

        // Validation
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

        // Validation date (doit être dans le futur)
        $datePrest = \DateTime::createFromFormat('Y-m-d', $datePrestation);
        if (!$datePrest || $datePrest < new \DateTime('today')) {
            return $this->json(['success' => false, 'message' => 'Date de prestation invalide.'], 400);
        }

        // Calcul du prix
        $prixMenu = $menu->getPrixParPersonne() * $nbPersonnes;
        if ($nbPersonnes >= $menu->getNombrePersonneMinimum() + 5) {
            $prixMenu *= 0.9; // Réduction 10%
        }

        $lieuLower = strtolower($lieuPrestation);
        $prixLivraison = (str_contains($lieuLower, 'bordeaux') || $distanceKm === 0) ? 0.0 : 5.0 + (0.59 * $distanceKm);

        $numeroCommande = 'CMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

        $commande = new Commande();
        $commande->setNumeroCommande($numeroCommande);
        $commande->setDateCommande(new \DateTime());
        $commande->setDatePrestation($datePrest);
        $commande->setLieuPrestation($lieuPrestation);
        $commande->setHeureLivraison($heureLivraison);
        $commande->setNombrePersonne($nbPersonnes);
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

        // Sync MongoDB
        $mongoDbService->syncCommande([
            'numero_commande' => $numeroCommande,
            'menu_titre' => $menu->getTitre(),
            'nombre_personne' => $nbPersonnes,
            'prix_menu' => $prixMenu,
            'prix_livraison' => $prixLivraison,
            'prix_total' => $prixMenu + $prixLivraison,
            'date_commande' => date('Y-m-d'),
            'statut' => 'en cours',
            'client_email' => $user->getEmail(),
        ]);
        $mongoDbService->ajouterSuivi($numeroCommande, 'en cours');

        // Email de confirmation
        try {
            $total = $prixMenu + $prixLivraison;
            $emailMsg = (new Email())
                ->from('noreply@viteetgourmand.fr')
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

    /**
     * PUT /api/user/commandes/{id} - Modifier une commande (seulement si statut "en cours")
     * Corps : { date_prestation, lieu_prestation, heure_livraison, pret_materiel, distance_km, nombre_personne }
     * Le menu NE PEUT PAS être modifié.
     */
    #[Route('/commandes/{id}', name: 'api_user_commande_update', methods: ['PUT'])]
    public function updateCommande(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em,
        MongoDbService $mongoDbService
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

        // Nombre de personnes
        if (isset($data['nombre_personne'])) {
            $nbPersonnes = (int) $data['nombre_personne'];
            if ($nbPersonnes < $menu->getNombrePersonneMinimum()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Minimum ' . $menu->getNombrePersonneMinimum() . ' personnes pour ce menu.'
                ], 400);
            }
            $commande->setNombrePersonne($nbPersonnes);

            // Recalcul du prix
            $prixMenu = $menu->getPrixParPersonne() * $nbPersonnes;
            if ($nbPersonnes >= $menu->getNombrePersonneMinimum() + 5) {
                $prixMenu *= 0.9;
            }
            $commande->setPrixMenu($prixMenu);
        }

        // Date de prestation
        if (isset($data['date_prestation'])) {
            $datePrest = \DateTime::createFromFormat('Y-m-d', $this->sanitize($data['date_prestation']));
            if (!$datePrest || $datePrest < new \DateTime('today')) {
                return $this->json(['success' => false, 'message' => 'Date de prestation invalide.'], 400);
            }
            $commande->setDatePrestation($datePrest);
        }

        // Lieu de prestation + recalcul livraison
        if (isset($data['lieu_prestation'])) {
            $lieu = $this->sanitize($data['lieu_prestation']);
            $commande->setLieuPrestation($lieu);
            $distanceKm = (int) ($data['distance_km'] ?? 0);
            $lieuLower = strtolower($lieu);
            $prixLivraison = (str_contains($lieuLower, 'bordeaux') || $distanceKm === 0)
                ? 0.0
                : 5.0 + (0.59 * $distanceKm);
            $commande->setPrixLivraison($prixLivraison);
        }

        if (isset($data['heure_livraison'])) {
            $commande->setHeureLivraison($this->sanitize($data['heure_livraison']));
        }

        if (isset($data['pret_materiel'])) {
            $commande->setPretMateriel((bool) $data['pret_materiel']);
        }

        $em->flush();

        // Sync MongoDB
        $mongoDbService->syncCommande([
            'numero_commande' => $commande->getNumeroCommande(),
            'menu_titre'      => $menu->getTitre(),
            'nombre_personne' => $commande->getNombrePersonne(),
            'prix_menu'       => $commande->getPrixMenu(),
            'prix_livraison'  => $commande->getPrixLivraison(),
            'prix_total'      => $commande->getPrixMenu() + $commande->getPrixLivraison(),
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

    /**
     * PUT /api/user/commandes/{id}/annuler - Annuler une commande
     */
    #[Route('/commandes/{id}/annuler', name: 'api_user_commande_annuler', methods: ['PUT'])]
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
            'prix_total' => $commande->getPrixMenu() + $commande->getPrixLivraison(),
            'date_commande' => $commande->getDateCommande() ? $commande->getDateCommande()->format('Y-m-d') : date('Y-m-d'),
            'statut' => 'annulée',
            'client_email' => $commande->getUtilisateur()->getEmail(),
        ]);
        $mongoDbService->ajouterSuivi($commande->getNumeroCommande(), 'annulée');

        return $this->json(['success' => true, 'message' => 'Commande annulée.']);
    }

    /**
     * POST /api/user/commandes/{id}/avis - Donner un avis
     */
    #[Route('/commandes/{id}/avis', name: 'api_user_avis', methods: ['POST'])]
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

        // Vérifier avis existant
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

    private function serializeCommande(Commande $commande): array
    {
        return [
            'id' => $commande->getId(),
            'numero_commande' => $commande->getNumeroCommande(),
            'date_commande' => $commande->getDateCommande()?->format('Y-m-d H:i'),
            'date_prestation' => $commande->getDatePrestation()?->format('Y-m-d'),
            'lieu_prestation' => htmlspecialchars($commande->getLieuPrestation() ?? '', ENT_QUOTES, 'UTF-8'),
            'heure_livraison' => $commande->getHeureLivraison(),
            'nombre_personne' => $commande->getNombrePersonne(),
            'prix_menu' => $commande->getPrixMenu(),
            'prix_livraison' => $commande->getPrixLivraison(),
            'prix_total' => $commande->getPrixMenu() + $commande->getPrixLivraison(),
            'statut' => $commande->getStatut(),
            'pret_materiel' => $commande->isPretMateriel(),
            'restitution_materiel' => $commande->isRestitutionMateriel(),
            'menu' => $commande->getMenu() ? [
                'id' => $commande->getMenu()->getId(),
                'titre' => htmlspecialchars($commande->getMenu()->getTitre(), ENT_QUOTES, 'UTF-8'),
            ] : null,
        ];
    }

    private function sanitize(string $input): string
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
}
