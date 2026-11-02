<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_catalog_category')]
#[ORM\Index(name:'portal_catalog_category_scope',columns:['tenant_id','owner_id'])]
class CatalogCategory extends Record {}
