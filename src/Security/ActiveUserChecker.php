<?php
namespace App\Security;
use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
class ActiveUserChecker implements UserCheckerInterface {
 public function checkPreAuth(UserInterface $user):void{if($user instanceof User&&!$user->active)throw new CustomUserMessageAccountStatusException('Dieses Demokonto ist deaktiviert.');}
 public function checkPostAuth(UserInterface $user):void{$this->checkPreAuth($user);}
}
