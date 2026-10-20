<?php
namespace App\Controller;

use App\Entity\{Property, Lead, Task, MediaAsset, ContentPost, AuditEvent, Record};
use App\Service\MockOnOfficeAdapter;
use Symfony\Component\HttpFoundation\{Request, Response, ResponseHeaderBag};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class WorkController extends PortalController
{
    private const PROPERTY_STATUSES = ['Entwurf','Vorbereitung','Aktiv','Reserviert','Verkauft','Archiviert'];
    private const PROPERTY_TYPES = ['Eigentumswohnung','Etagenwohnung','Einfamilienhaus','Doppelhaushälfte','Reihenmittelhaus','Stadthaus','Mehrfamilienhaus','Grundstück','Gewerbe','Sonstiges'];
    private const TASK_STATUSES = ['Offen','In Arbeit','Erledigt'];
    private const LEAD_STATUSES = ['Neu','Kontaktiert','Qualifiziert','Termin','Auftrag','Verloren'];
    private const CHECKLIST = ['Unterlagen zusammengestellt','Energieausweisdaten geprüft','Eigentümerfreigabe dokumentiert','Exposé geprüft','Portalveröffentlichung vorbereitet','Marketing geplant'];

    #[Route('/properties', name: 'properties', methods: ['GET'])]
    public function properties(Request $request): Response
    {
        $visible = $this->store->visible(Property::class, $this->user());
        $regions = array_values(array_unique(array_filter(array_map(fn ($p) => $p->data['region'] ?? '', $visible))));
        sort($regions);
        $owners = [];
        foreach ($visible as $record) {
            $owner = $this->store->em()->find(\App\Entity\User::class, $record->ownerId);
            if ($owner && $owner->tenantId === $this->user()->tenantId) $owners[$owner->id] = $owner->name;
        }
        $items = $this->filter($visible, $request, ['title','city','region','internalId','externalId']);
        foreach (['status','type'=>'propertyType','region','owner'=>'ownerId'] as $key => $field) {
            if (is_int($key)) $key = $field;
            if ($value = trim((string) $request->query->get($key, ''))) {
                $items = array_values(array_filter($items, fn ($item) => ($field === 'ownerId' ? $item->ownerId : ($item->data[$field] ?? '')) === $value));
            }
        }
        return $this->page('properties/index.html.twig', ['items'=>$items,'statuses'=>self::PROPERTY_STATUSES,'types'=>self::PROPERTY_TYPES,'regions'=>$regions,'owners'=>$owners,'filters'=>$request->query->all()]);
    }

    #[Route('/properties/new', name: 'property_new', methods: ['GET','POST'], priority: 20)]
    #[Route('/properties/{id}/edit', name: 'property_edit', methods: ['GET','POST'])]
    public function propertyForm(Request $request, ?string $id = null): Response
    {
        $property = $id ? $this->owned(Property::class, $id) : new Property(['status'=>'Entwurf','region'=>$this->user()->region,'marketingType'=>'Kauf','isDemo'=>true], $this->user()->id, $this->user()->tenantId);
        $data = array_merge($property->data, ['title'=>$property->title]);
        $errors = [];
        if ($request->isMethod('POST')) {
            $this->token($request);
            $data = $this->fields($request, ['title','internalId','propertyType','marketingType','city','district','region','address','status','condition','energyData','notes','source','dataSharing']);
            $this->required($data, ['title'=>'Bezeichnung','city'=>'Ort','region'=>'Region','propertyType'=>'Objektart'], $errors);
            $this->oneOf($data, 'status', self::PROPERTY_STATUSES, $errors);
            $this->oneOf($data, 'propertyType', self::PROPERTY_TYPES, $errors);
            $this->oneOf($data, 'marketingType', ['Kauf','Miete'], $errors);
            foreach (['areaSqm','usableAreaSqm','plotSqm','rooms','yearBuilt','priceEur','rentEur'] as $field) {
                $raw = trim((string) $request->request->get($field, ''));
                $normalized = str_replace(',', '.', $raw);
                if ($raw !== '' && (!is_numeric($normalized) || (float)$normalized <= 0 || (float)$normalized > 1000000000)) $errors[] = 'Bitte für '.(['areaSqm'=>'Wohnfläche','usableAreaSqm'=>'Nutzfläche','plotSqm'=>'Grundstück','rooms'=>'Zimmer','yearBuilt'=>'Baujahr','priceEur'=>'Preis','rentEur'=>'Miete'][$field]).' eine positive Zahl angeben.';
                if ($field === 'yearBuilt' && $raw !== '' && (!ctype_digit($raw) || (int)$raw < 1000 || (int)$raw > (int)date('Y')+5)) $errors[] = 'Bitte ein plausibles Baujahr zwischen 1000 und '.((int)date('Y')+5).' angeben.';
                $data[$field] = $raw === '' ? null : (float) $normalized;
            }
            $data['features'] = $this->lines((string)$request->request->get('features', ''));
            $data['sellingPoints'] = $this->lines((string)$request->request->get('sellingPoints', ''));
            if (count($data['features']) > 30 || count($data['sellingPoints']) > 30) $errors[] = 'Höchstens 30 Ausstattungsmerkmale oder Highlights angeben.';
            $this->lengths($data, $errors);
            if (!$errors) {
                $property->data = array_merge($property->data, $data, ['isDemo'=>true]);
                $property->title = $data['title'];
                $this->save($property);
                $this->audit($id ? 'Objekt bearbeitet' : 'Objekt erstellt', $property->id);
                $this->addFlash('success', 'Das Demo-Objekt wurde gespeichert.');
                return $this->redirect('/properties/'.$property->id);
            }
        }
        return $this->page('properties/form.html.twig', ['item'=>$property,'data'=>$data,'errors'=>$errors,'statuses'=>self::PROPERTY_STATUSES,'types'=>self::PROPERTY_TYPES]);
    }

    #[Route('/properties/{id}', name: 'property_show', methods: ['GET'], priority: -10)]
    public function propertyShow(string $id, Request $request): Response
    {
        $property = $this->owned(Property::class, $id);
        $related = fn (string $class) => array_values(array_filter($this->store->visible($class, $this->user()), fn ($r) => ($r->data['propertyId'] ?? '') === $id));
        $tab = (string)$request->query->get('tab', 'overview');
        if (!in_array($tab, ['overview','media','documents','texts','marketing','checklist','activity'], true)) $tab = 'overview';
        $activities = array_filter($this->store->visible(AuditEvent::class, $this->user()), fn ($a) => ($a->data['entityId'] ?? '') === $id);
        return $this->page('properties/show.html.twig', ['item'=>$property,'tab'=>$tab,'tasks'=>$related(Task::class),'media'=>$related(MediaAsset::class),'posts'=>$related(ContentPost::class),'activities'=>$activities,'checklist'=>self::CHECKLIST,'statuses'=>self::PROPERTY_STATUSES]);
    }

    #[Route('/properties/{id}/status', name: 'property_status', methods: ['POST'])]
    public function propertyStatus(string $id, Request $request): Response
    {
        $this->token($request);
        $item = $this->owned(Property::class, $id);
        $status = (string) $request->request->get('status', '');
        if (!in_array($status, self::PROPERTY_STATUSES, true)) throw $this->createNotFoundException();
        $item->data['status'] = $status;
        $this->save($item);
        $this->audit('Objektstatus geändert: '.$status, $id);
        $this->addFlash('success', 'Objektstatus gespeichert. Dies löst keine externe Veröffentlichung aus.');
        return $this->redirect('/properties/'.$id);
    }

    #[Route('/properties/{id}/archive', name: 'property_archive', methods: ['POST'])]
    public function archive(string $id, Request $request): Response
    {
        $this->token($request);
        $item = $this->owned(Property::class, $id);
        $item->data['status'] = 'Archiviert';
        $this->save($item);
        $this->audit('Objekt archiviert', $id);
        $this->addFlash('success', 'Objekt archiviert. Du kannst den Status weiterhin bearbeiten.');
        return $this->redirect('/properties/'.$id);
    }

    #[Route('/properties/{id}/checklist', name: 'property_checklist', methods: ['POST'])]
    public function checklist(string $id, Request $request): Response
    {
        $this->token($request);
        $item = $this->owned(Property::class, $id);
        $selected = $request->request->all('checks');
        $item->data['checklist'] = array_values(array_intersect(self::CHECKLIST, array_filter($selected, 'is_string')));
        $this->save($item);
        $this->audit('Objektcheckliste aktualisiert', $id);
        $this->addFlash('success', 'Checkliste gespeichert.');
        return $this->redirect('/properties/'.$id.'?tab=checklist');
    }

    #[Route('/properties/{id}/media', name: 'property_upload', methods: ['POST'])]
    public function upload(string $id, Request $request): Response
    {
        $this->token($request);
        $property = $this->owned(Property::class, $id);
        $file = $request->files->get('media');
        $types = ['image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp'],'application/pdf'=>['pdf']];
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile || !$file->isValid()) {
            $this->addFlash('error', 'Bitte eine gültige Datei auswählen.');
        } elseif ($file->getSize() > 8*1024*1024) {
            $this->addFlash('error', 'Die Datei darf höchstens 8 MB groß sein.');
        } else {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
            $extension = strtolower($file->getClientOriginalExtension());
            if (!isset($types[$mime]) || !in_array($extension, $types[$mime], true)) {
                $this->addFlash('error', 'Erlaubt sind JPG, PNG, WebP und PDF mit passendem Dateityp.');
            } else {
                $filename = bin2hex(random_bytes(20)).'.'.$extension;
                $directory = $this->getParameter('kernel.project_dir').'/var/uploads';
                if (!is_dir($directory)) mkdir($directory, 0700, true);
                $file->move($directory, $filename);
                chmod($directory.'/'.$filename, 0600);
                $asset = new MediaAsset(['title'=>mb_substr(basename($file->getClientOriginalName()),0,180),'propertyId'=>$id,'filename'=>$filename,'mime'=>$mime,'size'=>filesize($directory.'/'.$filename),'isDemo'=>true], $property->ownerId, $property->tenantId);
                $this->save($asset);
                $this->audit('Private Objektdatei hochgeladen', $id);
                $this->addFlash('success', 'Die Datei wurde privat gespeichert. Sie wird an keinen externen Anbieter gesendet.');
            }
        }
        return $this->redirect('/properties/'.$id.'?tab=media');
    }

    #[Route('/media/{id}', name: 'media_download', methods: ['GET'])]
    public function media(string $id): Response
    {
        $asset = $this->owned(MediaAsset::class, $id);
        $this->owned(Property::class, (string)($asset->data['propertyId'] ?? ''));
        $filename = (string)($asset->data['filename'] ?? '');
        if (!preg_match('/^[a-f0-9]{40}\.(jpg|jpeg|png|webp|pdf)$/', $filename)) throw $this->createNotFoundException();
        $path = $this->getParameter('kernel.project_dir').'/var/uploads/'.$filename;
        if (!is_file($path)) throw $this->createNotFoundException('Datei nicht mehr vorhanden.');
        $response = $this->file($path, $asset->title, str_starts_with((string)$asset->data['mime'], 'image/') ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        $response->headers->set('Content-Type', (string)$asset->data['mime']);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }

    #[Route('/tasks', name: 'tasks', methods: ['GET'])]
    public function tasks(Request $request): Response
    {
        $items = $this->filter($this->store->visible(Task::class, $this->user()), $request, ['title','notes']);
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Berlin'));
        $period = (string)$request->query->get('period', '');
        $items = array_values(array_filter($items, function ($t) use ($request, $today, $period) {
            if (($status=$request->query->get('status')) && ($t->data['status'] ?? '') !== $status) return false;
            if (($priority=$request->query->get('priority')) && ($t->data['priority'] ?? '') !== $priority) return false;
            if (($property=$request->query->get('property')) && ($t->data['propertyId'] ?? '') !== $property) return false;
            $due = $t->data['dueDate'] ?? '';
            return match($period) {
                'today' => $due === $today->format('Y-m-d'),
                'overdue' => $due && $due < $today->format('Y-m-d') && ($t->data['status'] ?? '') !== 'Erledigt',
                'week' => $due && $due >= $today->modify('monday this week')->format('Y-m-d') && $due <= $today->modify('sunday this week')->format('Y-m-d'),
                default => true,
            };
        }));
        if ($request->query->get('sort', 'due') === 'due') usort($items, fn ($a,$b) => strcmp(($a->data['dueDate'] ?? '') ?: '9999', ($b->data['dueDate'] ?? '') ?: '9999'));
        return $this->page('tasks/index.html.twig', ['items'=>$items,'statuses'=>self::TASK_STATUSES,'filters'=>$request->query->all()]);
    }

    #[Route('/tasks/new', name: 'task_new', methods: ['GET','POST'], priority: 20)]
    #[Route('/tasks/{id}/edit', name: 'task_edit', methods: ['GET','POST'])]
    public function taskForm(Request $request, ?string $id = null): Response
    {
        $item = $id ? $this->owned(Task::class,$id) : new Task(['status'=>'Offen','priority'=>'Mittel'], $this->user()->id, $this->user()->tenantId);
        $data = array_merge($item->data, ['title'=>$item->title]);
        if (!$id && !$request->isMethod('POST')) foreach (['property'=>'propertyId','lead'=>'leadId'] as $query=>$field) if ($value=$request->query->get($query)) {
            $this->owned($query === 'property' ? Property::class : Lead::class, (string)$value);
            $data[$field] = $value;
        }
        $errors=[];
        if ($request->isMethod('POST')) {
            $this->token($request);
            $data=$this->fields($request,['title','status','priority','dueDate','notes','propertyId','leadId']);
            $this->required($data,['title'=>'Titel'],$errors);
            $this->oneOf($data,'status',self::TASK_STATUSES,$errors);
            $this->oneOf($data,'priority',['Niedrig','Mittel','Hoch'],$errors);
            $this->date($data,'dueDate',$errors);
            $this->relation($data,'propertyId',Property::class);
            $this->relation($data,'leadId',Lead::class);
            $this->lengths($data,$errors);
            if (!$errors) {
                $item->title=$data['title'];$item->data=array_merge($item->data,$data,['isDemo'=>true]);
                $this->save($item);$this->audit($id?'Aufgabe bearbeitet':'Aufgabe erstellt',$item->id);
                $this->addFlash('success','Aufgabe gespeichert.');
                return $this->redirect('/tasks');
            }
        }
        return $this->page('tasks/form.html.twig',['item'=>$item,'data'=>$data,'errors'=>$errors,'statuses'=>self::TASK_STATUSES,'properties'=>$this->store->visible(Property::class,$this->user()),'leads'=>$this->store->visible(Lead::class,$this->user())]);
    }

    #[Route('/tasks/{id}/status', name: 'task_status', methods: ['POST'])]
    public function taskStatus(string $id,Request $request): Response
    {
        $this->token($request);$item=$this->owned(Task::class,$id);
        $status=(string)$request->request->get('status','');
        if (!in_array($status,self::TASK_STATUSES,true)) throw $this->createNotFoundException();
        $item->data['status']=$status;$this->save($item);$this->audit('Aufgabenstatus geändert',$id);
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
        return $this->page('leads/index.html.twig',['items'=>$items,'statuses'=>self::LEAD_STATUSES,'filters'=>$request->query->all()]);
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
                if ($note) $item->data['activities'][]=['date'=>(new \DateTimeImmutable())->format(DATE_ATOM),'note'=>$note];
                $this->save($item);$this->audit($id?'Lead bearbeitet':'Lead erstellt',$item->id);
                $this->addFlash('success','Synthetischer Demo-Lead gespeichert. Es wurde keine Nachricht versendet.');
                return $this->redirect('/leads/'.$item->id);
            }
        }
        $tasks=$id?array_values(array_filter($this->store->visible(Task::class,$this->user()),fn($t)=>($t->data['leadId']??'')===$id)):[];
        return $this->page('leads/form.html.twig',['item'=>$item,'data'=>$data,'errors'=>$errors,'statuses'=>self::LEAD_STATUSES,'properties'=>$this->store->visible(Property::class,$this->user()),'tasks'=>$tasks]);
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
