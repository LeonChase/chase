<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_media_asset')]
#[ORM\Index(name:'portal_media_asset_scope',columns:['tenant_id','owner_id'])]
class MediaAsset extends Record {}
