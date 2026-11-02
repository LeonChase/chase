<?php
namespace App\Service;
final class ToolLinks
{
 public function __construct(private string $projectDir){}
 public function all():array{return json_decode(file_get_contents($this->projectDir.'/data/tool-links.json'),true,512,JSON_THROW_ON_ERROR);}
 public function find(string $id):?array{foreach($this->all() as $link)if($link['id']===$id)return $link;return null;}
}
