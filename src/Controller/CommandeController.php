<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommandeFormType;
use App\Repository\MenuRepository;
use App\Service\MongoDbService;
use App\Service\TarificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
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
        MailerInterface $mailer,
        MongoDbService $mongoDbService,
        TarificationService $tarification,
        ?int $menu_id = null
    ): Response {
        $user = $this->getUser();
        $commande = new Commande();

        if ($menu_id) {
            $menu = $menuRepository->find($menu_id);
            if ($menu) {
                $commande->setMenu($menu);
                $commande->setNombrePersonne($menu->getNombrePersonneMinimum());
            }
        }

        $form = $this->createForm(CommandeFormType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && ($lieu = (string) $commande->getLieuPrestation()) !== '') {
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

        if ($form->isSubmitted() && $form->isValid()) {
            $menu = $commande->getMenu();
            $nbPersonnes = $commande->getNombrePersonne();

            if ($nbPersonnes < $menu->getNombrePersonneMinimum()) {
                $this->addFlash('error', 'Le nombre minimum de personnes pour ce menu est de ' . $menu->getNombrePersonneMinimum() . '.');
                return $this->redirectToRoute('app_commande', ['menu_id' => $menu->getId()]);
            }

            if ($menu->getQuantiteRestante() !== null && $menu->getQuantiteRestante() <= 0) {
                $this->addFlash('error', 'Ce menu n\'est plus disponible.');
                return $this->redirectToRoute('app_menus');
            }

            // La distance ne sert qu'en dehors de la zone gratuite : on ne
            // conserve pas de valeur parasite pour une livraison bordelaise.
            if ($tarification->estZoneGratuite($commande->getLieuPrestation())) {
                $commande->setDistanceKm(null);
            }

            $prix = $tarification->calculer(
                $menu,
                $nbPersonnes,
                $commande->getLieuPrestation(),
                $commande->getDistanceKm()
            );
            $prixMenu = $prix['prix_menu'];
            $prixLivraison = $prix['prix_livraison'];

            $numeroCommande = 'CMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $commande->setNumeroCommande($numeroCommande);
            $commande->setDateCommande(new \DateTime());
            $commande->setPrixMenu($prixMenu);
            $commande->setPrixLivraison($prixLivraison);
            $commande->setStatut('en cours');
            $commande->setRestitutionMateriel(false);
            $commande->setUtilisateur($user);

            if ($commande->isPretMateriel() === null) {
                $commande->setPretMateriel(false);
            }

            if ($menu->getQuantiteRestante() !== null) {
                $menu->setQuantiteRestante($menu->getQuantiteRestante() - 1);
            }

            $em->persist($commande);
            $em->flush();

            // Sync vers MongoDB
            $mongoDbService->syncCommande([
                'numero_commande' => $numeroCommande,
                'menu_titre' => $menu->getTitre(),
                'nombre_personne' => $nbPersonnes,
                'prix_menu' => $prixMenu,
                'prix_livraison' => $prixLivraison,
                'prix_total' => $prix['prix_total'],
                'date_commande' => date('Y-m-d'),
                'statut' => 'en cours',
                'client_email' => $user->getEmail(),
            ]);

            // Historique de suivi
            $mongoDbService->ajouterSuivi($numeroCommande, 'en cours');

            // Mail de confirmation de commande
            $total = $prix['prix_total'];
            $email = (new Email())
                ->from('maxnabil2ait@gmail.com')
                ->to($user->getEmail())
                ->subject('Confirmation de commande ' . $numeroCommande)
                ->html(
                    '<h2>Commande confirmée !</h2>' .
                    '<p>Bonjour ' . htmlspecialchars($user->getPrenom()) . ',</p>' .
                    '<p>Votre commande <strong>' . $numeroCommande . '</strong> a bien été enregistrée.</p>' .
                    '<h3>Récapitulatif :</h3>' .
                    '<ul>' .
                    '<li><strong>Menu :</strong> ' . htmlspecialchars($menu->getTitre()) . '</li>' .
                    '<li><strong>Nombre de personnes :</strong> ' . $nbPersonnes . '</li>' .
                    '<li><strong>Date :</strong> ' . $commande->getDatePrestation()->format('d/m/Y') . '</li>' .
                    '<li><strong>Lieu :</strong> ' . htmlspecialchars($commande->getLieuPrestation()) . '</li>' .
                    '<li><strong>Heure :</strong> ' . htmlspecialchars($commande->getHeureLivraison()) . '</li>' .
                    '</ul>' .
                    '<p><strong>Prix menu :</strong> ' . number_format($prixMenu, 2, ',', ' ') . ' €</p>' .
                    '<p><strong>Livraison :</strong> ' . number_format($prixLivraison, 2, ',', ' ') . ' €</p>' .
                    '<p style="font-size: 1.2em;"><strong>Total : ' . number_format($total, 2, ',', ' ') . ' €</strong></p>' .
                    '<p>Merci pour votre confiance !</p>' .
                    '<p><em>L\'équipe Vite & Gourmand</em></p>'
                );

            $mailer->send($email);

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
        if ($commande->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('commande/confirmation.html.twig', [
            'commande' => $commande,
        ]);
    }
}
