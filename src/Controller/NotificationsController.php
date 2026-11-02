<?php
namespace App\Controller;

use App\Service\PortalNotifications;
use Symfony\Component\HttpFoundation\{Request,Response,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;

final class NotificationsController extends PortalController
{
 #[Route('/notifications',name:'notifications',methods:['GET'])]
 public function index(PortalNotifications $notifications):Response
 {
  $items=$notifications->items($this->user());
  return $this->page('notifications/_items.html.twig',['items'=>$items]);
 }
 #[Route('/notifications/read',name:'notifications_read',methods:['POST'])]
 public function read(Request $request,PortalNotifications $notifications):Response
 {
  $this->token($request);$u=$this->user();$items=$notifications->items($u);$id=$request->request->getString('id');$valid=array_column($items,'id');
  // Only IDs from this user's current feed can ever be acknowledged.
  if($id!==''&&!in_array($id,$valid,true))throw $this->createNotFoundException();
  $ids=$id!==''?[$id]:$valid;$u->preferences['seenNotifications']=array_values(array_unique([...array_values(array_intersect($u->preferences['seenNotifications']??[],$valid)),...$ids]));$this->save($u);
  if($request->isXmlHttpRequest())return new JsonResponse(['unread'=>$notifications->unread($u)]);
  return $this->redirect('/?notifications=open');
 }
}
