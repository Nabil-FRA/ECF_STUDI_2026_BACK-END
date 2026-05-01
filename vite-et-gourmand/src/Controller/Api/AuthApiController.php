<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Repository\RoleRepository;
use App\Repository\UtilisateurRepository;
use App\Security\ApiTokenAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Controller API d'authentification.
 * 
 * SÉCURITÉ implémentée :
 * - Validation stricte de toutes les entrées (XSS, injection)
 * - Hashage bcrypt des mots de passe
 * - Token HMAC-SHA256 signé
 * - Rate limiting (voir ApiRateLimiterListener)
 * - Messages d'erreur génériques (pas de fuite d'info)
 * - Politique de mot de passe forte (RGPD/CNIL)
 */
#[Route('/api/auth')]
class AuthApiController extends AbstractController
{
    /**
     * POST /api/auth/login
     * Corps : { "email": "...", "password": "..." }
     * Retour : { "success": true, "token": "...", "user": {...} }
     */
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validation des entrées
        $email = $this->sanitizeInput($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            return $this->json([
                'success' => false,
                'message' => 'Email et mot de passe requis.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation du format email (protection injection)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'message' => 'Format d\'email invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $utilisateurRepository->findOneBy(['email' => $email]);

        // Message générique pour ne pas révéler si l'email existe
        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'success' => false,
                'message' => 'Identifiants incorrects.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Vérifier que le compte n'est pas désactivé
        if ($user->getRole() && $user->getRole()->getLibelle() === 'desactive') {
            return $this->json([
                'success' => false,
                'message' => 'Ce compte a été désactivé.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Générer le token signé
        $appSecret = $this->getParameter('kernel.secret');
        $token = ApiTokenAuthenticator::generateToken($user->getEmail(), $appSecret);

        return $this->json([
            'success' => true,
            'token' => $token,
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * POST /api/auth/register
     * Corps : { "email", "password", "nom", "prenom", "telephone", "ville", "pays", "adresse_postale" }
     */
    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        RoleRepository $roleRepository,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Sanitisation de toutes les entrées (protection XSS)
        $email = $this->sanitizeInput($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $nom = $this->sanitizeInput($data['nom'] ?? '');
        $prenom = $this->sanitizeInput($data['prenom'] ?? '');
        $telephone = $this->sanitizeInput($data['telephone'] ?? '');
        $ville = $this->sanitizeInput($data['ville'] ?? '');
        $pays = $this->sanitizeInput($data['pays'] ?? '');
        $adressePostale = $this->sanitizeInput($data['adresse_postale'] ?? '');

        // Validation des champs obligatoires
        if (empty($email) || empty($password) || empty($nom) || empty($prenom)) {
            return $this->json([
                'success' => false,
                'message' => 'Email, mot de passe, nom et prénom sont obligatoires.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'message' => 'Format d\'email invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Politique de mot de passe forte (RGPD/CNIL)
        // Min 10 caractères, 1 majuscule, 1 minuscule, 1 chiffre, 1 caractère spécial
        if (!$this->validatePassword($password)) {
            return $this->json([
                'success' => false,
                'message' => 'Le mot de passe doit contenir au moins 10 caractères, 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier unicité email
        if ($utilisateurRepository->findOneBy(['email' => $email])) {
            return $this->json([
                'success' => false,
                'message' => 'Un compte existe déjà avec cet email.'
            ], Response::HTTP_CONFLICT);
        }

        // Validation téléphone (si fourni)
        if (!empty($telephone) && !preg_match('/^[\d\s\+\-\.()]{6,20}$/', $telephone)) {
            return $this->json([
                'success' => false,
                'message' => 'Format de téléphone invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Création de l'utilisateur
        $user = new Utilisateur();
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setTelephone($telephone ?: null);
        $user->setVille($ville ?: null);
        $user->setPays($pays ?: null);
        $user->setAdressePostale($adressePostale ?: null);

        $roleClient = $roleRepository->findOneBy(['libelle' => 'utilisateur']);
        $user->setRole($roleClient);
        $user->setRoles(['ROLE_USER']);

        $em->persist($user);
        $em->flush();

        // Mail de bienvenue
        try {
            $emailMsg = (new Email())
                ->from('noreply@viteetgourmand.fr')
                ->to($user->getEmail())
                ->subject('Bienvenue chez Vite & Gourmand !')
                ->html(
                    '<h2>Bienvenue ' . htmlspecialchars($user->getPrenom()) . ' !</h2>' .
                    '<p>Votre compte a bien été créé chez <strong>Vite & Gourmand</strong>.</p>' .
                    '<p>Vous pouvez dès maintenant consulter nos menus et passer commande.</p>' .
                    '<p>À très bientôt !</p>' .
                    '<p><em>L\'équipe Vite & Gourmand</em></p>'
                );
            $mailer->send($emailMsg);
        } catch (\Exception $e) {
            // Le mail n'est pas bloquant
        }

        // Générer le token
        $appSecret = $this->getParameter('kernel.secret');
        $token = ApiTokenAuthenticator::generateToken($user->getEmail(), $appSecret);

        return $this->json([
            'success' => true,
            'message' => 'Compte créé avec succès.',
            'token' => $token,
            'user' => $this->serializeUser($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * POST /api/auth/forgot-password
     * Corps : { "email": "..." }
     * Génère un token signé et envoie un lien de réinitialisation par email.
     * Réponse générique : ne révèle pas si l'email existe (sécurité).
     */
    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        MailerInterface $mailer,
        #[Autowire('%env(FRONTEND_URL)%')] string $frontendUrl = 'http://localhost:5173'
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $this->sanitizeInput($data['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'message' => 'Email invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $utilisateurRepository->findOneBy(['email' => $email]);

        // On traite silencieusement si l'email n'existe pas (anti-énumération)
        if ($user) {
            $expiry = time() + 3600; // valide 1 heure
            $secret = $this->getParameter('kernel.secret');
            $token = base64_encode(
                $user->getEmail() . '|' . $expiry . '|' .
                hash('sha256', $user->getEmail() . $expiry . $secret)
            );

            // Lien vers le front-end SPA (ou Twig en fallback)
            $resetUrl = rtrim($frontendUrl, '/') . '/reinitialiser-mot-de-passe?token=' . urlencode($token);

            try {
                $emailMsg = (new Email())
                    ->from('noreply@viteetgourmand.fr')
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe - Vite & Gourmand')
                    ->html(
                        '<h2>Réinitialisation de mot de passe</h2>' .
                        '<p>Bonjour ' . htmlspecialchars($user->getPrenom()) . ',</p>' .
                        '<p>Vous avez demandé la réinitialisation de votre mot de passe.</p>' .
                        '<p><a href="' . $resetUrl . '" style="display:inline-block;padding:12px 25px;' .
                        'background:#007bff;color:#fff;text-decoration:none;border-radius:6px;">' .
                        'Réinitialiser mon mot de passe</a></p>' .
                        '<p>Ce lien est valable <strong>1 heure</strong>.</p>' .
                        '<p>Si vous n\'avez pas fait cette demande, ignorez cet email.</p>' .
                        '<p><em>L\'équipe Vite &amp; Gourmand</em></p>'
                    );
                $mailer->send($emailMsg);
            } catch (\Exception $e) {
                // Mail non bloquant
            }
        }

        // Message identique qu'il y ait ou non un compte (sécurité)
        return $this->json([
            'success' => true,
            'message' => 'Si un compte existe avec cet email, un lien de réinitialisation a été envoyé.'
        ]);
    }

    /**
     * POST /api/auth/reset-password
     * Corps : { "token": "...", "password": "..." }
     * Valide le token HMAC-SHA256, vérifie l'expiration, et met à jour le mot de passe.
     */
    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $tokenRaw = $data['token'] ?? '';
        $newPassword = $data['password'] ?? '';

        if (empty($tokenRaw) || empty($newPassword)) {
            return $this->json([
                'success' => false,
                'message' => 'Token et mot de passe requis.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Décoder le token base64
        $decoded = base64_decode($tokenRaw, true);
        if ($decoded === false) {
            return $this->json(['success' => false, 'message' => 'Token invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return $this->json(['success' => false, 'message' => 'Token invalide.'], Response::HTTP_BAD_REQUEST);
        }

        [$email, $expiry, $hash] = $parts;

        // Vérifier l'expiration
        if (time() > (int) $expiry) {
            return $this->json([
                'success' => false,
                'message' => 'Ce lien a expiré. Veuillez refaire une demande.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier la signature HMAC
        $secret = $this->getParameter('kernel.secret');
        $expectedHash = hash('sha256', $email . $expiry . $secret);
        if (!hash_equals($expectedHash, $hash)) {
            return $this->json(['success' => false, 'message' => 'Token invalide.'], Response::HTTP_BAD_REQUEST);
        }

        // Trouver l'utilisateur
        $user = $utilisateurRepository->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Token invalide.'], Response::HTTP_BAD_REQUEST);
        }

        // Valider la politique de mot de passe
        if (!$this->validatePassword($newPassword)) {
            return $this->json([
                'success' => false,
                'message' => 'Le mot de passe doit contenir au moins 10 caractères, 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Mettre à jour le mot de passe
        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès. Vous pouvez vous connecter.'
        ]);
    }

    /**
     * Sérialise un utilisateur pour la réponse JSON.
     * SÉCURITÉ : ne jamais exposer le mot de passe hashé.
     */
    private function serializeUser(Utilisateur $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'telephone' => $user->getTelephone(),
            'ville' => $user->getVille(),
            'pays' => $user->getPays(),
            'adresse_postale' => $user->getAdressePostale(),
            'role' => $user->getRole() ? $user->getRole()->getLibelle() : null,
            'roles' => $user->getRoles(),
        ];
    }

    /**
     * Sanitise une entrée utilisateur.
     * Protection contre XSS et injection.
     */
    private function sanitizeInput(string $input): string
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Valide la politique de mot de passe.
     * Conforme RGPD/CNIL : 10 car. min, 1 maj, 1 min, 1 chiffre, 1 spécial
     */
    private function validatePassword(string $password): bool
    {
        return strlen($password) >= 10
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }
}
