<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_task')]
#[ORM\Index(name:'portal_task_scope',columns:['tenant_id','owner_id'])]
class Task extends Record {}
