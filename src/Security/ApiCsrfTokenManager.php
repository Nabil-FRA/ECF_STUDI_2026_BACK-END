<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Jetons CSRF sans état pour l'API.
 *
 * Le firewall « api » est stateless : il n'y a pas de session où stocker un
 * jeton, le gestionnaire CSRF classique de Symfony est donc inutilisable ici.
 * On émet à la place un jeton signé en HMAC-SHA256 avec APP_SECRET, sur le même
 * principe que les jetons d'authentification (voir ApiTokenAuthenticator).
 *
 * Ce qui protège réellement, c'est le CORS : un site tiers ne peut pas lire la
 * réponse de GET /api/csrf-token depuis le navigateur de la victime, il ne peut
 * donc pas obtenir de jeton valide à rejouer.
 */
class ApiCsrfTokenManager
{
    /** En-tête HTTP qui transporte le jeton. */
    public const HEADER = 'X-CSRF-Token';

    /** Durée de validité d'un jeton, en secondes. */
    private const TTL = 7200;

    public function __construct(
        #[Autowire('%kernel.secret%')] private string $appSecret
    ) {
    }

    public function generate(): string
    {
        $exp = time() + self::TTL;

        return base64_encode(json_encode([
            'exp' => $exp,
            'sig' => $this->sign($exp),
        ]));
    }

    public function isValid(?string $token): bool
    {
        if (null === $token || '' === $token) {
            return false;
        }

        $decoded = json_decode((string) base64_decode($token, true), true);

        if (!is_array($decoded) || !isset($decoded['exp'], $decoded['sig'])) {
            return false;
        }

        if (!is_int($decoded['exp']) || !is_string($decoded['sig'])) {
            return false;
        }

        if ($decoded['exp'] < time()) {
            return false;
        }

        // hash_equals : comparaison à temps constant, comme pour le jeton Bearer.
        return hash_equals($this->sign($decoded['exp']), $decoded['sig']);
    }

    public function ttl(): int
    {
        return self::TTL;
    }

    private function sign(int $exp): string
    {
        return hash_hmac('sha256', 'csrf|' . $exp, $this->appSecret);
    }
}
