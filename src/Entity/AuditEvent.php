<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_audit_event')]
#[ORM\Index(name:'portal_audit_event_scope',columns:['tenant_id','owner_id'])]
class AuditEvent extends Record {}
