<?php
namespace App\Controller;
use App\Entity\{User,Record,AuditEvent};
use App\Service\PortalStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
abstract class PortalController extends AbstractController {
 public function __construct(protected PortalStore $store){}
 protected function user():User{$u=$this->getUser();if(!$u instanceof User||!$u->active)throw new AccessDeniedHttpException();return $u;}
 protected function owned(string $class,string $id):Record{return $this->store->findVisible($class,$id,$this->user());}
 protected function token(Request $r):void{if(!$this->isCsrfTokenValid('portal',(string)$r->request->get('_token')))throw new AccessDeniedHttpException('Die Sitzung ist abgelaufen. Bitte Formular erneut öffnen.');}
 protected function save(Record|User $record):void{$this->store->save($record);}
 protected function audit(string $action,string $entityId=''):void{$this->save(new AuditEvent(['title'=>$action,'entityId'=>$entityId],$this->user()->id,$this->user()->tenantId));}
 protected function page(string $template,array $data=[]):Response{return $this->render($template,$data);}
}
