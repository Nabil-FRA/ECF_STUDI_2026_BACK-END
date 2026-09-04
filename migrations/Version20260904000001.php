<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute distance_km sur commande : la distance saisie à la commande doit être
 * conservée pour que le prix de livraison puisse être recalculé lors d'une
 * modification.
 */
final class Version20260904000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne distance_km sur la table commande';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD distance_km INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP distance_km');
    }
}
