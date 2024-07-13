<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240713200759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE excluded_category (id INT AUTO_INCREMENT NOT NULL, node INT NOT NULL, UNIQUE INDEX UNIQ_AC3F1968857FE845 (node), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE offer_configuration (id INT AUTO_INCREMENT NOT NULL, domain INT NOT NULL, min_percentage INT NOT NULL, max_percentage INT NOT NULL, minimum_price DOUBLE PRECISION NOT NULL, channel_id VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE excluded_category');
        $this->addSql('DROP TABLE offer_configuration');
    }
}
