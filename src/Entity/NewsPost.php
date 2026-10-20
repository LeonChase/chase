<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_news_post')]
#[ORM\Index(name:'portal_news_post_scope',columns:['tenant_id','owner_id'])]
class NewsPost extends Record {}
