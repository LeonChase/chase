<?php
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261020000300 extends AbstractMigration {
 public function getDescription():string{return 'Private internal messages and explicit task invitations';}
 public function up(Schema $schema):void {
  foreach(['portal_message','portal_task_invitation'] as $table){
   $this->addSql('CREATE TABLE '.$table.' (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, recipient_id VARCHAR(64) NOT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))');
   $this->addSql('CREATE INDEX '.$table.'_scope ON '.$table.' (tenant_id,owner_id)');
   $this->addSql('CREATE INDEX '.$table.'_recipient ON '.$table.' (tenant_id,recipient_id)');
  }
 }
 public function down(Schema $schema):void {$this->addSql('DROP TABLE portal_message');$this->addSql('DROP TABLE portal_task_invitation');}
}
