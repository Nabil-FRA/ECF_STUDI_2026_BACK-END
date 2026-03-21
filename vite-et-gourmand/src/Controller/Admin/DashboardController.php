<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect($adminUrlGenerator->setController(MenuCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Vite & Gourmand Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Accueil Admin', 'fa fa-home');

        yield MenuItem::section('Catalogue');
        // La nouvelle syntaxe EA5 : linkTo(LeControleur::class, 'Le Nom', 'icone')
        yield MenuItem::linkTo(MenuCrudController::class, 'Les Menus', 'fas fa-utensils');
        yield MenuItem::linkTo(PlatCrudController::class, 'Les Plats', 'fas fa-hamburger');

        yield MenuItem::section('Catégories & Filtres');
        yield MenuItem::linkTo(ThemeCrudController::class, 'Thèmes', 'fas fa-star');
        yield MenuItem::linkTo(RegimeCrudController::class, 'Régimes', 'fas fa-leaf');
        yield MenuItem::linkTo(AllergeneCrudController::class, 'Allergènes', 'fas fa-exclamation-triangle');

        yield MenuItem::section('Activité');
        yield MenuItem::linkTo(CommandeCrudController::class, 'Les Commandes', 'fas fa-shopping-cart');
        yield MenuItem::linkTo(AvisCrudController::class, 'Avis Clients', 'fas fa-comments');

        yield MenuItem::section('Paramètres');
        yield MenuItem::linkTo(HoraireCrudController::class, 'Les Horaires', 'fas fa-clock');
        yield MenuItem::linkTo(UtilisateurCrudController::class, 'Les Utilisateurs', 'fas fa-users');
    }
}
