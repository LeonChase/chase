<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_valuation')]
#[ORM\Index(name:'portal_valuation_scope',columns:['tenant_id','owner_id'])]
class Valuation extends Record {}
