<?php

namespace App\Controller\Admin;

use App\Entity\Horaire;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class HoraireCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Horaire::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('jour', 'Jour de la semaine'),
            TextField::new('heureOuverture', 'Heure d\'ouverture (ex: 11:00)'),
            TextField::new('heureFermeture', 'Heure de fermeture (ex: 22:30)'),
        ];
    }
}
