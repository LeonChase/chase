<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_document')]
#[ORM\Index(name:'portal_document_scope',columns:['tenant_id','owner_id'])]
class Document extends Record {}
