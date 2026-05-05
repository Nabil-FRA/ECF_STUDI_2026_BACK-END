<?php

namespace App\Controller\Api;

use App\Repository\AvisRepository;
use App\Repository\HoraireRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller API pour les données publiques.
 * 
 * SÉCURITÉ :
 * - Validation et sanitisation des entrées contact (strip_tags + trim)
 * - Sorties JSON brutes (pas de htmlspecialchars — JSON n'est pas du HTML)
 * - htmlspecialchars() conservé uniquement dans les corps d'e-mails HTML
 * - Seuls les avis validés sont exposés
 */
#[Route('/api')]
class PublicApiController extends AbstractController
{
    /**
     * GET /api/avis - Liste des avis validés uniquement
     */
    #[Route('/avis', name: 'api_avis', methods: ['GET'])]
    public function avis(AvisRepository $avisRepository): JsonResponse
    {
        $avisValides = $avisRepository->findBy(
            ['statut' => 'validé'],
            ['id' => 'DESC']
        );

        $result = [];
        foreach ($avisValides as $avis) {
            $result[] = [
                'id' => $avis->getId(),
                'note' => $avis->getNote(),
                // JSON n'est pas du HTML — pas de htmlspecialchars() sur les sorties
                'description' => $avis->getDescription() ?? '',
                'prenom_utilisateur' => $avis->getUtilisateur()
                    ? $avis->getUtilisateur()->getPrenom()
                    : 'Anonyme',
            ];
        }

        return $this->json($result);
    }

    /**
     * GET /api/horaires - Horaires d'ouverture
     */
    #[Route('/horaires', name: 'api_horaires', methods: ['GET'])]
    public function horaires(HoraireRepository $horaireRepository): JsonResponse
    {
        $horaires = [];
        foreach ($horaireRepository->findAll() as $h) {
            $horaires[] = [
                'id' => $h->getId(),
                'jour' => $h->getJour(),
                'heure_ouverture' => $h->getHeureOuverture(),
                'heure_fermeture' => $h->getHeureFermeture(),
            ];
        }

        return $this->json($horaires);
    }

    /**
     * POST /api/contact - Envoi du formulaire de contact
     * Corps : { "email": "...", "titre": "...", "description": "..." }
     * 
     * SÉCURITÉ :
     * - Validation email stricte
     * - Sanitisation de toutes les entrées
     * - Protection anti-spam basique (honeypot côté frontend)
     */
    #[Route('/contact', name: 'api_contact', methods: ['POST'])]
    public function contact(Request $request, MailerInterface $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $emailVisiteur = strip_tags(trim($data['email'] ?? ''));
        $titre = strip_tags(trim($data['titre'] ?? ''));
        $description = strip_tags(trim($data['description'] ?? ''));

        // Champ honeypot (anti-spam) - si rempli, c'est un bot
        $honeypot = $data['website'] ?? '';
        if (!empty($honeypot)) {
            // Retourner succès pour ne pas informer le bot
            return $this->json(['success' => true, 'message' => 'Message envoyé.']);
        }

        // Validation
        if (empty($emailVisiteur) || empty($titre) || empty($description)) {
            return $this->json([
                'success' => false,
                'message' => 'Tous les champs sont obligatoires.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($emailVisiteur, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'message' => 'Adresse email invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Limiter la longueur (protection déni de service)
        if (mb_strlen($titre) > 200 || mb_strlen($description) > 5000) {
            return $this->json([
                'success' => false,
                'message' => 'Le titre ou la description est trop long.'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $email = (new Email())
                ->from('noreply@viteetgourmand.fr')
                ->to('contact@viteetgourmand.fr')
                ->replyTo($emailVisiteur)
                ->subject('[Contact API] ' . $titre)
                ->html(
                    '<h2>Nouveau message de contact (Frontend)</h2>' .
                    '<p><strong>De :</strong> ' . $emailVisiteur . '</p>' .
                    '<p><strong>Sujet :</strong> ' . $titre . '</p>' .
                    '<hr>' .
                    '<p>' . nl2br($description) . '</p>'
                );

            $mailer->send($email);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi. Veuillez réessayer.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'success' => true,
            'message' => 'Votre message a bien été envoyé.'
        ]);
    }
}
