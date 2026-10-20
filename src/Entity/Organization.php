<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_organization')]
#[ORM\Index(name:'portal_organization_scope',columns:['tenant_id','owner_id'])]
class Organization extends Record {}
