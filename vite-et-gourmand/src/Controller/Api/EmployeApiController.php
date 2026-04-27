<?php

namespace App\Controller\Api;

use App\Entity\Commande;
use App\Service\MongoDbService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller API pour l'espace employé.
 * 
 * SÉCURITÉ :
 * - Toutes les routes nécessitent ROLE_EMPLOYE
 * - Validation des transitions de statut
 * - Règles métier (contact client avant annulation, restitution matériel)
 */
#[Route('/api/employe')]
#[IsGranted('ROLE_EMPLOYE')]
class EmployeApiController extends AbstractController
{
    /**
     * GET /api/employe/commandes - Liste toutes les commandes
     */
    #[Route('/commandes', name: 'api_employe_commandes', methods: ['GET'])]
    public function commandes(EntityManagerInterface $em): JsonResponse
    {
        $commandes = $em->getRepository(Commande::class)->findBy([], ['date_commande' => 'DESC']);
        $result = [];

        foreach ($commandes as $c) {
            $result[] = [
                'id' => $c->getId(),
                'numero_commande' => $c->getNumeroCommande(),
                'date_commande' => $c->getDateCommande()?->format('Y-m-d H:i'),
                'date_prestation' => $c->getDatePrestation()?->format('Y-m-d'),
                'lieu_prestation' => htmlspecialchars($c->getLieuPrestation() ?? '', ENT_QUOTES, 'UTF-8'),
                'nombre_personne' => $c->getNombrePersonne(),
                'prix_total' => $c->getPrixMenu() + $c->getPrixLivraison(),
                'statut' => $c->getStatut(),
                'pret_materiel' => $c->isPretMateriel(),
                'restitution_materiel' => $c->isRestitutionMateriel(),
                'client_email' => $c->getUtilisateur() ? $c->getUtilisateur()->getEmail() : '',
                'menu_titre' => $c->getMenu() ? htmlspecialchars($c->getMenu()->getTitre(), ENT_QUOTES, 'UTF-8') : '',
            ];
        }

        return $this->json(['success' => true, 'commandes' => $result]);
    }

    /**
     * PUT /api/employe/commandes/{id}/statut - Changer le statut
     * Corps : { "statut": "...", "mode_contact_client": "...", "motif_annulation": "..." }
     */
    #[Route('/commandes/{id}/statut', name: 'api_employe_commande_statut', methods: ['PUT'])]
    public function updateStatut(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em,
        MongoDbService $mongoDbService,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $nouveauStatut = htmlspecialchars(strip_tags(trim($data['statut'] ?? '')), ENT_QUOTES, 'UTF-8');

        $statutsValides = [
            'en cours', 'accepté', 'en préparation', 'en cours de livraison',
            'livré', 'en attente du retour de matériel', 'terminée', 'annulée'
        ];

        if (!in_array($nouveauStatut, $statutsValides)) {
            return $this->json(['success' => false, 'message' => 'Statut invalide.'], 400);
        }

        // Règle métier : contact client obligatoire avant annulation
        if ($nouveauStatut === 'annulée') {
            $modeContact = $data['mode_contact_client'] ?? '';
            $motif = $data['motif_annulation'] ?? '';
            if (empty($modeContact) || empty($motif)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Mode de contact et motif obligatoires pour annulation.'
                ], 400);
            }
            $commande->setModeContactClient(htmlspecialchars(strip_tags($modeContact), ENT_QUOTES, 'UTF-8'));
            $commande->setMotifAnnulation(htmlspecialchars(strip_tags($motif), ENT_QUOTES, 'UTF-8'));
        }

        // Règle : pas de "en attente retour matériel" si pas de prêt
        if ($nouveauStatut === 'en attente du retour de matériel' && !$commande->isPretMateriel()) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun matériel prêté pour cette commande.'
            ], 400);
        }

        // Règle : restitution avant terminée
        if ($nouveauStatut === 'terminée' && $commande->isPretMateriel() && !$commande->isRestitutionMateriel()) {
            return $this->json([
                'success' => false,
                'message' => 'Le matériel doit être restitué avant de terminer.'
            ], 400);
        }

        $commande->setStatut($nouveauStatut);
        $em->flush();

        // Sync MongoDB
        $mongoDbService->syncCommande([
            'numero_commande' => $commande->getNumeroCommande(),
            'menu_titre' => $commande->getMenu() ? $commande->getMenu()->getTitre() : '',
            'nombre_personne' => $commande->getNombrePersonne(),
            'prix_menu' => $commande->getPrixMenu(),
            'prix_livraison' => $commande->getPrixLivraison(),
            'prix_total' => $commande->getPrixMenu() + $commande->getPrixLivraison(),
            'date_commande' => $commande->getDateCommande()?->format('Y-m-d') ?? date('Y-m-d'),
            'statut' => $nouveauStatut,
            'client_email' => $commande->getUtilisateur()?->getEmail() ?? '',
        ]);
        $mongoDbService->ajouterSuivi($commande->getNumeroCommande(), $nouveauStatut);

        // Emails automatiques
        if ($nouveauStatut === 'terminée' && $commande->getUtilisateur()) {
            try {
                $email = (new Email())
                    ->from('noreply@viteetgourmand.fr')
                    ->to($commande->getUtilisateur()->getEmail())
                    ->subject('Commande ' . $commande->getNumeroCommande() . ' terminée')
                    ->html('<h2>Commande terminée !</h2><p>Donnez-nous votre avis dans votre espace client.</p>');
                $mailer->send($email);
            } catch (\Exception $e) {}
        }

        return $this->json(['success' => true, 'message' => 'Statut mis à jour.']);
    }

    /**
     * GET /api/employe/avis - Liste des avis en attente
     */
    #[Route('/avis', name: 'api_employe_avis', methods: ['GET'])]
    public function avisList(EntityManagerInterface $em): JsonResponse
    {
        $avisRepo = $em->getRepository(\App\Entity\Avis::class);
        $avisList = $avisRepo->findBy([], ['id' => 'DESC']);

        $result = [];
        foreach ($avisList as $a) {
            $result[] = [
                'id' => $a->getId(),
                'note' => $a->getNote(),
                'description' => htmlspecialchars($a->getDescription() ?? '', ENT_QUOTES, 'UTF-8'),
                'statut' => $a->getStatut(),
                'client' => $a->getUtilisateur() ? $a->getUtilisateur()->getPrenom() . ' ' . $a->getUtilisateur()->getNom() : '',
                'commande' => $a->getCommande() ? $a->getCommande()->getNumeroCommande() : '',
            ];
        }

        return $this->json(['success' => true, 'avis' => $result]);
    }

    /**
     * PUT /api/employe/avis/{id}/statut - Valider/refuser un avis
     */
    #[Route('/avis/{id}/statut', name: 'api_employe_avis_statut', methods: ['PUT'])]
    public function updateAvisStatut(
        \App\Entity\Avis $avis,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $statut = htmlspecialchars(strip_tags(trim($data['statut'] ?? '')), ENT_QUOTES, 'UTF-8');

        if (!in_array($statut, ['validé', 'refusé'])) {
            return $this->json(['success' => false, 'message' => 'Statut invalide.'], 400);
        }

        $avis->setStatut($statut);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Avis ' . $statut . '.']);
    }
}
