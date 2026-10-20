<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_lead')]
#[ORM\Index(name:'portal_lead_scope',columns:['tenant_id','owner_id'])]
class Lead extends Record {}
