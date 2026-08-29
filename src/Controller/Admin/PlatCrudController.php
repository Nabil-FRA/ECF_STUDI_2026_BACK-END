<?php

namespace App\Controller\Admin;

use App\Entity\Plat;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PlatCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Plat::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('titre_plat', 'Nom du plat'),
            TextField::new('photo', 'Nom de la photo (ex: buche.jpg)'),

            AssociationField::new('allergenes', 'Allergènes')
                ->setFormTypeOption('choice_label', 'libelle'),

            AssociationField::new('menus', 'Menus associés')
                ->setFormTypeOption('choice_label', 'titre'),
        ];
    }
}
