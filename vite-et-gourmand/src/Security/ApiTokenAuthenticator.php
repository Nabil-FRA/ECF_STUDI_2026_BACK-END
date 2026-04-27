<?php

namespace App\Security;

use App\Repository\UtilisateurRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticator pour les requêtes API.
 * 
 * SÉCURITÉ :
 * - Vérifie le token Bearer dans le header Authorization
 * - Le token est un hash HMAC-SHA256 signé avec APP_SECRET
 * - Protège contre le timing attack via hash_equals()
 */
class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UtilisateurRepository $utilisateurRepository,
        private string $appSecret
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');
        $token = substr($authHeader, 7); // Retire "Bearer "

        if (empty($token)) {
            throw new CustomUserMessageAuthenticationException('Token manquant.');
        }

        // Décoder le token : base64(json({email, exp, signature}))
        $decoded = json_decode(base64_decode($token), true);

        if (!$decoded || !isset($decoded['email'], $decoded['exp'], $decoded['sig'])) {
            throw new CustomUserMessageAuthenticationException('Token invalide.');
        }

        // Vérifier l'expiration
        if ($decoded['exp'] < time()) {
            throw new CustomUserMessageAuthenticationException('Token expiré.');
        }

        // Vérifier la signature HMAC (protection contre la falsification)
        $payload = $decoded['email'] . '|' . $decoded['exp'];
        $expectedSig = hash_hmac('sha256', $payload, $this->appSecret);

        if (!hash_equals($expectedSig, $decoded['sig'])) {
            throw new CustomUserMessageAuthenticationException('Signature invalide.');
        }

        return new SelfValidatingPassport(
            new UserBadge($decoded['email'], function (string $email) {
                $user = $this->utilisateurRepository->findOneBy(['email' => $email]);
                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Utilisateur introuvable.');
                }
                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // Continue la requête
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'success' => false,
            'message' => $exception->getMessageKey()
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Génère un token signé pour un utilisateur.
     * Durée de validité : 24h
     */
    public static function generateToken(string $email, string $appSecret, int $ttl = 86400): string
    {
        $exp = time() + $ttl;
        $payload = $email . '|' . $exp;
        $sig = hash_hmac('sha256', $payload, $appSecret);

        return base64_encode(json_encode([
            'email' => $email,
            'exp' => $exp,
            'sig' => $sig,
        ]));
    }
}
