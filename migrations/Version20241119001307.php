<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241119001307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE php (id INT AUTO_INCREMENT NOT NULL, version VARCHAR(3) NOT NULL, start_servers INT NOT NULL, max_children INT NOT NULL, min_spare INT NOT NULL, max_spare INT NOT NULL, upload_size INT NOT NULL, error_log VARCHAR(255) DEFAULT NULL, slow_log VARCHAR(255) DEFAULT NULL, additional_config LONGTEXT DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_569121D1A76ED395 (user_id), UNIQUE INDEX version_user_idx (version, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE php ADD CONSTRAINT FK_569121D1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE php DROP FOREIGN KEY FK_569121D1A76ED395');
        $this->addSql('DROP TABLE php');
    }
}
