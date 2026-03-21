<?php

namespace App\Controller\Admin;

use App\Entity\Avis;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AvisCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Avis::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IntegerField::new('note', 'Note (sur 5)'),
            TextareaField::new('description', 'Commentaire'),
            TextField::new('statut', 'Statut (en attente / validé)'),

            // Les relations corrigées !
            AssociationField::new('utilisateur', 'Auteur')
                ->setFormTypeOption('choice_label', 'email')
                ->formatValue(fn($value) => $value ? $value->getEmail() : ''),

            AssociationField::new('commande', 'Commande liée')
                ->setFormTypeOption('choice_label', 'numero_commande')
                ->formatValue(fn($value) => $value ? $value->getNumeroCommande() : '')
        ];
    }
}
