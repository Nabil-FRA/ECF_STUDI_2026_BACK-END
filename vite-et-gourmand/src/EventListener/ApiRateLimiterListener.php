<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Rate Limiter pour protéger les endpoints sensibles.
 * 
 * SÉCURITÉ :
 * - Limite les tentatives de connexion (5/minute par IP)
 * - Limite les inscriptions (3/heure par IP)
 * - Protège contre les attaques par force brute
 * - Protège contre le credential stuffing
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class ApiRateLimiterListener
{
    public function __construct(
        private RateLimiterFactory $apiLoginLimiter,
        private RateLimiterFactory $apiRegisterLimiter,
        #[Autowire('%kernel.environment%')] private string $env = 'prod'
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Désactivé en dev pour permettre les tests répétés
        if ($this->env === 'dev') {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // Rate limit sur le login : 5 tentatives par minute
        if ($path === '/api/auth/login' && $method === 'POST') {
            $limiter = $this->apiLoginLimiter->create($request->getClientIp());
            if (!$limiter->consume()->isAccepted()) {
                $event->setResponse(new JsonResponse([
                    'success' => false,
                    'message' => 'Trop de tentatives de connexion. Réessayez dans 1 minute.'
                ], Response::HTTP_TOO_MANY_REQUESTS));
                return;
            }
        }

        // Rate limit sur l'inscription : 3 par heure
        if ($path === '/api/auth/register' && $method === 'POST') {
            $limiter = $this->apiRegisterLimiter->create($request->getClientIp());
            if (!$limiter->consume()->isAccepted()) {
                $event->setResponse(new JsonResponse([
                    'success' => false,
                    'message' => 'Trop de tentatives d\'inscription. Réessayez plus tard.'
                ], Response::HTTP_TOO_MANY_REQUESTS));
                return;
            }
        }
    }
}
