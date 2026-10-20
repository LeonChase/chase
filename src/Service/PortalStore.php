<?php
namespace App\Service;
use App\Entity\{Record,User,Document,AcademyArticle,AdvertisingProduct,NewsPost,PartnerProfile,TeamMembership,UserPreference,UsageEvent,CreditLedger,Message,TaskInvitation};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
class PortalStore {
 public function __construct(private EntityManagerInterface $entityManager){}
 public function em():EntityManagerInterface{return $this->entityManager;}
 public function all(string $class):array{return $this->entityManager->getRepository($class)->findBy([],['createdAt'=>'DESC']);}
 public function visibleOwnerIds(User $user):array {
  $ids=[$user->id];
  if(in_array('ROLE_REGION_MANAGER',$user->roles,true)){
   foreach($this->entityManager->getRepository(TeamMembership::class)->findBy(['tenantId'=>$user->tenantId,'ownerId'=>$user->id]) as $membership){
    if(($membership->data['active']??true)&&($membership->data['managerId']??$membership->ownerId)===$user->id&&isset($membership->data['memberId']))$ids[]=$membership->data['memberId'];
   }
  } return array_values(array_unique($ids));
 }
 public function canAccess(Record $record,User $user):bool {
  if(!$user->active||$record->tenantId!==$user->tenantId)return false;
  if($record instanceof Message||$record instanceof TaskInvitation)return $record->ownerId===$user->id||$record->recipientId===$user->id;
  if($record instanceof UserPreference||$record instanceof UsageEvent||$record instanceof CreditLedger)return $record->ownerId===$user->id;
  if($record instanceof Document){
   $audience=$record->data['audience']??'Alle Makler';
   if(str_contains($audience,'Regionalleitung'))return (bool)array_intersect($user->roles,['ROLE_REGION_MANAGER','ROLE_ADMIN','ROLE_SUPER_ADMIN']);
   if(str_contains($audience,'Admin'))return (bool)array_intersect($user->roles,['ROLE_ADMIN','ROLE_SUPER_ADMIN']);
   return true;
  }
  if($record instanceof NewsPost){return ($record->data['status']??'')==='Veröffentlicht'||(bool)array_intersect($user->roles,['ROLE_ADMIN','ROLE_SUPER_ADMIN']);}
  if($record instanceof PartnerProfile)return (bool)($record->data['visibleToPartners']??false);
  if($record instanceof AcademyArticle||$record instanceof AdvertisingProduct)return true;
  return $record->ownerId!==null&&in_array($record->ownerId,$this->visibleOwnerIds($user),true);
 }
 public function visible(string $class,User $user):array {
  if(!$user->active)return [];
  if(in_array($class,[Message::class,TaskInvitation::class],true))return $this->entityManager->getRepository($class)->createQueryBuilder('r')->where('r.tenantId = :tenant')->andWhere('r.ownerId = :uid OR r.recipientId = :uid')->setParameter('tenant',$user->tenantId)->setParameter('uid',$user->id)->orderBy('r.createdAt','DESC')->getQuery()->getResult();
  $shared=in_array($class,[Document::class,AcademyArticle::class,AdvertisingProduct::class,NewsPost::class,PartnerProfile::class],true);
  $criteria=['tenantId'=>$user->tenantId];if(!$shared)$criteria['ownerId']=in_array($class,[UserPreference::class,UsageEvent::class,CreditLedger::class],true)?[$user->id]:$this->visibleOwnerIds($user);
  return array_values(array_filter($this->entityManager->getRepository($class)->findBy($criteria,['createdAt'=>'DESC']),fn(Record $r)=>$this->canAccess($r,$user)));
 }
 public function findVisible(string $class,string $id,User $user):Record{
  $r=$this->entityManager->find($class,$id);if(!$r||!$this->canAccess($r,$user))throw new NotFoundHttpException('Eintrag nicht verfügbar.');return $r;
 }
 public function save(Record|User $record):void {$record->updatedAt=new \DateTimeImmutable();if($record instanceof \App\Entity\Property)$record->externalId=$record->data['externalId']??null;$this->entityManager->persist($record);$this->entityManager->flush();}
 public function remove(Record $record):void{$this->entityManager->remove($record);$this->entityManager->flush();}
}
