<?php

namespace App\Controller\Admin;

use App\Entity\Avis;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class AvisCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Avis::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Avis')
            ->setEntityLabelInPlural('Avis Clients')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('statut')->setChoices([
                'En attente' => 'en attente',
                'Validé' => 'validé',
                'Refusé' => 'refusé',
            ]));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IntegerField::new('note', 'Note (sur 5)'),
            TextareaField::new('description', 'Commentaire'),

            ChoiceField::new('statut', 'Statut')->setChoices([
                'En attente' => 'en attente',
                'Validé' => 'validé',
                'Refusé' => 'refusé',
            ]),

            AssociationField::new('utilisateur', 'Auteur')
                ->setFormTypeOption('choice_label', 'email')
                ->formatValue(fn($value) => $value ? $value->getEmail() : ''),

            AssociationField::new('commande', 'Commande liée')
                ->setFormTypeOption('choice_label', 'numero_commande')
                ->formatValue(fn($value) => $value ? $value->getNumeroCommande() : ''),
        ];
    }
}
