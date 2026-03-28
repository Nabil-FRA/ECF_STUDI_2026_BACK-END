<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Menu;
use App\Form\CommandeFormType;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CommandeController extends AbstractController
{
    #[Route('/commande/{menu_id?}', name: 'app_commande')]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        MenuRepository $menuRepository,
        ?int $menu_id = null
    ): Response {
        $user = $this->getUser();
        $commande = new Commande();

        // Pré-remplir le menu si on vient de la page détail
        if ($menu_id) {
            $menu = $menuRepository->find($menu_id);
            if ($menu) {
                $commande->setMenu($menu);
                $commande->setNombrePersonne($menu->getNombrePersonneMinimum());
            }
        }

        $form = $this->createForm(CommandeFormType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $menu = $commande->getMenu();
            $nbPersonnes = $commande->getNombrePersonne();

            // Vérifier le minimum de personnes
            if ($nbPersonnes < $menu->getNombrePersonneMinimum()) {
                $this->addFlash('error', 'Le nombre minimum de personnes pour ce menu est de ' . $menu->getNombrePersonneMinimum() . '.');
                return $this->redirectToRoute('app_commande', ['menu_id' => $menu->getId()]);
            }

            // Vérifier le stock
            if ($menu->getQuantiteRestante() !== null && $menu->getQuantiteRestante() <= 0) {
                $this->addFlash('error', 'Ce menu n\'est plus disponible.');
                return $this->redirectToRoute('app_menus');
            }

            // Calcul du prix du menu
            $prixMenu = $menu->getPrixParPersonne() * $nbPersonnes;

            // Réduction de 10% si 5 personnes de plus que le minimum
            if ($nbPersonnes >= $menu->getNombrePersonneMinimum() + 5) {
                $prixMenu = $prixMenu * 0.9;
            }

            // Calcul du prix de livraison
            $lieuPrestation = strtolower($commande->getLieuPrestation());
            if (str_contains($lieuPrestation, 'bordeaux')) {
                $prixLivraison = 0.0;
            } else {
                // Prix de base hors Bordeaux : 5€ + 0.59€/km
                // Pour simplifier, on met un forfait de 5€
                // (Le calcul exact par km nécessiterait une API de géolocalisation)
                $prixLivraison = 5.0;
            }

            // Générer un numéro de commande unique
            $numeroCommande = 'CMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            // Remplir la commande
            $commande->setNumeroCommande($numeroCommande);
            $commande->setDateCommande(new \DateTime());
            $commande->setPrixMenu($prixMenu);
            $commande->setPrixLivraison($prixLivraison);
            $commande->setStatut('en cours');
            $commande->setRestitutionMateriel(false);
            $commande->setUtilisateur($user);

            // Si pas de prêt de matériel, mettre false
            if ($commande->isPretMateriel() === null) {
                $commande->setPretMateriel(false);
            }

            // Décrémenter le stock
            if ($menu->getQuantiteRestante() !== null) {
                $menu->setQuantiteRestante($menu->getQuantiteRestante() - 1);
            }

            $em->persist($commande);
            $em->flush();

            $this->addFlash('success', 'Votre commande ' . $numeroCommande . ' a bien été enregistrée !');
            return $this->redirectToRoute('app_commande_confirmation', ['id' => $commande->getId()]);
        }

        return $this->render('commande/index.html.twig', [
            'form' => $form,
            'user' => $user,
            'menu' => $commande->getMenu(),
        ]);
    }

    #[Route('/commande/confirmation/{id}', name: 'app_commande_confirmation')]
    #[IsGranted('ROLE_USER')]
    public function confirmation(Commande $commande): Response
    {
        // Vérifier que la commande appartient à l'utilisateur connecté
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('commande/confirmation.html.twig', [
            'commande' => $commande,
        ]);
    }
}
