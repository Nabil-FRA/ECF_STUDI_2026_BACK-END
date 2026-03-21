<?php

namespace App\Controller\Admin;

use App\Entity\Menu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MenuCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Menu::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('titre', 'Titre du Menu'),
            IntegerField::new('nombre_personne_minimum', 'Nb de personnes min.'),
            NumberField::new('prix_par_personne', 'Prix par personne (€)'),
            TextareaField::new('description', 'Description'),
            TextareaField::new('conditions', 'Conditions'),
            IntegerField::new('quantite_restante', 'Quantité en stock'),

            // On configure le menu déroulant (choice_label) ET l'affichage dans le tableau (formatValue)
            AssociationField::new('theme', 'Thème du menu')
                ->setFormTypeOption('choice_label', 'libelle')
                ->formatValue(fn($value) => $value ? $value->getLibelle() : ''),

            AssociationField::new('regime', 'Régime alimentaire')
                ->setFormTypeOption('choice_label', 'libelle')
                ->formatValue(fn($value) => $value ? $value->getLibelle() : ''),

            AssociationField::new('plats', 'Plats inclus dans ce menu')
                ->setFormTypeOption('choice_label', 'titre_plat')
                // Pour "plats" (qui contient plusieurs éléments), pas besoin de formatValue, EasyAdmin affichera le nombre de plats.
        ];
    }
}
