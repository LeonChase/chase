<?php
namespace App\Service;

use App\Entity\{User,Task,Lead,Message,NewsPost,TaskInvitation,UserPreference};

/** A local activity feed. Every item is built from records the current user can actually access. */
final class PortalNotifications
{
 public function __construct(private PortalStore $store) {}
 public function items(User $user): array
 {
  $seen=array_fill_keys(array_filter($user->preferences['seenNotifications']??[],'is_string'),true);
  $readNews=$user->preferences['readNews']??[];
  foreach($this->store->visible(UserPreference::class,$user) as $p)if(in_array($p->data['kind']??'',['read','news_read'],true))$readNews[]=$p->data['targetId']??$p->data['newsId']??'';
  $today=(new \DateTimeImmutable('today',new \DateTimeZone('Europe/Berlin')))->format('Y-m-d');$items=[];
  $tasks=array_values(array_filter($this->store->visible(Task::class,$user),fn($t)=>($t->data['status']??'')!=='Erledigt'));
  $todayTasks=array_values(array_filter($tasks,fn($t)=>($t->data['dueDate']??'')===$today));
  if($todayTasks){$ids=array_map(fn($t)=>$t->id,$todayTasks);sort($ids);$items[]=['id'=>'today:'.$today.':'.substr(hash('sha256',implode('|',$ids)),0,16),'title'=>count($todayTasks).' '.(count($todayTasks)===1?'Termin / Aufgabe heute':'Termine / Aufgaben heute'),'body'=>implode("\n",array_map(fn($t)=>$t->title,$todayTasks)),'url'=>'/tasks?period=today','icon'=>'calendar','time'=>new \DateTimeImmutable($today.' 23:59:59',new \DateTimeZone('Europe/Berlin')),'kind'=>'today','read'=>false];}
  foreach($tasks as $task)$items[]=['id'=>'task:'.$task->id,'title'=>'Aufgabe: '.$task->title,'body'=>!empty($task->data['dueDate'])?'Fällig am '.(new \DateTimeImmutable($task->data['dueDate']))->format('d.m.Y'):'','url'=>'/tasks?task='.rawurlencode($task->id),'icon'=>'check-square','time'=>$task->createdAt,'kind'=>'task','read'=>false];
  foreach($this->store->visible(Lead::class,$user) as $lead)if(($lead->data['status']??'Neu')==='Neu')$items[]=['id'=>'lead:'.$lead->id,'title'=>'Neuer Lead: '.$lead->title,'body'=>(string)($lead->data['interest']??''),'url'=>'/leads/'.rawurlencode($lead->id),'icon'=>'users','time'=>$lead->createdAt,'kind'=>'lead','read'=>false];
  foreach($this->store->visible(Message::class,$user) as $message)if($message->recipientId===$user->id&&empty($message->data['readAt']))$items[]=['id'=>'message:'.$message->id,'title'=>'Neue interne Nachricht','body'=>mb_substr((string)($message->data['body']??''),0,160),'url'=>'/messages?recipientId='.rawurlencode((string)$message->ownerId),'icon'=>'message-circle','time'=>$message->createdAt,'kind'=>'message','read'=>false];
  foreach($this->store->visible(TaskInvitation::class,$user) as $offer)if($offer->recipientId===$user->id&&($offer->data['status']??'')==='pending')$items[]=['id'=>'offer:'.$offer->id,'title'=>'Aufgabe angeboten: '.$offer->title,'body'=>'Übernahme annehmen oder ablehnen.','url'=>'/tasks','icon'=>'check-square','time'=>$offer->createdAt,'kind'=>'offer','read'=>false];
  foreach($this->store->visible(NewsPost::class,$user) as $news)if(($news->data['status']??'')==='Veröffentlicht'){
   try{$time=new \DateTimeImmutable($news->data['publishedAt']??$news->createdAt->format(DATE_ATOM));}catch(\Exception){$time=$news->createdAt;}
   if($time>new \DateTimeImmutable())continue;
   $items[]=['id'=>'news:'.$news->id,'title'=>$news->title,'body'=>(string)($news->data['body']??$news->data['summary']??''),'summary'=>(string)($news->data['summary']??''),'url'=>null,'icon'=>'newspaper','time'=>$time,'kind'=>'news','read'=>in_array($news->id,$readNews,true)];
  }
  foreach($items as &$item)$item['read']=$item['read']||isset($seen[$item['id']]);unset($item);
  usort($items,fn($a,$b)=>($a['kind']==='today'?0:1)<=>($b['kind']==='today'?0:1)?:$b['time']<=>$a['time']?:strcmp($a['id'],$b['id']));
  return array_slice($items,0,100);
 }
 public function unread(User $user): int {return count(array_filter($this->items($user),fn($item)=>!$item['read']));}
}
