<?php
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261020000400 extends AbstractMigration {
 public function getDescription():string{return 'Tenant-specific advertising catalogue categories';}
 public function up(Schema $schema):void{$this->addSql('CREATE TABLE portal_catalog_category (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))');$this->addSql('CREATE INDEX portal_catalog_category_scope ON portal_catalog_category (tenant_id,owner_id)');}
 public function down(Schema $schema):void{$this->addSql('DROP TABLE portal_catalog_category');}
}
