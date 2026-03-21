<?php

namespace App\DataFixtures;

use App\Entity\Allergene;
use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\Horaire;
use App\Entity\Menu;
use App\Entity\MenuImage;
use App\Entity\Plat;
use App\Entity\Regime;
use App\Entity\Role;
use App\Entity\Theme;
use App\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        // ==========================================
        // 1. LES DICTIONNAIRES
        // ==========================================
        $roleAdmin = (new Role())->setLibelle('administrateur');
        $roleEmploye = (new Role())->setLibelle('employe');
        $roleUtilisateur = (new Role())->setLibelle('utilisateur');
        $manager->persist($roleAdmin); $manager->persist($roleEmploye); $manager->persist($roleUtilisateur);

        $themeNoel = (new Theme())->setLibelle('Noël');
        $manager->persist($themeNoel);

        $regimeClassique = (new Regime())->setLibelle('Classique');
        $manager->persist($regimeClassique);

        $allergeneGluten = (new Allergene())->setLibelle('Gluten');
        $manager->persist($allergeneGluten);

        // ==========================================
        // 2. LES HORAIRES (Du Lundi au Dimanche)
        // ==========================================
        $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        foreach ($jours as $jour) {
            $horaire = new Horaire();
            $horaire->setJour($jour);
            if ($jour === 'Lundi') {
                $horaire->setHeureOuverture('Fermé');
                $horaire->setHeureFermeture('Fermé');
            } else {
                $horaire->setHeureOuverture('11:00');
                $horaire->setHeureFermeture('22:30');
            }
            $manager->persist($horaire);
        }

        // ==========================================
        // 3. LES UTILISATEURS (Admin, Employé, Client)
        // ==========================================
        $admin = (new Utilisateur())->setEmail('jose@viteetgourmand.fr')->setNom('Patron')->setPrenom('José')->setRole($roleAdmin);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123!'));
        $manager->persist($admin);

        $employe = (new Utilisateur())->setEmail('employe@viteetgourmand.fr')->setNom('Martin')->setPrenom('Julie')->setRole($roleEmploye);
        $employe->setPassword($this->hasher->hashPassword($employe, 'employe123!'));
        $manager->persist($employe);

        $client = (new Utilisateur())->setEmail('client@gmail.com')->setNom('Dupont')->setPrenom('Jean')->setRole($roleUtilisateur)->setAdressePostale('10 rue de Paris')->setVille('Bordeaux');
        $client->setPassword($this->hasher->hashPassword($client, 'client123!'));
        $manager->persist($client);

        // ==========================================
        // 4. LES PLATS, MENUS ET IMAGES
        // ==========================================
        $plat1 = (new Plat())->setTitrePlat('Bûche Pralinée')->setPhoto('buche.jpg')->addAllergene($allergeneGluten);
        $manager->persist($plat1);

        $menuFete = (new Menu())
            ->setTitre('Menu Réveillon Magique')
            ->setNombrePersonneMinimum(10)
            ->setPrixParPersonne(45.50)
            ->setDescription('Un repas féérique pour vos fêtes.')
            ->setTheme($themeNoel)
            ->setRegime($regimeClassique)
            ->addPlat($plat1);
        $manager->persist($menuFete);

        $image1 = (new MenuImage())->setUrlImage('menu_noel_1.jpg')->setMenu($menuFete);
        $manager->persist($image1);

        // ==========================================
        // 5. LA COMMANDE ET L'AVIS
        // ==========================================
        $commande = (new Commande())
            ->setNumeroCommande('CMD-2026-001')
            ->setDateCommande(new \DateTime('now'))
            ->setDatePrestation(new \DateTime('+10 days'))
            ->setLieuPrestation('Salle des fêtes, Bordeaux')
            ->setHeureLivraison('19:00')
            ->setPrixMenu(455.00)
            ->setNombrePersonne(10)
            ->setPrixLivraison(15.00)
            ->setStatut('livré')
            ->setPretMateriel(false)
            ->setRestitutionMateriel(false)
            ->setUtilisateur($client)
            ->setMenu($menuFete);
        $manager->persist($commande);

        $avis = (new Avis())
            ->setNote(5)
            ->setDescription('Parfait !')
            ->setStatut('validé')
            ->setUtilisateur($client)
            ->setCommande($commande);
        $manager->persist($avis);

        $manager->flush();
    }
}
