<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
#[ORM\Entity]
#[ORM\Table(name:'portal_user')]
#[ORM\UniqueConstraint(name:'user_email',columns:['email'])]
#[ORM\Index(name:'user_tenant',columns:['tenant_id'])]
class User implements UserInterface,PasswordAuthenticatedUserInterface,\Symfony\Component\Security\Core\User\EquatableInterface {
 #[ORM\Id,ORM\Column(length:64)] public string $id;
 #[ORM\Column(length:80)] public string $tenantId='chase-demo';
 #[ORM\Column(length:150)] public string $name='';
 #[ORM\Column(length:180)] public string $email='';
 #[ORM\Column(length:255)] public string $password='';
 #[ORM\Column(type:'json')] public array $roles=['ROLE_AGENT'];
 #[ORM\Column(length:180)] public string $region='';
 #[ORM\Column(type:'boolean')] public bool $active=true;
 #[ORM\Column(type:'integer')] public int $budgetCredits=100;
 #[ORM\Column(type:'json')] public array $preferences=[];
 #[ORM\Column(type:'datetime_immutable')] public \DateTimeImmutable $createdAt;
 #[ORM\Column(type:'datetime_immutable')] public \DateTimeImmutable $updatedAt;
 public function __construct(){ $this->id=bin2hex(random_bytes(12));$this->createdAt=$this->updatedAt=new \DateTimeImmutable(); }
 public function getUserIdentifier():string{return $this->email;}
 public function getPassword():?string{return $this->password;}
 public function setPassword(string $password):self{$this->password=$password;return $this;}
 public function getRoles():array{return array_values(array_unique([...$this->roles,'ROLE_USER']));}
 public function isEqualTo(UserInterface $user):bool{return $user instanceof self&&$this->id===$user->id&&$this->tenantId===$user->tenantId&&$this->active===$user->active&&$this->roles===$user->roles&&$this->password===$user->password;}
 public function eraseCredentials():void{}
}
