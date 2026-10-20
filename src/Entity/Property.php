<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_property')]
#[ORM\Index(name:'portal_property_scope',columns:['tenant_id','owner_id'])]
#[ORM\UniqueConstraint(name:'property_crm_identity',columns:['tenant_id','external_id'])]
class Property extends Record {
 #[ORM\Column(length:120,nullable:true)] public ?string $externalId=null;
 public function __construct(array $data=[],?string $ownerId=null,string $tenantId='chase-demo') { parent::__construct($data,$ownerId,$tenantId);$this->externalId=$data['externalId']??null; }
}
