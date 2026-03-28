<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Repository\RoleRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class UtilisateurCrudController extends AbstractCrudController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private RoleRepository $roleRepository
    ) {}

    public static function getEntityFqcn(): string
    {
        return Utilisateur::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs');
    }

    public function configureActions(Actions $actions): Actions
    {
        $toggleActive = Action::new('toggleActive', 'Désactiver/Réactiver')
            ->linkToRoute('admin_toggle_user', function (Utilisateur $entity) {
                return ['id' => $entity->getId()];
            })
            ->addCssClass('btn btn-warning');

        return $actions
            ->add(Crud::PAGE_INDEX, $toggleActive);
    }

    #[Route('/admin/utilisateur/{id}/toggle', name: 'admin_toggle_user')]
    public function toggleUser(
        Utilisateur $user,
        EntityManagerInterface $em,
        AdminUrlGenerator $adminUrlGenerator
    ): Response {
        if ($user->getRole()->getLibelle() === 'desactive') {
            // Réactiver le compte
            $roleEmploye = $this->roleRepository->findOneBy(['libelle' => 'employe']);
            if ($roleEmploye) {
                $user->setRole($roleEmploye);
                $user->setRoles(['ROLE_EMPLOYE']);
                $this->addFlash('success', 'Le compte de ' . $user->getEmail() . ' a été réactivé.');
            }
        } else {
            // Désactiver le compte
            $roleDesactive = $this->roleRepository->findOneBy(['libelle' => 'desactive']);
            if ($roleDesactive) {
                $user->setRole($roleDesactive);
                $user->setRoles([]);
                $this->addFlash('warning', 'Le compte de ' . $user->getEmail() . ' a été désactivé.');
            }
        }

        $em->flush();

        return $this->redirect(
            $adminUrlGenerator
                ->setController(self::class)
                ->setAction('index')
                ->generateUrl()
        );
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
                ->setQueryBuilder(function (QueryBuilder $qb) {
                    return $qb
                        ->andWhere('entity.libelle != :admin')
                        ->setParameter('admin', 'administrateur');
                })
                ->formatValue(fn($value) => $value ? $value->getLibelle() : ''),
        ];

        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            $fields[] = TextField::new('plainPassword', 'Mot de passe')
                ->setFormType(PasswordType::class)
                ->setFormTypeOption('mapped', false)
                ->setRequired($pageName === Crud::PAGE_NEW)
                ->setHelp($pageName === Crud::PAGE_EDIT ? 'Laissez vide pour ne pas changer' : '10 car. min, 1 maj, 1 min, 1 chiffre, 1 spécial');
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

    private function hashPassword(Utilisateur $entity): void
    {
        $request = $this->getContext()->getRequest();
        $formData = $request->request->all('Utilisateur');
        $plainPassword = $formData['plainPassword'] ?? null;

        if (!empty($plainPassword)) {
            $hashedPassword = $this->passwordHasher->hashPassword($entity, $plainPassword);
            $entity->setPassword($hashedPassword);
        }
    }
}
