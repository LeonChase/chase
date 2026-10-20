<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_task_invitation')]
#[ORM\Index(name:'portal_task_invitation_scope',columns:['tenant_id','owner_id'])]
#[ORM\Index(name:'portal_task_invitation_recipient',columns:['tenant_id','recipient_id'])]
class TaskInvitation extends Record {
 #[ORM\Column(length:64)] public string $recipientId='';
 public function __construct(array $data=[],?string $ownerId=null,string $tenantId='chase-demo') {parent::__construct($data,$ownerId,$tenantId);$this->recipientId=(string)($data['recipientId']??'');}
}
