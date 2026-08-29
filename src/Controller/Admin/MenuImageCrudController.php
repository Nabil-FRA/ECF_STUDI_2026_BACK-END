<?php

namespace App\Controller\Admin;

use App\Entity\MenuImage;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class MenuImageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MenuImage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Image de Menu')
            ->setEntityLabelInPlural('Images des Menus');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            UrlField::new('url_image', 'URL de l\'image')
                ->setHelp('Collez l\'URL complète de l\'image (ex: https://images.unsplash.com/photo-xxx). Images gratuites : <a href="https://unsplash.com" target="_blank">Unsplash</a> ou <a href="https://www.pexels.com" target="_blank">Pexels</a>'),

            AssociationField::new('menu', 'Menu associé')
                ->setFormTypeOption('choice_label', 'titre')
                ->formatValue(fn($value) => $value ? $value->getTitre() : ''),
        ];
    }
}
