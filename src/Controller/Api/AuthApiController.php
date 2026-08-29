<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Repository\RoleRepository;
use App\Repository\UtilisateurRepository;
use App\Security\ApiTokenAuthenticator;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/api/auth')]
#[OA\Tag(name: 'Authentification')]
class AuthApiController extends AbstractController
{
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    #[OA\Post(
        summary: 'Connexion utilisateur',
        description: 'Authentifie un utilisateur et retourne un token Bearer HMAC-SHA256.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'client@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'MonMotDePasse1!')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Connexion réussie — retourne le token et les infos utilisateur'),
            new OA\Response(response: 400, description: 'Email ou mot de passe manquant / format email invalide'),
            new OA\Response(response: 401, description: 'Identifiants incorrects'),
            new OA\Response(response: 403, description: 'Compte désactivé')
        ]
    )]
    public function login(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $email = $this->sanitizeInput($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            return $this->json([
                'success' => false,
                'message' => 'Email et mot de passe requis.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'message' => 'Format d\'email invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $utilisateurRepository->findOneBy(['email' => $email]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'success' => false,
                'message' => 'Identifiants incorrects.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->getRole() && $user->getRole()->getLibelle() === 'desactive') {
            return $this->json([
                'success' => false,
                'message' => 'Ce compte a été désactivé.'
            ], Response::HTTP_FORBIDDEN);
        }

        $appSecret = $this->getParameter('kernel.secret');
        $token = ApiTokenAuthenticator::generateToken($user->getEmail(), $appSecret);

        return $this->json([
            'success' => true,
            'token' => $token,
            'user' => $this->serializeUser($user),
        ]);
    }

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    #[OA\Post(
        summary: 'Inscription utilisateur',
        description: 'Crée un nouveau compte client. Envoie un email de bienvenue. Retourne un token.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'nom', 'prenom'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'nouveau@example.com'),
                    new OA\Property(property: 'password', type: 'string', description: 'Min 10 car., 1 maj, 1 min, 1 chiffre, 1 spécial', example: 'MonMotDePasse1!'),
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
            new OA\Response(response: 201, description: 'Compte créé avec succès — retourne token et infos'),
            new OA\Response(response: 400, description: 'Champs obligatoires manquants / mot de passe trop faible / téléphone invalide'),
            new OA\Response(response: 409, description: 'Email déjà utilisé')
        ]
    )]
    public function register(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        RoleRepository $roleRepository,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $email = $this->sanitizeInput($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $nom = $this->sanitizeInput($data['nom'] ?? '');
        $prenom = $this->sanitizeInput($data['prenom'] ?? '');
        $telephone = $this->sanitizeInput($data['telephone'] ?? '');
        $ville = $this->sanitizeInput($data['ville'] ?? '');
        $pays = $this->sanitizeInput($data['pays'] ?? '');
        $adressePostale = $this->sanitizeInput($data['adresse_postale'] ?? '');

        if (empty($email) || empty($password) || empty($nom) || empty($prenom)) {
            return $this->json([
                'success' => false,
                'message' => 'Email, mot de passe, nom et prénom sont obligatoires.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'message' => 'Format d\'email invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->validatePassword($password)) {
            return $this->json([
                'success' => false,
                'message' => 'Le mot de passe doit contenir au moins 10 caractères, 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($utilisateurRepository->findOneBy(['email' => $email])) {
            return $this->json([
                'success' => false,
                'message' => 'Un compte existe déjà avec cet email.'
            ], Response::HTTP_CONFLICT);
        }

        if (!empty($telephone) && !preg_match('/^[\d\s\+\-\.()]{6,20}$/', $telephone)) {
            return $this->json([
                'success' => false,
                'message' => 'Format de téléphone invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

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

        try {
            $emailMsg = (new Email())
                ->from('maxnabil2ait@gmail.com')
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
        }

        $appSecret = $this->getParameter('kernel.secret');
        $token = ApiTokenAuthenticator::generateToken($user->getEmail(), $appSecret);

        return $this->json([
            'success' => true,
            'message' => 'Compte créé avec succès.',
            'token' => $token,
            'user' => $this->serializeUser($user),
        ], Response::HTTP_CREATED);
    }

    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    #[OA\Post(
        summary: 'Mot de passe oublié',
        description: 'Envoie un email avec un lien de réinitialisation (token signé HMAC, valide 1h). Réponse identique que le compte existe ou non (anti-énumération).',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'client@example.com')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Message générique envoyé (ne révèle pas si le compte existe)'),
            new OA\Response(response: 400, description: 'Email invalide')
        ]
    )]
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

        if ($user) {
            $expiry = time() + 3600;
            $secret = $this->getParameter('kernel.secret');
            $token = base64_encode(
                $user->getEmail() . '|' . $expiry . '|' .
                hash('sha256', $user->getEmail() . $expiry . $secret)
            );

            $resetUrl = rtrim($frontendUrl, '/') . '/reinitialiser-mot-de-passe?token=' . urlencode($token);

            try {
                $emailMsg = (new Email())
                    ->from('maxnabil2ait@gmail.com')
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
            }
        }

        return $this->json([
            'success' => true,
            'message' => 'Si un compte existe avec cet email, un lien de réinitialisation a été envoyé.'
        ]);
    }

    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    #[OA\Post(
        summary: 'Réinitialiser le mot de passe',
        description: 'Valide le token reçu par email et met à jour le mot de passe.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'password'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', description: 'Token reçu par email'),
                    new OA\Property(property: 'password', type: 'string', description: 'Nouveau mot de passe (mêmes règles que l\'inscription)', example: 'NouveauMdp2024!')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe réinitialisé avec succès'),
            new OA\Response(response: 400, description: 'Token invalide / expiré / mot de passe trop faible')
        ]
    )]
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

        $decoded = base64_decode($tokenRaw, true);
        if ($decoded === false) {
            return $this->json(['success' => false, 'message' => 'Token invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return $this->json(['success' => false, 'message' => 'Token invalide.'], Response::HTTP_BAD_REQUEST);
        }

        [$email, $expiry, $hash] = $parts;

        if (time() > (int) $expiry) {
            return $this->json([
                'success' => false,
                'message' => 'Ce lien a expiré. Veuillez refaire une demande.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $secret = $this->getParameter('kernel.secret');
        $expectedHash = hash('sha256', $email . $expiry . $secret);
        if (!hash_equals($expectedHash, $hash)) {
            return $this->json(['success' => false, 'message' => 'Token invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $utilisateurRepository->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Token invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->validatePassword($newPassword)) {
            return $this->json([
                'success' => false,
                'message' => 'Le mot de passe doit contenir au moins 10 caractères, 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès. Vous pouvez vous connecter.'
        ]);
    }

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

    private function sanitizeInput(string $input): string
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    private function validatePassword(string $password): bool
    {
        return strlen($password) >= 10
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }
}
