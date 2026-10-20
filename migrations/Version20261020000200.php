<?php
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261020000200 extends AbstractMigration {
 public function getDescription():string{return 'CRM identity as mapped column compatible with SQLite schema introspection';}
 public function up(Schema $schema):void {
  $this->addSql('DROP INDEX property_crm_identity');
  $this->addSql('ALTER TABLE portal_property ADD COLUMN external_id VARCHAR(120) DEFAULT NULL');
  $this->addSql("UPDATE portal_property SET external_id=json_extract(data, '$.externalId')");
  $this->addSql('CREATE UNIQUE INDEX property_crm_identity ON portal_property (tenant_id, external_id)');
 }
 public function down(Schema $schema):void {$this->throwIrreversibleMigrationException();}
}
