<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_advertising_product')]
#[ORM\Index(name:'portal_advertising_product_scope',columns:['tenant_id','owner_id'])]
class AdvertisingProduct extends Record {}
