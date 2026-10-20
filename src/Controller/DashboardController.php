<?php
namespace App\Controller;
use App\Entity\{Property,Task,Lead,NewsPost};
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
class DashboardController extends PortalController {
 #[Route('/',name:'dashboard',methods:['GET'])]
 public function index():Response {
  $user=$this->user();$today=new \DateTimeImmutable('today',new \DateTimeZone('Europe/Berlin'));
  $properties=$this->store->visible(Property::class,$user);$tasks=$this->store->visible(Task::class,$user);$leads=$this->store->visible(Lead::class,$user);
  $news=array_values(array_filter($this->store->visible(NewsPost::class,$user),fn($r)=>($r->data['status']??'')==='Veröffentlicht'));
  usort($news,fn($a,$b)=>(int)($b->data['pinned']??false)<=>(int)($a->data['pinned']??false)?:strcmp($b->data['publishedAt']??'',$a->data['publishedAt']??''));
  $tasks=array_values(array_filter($tasks,fn($r)=>($r->data['status']??'')!=='Erledigt'));
  usort($tasks,fn($a,$b)=>strcmp(($a->data['dueDate']??'')?:'9999',($b->data['dueDate']??'')?:'9999'));
  $date=$today->format('Y-m-d');$dueToday=array_values(array_filter($tasks,fn($r)=>($r->data['dueDate']??'')===$date));
  $overdue=array_values(array_filter($tasks,fn($r)=>!empty($r->data['dueDate'])&&$r->data['dueDate']<$date));
  $weekStart=$today->modify('monday this week');$week=[];$weekNames=['Mo','Di','Mi','Do','Fr','Sa','So'];
  for($i=0;$i<7;$i++){$day=$weekStart->modify('+'.$i.' days');$key=$day->format('Y-m-d');$week[]=['date'=>$day,'label'=>$weekNames[$i],'today'=>$key===$date,'count'=>count(array_filter($tasks,fn($r)=>($r->data['dueDate']??'')===$key))];}
  $stats=['properties'=>count(array_filter($properties,fn($r)=>!in_array($r->data['status']??'',['Archiviert','Verkauft'],true))),'tasks'=>count($tasks),'leads'=>count(array_filter($leads,fn($r)=>($r->data['status']??'')==='Neu'))];
  return $this->page('dashboard/index.html.twig',compact('properties','tasks','leads','news','stats','today','dueToday','overdue','week'));
 }
}
