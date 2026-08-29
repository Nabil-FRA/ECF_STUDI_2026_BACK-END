<?php

namespace App\Controller\Admin;

use App\Entity\Commande;
use App\Service\MongoDbService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class CommandeCrudController extends AbstractCrudController
{
    public function __construct(
        private MailerInterface $mailer,
        private MongoDbService $mongoDbService
    ) {}

    public static function getEntityFqcn(): string
    {
        return Commande::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Commande')
            ->setEntityLabelInPlural('Commandes')
            ->setDefaultSort(['date_commande' => 'DESC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('statut')->setChoices([
                'En cours' => 'en cours',
                'Accepté' => 'accepté',
                'En préparation' => 'en préparation',
                'En cours de livraison' => 'en cours de livraison',
                'Livré' => 'livré',
                'En attente du retour de matériel' => 'en attente du retour de matériel',
                'Terminée' => 'terminée',
                'Annulée' => 'annulée',
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('numero_commande', 'N° de Commande')
                ->setFormTypeOption('disabled', $pageName === Crud::PAGE_EDIT),

            DateTimeField::new('date_commande', 'Date de la commande')
                ->setFormTypeOption('disabled', true),

            DateTimeField::new('date_prestation', 'Date de la prestation'),
            TextField::new('lieu_prestation', 'Lieu'),
            TextField::new('heure_livraison', 'Heure de livraison'),
            IntegerField::new('nombre_personne', 'Nombre de personnes'),

            NumberField::new('prix_menu', 'Prix du menu (€)'),
            NumberField::new('prix_livraison', 'Frais de livraison (€)'),

            ChoiceField::new('statut', 'Statut')->setChoices([
                'En cours' => 'en cours',
                'Accepté' => 'accepté',
                'En préparation' => 'en préparation',
                'En cours de livraison' => 'en cours de livraison',
                'Livré' => 'livré',
                'En attente du retour de matériel' => 'en attente du retour de matériel',
                'Terminée' => 'terminée',
                'Annulée' => 'annulée',
            ]),

            BooleanField::new('pret_materiel', 'Prêt de matériel'),
            BooleanField::new('restitution_materiel', 'Restitution matériel'),

            ChoiceField::new('mode_contact_client', 'Mode de contact client')
                ->setChoices([
                    'Aucun' => null,
                    'Appel téléphonique' => 'telephone',
                    'Email' => 'email',
                ])
                ->setRequired(false)
                ->setHelp('Obligatoire avant annulation ou modification'),

            TextareaField::new('motif_annulation', "Motif d'annulation / modification")
                ->setRequired(false)
                ->setHelp('Obligatoire si annulation'),

            AssociationField::new('utilisateur', 'Client')
                ->setFormTypeOption('choice_label', 'email')
                ->formatValue(fn($value) => $value ? $value->getEmail() : ''),

            AssociationField::new('menu', 'Menu choisi')
                ->setFormTypeOption('choice_label', 'titre')
                ->formatValue(fn($value) => $value ? $value->getTitre() : ''),
        ];
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        /** @var Commande $commande */
        $commande = $entityInstance;

        if ($commande->getStatut() === 'annulée') {
            if (empty($commande->getModeContactClient()) || empty($commande->getMotifAnnulation())) {
                $this->addFlash('danger', 'Vous devez spécifier le mode de contact et le motif avant d\'annuler une commande.');
                return;
            }
        }

        if ($commande->getStatut() === 'en attente du retour de matériel' && !$commande->isPretMateriel()) {
            $this->addFlash('danger', 'Impossible : aucun matériel n\'a été prêté pour cette commande.');
            return;
        }

        if ($commande->getStatut() === 'terminée' && $commande->isPretMateriel() && !$commande->isRestitutionMateriel()) {
            $this->addFlash('danger', 'Le matériel doit être restitué avant de terminer la commande.');
            return;
        }

        // Mail quand commande terminée
        if ($commande->getStatut() === 'terminée') {
            $this->envoyerMail(
                $commande->getUtilisateur()->getEmail(),
                'Votre commande ' . $commande->getNumeroCommande() . ' est terminée',
                '<h2>Commande terminée !</h2>' .
                '<p>Bonjour ' . htmlspecialchars($commande->getUtilisateur()->getPrenom()) . ',</p>' .
                '<p>Votre commande <strong>' . $commande->getNumeroCommande() . '</strong> est maintenant terminée.</p>' .
                '<p>Nous espérons que vous avez apprécié nos services !</p>' .
                '<p><strong>Donnez-nous votre avis</strong> en vous connectant à votre espace client.</p>' .
                '<p>Merci pour votre confiance !</p>' .
                '<p><em>L\'équipe Vite & Gourmand</em></p>'
            );
        }

        // Mail quand en attente du retour de matériel
        if ($commande->getStatut() === 'en attente du retour de matériel') {
            $this->envoyerMail(
                $commande->getUtilisateur()->getEmail(),
                'Retour de matériel - Commande ' . $commande->getNumeroCommande(),
                '<h2>Retour de matériel</h2>' .
                '<p>Bonjour ' . htmlspecialchars($commande->getUtilisateur()->getPrenom()) . ',</p>' .
                '<p>Du matériel vous a été prêté pour la commande <strong>' . $commande->getNumeroCommande() . '</strong>.</p>' .
                '<p style="color: red; font-weight: bold;">Vous disposez de 10 jours ouvrés pour restituer le matériel.</p>' .
                '<p>Passé ce délai, des frais de <strong>600 €</strong> vous seront facturés (conformément aux CGV).</p>' .
                '<p>Pour organiser le retour, contactez-nous par téléphone ou par mail.</p>' .
                '<p><em>L\'équipe Vite & Gourmand</em></p>'
            );
        }

        // Sync vers MongoDB
        $this->mongoDbService->syncCommande([
            'numero_commande' => $commande->getNumeroCommande(),
            'menu_titre' => $commande->getMenu() ? $commande->getMenu()->getTitre() : 'Inconnu',
            'nombre_personne' => $commande->getNombrePersonne(),
            'prix_menu' => $commande->getPrixMenu(),
            'prix_livraison' => $commande->getPrixLivraison(),
            'prix_total' => $commande->getPrixMenu() + $commande->getPrixLivraison(),
            'date_commande' => $commande->getDateCommande() ? $commande->getDateCommande()->format('Y-m-d') : date('Y-m-d'),
            'statut' => $commande->getStatut(),
            'client_email' => $commande->getUtilisateur() ? $commande->getUtilisateur()->getEmail() : '',
        ]);

        parent::updateEntity($em, $entityInstance);
    }

    private function envoyerMail(string $to, string $subject, string $htmlBody): void
    {
        $email = (new Email())
            ->from('maxnabil2ait@gmail.com')
            ->to($to)
            ->subject($subject)
            ->html($htmlBody);

        $this->mailer->send($email);
    }
}
