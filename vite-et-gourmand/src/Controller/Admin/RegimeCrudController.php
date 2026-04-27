<?php

namespace App\Controller\Admin;

use App\Entity\Regime;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class RegimeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Regime::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('libelle', 'Nom du Régime (ex: Végétarien)'),
        ];
    }
}
