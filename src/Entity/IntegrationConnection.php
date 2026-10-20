<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_integration_connection')]
#[ORM\Index(name:'portal_integration_connection_scope',columns:['tenant_id','owner_id'])]
class IntegrationConnection extends Record {}
