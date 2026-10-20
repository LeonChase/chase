<?php
namespace App\Service;
use App\Entity\{User,NewsPost,UserPreference};
use Symfony\Bundle\SecurityBundle\Security;
class PortalContext {
 public function __construct(private Security $security,private PortalStore $store,private string $projectDir){}
 public function getBrand():array{return json_decode(file_get_contents($this->projectDir.'/data/brand.json'),true,512,JSON_THROW_ON_ERROR);}
 public function getInitials():string{$u=$this->security->getUser();if(!$u instanceof User)return 'CH';$p=preg_split('/\s+/u',$u->name);return mb_strtoupper(mb_substr($p[0]??'',0,1).mb_substr($p[1]??'',0,1));}
 public function getUnreadNews():int{
  $u=$this->security->getUser();if(!$u instanceof User)return 0;
  $prefs=$this->store->visible(UserPreference::class,$u);$read=[];
  foreach($prefs as $p)if(in_array($p->data['kind']??'',['read','news_read'],true))$read[]=$p->data['targetId']??$p->data['newsId']??'';
  $read=array_merge($read,$u->preferences['readNews']??[]);
  return count(array_filter($this->store->visible(NewsPost::class,$u),fn($n)=>($n->data['status']??'')==='Veröffentlicht'&&!in_array($n->id,$read,true)));
 }

 public function getUnreadMessages():int {
  $u=$this->security->getUser();if(!$u instanceof User)return 0;
  return count(array_filter($this->store->visible(\App\Entity\Message::class,$u),fn($m)=>$m->recipientId===$u->id&&empty($m->data['readAt'])));
 }
 public function getRecentNews():array {
  $u=$this->security->getUser();if(!$u instanceof User)return [];
  $items=array_values(array_filter($this->store->visible(NewsPost::class,$u),fn($n)=>($n->data['status']??'')==='Veröffentlicht'));
  usort($items,fn($a,$b)=>strcmp($b->data['publishedAt']??'',$a->data['publishedAt']??''));return array_slice($items,0,3);
 }
}
