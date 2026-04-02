<?php

namespace App\Controller\Admin;

use App\Entity\Menu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
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

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Menu')
            ->setEntityLabelInPlural('Menus');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('titre', 'Titre'),
            TextareaField::new('description', 'Description'),
            TextareaField::new('conditions', 'Conditions'),
            NumberField::new('prix_par_personne', 'Prix par personne (€)'),
            IntegerField::new('nombre_personne_minimum', 'Nombre minimum de personnes'),
            IntegerField::new('quantite_restante', 'Stock disponible'),
            AssociationField::new('regime', 'Régime')
                ->setFormTypeOption('choice_label', 'libelle')
                ->formatValue(fn($value) => $value ? $value->getLibelle() : ''),
            AssociationField::new('theme', 'Thème')
                ->setFormTypeOption('choice_label', 'libelle')
                ->formatValue(fn($value) => $value ? $value->getLibelle() : ''),
            AssociationField::new('plats', 'Plats')
                ->setFormTypeOption('choice_label', 'titrePlat'),
        ];
    }
}
