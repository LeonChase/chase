<?php
namespace App\Controller;

use App\Entity\{Task,TaskInvitation,Property,Lead,Record};
use App\Service\{CalendarExport,ContactDirectory};
use Symfony\Component\HttpFoundation\{Request,Response,HeaderUtils};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TaskPlanningController extends PortalController
{
    #[Route('/tasks/calendar.ics',name:'task_calendar_export',methods:['GET'],priority:30)]
    public function export(CalendarExport $calendar): Response
    {
        $tasks=array_values(array_filter($this->store->visible(Task::class,$this->user()),fn(Task $t)=>$t->ownerId===$this->user()->id));
        return new Response($calendar->export($tasks),200,['Content-Type'=>'text/calendar; charset=UTF-8','Content-Disposition'=>HeaderUtils::makeDisposition('attachment','CHASE-Aufgaben.ics'),'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }

    #[Route('/tasks/calendar/import',name:'task_calendar_import',methods:['POST'],priority:30)]
    public function import(Request $request,CalendarExport $calendar): Response
    {
        $this->token($request);$file=$request->files->get('calendar');
        if(!$file instanceof UploadedFile||!$file->isValid()||$file->getSize()>1048576||strtolower($file->getClientOriginalExtension())!=='ics'){
            $this->addFlash('error','Bitte eine gültige .ics-Kalenderdatei bis 1 MB auswählen.');return $this->redirect('/tasks');
        }
        try{$result=$calendar->import((string)file_get_contents($file->getPathname()));}
        catch(\InvalidArgumentException $e){$this->addFlash('error',$e->getMessage());return $this->redirect('/tasks');}
        $uids=[];foreach($this->store->visible(Task::class,$this->user()) as $task)if($task->ownerId===$this->user()->id){
            if(isset($task->data['calendarUid']))$uids[$task->data['calendarUid']]=true;
            $uids[$task->id.'@chase.local']=true;
        }
        $created=0;$duplicates=0;$em=$this->store->em();
        $em->wrapInTransaction(function()use($result,&$uids,&$created,&$duplicates,$em){
            foreach($result['events'] as $event){
                if(isset($uids[$event['calendarUid']])){++$duplicates;continue;}
                $uids[$event['calendarUid']]=true;
                $task=new Task($event+['recurrence'=>'none','checklist'=>[],'source'=>'Lokaler iCalendar-Import','isDemo'=>true],$this->user()->id,$this->user()->tenantId);
                $em->persist($task);++$created;
            }
        });
        $this->audit('Lokalen Kalender importiert');
        $this->addFlash('success',sprintf('%d Aufgaben importiert, %d bereits vorhanden, %d nicht unterstützte Termine übersprungen. Keine Verbindung zu einem Kalenderdienst.',$created,$duplicates,$result['skipped']));
        return $this->redirect('/tasks');
    }

    #[Route('/tasks/{id}/checklist',name:'task_checklist',methods:['POST'])]
    public function checklist(string $id,Request $request): Response
    {
        $this->token($request);$task=$this->owned(Task::class,$id);$stepId=$request->request->getString('stepId');$found=false;
        foreach($task->data['checklist']??[] as $index=>$step)if(($step['id']??'')===$stepId){$task->data['checklist'][$index]['done']=$request->request->getString('done')==='1';$found=true;break;}
        if(!$found)throw $this->createNotFoundException();
        $this->save($task);return $this->redirect('/tasks/'.$id.'/edit#checklist');
    }

    #[Route('/tasks/{id}/offer',name:'task_offer',methods:['POST'])]
    public function offer(string $id,Request $request,ContactDirectory $directory): Response
    {
        $this->token($request);$task=$this->owned(Task::class,$id);$user=$this->user();
        if($task->ownerId!==$user->id)throw $this->createAccessDeniedException('Nur eigene Aufgaben können zur Übernahme angeboten werden.');
        $recipient=$directory->recipient($request->request->getString('recipientId'),$user);
        if(!$recipient){$this->addFlash('error','Bitte einen aktiven, sichtbaren internen Kontakt auswählen.');return $this->redirect('/tasks/'.$id.'/edit');}
        foreach($this->store->visible(TaskInvitation::class,$user) as $existing)if($existing->ownerId===$user->id&&$existing->recipientId===$recipient->id&&($existing->data['taskId']??'')===$id&&($existing->data['status']??'')==='pending'){
            $this->addFlash('success','Für diesen Kontakt besteht bereits eine offene Aufgabenanfrage.');return $this->redirect('/tasks/'.$id.'/edit');
        }
        $data=['title'=>$task->title,'taskId'=>$task->id,'recipientId'=>$recipient->id,'dueDate'=>$task->data['dueDate']??null,'priority'=>$task->data['priority']??'Mittel','status'=>'pending'];
        // Offering a task does not grant access to its private property or lead.
        foreach(['propertyId'=>Property::class,'leadId'=>Lead::class] as $field=>$class){
            $record=!empty($task->data[$field])?$this->store->em()->find($class,$task->data[$field]):null;
            if($record instanceof Record&&$this->store->canAccess($record,$user)&&$this->store->canAccess($record,$recipient))$data[$field]=$record->id;
        }
        $invitation=new TaskInvitation($data,$user->id,$user->tenantId);$this->save($invitation);$this->audit('Aufgabe intern angeboten',$invitation->id);
        $this->addFlash('success','Aufgabenanfrage gespeichert. Nach Annahme erhält der Kontakt eine eigene Aufgaben-Kopie; Ihre Aufgabe bleibt erhalten.');return $this->redirect('/tasks/'.$id.'/edit');
    }

    #[Route('/tasks/invitations/{id}/respond',name:'task_invitation_respond',methods:['POST'],priority:30)]
    public function respond(string $id,Request $request): Response
    {
        $this->token($request);$invitation=$this->owned(TaskInvitation::class,$id);$user=$this->user();
        if($invitation->recipientId!==$user->id)throw $this->createAccessDeniedException();
        $decision=$request->request->getString('decision');
        if(!in_array($decision,['accept','decline'],true))throw $this->createNotFoundException();
        $em=$this->store->em();$changed=false;
        $em->wrapInTransaction(function()use($em,$invitation,$user,$decision,&$changed){
            $connection=$em->getConnection();
            $raw=$connection->fetchOne('SELECT data FROM portal_task_invitation WHERE id = :id AND recipient_id = :recipient AND tenant_id = :tenant',['id'=>$invitation->id,'recipient'=>$user->id,'tenant'=>$user->tenantId]);
            if(!is_string($raw))throw $this->createNotFoundException();
            $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            if(($data['status']??'')!=='pending')return;
            $task=null;
            if($decision==='accept'){
                $taskData=['title'=>$invitation->title,'dueDate'=>$data['dueDate']??null,'priority'=>$data['priority']??'Mittel','status'=>'Offen','recurrence'=>'none','checklist'=>[],'invitationId'=>$invitation->id,'isDemo'=>true];
                foreach(['propertyId'=>Property::class,'leadId'=>Lead::class] as $field=>$class){
                    $record=!empty($data[$field])?$em->find($class,$data[$field]):null;
                    if($record instanceof Record&&$this->store->canAccess($record,$user))$taskData[$field]=$record->id;
                }
                $task=new Task($taskData,$user->id,$user->tenantId);$data['acceptedTaskId']=$task->id;
            }
            $data['status']=$decision==='accept'?'accepted':'declined';$data['respondedAt']=(new \DateTimeImmutable())->format(DATE_ATOM);
            // Compare-and-swap protects the transition and task creation from repeated submissions.
            $changed=$connection->executeStatement('UPDATE portal_task_invitation SET data = :data, updated_at = :updated WHERE id = :id AND data = :previous',['data'=>json_encode($data,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION),'updated'=>(new \DateTimeImmutable())->format('Y-m-d H:i:s'),'id'=>$invitation->id,'previous'=>$raw])===1;
            if($changed&&$task)$em->persist($task);
            $em->refresh($invitation);
        });
        if($changed)$this->audit($decision==='accept'?'Aufgabenanfrage angenommen':'Aufgabenanfrage abgelehnt',$id);
        $this->addFlash('success',$changed?($decision==='accept'?'Aufgabe angenommen und in Ihrer Liste angelegt.':'Aufgabenanfrage abgelehnt.'):'Diese Aufgabenanfrage wurde bereits beantwortet.');
        return $this->redirect('/tasks');
    }
}
