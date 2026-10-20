<?php
namespace App\Controller;
use App\Entity\{Property,Task,Lead,ContentPost,NewsPost};
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
class DashboardController extends PortalController {
 #[Route('/',name:'dashboard',methods:['GET'])]
 public function index():Response {
  $u=$this->user();$properties=$this->store->visible(Property::class,$u);$tasks=$this->store->visible(Task::class,$u);$leads=$this->store->visible(Lead::class,$u);$posts=array_values(array_filter($this->store->visible(ContentPost::class,$u),fn($r)=>($r->data['kind']??null)==='marketing'||in_array($r->data['tool']??'',['social','social-post'],true)||(isset($r->data['channel'])&&!isset($r->data['tool']))));$news=array_values(array_filter($this->store->visible(NewsPost::class,$u),fn($r)=>($r->data['status']??'')==='Veröffentlicht'));
  $stats=['properties'=>count(array_filter($properties,fn($r)=>!in_array($r->data['status']??'',['Archiviert','Verkauft'],true))),'tasks'=>count(array_filter($tasks,fn($r)=>($r->data['status']??'')!=='Erledigt')),'leads'=>count(array_filter($leads,fn($r)=>($r->data['status']??'')==='Neu')),'posts'=>count($posts)];
  usort($tasks,fn($a,$b)=>strcmp($a->data['dueDate']??'9999',$b->data['dueDate']??'9999'));
  return $this->page('dashboard/index.html.twig',compact('properties','tasks','leads','posts','news','stats')+['today'=>new \DateTimeImmutable('now',new \DateTimeZone('Europe/Berlin'))]);
 }
}
