<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\MappedSuperclass]
abstract class Record {
 #[ORM\Id, ORM\Column(length:64)] public string $id;
 #[ORM\Column(length:80)] public string $tenantId;
 #[ORM\Column(length:64,nullable:true)] public ?string $ownerId;
 #[ORM\Column(length:250)] public string $title;
 #[ORM\Column(type:'json')] public array $data=[];
 #[ORM\Column(type:'datetime_immutable')] public \DateTimeImmutable $createdAt;
 #[ORM\Column(type:'datetime_immutable')] public \DateTimeImmutable $updatedAt;
 public function __construct(array $data=[],?string $ownerId=null,string $tenantId='chase-demo') {
  $this->id=bin2hex(random_bytes(12));$this->tenantId=$tenantId;$this->ownerId=$ownerId;
  $this->title=(string)($data['title']??$data['name']??'');$this->data=$data;$this->createdAt=$this->updatedAt=new \DateTimeImmutable();
 }
 public function __get(string $key):mixed { return $this->data[$key]??null; }
 public function __isset(string $key):bool { return array_key_exists($key,$this->data); }
}
