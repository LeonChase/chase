<?php
namespace App\Controller;

use App\Entity\{Property, Lead, Task, TaskInvitation, User, ContentPost, Record};
use App\Service\{MockOnOfficeAdapter, ContactDirectory};
use Symfony\Component\HttpFoundation\{Request, Response, ResponseHeaderBag};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class WorkController extends PortalController
{
    private const TASK_STATUSES = ['Offen','In Arbeit','Erledigt'];
    private const LEAD_STATUSES = ['Neu','Kontaktiert','Qualifiziert','Termin','Auftrag','Verloren'];

    #[Route('/tasks', name: 'tasks', methods: ['GET'])]
    public function tasks(Request $request): Response
    {
        $all = $this->store->visible(Task::class, $this->user());
        $items = $this->filter($all, $request, ['title','notes']);
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Berlin'));
        $period = (string)$request->query->get('period', '');
        $exactDate = $request->query->getString('date');
        if ($exactDate && !$this->validDate($exactDate)) throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Bitte ein gültiges Kalenderdatum wählen.');
        $items = array_values(array_filter($items, function ($t) use ($request, $today, $period, $exactDate) {
            if (($status=$request->query->get('status')) && ($t->data['status'] ?? '') !== $status) return false;
            if (($priority=$request->query->get('priority')) && ($t->data['priority'] ?? '') !== $priority) return false;
            if (($property=$request->query->get('property')) && ($t->data['propertyId'] ?? '') !== $property) return false;
            $due = $t->data['dueDate'] ?? '';
            if ($exactDate && $due !== $exactDate) return false;
            return match($period) {
                'today' => $due === $today->format('Y-m-d'),
                'overdue' => $due && $due < $today->format('Y-m-d') && ($t->data['status'] ?? '') !== 'Erledigt',
                'week' => $due && $due >= $today->modify('monday this week')->format('Y-m-d') && $due <= $today->modify('sunday this week')->format('Y-m-d'),
                'important' => ($t->data['priority'] ?? '') === 'Hoch' && ($t->data['status'] ?? '') !== 'Erledigt',
                'completed' => ($t->data['status'] ?? '') === 'Erledigt',
                default => true,
            };
        }));
        if ($request->query->get('sort', 'due') === 'due') usort($items, fn ($a,$b) => strcmp(($a->data['dueDate'] ?? '') ?: '9999', ($b->data['dueDate'] ?? '') ?: '9999'));
        $selected = new \DateTimeImmutable($exactDate ?: $today->format('Y-m-d'), new \DateTimeZone('Europe/Berlin'));
        $view = $request->query->getString('view') === 'week' ? 'week' : 'month';
        $month = $request->query->getString('month');
        $anchor = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? new \DateTimeImmutable($month.'-01', new \DateTimeZone('Europe/Berlin')) : $selected->modify('first day of this month');
        $first = $view === 'week' ? $selected->modify('monday this week') : $anchor->modify('monday this week');
        $last = $view === 'week' ? $first->modify('+6 days') : $anchor->modify('last day of this month')->modify('sunday this week');
        $calendar = [];
        for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $dayTasks = array_values(array_filter($all, fn(Task $t) => ($t->data['dueDate'] ?? '') === $date && ($t->data['status'] ?? '') !== 'Erledigt'));
            $calendar[] = ['date'=>$date,'day'=>$day->format('j'),'today'=>$date===$today->format('Y-m-d'),'selected'=>$date===$exactDate,'inMonth'=>$day->format('Y-m')===$anchor->format('Y-m'),'tasks'=>$dayTasks];
        }
        $invitations = $this->store->visible(TaskInvitation::class, $this->user());
        $names = [];
        foreach ($this->store->em()->getRepository(User::class)->findBy(['tenantId'=>$this->user()->tenantId]) as $u) $names[$u->id]=$u->name;
        return $this->page('tasks/index.html.twig', ['items'=>$items,'statuses'=>self::TASK_STATUSES,'filters'=>$request->query->all(),'today'=>$today,'selected'=>$selected,'anchor'=>$anchor,'calendar'=>$calendar,'view'=>$view,'first'=>$first,'last'=>$last,'invitations'=>$invitations,'names'=>$names]);
    }

    #[Route('/tasks/new', name: 'task_new', methods: ['GET','POST'], priority: 20)]
    #[Route('/tasks/{id}/edit', name: 'task_edit', methods: ['GET','POST'])]
    public function taskForm(Request $request, ContactDirectory $directory, ?string $id = null): Response
    {
        $item = $id ? $this->owned(Task::class,$id) : new Task(['status'=>'Offen','priority'=>'Mittel'], $this->user()->id, $this->user()->tenantId);
        $data = array_merge($item->data, ['title'=>$item->title,'checklistText'=>implode("\n",array_column($item->data['checklist']??[],'title'))]);
        if (!$id && !$request->isMethod('POST') && $this->validDate($request->query->getString('date'))) $data['dueDate']=$request->query->getString('date');
        if (!$id && !$request->isMethod('POST')) foreach (['property'=>'propertyId','lead'=>'leadId'] as $query=>$field) if ($value=$request->query->get($query)) {
            $this->owned($query === 'property' ? Property::class : Lead::class, (string)$value);
            $data[$field] = $value;
        }
        $errors=[];
        if ($request->isMethod('POST')) {
            $this->token($request);
            $data=$this->fields($request,['title','status','priority','dueDate','notes','propertyId','leadId']);
            $data['recurrence']=$request->request->getString('recurrence',$item->data['recurrence']??'none');
            $checklistText=$request->request->getString('checklistText',implode("\n",array_column($item->data['checklist']??[],'title')));
            $this->required($data,['title'=>'Titel'],$errors);
            $this->oneOf($data,'status',self::TASK_STATUSES,$errors);
            $this->oneOf($data,'priority',['Niedrig','Mittel','Hoch'],$errors);
            $this->oneOf($data,'recurrence',['none','daily','weekly','monthly'],$errors);
            if ($data['recurrence']!=='none' && !$data['dueDate']) $errors[]='Wiederkehrende Aufgaben benötigen ein Fälligkeitsdatum.';
            $this->date($data,'dueDate',$errors);
            $this->relation($data,'propertyId',Property::class);
            $this->relation($data,'leadId',Lead::class);
            $this->lengths($data,$errors);
            $checklist=$this->checklist($checklistText,$item->data['checklist']??[],$errors);
            $data['checklistText']=$checklistText;
            if (!$errors) {
                unset($data['checklistText']);
                $wasDone=($item->data['status']??'')==='Erledigt';
                $item->title=$data['title'];$item->data=array_merge($item->data,$data,['checklist'=>$checklist,'isDemo'=>true]);
                $this->saveTaskAndSuccessor($item,$wasDone);$this->audit($id?'Aufgabe bearbeitet':'Aufgabe erstellt',$item->id);
                $this->addFlash('success','Aufgabe gespeichert.');
                return $this->redirect('/tasks');
            }
        }
        return $this->page('tasks/form.html.twig',['item'=>$item,'data'=>$data,'errors'=>$errors,'statuses'=>self::TASK_STATUSES,'properties'=>$this->store->visible(Property::class,$this->user()),'leads'=>$this->store->visible(Lead::class,$this->user()),'contacts'=>$directory->contacts($this->user()),'invitations'=>$id?array_values(array_filter($this->store->visible(TaskInvitation::class,$this->user()),fn($i)=>($i->data['taskId']??'')===$id)):[]]);
    }

    #[Route('/tasks/{id}/status', name: 'task_status', methods: ['POST'])]
    public function taskStatus(string $id,Request $request): Response
    {
        $this->token($request);$item=$this->owned(Task::class,$id);
        $status=(string)$request->request->get('status','');
        if (!in_array($status,self::TASK_STATUSES,true)) throw $this->createNotFoundException();
        $wasDone=($item->data['status']??'')==='Erledigt';
        $item->data['status']=$status;
        $this->saveTaskAndSuccessor($item,$wasDone);$this->audit('Aufgabenstatus geändert',$id);
        $this->addFlash('success','Aufgabenstatus gespeichert.');return $this->redirect('/tasks');
    }

    #[Route('/tasks/{id}/delete', name: 'task_delete', methods: ['POST'])]
    #[Route('/leads/{id}/delete', name: 'lead_delete', methods: ['POST'])]
    public function delete(string $id,Request $request): Response
    {
        $this->token($request);$lead=str_starts_with($request->getPathInfo(),'/leads/');
        $item=$this->owned($lead?Lead::class:Task::class,$id);
        if ($lead) foreach ($this->store->visible(Task::class,$this->user()) as $task) if (($task->data['leadId']??'')===$id) {$task->data['leadId']=null;$this->save($task);}
        $this->store->remove($item);$this->audit($lead?'Demo-Lead gelöscht':'Aufgabe gelöscht',$id);
        $this->addFlash('success',$lead?'Demo-Lead gelöscht. Verknüpfte Aufgaben bleiben erhalten.':'Aufgabe gelöscht.');
        return $this->redirect($lead?'/leads':'/tasks');
    }

    #[Route('/leads', name: 'leads', methods: ['GET'])]
    public function leads(Request $request): Response
    {
        $items=$this->filter($this->store->visible(Lead::class,$this->user()),$request,['title','name','email','interest','source']);
        if ($status=$request->query->get('status')) $items=array_values(array_filter($items,fn($l)=>($l->data['status']??'')===$status));
        $nextTasks=[];
        $tasks=array_values(array_filter($this->store->visible(Task::class,$this->user()),fn($t)=>($t->data['status']??'')!=='Erledigt'));
        usort($tasks,fn($a,$b)=>strcmp(($a->data['dueDate']??'')?:'9999',($b->data['dueDate']??'')?:'9999'));
        foreach($tasks as $task) if(($leadId=$task->data['leadId']??null)&&!isset($nextTasks[$leadId])) $nextTasks[$leadId]=$task;
        $lanes=array_fill_keys(self::LEAD_STATUSES,[]);foreach($items as $lead)$lanes[$lead->data['status']??'Neu'][]=$lead;
        return $this->page('leads/index.html.twig',['items'=>$items,'statuses'=>self::LEAD_STATUSES,'filters'=>$request->query->all(),'nextTasks'=>$nextTasks,'lanes'=>$lanes]);
    }

    #[Route('/leads/new', name: 'lead_new', methods: ['GET','POST'], priority: 20)]
    #[Route('/leads/{id}', name: 'lead_show', methods: ['GET','POST'], priority: -10)]
    #[Route('/leads/{id}/edit', name: 'lead_edit', methods: ['GET','POST'])]
    public function leadForm(Request $request, ?string $id=null): Response
    {
        $item=$id?$this->owned(Lead::class,$id):new Lead(['status'=>'Neu'], $this->user()->id,$this->user()->tenantId);
        $data=array_merge($item->data,['name'=>$item->data['name']??$item->title]);$errors=[];
        if ($request->isMethod('POST')) {
            $this->token($request);$data=$this->fields($request,['name','email','phone','interest','source','status','propertyId','nextFollowUp','notes','activityNote']);
            $this->required($data,['name'=>'Name','interest'=>'Interesse'],$errors);
            $this->oneOf($data,'status',self::LEAD_STATUSES,$errors);
            $this->date($data,'nextFollowUp',$errors);$this->relation($data,'propertyId',Property::class);$this->lengths($data,$errors);
            if ($data['email'] && (!filter_var($data['email'],FILTER_VALIDATE_EMAIL) || !str_ends_with(strtolower($data['email']),'.test'))) $errors[]='Für diese Demo bitte eine erfundene E-Mail-Adresse mit .test verwenden.';
            if (!$errors) {
                $note=$data['activityNote'];unset($data['activityNote']);
                $item->data=array_merge($item->data,$data,['isDemo'=>true]);$item->title=$data['name'];
                if(!$id)$item->data['sourceProvenance']=['method'=>'manual','source'=>$data['source']?:'Manuelle Demo-Erfassung','recordedAt'=>(new \DateTimeImmutable())->format(DATE_ATOM)];
                if ($note) $item->data['activities'][]=['date'=>(new \DateTimeImmutable())->format(DATE_ATOM),'note'=>$note];
                $this->save($item);$this->audit($id?'Lead bearbeitet':'Lead erstellt',$item->id);
                $this->addFlash('success','Synthetischer Demo-Lead gespeichert. Es wurde keine Nachricht versendet.');
                return $this->redirect('/leads/'.$item->id);
            }
        }
        $tasks=$id?array_values(array_filter($this->store->visible(Task::class,$this->user()),fn($t)=>($t->data['leadId']??'')===$id)):[];
        usort($tasks,fn($a,$b)=>strcmp(($a->data['dueDate']??'')?:'9999',($b->data['dueDate']??'')?:'9999'));
        $drafts=$id?array_values(array_filter($this->store->visible(ContentPost::class,$this->user()),fn($p)=>($p->data['sourceLeadId']??'')===$id)):[];
        return $this->page('leads/form.html.twig',['item'=>$item,'data'=>$data,'errors'=>$errors,'statuses'=>self::LEAD_STATUSES,'properties'=>$this->store->visible(Property::class,$this->user()),'tasks'=>$tasks,'drafts'=>$drafts]);
    }

    #[Route('/leads/{id}/status', name: 'lead_status', methods: ['POST'])]
    public function leadStatus(string $id, Request $request): Response
    {
        $this->token($request);$lead=$this->owned(Lead::class,$id);$status=$request->request->getString('status');
        if(!in_array($status,self::LEAD_STATUSES,true))throw $this->createNotFoundException();
        if(($lead->data['status']??'Neu')!==$status){
            $lead->data['activities'][]=['date'=>(new \DateTimeImmutable())->format(DATE_ATOM),'note'=>'Pipeline: '.($lead->data['status']??'Neu').' → '.$status];
            $lead->data['status']=$status;$this->save($lead);$this->audit('Leadstatus geändert',$id);
        }
        return $this->redirect('/leads');
    }

    #[Route('/leads/{id}/followup', name: 'lead_followup', methods: ['POST'])]
    public function leadFollowup(string $id, Request $request): Response
    {
        $this->token($request);$lead=$this->owned(Lead::class,$id);$now=(new \DateTimeImmutable())->format(DATE_ATOM);
        $name=$lead->data['name']??$lead->title;$interest=$lead->data['interest']??'';
        $text='Guten Tag '.$name.",\n\n";
        $text.=$interest!==''?'ich möchte an Ihre Anfrage zum Thema „'.$interest.'“ anknüpfen.': 'ich möchte an unseren bisherigen Kontakt anknüpfen.';
        $text.="\n\nWelche Fragen sind für Sie aktuell offen? Gern stimmen wir die nächsten Schritte gemeinsam ab.\n\nFreundliche Grüße\n".$this->user()->name;
        $title=mb_substr('Check-up: '.$lead->title,0,200);
        $draft=new ContentPost(['title'=>$title,'kind'=>'text','tool'=>'email','sourceLeadId'=>$lead->id,'source'=>['type'=>'lead','id'=>$lead->id,'facts'=>['name'=>$name,'interest'=>$interest]],'content'=>$text,'status'=>'Entwurf','approved'=>false,'isPublished'=>false,'sent'=>false,'mode'=>'Lokale Textvorlage · bekannte Leadangaben','checklist'=>['Kontaktgrund und Einwilligung vor einer Verwendung prüfen.','Name, Anlass und Wortlaut manuell prüfen.','Nur ein lokaler Entwurf; kein E-Mail-Dienst verbunden.'],'version'=>1,'versions'=>[['number'=>1,'title'=>$title,'text'=>$text,'time'=>$now,'approved'=>false]],'isDemo'=>true],$this->user()->id,$this->user()->tenantId);
        $this->save($draft);$lead->data['activities'][]=['date'=>$now,'note'=>'Check-up-Text als lokaler Entwurf erstellt. Kein Versand.'];$this->save($lead);$this->audit('Lokalen Lead-Textentwurf erstellt',$draft->id);
        $this->addFlash('success','Check-up-Entwurf gespeichert. Bitte prüfen, bei Bedarf bearbeiten und manuell kopieren. Es wurde keine E-Mail versendet.');
        return $this->redirect('/leads/'.$lead->id.'#followup-drafts');
    }

    #[Route('/crm', name: 'crm', methods: ['GET'])]
    public function crm(MockOnOfficeAdapter $adapter): Response
    {
        return $this->page('crm/index.html.twig',['items'=>$adapter->listProperties($this->user())]);
    }

    #[Route('/crm/import', name: 'crm_import', methods: ['POST'])]
    public function import(Request $request,MockOnOfficeAdapter $adapter): Response
    {
        $this->token($request);$result=$adapter->syncProperties($this->user());$this->audit('Lokaler onOffice-Demoimport');
        $this->addFlash('success',sprintf('Demo-Import abgeschlossen: %d neu, %d aktualisiert, %d übersprungen. Keine Verbindung zu onOffice.',$result['created'],$result['updated'],$result['skipped']));
        return $this->redirect('/crm');
    }

    private function fields(Request $request,array $names): array
    {
        $data=[];foreach($names as $name) $data[$name]=trim((string)$request->request->get($name,''));return $data;
    }
    private function required(array $data,array $fields,array &$errors): void
    {
        foreach($fields as $key=>$label) if (($data[$key]??'')==='') $errors[]=$label.' ist erforderlich.';
    }
    private function oneOf(array $data,string $field,array $values,array &$errors): void
    {
        if (!in_array($data[$field]??'', $values,true)) $errors[]='Bitte eine gültige Auswahl für '.$field.' treffen.';
    }
    private function lengths(array $data,array &$errors): void
    {
        foreach($data as $field=>$value) if (is_string($value) && mb_strlen($value) > (in_array($field,['notes','energyData','activityNote'],true)?5000:250)) $errors[]='Das Feld '.$field.' ist zu lang.';
        foreach(['features','sellingPoints'] as $field) foreach($data[$field]??[] as $value) if(mb_strlen($value)>250) $errors[]='Ein Merkmal ist zu lang (maximal 250 Zeichen).';
    }
    private function date(array $data,string $field,array &$errors): void
    {
        if (!$value=$data[$field]??'') return;
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if (!$date || $date->format('Y-m-d')!==$value) $errors[]='Bitte ein gültiges Datum angeben.';
    }
    private function validDate(string $value): bool
    {
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        return $date&&$date->format('Y-m-d')===$value;
    }
    private function saveTaskAndSuccessor(Task $item,bool $wasDone): void
    {
        // A completed occurrence has at most one successor, even after reopening it.
        $em=$this->store->em();$em->wrapInTransaction(function()use($item,$wasDone,$em){
            if(($item->data['status']??'')==='Erledigt'&&!$wasDone&&!empty($item->data['dueDate'])&&in_array($item->data['recurrence']??'none',['daily','weekly','monthly'],true)&&empty($item->data['nextTaskId'])){
                $due=new \DateTimeImmutable($item->data['dueDate']);$month=$due->modify('first day of next month');
                $nextDue=match($item->data['recurrence']){'daily'=>$due->modify('+1 day'),'weekly'=>$due->modify('+1 week'),default=>$month->setDate((int)$month->format('Y'),(int)$month->format('m'),min((int)$due->format('j'),(int)$month->format('t')))};
                $data=$item->data;unset($data['nextTaskId'],$data['calendarUid']);$data['status']='Offen';$data['dueDate']=$nextDue->format('Y-m-d');$data['previousTaskId']=$item->id;
                $data['checklist']=array_map(fn($step)=>array_merge($step,['done'=>false]),$data['checklist']??[]);
                $next=new Task($data,$item->ownerId,$item->tenantId);$next->title=$item->title;$em->persist($next);$item->data['nextTaskId']=$next->id;
            }
            $item->updatedAt=new \DateTimeImmutable();$em->persist($item);
        });
    }
    private function checklist(string $text,array $existing,array &$errors): array
    {
        if(mb_strlen($text)>6000){$errors[]='Die Checkliste ist zu lang (maximal 6.000 Zeichen).';return $existing;}
        $lines=array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/u',$text)?:[]),fn($line)=>$line!==''));
        if(count($lines)>30)$errors[]='Bitte höchstens 30 Teilschritte anlegen.';
        $result=[];
        foreach(array_slice($lines,0,30) as $line){
            if(mb_strlen($line)>180)$errors[]='Ein Teilschritt darf maximal 180 Zeichen enthalten.';
            $step=null;foreach($existing as $key=>$old)if(($old['title']??'')===$line){$step=$old;unset($existing[$key]);break;}
            $result[]=$step??['id'=>bin2hex(random_bytes(6)),'title'=>$line,'done'=>false];
        }
        return $result;
    }
    private function relation(array &$data,string $field,string $class): void
    {
        if ($data[$field]??'') $this->owned($class,$data[$field]);else $data[$field]=null;
    }
    private function lines(string $value): array
    {
        return array_values(array_filter(array_map('trim',preg_split('/[\r\n,]+/u',$value)?:[])));
    }
    private function filter(array $items,Request $request,array $fields): array
    {
        $query=mb_strtolower(mb_substr(trim((string)$request->query->get('q','')),0,200));
        $items=array_values(array_filter($items,function($item) use($query,$fields,$request){
            if (($from=$request->query->get('from')) && $item->createdAt->format('Y-m-d')<$from) return false;
            if (($to=$request->query->get('to')) && $item->createdAt->format('Y-m-d')>$to) return false;
            if (!$query) return true;
            $text=$item->title;foreach($fields as $f) $text.=' '.(string)($item->data[$f]??'');
            return str_contains(mb_strtolower($text),$query);
        }));
        $sort=(string)$request->query->get('sort','newest');
        usort($items,fn($a,$b)=>match($sort){'title'=>strnatcasecmp($a->title,$b->title),'oldest'=>$a->createdAt<=>$b->createdAt,default=>$b->createdAt<=>$a->createdAt});
        return $items;
    }
}
