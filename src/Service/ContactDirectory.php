<?php
namespace App\Service;
use App\Entity\{User,PartnerProfile};
final class ContactDirectory {
 public function __construct(private PortalStore $store){}
 public function contacts(User $viewer):array {
  $ids=[];foreach($this->store->visible(PartnerProfile::class,$viewer) as $profile){if($profile->data['visibleToPartners']??false)$ids[]=$profile->data['userId']??'';}
  foreach($this->store->visible(\App\Entity\Message::class,$viewer) as $message)$ids[]=$message->ownerId===$viewer->id?$message->recipientId:$message->ownerId;
  $users=$this->store->em()->getRepository(User::class)->findBy(['tenantId'=>$viewer->tenantId,'active'=>true],['name'=>'ASC']);
  return array_values(array_filter($users,fn(User $u)=>$u->id!==$viewer->id&&(in_array($u->id,$ids,true)||(bool)array_intersect($u->roles,['ROLE_ADMIN','ROLE_SUPER_ADMIN']))));
 }
 public function recipient(string $id,User $viewer):?User {foreach($this->contacts($viewer) as $u)if($u->id===$id)return $u;return null;}
}
