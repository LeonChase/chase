<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'portal_academy_article')]
#[ORM\Index(name:'portal_academy_article_scope',columns:['tenant_id','owner_id'])]
class AcademyArticle extends Record {}
