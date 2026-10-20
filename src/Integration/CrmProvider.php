<?php
namespace App\Integration;
use App\Entity\User;
interface CrmProvider {
 public function listProperties(User $user):array;
 public function getProperty(string $id,User $user):?array;
 public function syncProperties(User $user):array;
}
