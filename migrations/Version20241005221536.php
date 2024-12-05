<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241005221536 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ip_address ADD owner_id INT NOT NULL, ADD webroot VARCHAR(255) NOT NULL, ADD redirect_to_ssl TINYINT(1) NOT NULL, ADD php_version VARCHAR(3) NOT NULL');
        $this->addSql('ALTER TABLE ip_address ADD CONSTRAINT FK_22FFD58C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_22FFD58C7E3C61F9 ON ip_address (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ip_address DROP FOREIGN KEY FK_22FFD58C7E3C61F9');
        $this->addSql('DROP INDEX IDX_22FFD58C7E3C61F9 ON ip_address');
        $this->addSql('ALTER TABLE ip_address DROP owner_id, DROP webroot, DROP redirect_to_ssl, DROP php_version');
    }
}
