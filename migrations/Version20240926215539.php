<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240926215539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE domain (id INT AUTO_INCREMENT NOT NULL, ssl_cert_id INT NOT NULL, owner_id INT NOT NULL, fqdn VARCHAR(255) NOT NULL, webroot VARCHAR(255) NOT NULL, redirect_to_ssl TINYINT(1) NOT NULL, custom_config LONGTEXT DEFAULT NULL, custom_config_ssl LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, INDEX IDX_A7A91E0B3B407888 (ssl_cert_id), INDEX IDX_A7A91E0B7E3C61F9 (owner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE domain_ip_address (domain_id INT NOT NULL, ip_address_id INT NOT NULL, INDEX IDX_B37DC9C9115F0EE5 (domain_id), INDEX IDX_B37DC9C95F23F921 (ip_address_id), PRIMARY KEY(domain_id, ip_address_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE domain_alias (id INT AUTO_INCREMENT NOT NULL, domain_id_id INT NOT NULL, domain_name VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, INDEX IDX_32174342AC3FB681 (domain_id_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ip_address (id INT AUTO_INCREMENT NOT NULL, ssl_cert_id INT DEFAULT NULL, ip_address VARCHAR(40) NOT NULL, is_active TINYINT(1) NOT NULL, INDEX IDX_22FFD58C3B407888 (ssl_cert_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ssl_cert (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, crt_file VARCHAR(255) NOT NULL, key_file VARCHAR(255) NOT NULL, ca_file VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE system_user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, parent_user_id INT DEFAULT NULL, username VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, uid INT NOT NULL, home_dir VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, INDEX IDX_8D93D649D526A7D3 (parent_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE domain ADD CONSTRAINT FK_A7A91E0B3B407888 FOREIGN KEY (ssl_cert_id) REFERENCES ssl_cert (id)');
        $this->addSql('ALTER TABLE domain ADD CONSTRAINT FK_A7A91E0B7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE domain_ip_address ADD CONSTRAINT FK_B37DC9C9115F0EE5 FOREIGN KEY (domain_id) REFERENCES domain (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE domain_ip_address ADD CONSTRAINT FK_B37DC9C95F23F921 FOREIGN KEY (ip_address_id) REFERENCES ip_address (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE domain_alias ADD CONSTRAINT FK_32174342AC3FB681 FOREIGN KEY (domain_id_id) REFERENCES domain (id)');
        $this->addSql('ALTER TABLE ip_address ADD CONSTRAINT FK_22FFD58C3B407888 FOREIGN KEY (ssl_cert_id) REFERENCES ssl_cert (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649D526A7D3 FOREIGN KEY (parent_user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE domain DROP FOREIGN KEY FK_A7A91E0B3B407888');
        $this->addSql('ALTER TABLE domain DROP FOREIGN KEY FK_A7A91E0B7E3C61F9');
        $this->addSql('ALTER TABLE domain_ip_address DROP FOREIGN KEY FK_B37DC9C9115F0EE5');
        $this->addSql('ALTER TABLE domain_ip_address DROP FOREIGN KEY FK_B37DC9C95F23F921');
        $this->addSql('ALTER TABLE domain_alias DROP FOREIGN KEY FK_32174342AC3FB681');
        $this->addSql('ALTER TABLE ip_address DROP FOREIGN KEY FK_22FFD58C3B407888');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649D526A7D3');
        $this->addSql('DROP TABLE domain');
        $this->addSql('DROP TABLE domain_ip_address');
        $this->addSql('DROP TABLE domain_alias');
        $this->addSql('DROP TABLE ip_address');
        $this->addSql('DROP TABLE ssl_cert');
        $this->addSql('DROP TABLE system_user');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
