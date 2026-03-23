<?php

namespace App\Controller\Admin;

use App\Entity\MenuImage;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MenuImageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MenuImage::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('url_image', 'Nom du fichier image (ex: reveillon.jpg)'),

            AssociationField::new('menu', 'Menu associé')
                ->setFormTypeOption('choice_label', 'titre')
                ->formatValue(fn($value) => $value ? $value->getTitre() : ''),
        ];
    }
}
