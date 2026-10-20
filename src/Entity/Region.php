<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_region')]
#[ORM\Index(name:'portal_region_scope',columns:['tenant_id','owner_id'])]
class Region extends Record {}
