<?php

namespace App\Service;

use App\Entity\Menu;

/**
 * Calcul centralisé du prix d'une commande.
 *
 * Toutes les règles tarifaires sont regroupées ici : les contrôleurs web et
 * l'API appellent ce service, ce qui évite que les formules divergent d'un
 * point d'entrée à l'autre.
 */
class TarificationService
{
    /** Remise appliquée sur le prix du menu pour les grosses commandes (10 %). */
    public const REMISE_GROS_VOLUME = 0.10;

    /** Nombre de personnes au-dessus du minimum du menu déclenchant la remise. */
    public const SEUIL_REMISE = 5;

    /** Forfait de prise en charge dû pour toute livraison hors zone gratuite. */
    public const FORFAIT_LIVRAISON = 5.0;

    /** Prix au kilomètre, facturé en plus du forfait. */
    public const PRIX_PAR_KM = 0.59;

    /** Codes postaux de Bordeaux : livraison offerte. */
    public const CODES_POSTAUX_ZONE_GRATUITE = ['33000', '33100', '33200', '33300', '33800'];

    /** Département desservi (Gironde). */
    public const DEPARTEMENT_LIVRE = '33';

    /** Distance maximale acceptée, en kilomètres. */
    public const DISTANCE_MAX_KM = 150;

    /**
     * Prix du menu : prix par personne x nombre de personnes, moins la remise
     * gros volume le cas échéant.
     */
    public function calculerPrixMenu(Menu $menu, int $nbPersonnes): float
    {
        $prix = $menu->getPrixParPersonne() * $nbPersonnes;

        if ($this->beneficieDeLaRemise($menu, $nbPersonnes)) {
            $prix *= (1 - self::REMISE_GROS_VOLUME);
        }

        return round($prix, 2);
    }

    public function beneficieDeLaRemise(Menu $menu, int $nbPersonnes): bool
    {
        return $nbPersonnes >= $menu->getNombrePersonneMinimum() + self::SEUIL_REMISE;
    }

    /**
     * Prix de la livraison : offerte à Bordeaux, sinon forfait + prix au km.
     *
     * Une distance nulle ou inconnue hors de la zone gratuite ne rend pas la
     * livraison gratuite : le forfait reste dû.
     */
    public function calculerPrixLivraison(string $lieuPrestation, ?int $distanceKm): float
    {
        if ($this->estZoneGratuite($lieuPrestation)) {
            return 0.0;
        }

        $km = min(max(0, $distanceKm ?? 0), self::DISTANCE_MAX_KM);

        return round(self::FORFAIT_LIVRAISON + self::PRIX_PAR_KM * $km, 2);
    }

    /**
     * Calcul complet, à utiliser dès qu'une commande est créée ou modifiée.
     *
     * @return array{prix_menu: float, prix_livraison: float, prix_total: float}
     */
    public function calculer(Menu $menu, int $nbPersonnes, string $lieuPrestation, ?int $distanceKm): array
    {
        $prixMenu = $this->calculerPrixMenu($menu, $nbPersonnes);
        $prixLivraison = $this->calculerPrixLivraison($lieuPrestation, $distanceKm);

        return [
            'prix_menu' => $prixMenu,
            'prix_livraison' => $prixLivraison,
            'prix_total' => round($prixMenu + $prixLivraison, 2),
        ];
    }

    /**
     * Zone de livraison offerte, déterminée sur le code postal de l'adresse.
     * À défaut de code postal, on retombe sur le nom de la ville en fin
     * d'adresse : « rue de Bordeaux, Arcachon » n'est donc pas gratuit.
     */
    public function estZoneGratuite(string $lieuPrestation): bool
    {
        $codePostal = $this->extraireCodePostal($lieuPrestation);

        if ($codePostal !== null) {
            return in_array($codePostal, self::CODES_POSTAUX_ZONE_GRATUITE, true);
        }

        return (bool) preg_match('/\bbordeaux\s*$/iu', trim($lieuPrestation));
    }

    /** Vrai si l'adresse porte un code postal hors du département desservi. */
    public function estHorsZoneDeLivraison(string $lieuPrestation): bool
    {
        $codePostal = $this->extraireCodePostal($lieuPrestation);

        return $codePostal !== null && !str_starts_with($codePostal, self::DEPARTEMENT_LIVRE);
    }

    /**
     * Vrai si la distance est indispensable au calcul et n'a pas été renseignée.
     * Sert à demander l'information au client plutôt que de le sous-facturer.
     */
    public function distanceRequise(string $lieuPrestation, ?int $distanceKm): bool
    {
        return !$this->estZoneGratuite($lieuPrestation) && ($distanceKm === null || $distanceKm <= 0);
    }

    private function extraireCodePostal(string $lieuPrestation): ?string
    {
        return preg_match('/\b(\d{5})\b/', $lieuPrestation, $matches) ? $matches[1] : null;
    }
}
