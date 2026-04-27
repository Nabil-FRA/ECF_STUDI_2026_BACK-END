<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Repository\RoleRepository;
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

        $email = htmlspecialchars(strip_tags(trim($data['email'] ?? '')), ENT_QUOTES, 'UTF-8');
        $nom = htmlspecialchars(strip_tags(trim($data['nom'] ?? '')), ENT_QUOTES, 'UTF-8');
        $prenom = htmlspecialchars(strip_tags(trim($data['prenom'] ?? '')), ENT_QUOTES, 'UTF-8');
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
     * GET /api/admin/stats - Statistiques (chiffre d'affaires)
     */
    #[Route('/stats', name: 'api_admin_stats', methods: ['GET'])]
    public function stats(Request $request, MongoDbService $mongoDbService): JsonResponse
    {
        $menuTitre = $request->query->get('menu');
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');

        $data = $mongoDbService->getChiffreAffaires(
            $menuTitre ?: null,
            $dateDebut ?: null,
            $dateFin ?: null
        );

        $commandesParMenu = $mongoDbService->getCommandesParMenu();
        $menusList = $mongoDbService->getMenusList();

        return $this->json([
            'success' => true,
            'chiffre_affaires' => $data,
            'commandes_par_menu' => $commandesParMenu,
            'menus_list' => $menusList,
        ]);
    }
}
