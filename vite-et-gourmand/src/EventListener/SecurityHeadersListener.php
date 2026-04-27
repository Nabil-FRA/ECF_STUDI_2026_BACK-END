<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute les en-têtes de sécurité à toutes les réponses API.
 * 
 * SÉCURITÉ implémentée :
 * - X-Content-Type-Options: nosniff → empêche le MIME-type sniffing
 * - X-Frame-Options: DENY → protège contre le clickjacking
 * - X-XSS-Protection → active le filtre XSS du navigateur
 * - Strict-Transport-Security → force HTTPS
 * - Content-Security-Policy → restreint les sources de contenu
 * - Referrer-Policy → limite les infos envoyées dans le Referer
 * - Permissions-Policy → désactive les API sensibles du navigateur
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
class SecurityHeadersListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        // Appliquer uniquement sur les routes API
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        // Protection contre le MIME-type sniffing (XSS via upload)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Protection contre le clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // Active le filtre XSS du navigateur
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Force HTTPS (HSTS) - 1 an
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // Politique de sécurité du contenu pour les réponses API (JSON uniquement)
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

        // Limite les informations dans le header Referer
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Désactive les API sensibles du navigateur
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }
}
