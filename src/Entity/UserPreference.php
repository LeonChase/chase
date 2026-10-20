<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_user_preference')]
#[ORM\Index(name:'portal_user_preference_scope',columns:['tenant_id','owner_id'])]
class UserPreference extends Record {}
