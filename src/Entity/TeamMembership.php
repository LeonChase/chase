<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_team_membership')]
#[ORM\Index(name:'portal_team_membership_scope',columns:['tenant_id','owner_id'])]
class TeamMembership extends Record {}
