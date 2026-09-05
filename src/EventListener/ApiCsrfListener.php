<?php

namespace App\EventListener;

use App\Security\ApiCsrfTokenManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Vérifie le jeton CSRF sur toutes les routes de l'API qui modifient des données.
 *
 * SÉCURITÉ :
 * - Sur les pages Twig, le composant Form valide le champ « _token » tout seul.
 *   L'API reçoit du JSON brut, sans formulaire : rien ne se déclenche.
 * - Ce listener couvre donc l'ensemble de /api/ d'un coup, sans avoir à modifier
 *   les 36 routes POST/PUT/DELETE une par une.
 * - Priorité 7, soit juste après le firewall (8) : une requête non authentifiée
 *   reçoit toujours son 401 plutôt qu'un 403 CSRF trompeur.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
class ApiCsrfListener
{
    /** Méthodes qui modifient l'état du serveur. */
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Routes exemptées : sans cela il deviendrait impossible d'obtenir un jeton.
     */
    private const EXEMPT_PATHS = ['/api/csrf-token'];

    public function __construct(private ApiCsrfTokenManager $csrf)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // OPTIONS n'est pas dans la liste : les preflight CORS passent librement.
        if (!in_array($request->getMethod(), self::UNSAFE_METHODS, true)) {
            return;
        }

        if (in_array($path, self::EXEMPT_PATHS, true)) {
            return;
        }

        if ($this->csrf->isValid($request->headers->get(ApiCsrfTokenManager::HEADER))) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'success' => false,
            'message' => 'Jeton CSRF absent ou invalide.',
        ], Response::HTTP_FORBIDDEN));
    }
}
