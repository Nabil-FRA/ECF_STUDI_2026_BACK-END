<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Commande;
use App\Form\AvisFormType;
use App\Form\CommandeFormType;
use App\Form\ProfilFormType;
use App\Service\MongoDbService;
use App\Service\TarificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/espace')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_espace')]
    public function index(): Response
    {
        $user = $this->getUser();

        return $this->render('user/index.html.twig', [
            'commandes' => $user->getCommandes(),
        ]);
    }

    #[Route('/profil', name: 'app_user_profil')]
    public function profil(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ProfilFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Vos informations ont été mises à jour.');
            return $this->redirectToRoute('app_user_profil');
        }

        return $this->render('user/profil.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Voir le détail d'une commande + historique de suivi MongoDB
     */
    #[Route('/commande/{id}', name: 'app_user_commande_detail')]
    public function commandeDetail(Commande $commande, MongoDbService $mongoDbService): Response
    {
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $suivi = $mongoDbService->getSuivi($commande->getNumeroCommande());

        return $this->render('user/commande_detail.html.twig', [
            'commande' => $commande,
            'suivi' => $suivi,
        ]);
    }

    #[Route('/commande/{id}/modifier', name: 'app_user_commande_modifier')]
    public function commandeModifier(
        Commande $commande,
        Request $request,
        EntityManagerInterface $em,
        TarificationService $tarification
    ): Response {
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($commande->getStatut() !== 'en cours') {
            $this->addFlash('error', 'Cette commande ne peut plus être modifiée.');
            return $this->redirectToRoute('app_user_commande_detail', ['id' => $commande->getId()]);
        }

        // Le menu d'une commande existante n'est pas modifiable : le champ est
        // désactivé côté formulaire pour que la valeur soumise soit ignorée.
        $form = $this->createForm(CommandeFormType::class, $commande, [
            'menu_verrouille' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $minimum = $commande->getMenu()->getNombrePersonneMinimum();
            if ((int) $commande->getNombrePersonne() < $minimum) {
                $form->get('nombre_personne')->addError(new FormError(
                    'Le nombre minimum de personnes pour ce menu est de ' . $minimum . '.'
                ));
            }

            if (($lieu = (string) $commande->getLieuPrestation()) !== '') {
                if ($tarification->estHorsZoneDeLivraison($lieu)) {
                    $form->get('lieu_prestation')->addError(new FormError(
                        'Nous livrons uniquement en Gironde (codes postaux 33xxx).'
                    ));
                }

                if ($tarification->distanceRequise($lieu, $commande->getDistanceKm())) {
                    $form->get('distance_km')->addError(new FormError(
                        'Indiquez la distance depuis Bordeaux : la livraison hors Bordeaux est facturée au kilomètre.'
                    ));
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($tarification->estZoneGratuite($commande->getLieuPrestation())) {
                $commande->setDistanceKm(null);
            }

            $prix = $tarification->calculer(
                $commande->getMenu(),
                $commande->getNombrePersonne(),
                $commande->getLieuPrestation(),
                $commande->getDistanceKm()
            );

            $commande->setPrixMenu($prix['prix_menu']);
            $commande->setPrixLivraison($prix['prix_livraison']);

            $em->flush();
            $this->addFlash('success', 'Votre commande a été modifiée.');
            return $this->redirectToRoute('app_user_commande_detail', ['id' => $commande->getId()]);
        }

        return $this->render('user/commande_modifier.html.twig', [
            'form' => $form,
            'commande' => $commande,
        ]);
    }

    #[Route('/commande/{id}/annuler', name: 'app_user_commande_annuler')]
    public function commandeAnnuler(Commande $commande, EntityManagerInterface $em, MongoDbService $mongoDbService): Response
    {
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($commande->getStatut() !== 'en cours') {
            $this->addFlash('error', 'Cette commande ne peut plus être annulée.');
            return $this->redirectToRoute('app_user_commande_detail', ['id' => $commande->getId()]);
        }

        $menu = $commande->getMenu();
        if ($menu->getQuantiteRestante() !== null) {
            $menu->setQuantiteRestante($menu->getQuantiteRestante() + 1);
        }

        $commande->setStatut('annulée');
        $em->flush();

        // Sync vers MongoDB
        $mongoDbService->syncCommande([
            'numero_commande' => $commande->getNumeroCommande(),
            'menu_titre' => $menu->getTitre(),
            'nombre_personne' => $commande->getNombrePersonne(),
            'prix_menu' => $commande->getPrixMenu(),
            'prix_livraison' => $commande->getPrixLivraison(),
            'prix_total' => $commande->getPrixTotal(),
            'date_commande' => $commande->getDateCommande() ? $commande->getDateCommande()->format('Y-m-d') : date('Y-m-d'),
            'statut' => 'annulée',
            'client_email' => $commande->getUtilisateur()->getEmail(),
        ]);

        // Historique de suivi
        $mongoDbService->ajouterSuivi($commande->getNumeroCommande(), 'annulée');

        $this->addFlash('success', 'Votre commande a été annulée.');
        return $this->redirectToRoute('app_user_espace');
    }

    #[Route('/commande/{id}/avis', name: 'app_user_avis')]
    public function donnerAvis(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($commande->getStatut() !== 'terminée') {
            $this->addFlash('error', 'Vous ne pouvez donner un avis que sur une commande terminée.');
            return $this->redirectToRoute('app_user_commande_detail', ['id' => $commande->getId()]);
        }

        foreach ($commande->getAvis() as $existingAvis) {
            if ($existingAvis->getUtilisateur() === $this->getUser()) {
                $this->addFlash('error', 'Vous avez déjà donné un avis sur cette commande.');
                return $this->redirectToRoute('app_user_commande_detail', ['id' => $commande->getId()]);
            }
        }

        $avis = new Avis();
        $form = $this->createForm(AvisFormType::class, $avis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avis->setUtilisateur($this->getUser());
            $avis->setCommande($commande);
            $avis->setStatut('en attente');

            $em->persist($avis);
            $em->flush();

            $this->addFlash('success', 'Merci pour votre avis ! Il sera visible après validation.');
            return $this->redirectToRoute('app_user_commande_detail', ['id' => $commande->getId()]);
        }

        return $this->render('user/avis.html.twig', [
            'form' => $form,
            'commande' => $commande,
        ]);
    }
}
