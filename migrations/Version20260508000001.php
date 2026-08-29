<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Augmente la longueur de titre_plat de 50 à 150 caractères.
 */
final class Version20260508000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Augmente titre_plat de VARCHAR(50) à VARCHAR(150)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plat ALTER COLUMN titre_plat TYPE VARCHAR(150)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plat ALTER COLUMN titre_plat TYPE VARCHAR(50)');
    }
}
