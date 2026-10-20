<?php
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261020000100 extends AbstractMigration {
 public function getDescription():string{return 'CHASE tenant-scoped local portal model and CRM uniqueness';}
 public function up(Schema $schema):void{
  $this->addSql("CREATE TABLE portal_user (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, name VARCHAR(150) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles CLOB NOT NULL, region VARCHAR(180) NOT NULL, active BOOLEAN NOT NULL, budget_credits INTEGER NOT NULL, preferences CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE UNIQUE INDEX user_email ON portal_user (email)");
  $this->addSql("CREATE INDEX user_tenant ON portal_user (tenant_id)");
  $this->addSql("CREATE TABLE portal_property (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_property_scope ON portal_property (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_lead (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_lead_scope ON portal_lead (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_task (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_task_scope ON portal_task (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_document (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_document_scope ON portal_document (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_content_post (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_content_post_scope ON portal_content_post (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_news_post (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_news_post_scope ON portal_news_post (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_media_asset (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_media_asset_scope ON portal_media_asset (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_usage_event (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_usage_event_scope ON portal_usage_event (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_credit_ledger (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_credit_ledger_scope ON portal_credit_ledger (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_audit_event (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_audit_event_scope ON portal_audit_event (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_integration_connection (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_integration_connection_scope ON portal_integration_connection (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_organization (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_organization_scope ON portal_organization (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_region (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_region_scope ON portal_region (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_team_membership (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_team_membership_scope ON portal_team_membership (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_academy_article (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_academy_article_scope ON portal_academy_article (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_advertising_product (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_advertising_product_scope ON portal_advertising_product (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_partner_profile (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_partner_profile_scope ON portal_partner_profile (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_user_preference (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_user_preference_scope ON portal_user_preference (tenant_id,owner_id)");
  $this->addSql("CREATE TABLE portal_valuation (id VARCHAR(64) NOT NULL, tenant_id VARCHAR(80) NOT NULL, owner_id VARCHAR(64) DEFAULT NULL, title VARCHAR(250) NOT NULL, data CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))");
  $this->addSql("CREATE INDEX portal_valuation_scope ON portal_valuation (tenant_id,owner_id)");
  $this->addSql("CREATE UNIQUE INDEX property_crm_identity ON portal_property (tenant_id,json_extract(data, '$.externalId')) WHERE json_extract(data, '$.externalId') IS NOT NULL");
 }
 public function down(Schema $schema):void {
  $this->addSql("DROP TABLE portal_user");
  $this->addSql("DROP TABLE portal_property");
  $this->addSql("DROP TABLE portal_lead");
  $this->addSql("DROP TABLE portal_task");
  $this->addSql("DROP TABLE portal_document");
  $this->addSql("DROP TABLE portal_content_post");
  $this->addSql("DROP TABLE portal_news_post");
  $this->addSql("DROP TABLE portal_media_asset");
  $this->addSql("DROP TABLE portal_usage_event");
  $this->addSql("DROP TABLE portal_credit_ledger");
  $this->addSql("DROP TABLE portal_audit_event");
  $this->addSql("DROP TABLE portal_integration_connection");
  $this->addSql("DROP TABLE portal_organization");
  $this->addSql("DROP TABLE portal_region");
  $this->addSql("DROP TABLE portal_team_membership");
  $this->addSql("DROP TABLE portal_academy_article");
  $this->addSql("DROP TABLE portal_advertising_product");
  $this->addSql("DROP TABLE portal_partner_profile");
  $this->addSql("DROP TABLE portal_user_preference");
  $this->addSql("DROP TABLE portal_valuation");
 }
}
