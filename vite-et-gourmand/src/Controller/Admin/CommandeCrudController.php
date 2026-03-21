<?php

namespace App\Controller\Admin;

use App\Entity\Commande;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class CommandeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Commande::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('numero_commande', 'N° de Commande'),
            DateTimeField::new('date_commande', 'Date de la commande'),
            DateTimeField::new('date_prestation', 'Date de la prestation'),
            TextField::new('lieu_prestation', 'Lieu'),
            TextField::new('heure_livraison', 'Heure de livraison'),
            IntegerField::new('nombre_personne', 'Nombre de personnes'),

            NumberField::new('prix_menu', 'Prix du menu (€)'),
            NumberField::new('prix_livraison', 'Frais de livraison (€)'),

            TextField::new('statut', 'Statut'),

            BooleanField::new('pret_materiel', 'Prêt de matériel'),
            BooleanField::new('restitution_materiel', 'Restitution matériel'),

            TextField::new('mode_contact_client', 'Mode de contact'),
            TextareaField::new('motif_annulation', 'Motif d\'annulation'),

            AssociationField::new('utilisateur', 'Client')
                ->setFormTypeOption('choice_label', 'email')
                ->formatValue(fn($value) => $value ? $value->getEmail() : ''),

            AssociationField::new('menu', 'Menu choisi')
                ->setFormTypeOption('choice_label', 'titre')
                ->formatValue(fn($value) => $value ? $value->getTitre() : ''),
        ];
    }
}
