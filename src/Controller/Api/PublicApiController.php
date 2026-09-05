<?php

namespace App\Controller\Api;

use App\Repository\AvisRepository;
use App\Repository\HoraireRepository;
use App\Security\ApiCsrfTokenManager;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
#[OA\Tag(name: 'Public')]
class PublicApiController extends AbstractController
{
    #[Route('/avis', name: 'api_avis', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste des avis validés',
        description: 'Retourne tous les avis clients dont le statut est « validé ». Trié par ID décroissant.',
        responses: [
            new OA\Response(response: 200, description: 'Liste des avis validés')
        ]
    )]
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
                'description' => $avis->getDescription() ?? '',
                'prenom_utilisateur' => $avis->getUtilisateur()
                    ? $avis->getUtilisateur()->getPrenom()
                    : 'Anonyme',
            ];
        }

        return $this->json($result);
    }

    #[Route('/horaires', name: 'api_horaires', methods: ['GET'])]
    #[OA\Get(
        summary: 'Horaires d\'ouverture',
        description: 'Retourne les horaires d\'ouverture du restaurant pour chaque jour de la semaine.',
        responses: [
            new OA\Response(response: 200, description: 'Liste des horaires par jour')
        ]
    )]
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

    #[Route('/contact', name: 'api_contact', methods: ['POST'])]
    #[OA\Post(
        summary: 'Envoyer un message de contact',
        description: 'Envoie un email de contact au restaurant. Inclut un champ honeypot anti-spam.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'titre', 'description'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'visiteur@example.com'),
                    new OA\Property(property: 'titre', type: 'string', maxLength: 200, example: 'Demande de renseignement'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 5000, example: 'Bonjour, je souhaite organiser un événement...'),
                    new OA\Property(property: 'website', type: 'string', description: 'Champ honeypot anti-spam — laisser vide')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Message envoyé avec succès'),
            new OA\Response(response: 400, description: 'Champs manquants / email invalide / texte trop long'),
            new OA\Response(response: 500, description: 'Erreur lors de l\'envoi de l\'email')
        ]
    )]
    public function contact(Request $request, MailerInterface $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $emailVisiteur = strip_tags(trim($data['email'] ?? ''));
        $titre = strip_tags(trim($data['titre'] ?? ''));
        $description = strip_tags(trim($data['description'] ?? ''));

        $honeypot = $data['website'] ?? '';
        if (!empty($honeypot)) {
            return $this->json(['success' => true, 'message' => 'Message envoyé.']);
        }

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

        if (mb_strlen($titre) > 200 || mb_strlen($description) > 5000) {
            return $this->json([
                'success' => false,
                'message' => 'Le titre ou la description est trop long.'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $email = (new Email())
                ->from('maxnabil2ait@gmail.com')
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

    #[Route('/csrf-token', name: 'api_csrf_token', methods: ['GET'])]
    #[OA\Get(
        summary: 'Obtenir un jeton CSRF',
        description: 'Retourne un jeton signé à renvoyer dans l\'en-tête X-CSRF-Token '
            . 'sur toute requête POST, PUT, PATCH ou DELETE de l\'API. '
            . 'Le CORS empêche un site tiers de lire cette réponse.',
        responses: [
            new OA\Response(response: 200, description: 'Jeton CSRF et sa durée de validité')
        ]
    )]
    public function csrfToken(ApiCsrfTokenManager $csrf): JsonResponse
    {
        return $this->json([
            'token' => $csrf->generate(),
            'expires_in' => $csrf->ttl(),
        ]);
    }
}
