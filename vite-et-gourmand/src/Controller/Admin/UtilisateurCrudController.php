<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UtilisateurCrudController extends AbstractCrudController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public static function getEntityFqcn(): string
    {
        return Utilisateur::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            EmailField::new('email', 'Adresse Email'),
            TextField::new('nom', 'Nom'),
            TextField::new('prenom', 'Prénom'),
            TextField::new('telephone', 'Téléphone'),
            TextField::new('ville', 'Ville'),
            TextField::new('pays', 'Pays'),
            TextField::new('adresse_postale', 'Adresse postale'),

            AssociationField::new('role', 'Rôle')
                ->setFormTypeOption('choice_label', 'libelle')
                ->formatValue(fn($value) => $value ? $value->getLibelle() : ''),
        ];

        // Le mot de passe ne s'affiche que sur les pages de création/édition
        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            $fields[] = TextField::new('plainPassword', 'Mot de passe')
                ->setFormTypeOption('mapped', false)
                ->setRequired($pageName === Crud::PAGE_NEW);
        }

        return $fields;
    }

    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        $this->hashPassword($entityInstance);
        parent::persistEntity($em, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        $this->hashPassword($entityInstance);
        parent::updateEntity($em, $entityInstance);
    }

    private function hashPassword($entity): void
    {
        // Récupère la valeur saisie dans le champ non-mappé
        $plainPassword = $this->getContext()
            ->getRequest()
            ->request->all()['Utilisateur']['plainPassword'] ?? null;

        if ($plainPassword) {
            $hashedPassword = $this->passwordHasher->hashPassword($entity, $plainPassword);
            $entity->setPassword($hashedPassword);
        }
    }
}
