<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_partner_profile')]
#[ORM\Index(name:'portal_partner_profile_scope',columns:['tenant_id','owner_id'])]
class PartnerProfile extends Record {}
