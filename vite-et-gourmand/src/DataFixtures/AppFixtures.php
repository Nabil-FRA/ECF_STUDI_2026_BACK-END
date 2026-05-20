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
use App\Service\MongoDbService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;
    private MongoDbService $mongoDbService;

public function __construct(UserPasswordHasherInterface $hasher, MongoDbService $mongoDbService)
{
    $this->hasher = $hasher;
    $this->mongoDbService = $mongoDbService;
}

    public function load(ObjectManager $manager): void
    {
        // ============================================================
        // 1. RÔLES
        // ============================================================
        $roleAdmin = (new Role())->setLibelle('administrateur');
        $roleEmploye = (new Role())->setLibelle('employe');
        $roleUtilisateur = (new Role())->setLibelle('utilisateur');
        $roleDesactive = (new Role())->setLibelle('desactive');
        $manager->persist($roleAdmin);
        $manager->persist($roleEmploye);
        $manager->persist($roleUtilisateur);
        $manager->persist($roleDesactive);

        // ============================================================
        // 2. THÈMES
        // ============================================================
        $themeNoel = (new Theme())->setLibelle('Noël');
        $themePaques = (new Theme())->setLibelle('Pâques');
        $themeClassique = (new Theme())->setLibelle('Classique');
        $themeEvenement = (new Theme())->setLibelle('Évènement');
        $manager->persist($themeNoel);
        $manager->persist($themePaques);
        $manager->persist($themeClassique);
        $manager->persist($themeEvenement);

        // ============================================================
        // 3. RÉGIMES
        // ============================================================
        $regimeClassique = (new Regime())->setLibelle('Classique');
        $regimeVegetarien = (new Regime())->setLibelle('Végétarien');
        $regimeVegan = (new Regime())->setLibelle('Végan');
        $regimeSansGluten = (new Regime())->setLibelle('Sans Gluten');
        $manager->persist($regimeClassique);
        $manager->persist($regimeVegetarien);
        $manager->persist($regimeVegan);
        $manager->persist($regimeSansGluten);

        // ============================================================
        // 4. ALLERGÈNES (14 réglementaires européens)
        // ============================================================
        $allergenes = [];
        $nomsAllergenes = [
            'Gluten', 'Crustacés', 'Œufs', 'Poisson', 'Arachides',
            'Soja', 'Lait', 'Fruits à coque', 'Céleri', 'Moutarde',
            'Sésame', 'Sulfites', 'Lupin', 'Mollusques'
        ];
        foreach ($nomsAllergenes as $nom) {
            $allergene = (new Allergene())->setLibelle($nom);
            $manager->persist($allergene);
            $allergenes[$nom] = $allergene;
        }

        // ============================================================
        // 5. HORAIRES
        // ============================================================
        $horairesDef = [
            'Lundi'    => ['Fermé', 'Fermé'],
            'Mardi'    => ['09:00', '18:00'],
            'Mercredi' => ['09:00', '18:00'],
            'Jeudi'    => ['09:00', '18:00'],
            'Vendredi' => ['09:00', '21:00'],
            'Samedi'   => ['10:00', '21:00'],
            'Dimanche' => ['10:00', '15:00'],
        ];
        foreach ($horairesDef as $jour => $heures) {
            $horaire = (new Horaire())
                ->setJour($jour)
                ->setHeureOuverture($heures[0])
                ->setHeureFermeture($heures[1]);
            $manager->persist($horaire);
        }

        // ============================================================
        // 6. UTILISATEURS
        // ============================================================
        $admin = (new Utilisateur())
            ->setEmail('jose@viteetgourmand.fr')
            ->setNom('Patron')->setPrenom('José')
            ->setTelephone('0612345678')
            ->setVille('Bordeaux')->setPays('France')
            ->setAdressePostale('15 rue de la Restauration, 33000 Bordeaux')
            ->setRole($roleAdmin);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'J0se!Patron#2026'));
        $manager->persist($admin);

        $employe = (new Utilisateur())
            ->setEmail('julie@viteetgourmand.fr')
            ->setNom('Martin')->setPrenom('Julie')
            ->setTelephone('0698765432')
            ->setVille('Bordeaux')->setPays('France')
            ->setAdressePostale('10 rue Sainte-Catherine, 33000 Bordeaux')
            ->setRole($roleEmploye);
        $employe->setRoles(['ROLE_EMPLOYE']);
        $employe->setPassword($this->hasher->hashPassword($employe, 'Jul!eMartin#33'));
        $manager->persist($employe);

        $employe2 = (new Utilisateur())
            ->setEmail('marc@viteetgourmand.fr')
            ->setNom('Leroy')->setPrenom('Marc')
            ->setTelephone('0655443322')
            ->setVille('Bordeaux')->setPays('France')
            ->setAdressePostale('22 cours Victor Hugo, 33000 Bordeaux')
            ->setRole($roleEmploye);
        $employe2->setRoles(['ROLE_EMPLOYE']);
        $employe2->setPassword($this->hasher->hashPassword($employe2, 'M@rcLeroy*2026'));
        $manager->persist($employe2);

        $client1 = (new Utilisateur())
            ->setEmail('jean.dupont@gmail.com')
            ->setNom('Dupont')->setPrenom('Jean')
            ->setTelephone('0711223344')
            ->setVille('Mérignac')->setPays('France')
            ->setAdressePostale('5 avenue de la République, 33700 Mérignac')
            ->setRole($roleUtilisateur);
        $client1->setPassword($this->hasher->hashPassword($client1, 'Je@nDupont!2026'));
        $manager->persist($client1);

        $client2 = (new Utilisateur())
            ->setEmail('sophie.bernard@gmail.com')
            ->setNom('Bernard')->setPrenom('Sophie')
            ->setTelephone('0622334455')
            ->setVille('Pessac')->setPays('France')
            ->setAdressePostale('12 rue Jean Jaurès, 33600 Pessac')
            ->setRole($roleUtilisateur);
        $client2->setPassword($this->hasher->hashPassword($client2, 'S0phie#Bernard26'));
        $manager->persist($client2);

        $client3 = (new Utilisateur())
            ->setEmail('pierre.moreau@gmail.com')
            ->setNom('Moreau')->setPrenom('Pierre')
            ->setTelephone('0633445566')
            ->setVille('Talence')->setPays('France')
            ->setAdressePostale('8 avenue de la Libération, 33400 Talence')
            ->setRole($roleUtilisateur);
        $client3->setPassword($this->hasher->hashPassword($client3, 'P!erre_Moreau33'));
        $manager->persist($client3);

        $client4 = (new Utilisateur())
            ->setEmail('marie.petit@gmail.com')
            ->setNom('Petit')->setPrenom('Marie')
            ->setTelephone('0644556677')
            ->setVille('Bordeaux')->setPays('France')
            ->setAdressePostale('30 quai des Chartrons, 33000 Bordeaux')
            ->setRole($roleUtilisateur);
        $client4->setPassword($this->hasher->hashPassword($client4, 'Mar!ePetit#2026'));
        $manager->persist($client4);

        $client5 = (new Utilisateur())
            ->setEmail('lucas.roux@gmail.com')
            ->setNom('Roux')->setPrenom('Lucas')
            ->setTelephone('0655667788')
            ->setVille('Libourne')->setPays('France')
            ->setAdressePostale('3 place Abel Surchamp, 33500 Libourne')
            ->setRole($roleUtilisateur);
        $client5->setPassword($this->hasher->hashPassword($client5, 'Luc@sRoux*2026'));
        $manager->persist($client5);

        // ============================================================
        // 7. PLATS - ENTRÉES
        // ============================================================
        $foieGras = (new Plat())->setTitrePlat('Foie gras maison sur toast brioché')
            ->setPhoto('https://images.unsplash.com/photo-1625943553852-781c6dd46faa?w=400');
        $foieGras->addAllergene($allergenes['Sulfites'])->addAllergene($allergenes['Gluten']);
        $manager->persist($foieGras);

        $veloute = (new Plat())->setTitrePlat('Velouté de potimarron et crème fraîche')
            ->setPhoto('https://images.unsplash.com/photo-1547592166-23ac45744acd?w=400');
        $veloute->addAllergene($allergenes['Lait'])->addAllergene($allergenes['Céleri']);
        $manager->persist($veloute);

        $saladeChevreChaud = (new Plat())->setTitrePlat('Salade de chèvre chaud aux noix')
            ->setPhoto('https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=400');
        $saladeChevreChaud->addAllergene($allergenes['Lait'])->addAllergene($allergenes['Gluten'])->addAllergene($allergenes['Fruits à coque']);
        $manager->persist($saladeChevreChaud);

        $terrine = (new Plat())->setTitrePlat('Terrine de campagne aux cornichons')
            ->setPhoto('https://images.unsplash.com/photo-1608039829572-25e0745898c3?w=400');
        $terrine->addAllergene($allergenes['Gluten'])->addAllergene($allergenes['Œufs']);
        $manager->persist($terrine);

        $oeufsMimosa = (new Plat())->setTitrePlat('Œufs mimosa à la mayonnaise maison')
            ->setPhoto('https://images.unsplash.com/photo-1482049016688-2d3e1b311543?w=400');
        $oeufsMimosa->addAllergene($allergenes['Œufs'])->addAllergene($allergenes['Moutarde']);
        $manager->persist($oeufsMimosa);

        $bruschetta = (new Plat())->setTitrePlat('Bruschetta tomates confites et basilic frais')
            ->setPhoto('https://images.unsplash.com/photo-1572695157366-5e585ab2b69f?w=400');
        $bruschetta->addAllergene($allergenes['Gluten']);
        $manager->persist($bruschetta);

        $soupeOignon = (new Plat())->setTitrePlat('Soupe à l\'oignon gratinée au gruyère')
            ->setPhoto('https://images.unsplash.com/photo-1547592166-23ac45744acd?w=400');
        $soupeOignon->addAllergene($allergenes['Gluten'])->addAllergene($allergenes['Lait']);
        $manager->persist($soupeOignon);

        $carpaccioBetteraves = (new Plat())->setTitrePlat('Carpaccio de betteraves et vinaigrette balsamique')
            ->setPhoto('https://images.unsplash.com/photo-1540420773420-3366772f4999?w=400');
        $manager->persist($carpaccioBetteraves);

        $saumonFume = (new Plat())->setTitrePlat('Saumon fumé et blinis maison')
            ->setPhoto('https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=400');
        $saumonFume->addAllergene($allergenes['Poisson'])->addAllergene($allergenes['Gluten'])->addAllergene($allergenes['Lait']);
        $manager->persist($saumonFume);

        $gaspacho = (new Plat())->setTitrePlat('Gaspacho andalou aux herbes fraîches')
            ->setPhoto('https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?w=400');
        $manager->persist($gaspacho);

        $tartareSaumon = (new Plat())->setTitrePlat('Tartare de saumon aux agrumes')
            ->setPhoto('https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=400');
        $tartareSaumon->addAllergene($allergenes['Poisson'])->addAllergene($allergenes['Sésame']);
        $manager->persist($tartareSaumon);

        $houmous = (new Plat())->setTitrePlat('Houmous maison et crudités de saison')
            ->setPhoto('https://images.unsplash.com/photo-1577805947697-89e18249d767?w=400');
        $houmous->addAllergene($allergenes['Sésame']);
        $manager->persist($houmous);

        // ============================================================
        // 7. PLATS - PLATS PRINCIPAUX
        // ============================================================
        $dindeMarrons = (new Plat())->setTitrePlat('Dinde rôtie aux marrons et jus de cuisson')
            ->setPhoto('https://images.unsplash.com/photo-1574484284002-952d92456975?w=400');
        $dindeMarrons->addAllergene($allergenes['Fruits à coque']);
        $manager->persist($dindeMarrons);

        $gigotAgneau = (new Plat())->setTitrePlat('Gigot d\'agneau rôti aux herbes de Provence')
            ->setPhoto('https://images.unsplash.com/photo-1514516345957-556ca7d90a29?w=400');
        $gigotAgneau->addAllergene($allergenes['Moutarde']);
        $manager->persist($gigotAgneau);

        $saumonCroute = (new Plat())->setTitrePlat('Saumon en croûte feuilletée aux épinards')
            ->setPhoto('https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=400');
        $saumonCroute->addAllergene($allergenes['Gluten'])->addAllergene($allergenes['Poisson'])->addAllergene($allergenes['Œufs']);
        $manager->persist($saumonCroute);

        $boeufBourguignon = (new Plat())->setTitrePlat('Bœuf bourguignon mijoté et carottes glacées')
            ->setPhoto('https://images.unsplash.com/photo-1534939561126-855b8675edd7?w=400');
        $boeufBourguignon->addAllergene($allergenes['Sulfites'])->addAllergene($allergenes['Céleri']);
        $manager->persist($boeufBourguignon);

        $risotto = (new Plat())->setTitrePlat('Risotto crémeux aux champignons des bois')
            ->setPhoto('https://images.unsplash.com/photo-1476124369491-e7addf5db371?w=400');
        $risotto->addAllergene($allergenes['Lait']);
        $manager->persist($risotto);

        $lasagnesVege = (new Plat())->setTitrePlat('Lasagnes végétariennes aux légumes grillés')
            ->setPhoto('https://images.unsplash.com/photo-1574894709920-11b28e7367e3?w=400');
        $lasagnesVege->addAllergene($allergenes['Gluten'])->addAllergene($allergenes['Lait'])->addAllergene($allergenes['Œufs']);
        $manager->persist($lasagnesVege);

        $filetMignon = (new Plat())->setTitrePlat('Filet mignon de porc sauce moutarde à l\'ancienne')
            ->setPhoto('https://images.unsplash.com/photo-1558030006-450675393462?w=400');
        $filetMignon->addAllergene($allergenes['Moutarde'])->addAllergene($allergenes['Lait']);
        $manager->persist($filetMignon);

        $curryLegumes = (new Plat())->setTitrePlat('Curry de légumes au lait de coco et riz basmati')
            ->setPhoto('https://images.unsplash.com/photo-1455619452474-d2be8b1e70cd?w=400');
        $manager->persist($curryLegumes);

        $coqAuVin = (new Plat())->setTitrePlat('Coq au vin traditionnel et pommes vapeur')
            ->setPhoto('https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?w=400');
        $coqAuVin->addAllergene($allergenes['Sulfites'])->addAllergene($allergenes['Céleri']);
        $manager->persist($coqAuVin);

        $dorade = (new Plat())->setTitrePlat('Dorade grillée au citron et fenouil rôti')
            ->setPhoto('https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=400');
        $dorade->addAllergene($allergenes['Poisson']);
        $manager->persist($dorade);

        $tagliatelles = (new Plat())->setTitrePlat('Tagliatelles fraîches aux légumes du soleil')
            ->setPhoto('https://images.unsplash.com/photo-1473093295043-cdd812d0e601?w=400');
        $tagliatelles->addAllergene($allergenes['Gluten'])->addAllergene($allergenes['Œufs']);
        $manager->persist($tagliatelles);

        $gratinDauphinois = (new Plat())->setTitrePlat('Gratin dauphinois à la crème fraîche')
            ->setPhoto('https://images.unsplash.com/photo-1528735602780-2552fd46c7af?w=400');
        $gratinDauphinois->addAllergene($allergenes['Lait']);
        $manager->persist($gratinDauphinois);

        $ratatouille = (new Plat())->setTitrePlat('Ratatouille provençale et polenta grillée')
            ->setPhoto('https://images.unsplash.com/photo-1572453800999-e8d2d1589b7c?w=400');
        $manager->persist($ratatouille);

        $canardOrange = (new Plat())->setTitrePlat('Magret de canard à l\'orange')
            ->setPhoto('https://images.unsplash.com/photo-1432139509613-5c4255a78e03?w=400');
        $canardOrange->addAllergene($allergenes['Sulfites']);
        $manager->persist($canardOrange);

        // ============================================================
        // 7. PLATS - DESSERTS
        // ============================================================
        $bucheNoel = (new Plat())->setTitrePlat('Bûche de Noël tout chocolat')
            ->setPhoto('https://images.unsplash.com/photo-1551024506-0bccd828d307?w=400');
        $bucheNoel->addAllergene($allergenes['Gluten'])->addAllergene($allergenes['Lait'])->addAllergene($allergenes['Œufs']);
        $manager->persist($bucheNoel);

        $tarteCitron = (new Plat())->setTitrePlat('Tarte au citron meringuée')
            ->setPhoto('https://images.unsplash.com/photo-1519915028121-7d3463d20b13?w=400');
        $tarteCitron->addAllergene($allergenes['Gluten'])->addAllergene($allergenes['Œufs'])->addAllergene($allergenes['Lait']);
        $manager->persist($tarteCitron);

        $mousseChocolat = (new Plat())->setTitrePlat('Mousse au chocolat noir intense')
            ->setPhoto('https://images.unsplash.com/photo-1541783245831-57d6fb0926d3?w=400');
        $mousseChocolat->addAllergene($allergenes['Lait'])->addAllergene($allergenes['Œufs']);
        $manager->persist($mousseChocolat);

        $cremeBrulee = (new Plat())->setTitrePlat('Crème brûlée à la vanille de Madagascar')
            ->setPhoto('https://images.unsplash.com/photo-1470124182917-cc6e71b22ecc?w=400');
        $cremeBrulee->addAllergene($allergenes['Lait'])->addAllergene($allergenes['Œufs']);
        $manager->persist($cremeBrulee);

        $saladeFruits = (new Plat())->setTitrePlat('Salade de fruits frais de saison')
            ->setPhoto('https://images.unsplash.com/photo-1564093497595-593b96d80571?w=400');
        $manager->persist($saladeFruits);

        $fondantChocolat = (new Plat())->setTitrePlat('Fondant au chocolat cœur coulant')
            ->setPhoto('https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=400');
        $fondantChocolat->addAllergene($allergenes['Gluten'])->addAllergene($allergenes['Lait'])->addAllergene($allergenes['Œufs']);
        $manager->persist($fondantChocolat);

        $pannaCotta = (new Plat())->setTitrePlat('Panna cotta vanille et coulis de fruits rouges')
            ->setPhoto('https://images.unsplash.com/photo-1488477181946-6428a0291777?w=400');
        $pannaCotta->addAllergene($allergenes['Lait']);
        $manager->persist($pannaCotta);

        $tiramisu = (new Plat())->setTitrePlat('Tiramisu traditionnel au café')
            ->setPhoto('https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400');
        $tiramisu->addAllergene($allergenes['Œufs'])->addAllergene($allergenes['Lait'])->addAllergene($allergenes['Gluten']);
        $manager->persist($tiramisu);

        $tarteAuxPommes = (new Plat())->setTitrePlat('Tarte aux pommes caramélisées')
            ->setPhoto('https://images.unsplash.com/photo-1568571780765-9276ac8b75a2?w=400');
        $tarteAuxPommes->addAllergene($allergenes['Gluten'])->addAllergene($allergenes['Lait'])->addAllergene($allergenes['Œufs']);
        $manager->persist($tarteAuxPommes);

        $sorbetFruits = (new Plat())->setTitrePlat('Trio de sorbets artisanaux')
            ->setPhoto('https://images.unsplash.com/photo-1497034825429-c343d7c6a68f?w=400');
        $manager->persist($sorbetFruits);

        $profiteroles = (new Plat())->setTitrePlat('Profiteroles au chocolat chaud')
            ->setPhoto('https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400');
        $profiteroles->addAllergene($allergenes['Gluten'])->addAllergene($allergenes['Lait'])->addAllergene($allergenes['Œufs']);
        $manager->persist($profiteroles);

        $clafoutis = (new Plat())->setTitrePlat('Clafoutis aux cerises')
            ->setPhoto('https://images.unsplash.com/photo-1464305795204-6f5bbfc7fb81?w=400');
        $clafoutis->addAllergene($allergenes['Gluten'])->addAllergene($allergenes['Lait'])->addAllergene($allergenes['Œufs']);
        $manager->persist($clafoutis);

        // ============================================================
        // 8. MENUS
        // ============================================================

        // Menu 1 : Réveillon de Noël
        $menuNoel = (new Menu())
            ->setTitre('Menu Réveillon de Noël')
            ->setNombrePersonneMinimum(6)->setPrixParPersonne(45.00)
            ->setDescription('Un menu festif et généreux pour célébrer Noël en famille. Produits de saison soigneusement sélectionnés par notre chef, du foie gras maison à la traditionnelle bûche de Noël.')
            ->setConditions('Commande à passer au minimum 7 jours avant la prestation. Matériel de service prêté (assiettes, couverts, verres). Conservation au réfrigérateur dès réception.')
            ->setQuantiteRestante(10)->setTheme($themeNoel)->setRegime($regimeClassique)
            ->addPlat($foieGras)->addPlat($veloute)->addPlat($saumonFume)
            ->addPlat($dindeMarrons)->addPlat($saumonCroute)
            ->addPlat($bucheNoel)->addPlat($mousseChocolat)->addPlat($profiteroles);
        $manager->persist($menuNoel);
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1574484284002-952d92456975?w=600')->setMenu($menuNoel));
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=600')->setMenu($menuNoel));
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1482275548304-a58859dc31b7?w=600')->setMenu($menuNoel));

        // Menu 2 : Pâques Gourmand
        $menuPaques = (new Menu())
            ->setTitre('Menu Pâques Gourmand')
            ->setNombrePersonneMinimum(4)->setPrixParPersonne(38.00)
            ->setDescription('Célébrez Pâques avec un menu printanier et raffiné. Des saveurs délicates avec le gigot d\'agneau traditionnel et des desserts gourmands pour toute la famille.')
            ->setConditions('Commande à passer au minimum 5 jours avant la prestation. Conservation au réfrigérateur dès réception.')
            ->setQuantiteRestante(15)->setTheme($themePaques)->setRegime($regimeClassique)
            ->addPlat($oeufsMimosa)->addPlat($saladeChevreChaud)->addPlat($tartareSaumon)
            ->addPlat($gigotAgneau)->addPlat($filetMignon)
            ->addPlat($tarteCitron)->addPlat($cremeBrulee)->addPlat($clafoutis);
        $manager->persist($menuPaques);
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1490645935967-10de6ba17061?w=600')->setMenu($menuPaques));
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1514516345957-556ca7d90a29?w=600')->setMenu($menuPaques));

        // Menu 3 : Classique Tradition
        $menuClassique = (new Menu())
            ->setTitre('Menu Classique Tradition')
            ->setNombrePersonneMinimum(2)->setPrixParPersonne(28.00)
            ->setDescription('Un menu classique de la gastronomie française, idéal pour un repas convivial en toute simplicité. Des valeurs sûres cuisinées avec passion.')
            ->setConditions('Commande à passer au minimum 3 jours avant la prestation. Livraison en contenants isothermes.')
            ->setQuantiteRestante(20)->setTheme($themeClassique)->setRegime($regimeClassique)
            ->addPlat($terrine)->addPlat($soupeOignon)
            ->addPlat($boeufBourguignon)->addPlat($coqAuVin)->addPlat($gratinDauphinois)
            ->addPlat($mousseChocolat)->addPlat($fondantChocolat)->addPlat($tarteAuxPommes);
        $manager->persist($menuClassique);
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=600')->setMenu($menuClassique));
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1534939561126-855b8675edd7?w=600')->setMenu($menuClassique));

        // Menu 4 : Végétarien Découverte
        $menuVege = (new Menu())
            ->setTitre('Menu Végétarien Découverte')
            ->setNombrePersonneMinimum(2)->setPrixParPersonne(32.00)
            ->setDescription('Un menu 100% végétarien, savoureux et équilibré. Cuisine créative qui met en valeur les légumes de saison et les céréales.')
            ->setConditions('Commande à passer au minimum 3 jours avant la prestation. Merci d\'indiquer toute allergie lors de la commande.')
            ->setQuantiteRestante(12)->setTheme($themeClassique)->setRegime($regimeVegetarien)
            ->addPlat($bruschetta)->addPlat($saladeChevreChaud)
            ->addPlat($risotto)->addPlat($lasagnesVege)->addPlat($tagliatelles)
            ->addPlat($pannaCotta)->addPlat($saladeFruits)->addPlat($clafoutis);
        $manager->persist($menuVege);
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600')->setMenu($menuVege));
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1476124369491-e7addf5db371?w=600')->setMenu($menuVege));

        // Menu 5 : Évènement Prestige
        $menuPrestige = (new Menu())
            ->setTitre('Menu Évènement Prestige')
            ->setNombrePersonneMinimum(10)->setPrixParPersonne(55.00)
            ->setDescription('Le menu idéal pour vos grands évènements : mariages, anniversaires, galas. Service traiteur complet avec matériel de réception inclus.')
            ->setConditions('Commande à passer au minimum 14 jours avant la prestation. Matériel de service prêté (vaisselle, verrerie, nappes). Acompte de 30% à la commande. Restitution du matériel sous 10 jours ouvrés.')
            ->setQuantiteRestante(5)->setTheme($themeEvenement)->setRegime($regimeClassique)
            ->addPlat($foieGras)->addPlat($saumonFume)->addPlat($carpaccioBetteraves)
            ->addPlat($saumonCroute)->addPlat($filetMignon)->addPlat($canardOrange)->addPlat($gratinDauphinois)
            ->addPlat($fondantChocolat)->addPlat($tiramisu)->addPlat($cremeBrulee)->addPlat($profiteroles);
        $manager->persist($menuPrestige);
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1555244162-803834f70033?w=600')->setMenu($menuPrestige));
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1543339308-d595c4975a35?w=600')->setMenu($menuPrestige));
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=600')->setMenu($menuPrestige));

        // Menu 6 : Végan Bien-être
        $menuVegan = (new Menu())
            ->setTitre('Menu Végan Bien-être')
            ->setNombrePersonneMinimum(2)->setPrixParPersonne(30.00)
            ->setDescription('Un menu végan gourmand et coloré. Aucun produit d\'origine animale, pour un repas sain, respectueux et plein de saveurs.')
            ->setConditions('Commande à passer au minimum 3 jours avant la prestation. 100% végan certifié.')
            ->setQuantiteRestante(8)->setTheme($themeClassique)->setRegime($regimeVegan)
            ->addPlat($bruschetta)->addPlat($carpaccioBetteraves)->addPlat($gaspacho)->addPlat($houmous)
            ->addPlat($curryLegumes)->addPlat($ratatouille)
            ->addPlat($saladeFruits)->addPlat($sorbetFruits);
        $manager->persist($menuVegan);
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1540914124281-342587941389?w=600')->setMenu($menuVegan));
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1455619452474-d2be8b1e70cd?w=600')->setMenu($menuVegan));

        // Menu 7 : Sans Gluten Saveurs
        $menuSansGluten = (new Menu())
            ->setTitre('Menu Sans Gluten Saveurs')
            ->setNombrePersonneMinimum(2)->setPrixParPersonne(35.00)
            ->setDescription('Un menu entièrement sans gluten, sans compromis sur le goût. Plats élaborés pour les personnes intolérantes ou sensibles au gluten.')
            ->setConditions('Commande à passer au minimum 4 jours avant. Préparé dans un environnement contrôlé. Nous contacter pour toute allergie sévère.')
            ->setQuantiteRestante(10)->setTheme($themeClassique)->setRegime($regimeSansGluten)
            ->addPlat($carpaccioBetteraves)->addPlat($gaspacho)
            ->addPlat($gigotAgneau)->addPlat($dorade)->addPlat($risotto)
            ->addPlat($cremeBrulee)->addPlat($pannaCotta)->addPlat($sorbetFruits);
        $manager->persist($menuSansGluten);
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1490645935967-10de6ba17061?w=600')->setMenu($menuSansGluten));
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=600')->setMenu($menuSansGluten));

        // Menu 8 : Brunch du Dimanche
        $menuBrunch = (new Menu())
            ->setTitre('Brunch du Dimanche')
            ->setNombrePersonneMinimum(4)->setPrixParPersonne(25.00)
            ->setDescription('Un brunch copieux et convivial pour profiter du dimanche en famille ou entre amis. Sucré, salé, il y en a pour tous les goûts.')
            ->setConditions('Commande à passer au minimum 3 jours avant. Livraison le dimanche matin entre 9h et 11h uniquement.')
            ->setQuantiteRestante(18)->setTheme($themeClassique)->setRegime($regimeClassique)
            ->addPlat($oeufsMimosa)->addPlat($bruschetta)->addPlat($saumonFume)
            ->addPlat($saladeFruits)->addPlat($tarteAuxPommes)->addPlat($clafoutis)->addPlat($pannaCotta);
        $manager->persist($menuBrunch);
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1504754524776-8f4f37790ca0?w=600')->setMenu($menuBrunch));
        $manager->persist((new MenuImage())->setUrlImage('https://images.unsplash.com/photo-1482049016688-2d3e1b311543?w=600')->setMenu($menuBrunch));

        // ============================================================
        // 9. COMMANDES
        // ============================================================
        $cmd1 = (new Commande())->setNumeroCommande('CMD-2026-001')
            ->setDateCommande(new \DateTime('2025-12-10'))->setDatePrestation(new \DateTime('2025-12-24'))
            ->setLieuPrestation('15 rue du Château, 33000 Bordeaux')->setHeureLivraison('18:00')
            ->setPrixMenu(270.00)->setNombrePersonne(6)->setPrixLivraison(0.00)
            ->setStatut('terminée')->setPretMateriel(true)->setRestitutionMateriel(true)
            ->setUtilisateur($client1)->setMenu($menuNoel);
        $manager->persist($cmd1);

        $cmd2 = (new Commande())->setNumeroCommande('CMD-2026-002')
            ->setDateCommande(new \DateTime('2026-01-15'))->setDatePrestation(new \DateTime('2026-01-25'))
            ->setLieuPrestation('12 rue Jean Jaurès, 33600 Pessac')->setHeureLivraison('12:30')
            ->setPrixMenu(84.00)->setNombrePersonne(3)->setPrixLivraison(5.59)
            ->setStatut('terminée')->setPretMateriel(false)->setRestitutionMateriel(false)
            ->setUtilisateur($client2)->setMenu($menuClassique);
        $manager->persist($cmd2);

        $cmd3 = (new Commande())->setNumeroCommande('CMD-2026-003')
            ->setDateCommande(new \DateTime('2026-02-01'))->setDatePrestation(new \DateTime('2026-02-10'))
            ->setLieuPrestation('8 avenue de la Libération, 33400 Talence')->setHeureLivraison('19:30')
            ->setPrixMenu(128.00)->setNombrePersonne(4)->setPrixLivraison(5.59)
            ->setStatut('terminée')->setPretMateriel(false)->setRestitutionMateriel(false)
            ->setUtilisateur($client3)->setMenu($menuVege);
        $manager->persist($cmd3);

        $cmd4 = (new Commande())->setNumeroCommande('CMD-2026-004')
            ->setDateCommande(new \DateTime('2026-03-20'))->setDatePrestation(new \DateTime('2026-04-15'))
            ->setLieuPrestation('30 quai des Chartrons, 33000 Bordeaux')->setHeureLivraison('19:00')
            ->setPrixMenu(550.00)->setNombrePersonne(10)->setPrixLivraison(0.00)
            ->setStatut('accepté')->setPretMateriel(true)->setRestitutionMateriel(false)
            ->setUtilisateur($client4)->setMenu($menuPrestige);
        $manager->persist($cmd4);

        $cmd5 = (new Commande())->setNumeroCommande('CMD-2026-005')
            ->setDateCommande(new \DateTime('2026-03-25'))->setDatePrestation(new \DateTime('2026-04-20'))
            ->setLieuPrestation('5 avenue de la République, 33700 Mérignac')->setHeureLivraison('12:00')
            ->setPrixMenu(342.00)->setNombrePersonne(9)->setPrixLivraison(5.59)
            ->setStatut('en préparation')->setPretMateriel(false)->setRestitutionMateriel(false)
            ->setUtilisateur($client1)->setMenu($menuPaques);
        $manager->persist($cmd5);

        $cmd6 = (new Commande())->setNumeroCommande('CMD-2026-006')
            ->setDateCommande(new \DateTime('2026-03-28'))->setDatePrestation(new \DateTime('2026-04-05'))
            ->setLieuPrestation('3 place Abel Surchamp, 33500 Libourne')->setHeureLivraison('13:00')
            ->setPrixMenu(120.00)->setNombrePersonne(4)->setPrixLivraison(15.34)
            ->setStatut('en cours de livraison')->setPretMateriel(false)->setRestitutionMateriel(false)
            ->setUtilisateur($client5)->setMenu($menuVegan);
        $manager->persist($cmd6);

        $cmd7 = (new Commande())->setNumeroCommande('CMD-2026-007')
            ->setDateCommande(new \DateTime('2026-04-01'))->setDatePrestation(new \DateTime('2026-04-13'))
            ->setLieuPrestation('12 rue Jean Jaurès, 33600 Pessac')->setHeureLivraison('10:00')
            ->setPrixMenu(150.00)->setNombrePersonne(6)->setPrixLivraison(5.59)
            ->setStatut('accepté')->setPretMateriel(false)->setRestitutionMateriel(false)
            ->setUtilisateur($client2)->setMenu($menuBrunch);
        $manager->persist($cmd7);

        $cmd8 = (new Commande())->setNumeroCommande('CMD-2026-008')
            ->setDateCommande(new \DateTime('2026-02-14'))->setDatePrestation(new \DateTime('2026-02-22'))
            ->setLieuPrestation('30 quai des Chartrons, 33000 Bordeaux')->setHeureLivraison('20:00')
            ->setPrixMenu(70.00)->setNombrePersonne(2)->setPrixLivraison(0.00)
            ->setStatut('terminée')->setPretMateriel(false)->setRestitutionMateriel(false)
            ->setUtilisateur($client4)->setMenu($menuSansGluten);
        $manager->persist($cmd8);

        // ============================================================
        // 10. AVIS
        // ============================================================
        $manager->persist((new Avis())->setNote(5)
            ->setDescription('Menu de Noël exceptionnel ! Le foie gras était d\'une qualité remarquable et la bûche un pur délice. Toute la famille a adoré !')
            ->setStatut('validé')->setUtilisateur($client1)->setCommande($cmd1));

        $manager->persist((new Avis())->setNote(4)
            ->setDescription('Très bon bœuf bourguignon, vraiment savoureux. La mousse au chocolat était parfaite. Petit bémol : livraison avec 15 min de retard.')
            ->setStatut('validé')->setUtilisateur($client2)->setCommande($cmd2));

        $manager->persist((new Avis())->setNote(5)
            ->setDescription('Enfin un traiteur qui propose un vrai menu végétarien de qualité ! Le risotto aux champignons était incroyable. On recommande vivement.')
            ->setStatut('validé')->setUtilisateur($client3)->setCommande($cmd3));

        $manager->persist((new Avis())->setNote(4)
            ->setDescription('Très satisfaite du menu sans gluten. La dorade était parfaitement cuite et les sorbets un régal. Service impeccable.')
            ->setStatut('validé')->setUtilisateur($client4)->setCommande($cmd8));

        $manager->persist((new Avis())->setNote(3)
            ->setDescription('Menu correct mais les portions étaient un peu petites pour le prix. Le goût était bon cependant.')
            ->setStatut('en attente')->setUtilisateur($client5)->setCommande($cmd6));
            // ============================================================
        // SYNC MONGODB
        // ============================================================
    $manager->flush();

    $commandesMongo = [
        ['numero_commande' => 'CMD-2026-001', 'menu_titre' => 'Menu Réveillon de Noël', 'prix_total' => 270.00, 'date_commande' => '2025-12-10', 'statut' => 'terminée'],
        ['numero_commande' => 'CMD-2026-002', 'menu_titre' => 'Menu Classique Tradition', 'prix_total' => 84.00, 'date_commande' => '2026-01-15', 'statut' => 'terminée'],
        ['numero_commande' => 'CMD-2026-003', 'menu_titre' => 'Menu Végétarien Découverte', 'prix_total' => 128.00, 'date_commande' => '2026-02-01', 'statut' => 'terminée'],
        ['numero_commande' => 'CMD-2026-004', 'menu_titre' => 'Menu Évènement Prestige', 'prix_total' => 550.00, 'date_commande' => '2026-03-20', 'statut' => 'accepté'],
        ['numero_commande' => 'CMD-2026-005', 'menu_titre' => 'Menu Pâques Gourmand', 'prix_total' => 342.00, 'date_commande' => '2026-03-25', 'statut' => 'en préparation'],
        ['numero_commande' => 'CMD-2026-006', 'menu_titre' => 'Menu Végan Bien-être', 'prix_total' => 120.00, 'date_commande' => '2026-03-28', 'statut' => 'en cours de livraison'],
        ['numero_commande' => 'CMD-2026-007', 'menu_titre' => 'Brunch du Dimanche', 'prix_total' => 150.00, 'date_commande' => '2026-04-01', 'statut' => 'accepté'],
        ['numero_commande' => 'CMD-2026-008', 'menu_titre' => 'Menu Sans Gluten Saveurs', 'prix_total' => 70.00, 'date_commande' => '2026-02-14', 'statut' => 'terminée'],
    ];

    foreach ($commandesMongo as $cmdData) {
        $this->mongoDbService->syncCommande($cmdData);
    }

    }
}
