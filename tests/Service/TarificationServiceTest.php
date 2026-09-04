<?php

namespace App\Tests\Service;

use App\Entity\Menu;
use App\Service\TarificationService;
use PHPUnit\Framework\TestCase;

class TarificationServiceTest extends TestCase
{
    private TarificationService $tarification;

    protected function setUp(): void
    {
        $this->tarification = new TarificationService();
    }

    private function menu(float $prixParPersonne, int $minimum): Menu
    {
        return (new Menu())
            ->setTitre('Menu de test')
            ->setPrixParPersonne($prixParPersonne)
            ->setNombrePersonneMinimum($minimum)
            ->setDescription('Menu utilisé par les tests.');
    }

    public function testPrixMenuSansRemise(): void
    {
        self::assertSame(270.00, $this->tarification->calculerPrixMenu($this->menu(45.00, 6), 6));
    }

    public function testRemiseDesCinqPersonnesAuDessusDuMinimum(): void
    {
        $menu = $this->menu(45.00, 6);

        // 11 personnes = minimum + 5 : 495,00 - 10 %
        self::assertSame(445.50, $this->tarification->calculerPrixMenu($menu, 11));
        // 10 personnes : seuil non atteint, plein tarif
        self::assertSame(450.00, $this->tarification->calculerPrixMenu($menu, 10));
    }

    public function testPrixMenuArrondiAuCentime(): void
    {
        // 89,90 x 12 = 1078,80 puis -10 % : la multiplication flottante
        // donne 970,9200000000002 et doit être arrondie.
        self::assertSame(970.92, $this->tarification->calculerPrixMenu($this->menu(89.90, 6), 12));
    }

    public function testLivraisonOfferteSurUnCodePostalBordelais(): void
    {
        self::assertSame(0.0, $this->tarification->calculerPrixLivraison('15 rue du Château, 33000 Bordeaux', 12));
    }

    public function testLivraisonFactureeAuKilometreHorsBordeaux(): void
    {
        self::assertSame(22.70, $this->tarification->calculerPrixLivraison('3 place Abel Surchamp, 33500 Libourne', 30));
    }

    public function testDistanceInconnueNeRendPasLaLivraisonGratuite(): void
    {
        // Régression : une distance à 0 ou absente offrait la livraison.
        self::assertSame(5.0, $this->tarification->calculerPrixLivraison('3 place Abel Surchamp, 33500 Libourne', null));
        self::assertSame(5.0, $this->tarification->calculerPrixLivraison('3 place Abel Surchamp, 33500 Libourne', 0));
    }

    public function testDistancePlafonnee(): void
    {
        $plafond = round(
            TarificationService::FORFAIT_LIVRAISON
            + TarificationService::PRIX_PAR_KM * TarificationService::DISTANCE_MAX_KM,
            2
        );

        self::assertSame($plafond, $this->tarification->calculerPrixLivraison('33500 Libourne', 10000));
    }

    public function testUneAdresseQuiContientBordeauxSansEnEtreNEstPasGratuite(): void
    {
        self::assertFalse($this->tarification->estZoneGratuite('12 rue de Bordeaux, 33120 Arcachon'));
        self::assertTrue($this->tarification->estZoneGratuite('20 rue de la Paix, Bordeaux'));
        self::assertTrue($this->tarification->estZoneGratuite('30 quai des Chartrons, 33000 Bordeaux'));
    }

    public function testDetectionHorsZoneDeLivraison(): void
    {
        self::assertTrue($this->tarification->estHorsZoneDeLivraison('42 avenue des Fleurs, 69000 Lyon'));
        self::assertFalse($this->tarification->estHorsZoneDeLivraison('12 rue Jean Jaurès, 33600 Pessac'));
        self::assertFalse($this->tarification->estHorsZoneDeLivraison('20 rue de la Paix, Bordeaux'));
    }

    public function testDistanceRequiseUniquementHorsZoneGratuite(): void
    {
        self::assertTrue($this->tarification->distanceRequise('12 rue Jean Jaurès, 33600 Pessac', null));
        self::assertTrue($this->tarification->distanceRequise('12 rue Jean Jaurès, 33600 Pessac', 0));
        self::assertFalse($this->tarification->distanceRequise('12 rue Jean Jaurès, 33600 Pessac', 7));
        self::assertFalse($this->tarification->distanceRequise('33000 Bordeaux', null));
    }

    public function testCalculComplet(): void
    {
        $prix = $this->tarification->calculer(
            $this->menu(28.00, 2),
            4,
            '12 rue Jean Jaurès, 33600 Pessac',
            7
        );

        self::assertSame(112.00, $prix['prix_menu']);
        self::assertSame(9.13, $prix['prix_livraison']);
        self::assertSame(121.13, $prix['prix_total']);
    }
}
