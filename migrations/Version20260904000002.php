<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reprise de données : reconstitue distance_km pour les commandes créées avant
 * l'ajout de la colonne.
 *
 * L'ancienne formule de livraison était « forfait 5 € + 0,59 €/km », donc la
 * distance se déduit du montant facturé : (prix_livraison - 5) / 0,59.
 * Sans cette reprise, la modification d'une commande existante recalculerait
 * la livraison au seul forfait et perdrait la part kilométrique déjà facturée.
 *
 * Sont volontairement écartées :
 *  - les livraisons à 0 € (zone gratuite, la distance n'a pas de sens) ;
 *  - les livraisons à 5 € exactement (forfait seul, la distance vaut déjà 0).
 */
final class Version20260904000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reconstitue distance_km depuis prix_livraison pour les commandes existantes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE commande
                SET distance_km = ROUND((prix_livraison - 5) / 0.59)::int
              WHERE distance_km IS NULL
                AND prix_livraison > 5'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE commande SET distance_km = NULL');
    }
}
