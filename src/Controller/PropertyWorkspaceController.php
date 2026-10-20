<?php
namespace App\Controller;

use App\Entity\{Property, Task, MediaAsset, ContentPost, AuditEvent, UserPreference, Valuation};
use App\Service\{PropertyFormProcessor, PropertyFormSchema};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, Response, ResponseHeaderBag};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class PropertyWorkspaceController extends PortalController
{
    private const IMAGE_CATEGORIES = ['Außenansicht','Wohnbereich','Küche','Bad','Schlafzimmer','Garten','Details','Sonstiges'];
    private const DOCUMENT_CATEGORIES = ['Grundriss','Skizze','Energieausweis','Grundbuchauszug','Lageplan','Wohnflächenberechnung','Hausgeldabrechnung','Mietvertrag','Sonstiges'];
    private const PROPERTY_STATUSES = PropertyFormSchema::STATUSES;
    private const PROPERTY_TYPES = PropertyFormSchema::TYPES;
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
    public function propertyForm(Request $request, PropertyFormProcessor $processor, ?string $id = null): Response
    {
        $property = $id ? $this->owned(Property::class, $id) : new Property(['status'=>'Entwurf','region'=>$this->user()->region,'marketingType'=>'Kauf','source'=>'Lokal erfasst','isDemo'=>false], $this->user()->id, $this->user()->tenantId);
        $data = array_merge($property->data, ['title'=>$property->title]);
        $errors = [];
        if ($request->isMethod('POST')) {
            $this->token($request);
            $result = $processor->process($request, $data);
            $data = $result['data'];
            $errors = $result['errors'];
            if (!$errors) {
                $property->data = $data;
                $property->title = $data['title'];
                $this->save($property);
                $this->audit($id ? 'Objektarbeitsplatz gespeichert' : 'Objekt erstellt', $property->id);
                $this->addFlash('success', 'Objektdaten gespeichert. Du kannst direkt im nächsten Abschnitt weiterarbeiten.');
                $section = $this->section($request->request->get('returnSection', ''));
                return $this->redirect('/properties/'.$property->id.($section ? '#'.$section : ''));
            }
        }
        return $this->workspace($property, $request, $id !== null, $data, $errors);
    }

    #[Route('/properties/{id}', name: 'property_show', methods: ['GET'], priority: -10)]
    public function propertyShow(string $id, Request $request): Response
    {
        return $this->workspace($this->owned(Property::class, $id), $request, true);
    }

    private function workspace(Property $property, Request $request, bool $saved, ?array $data = null, array $errors = []): Response
    {
        $id = $property->id;
        $related = fn (string $class) => $saved ? array_values(array_filter($this->store->visible($class, $this->user()), fn ($r) => ($r->data['propertyId'] ?? '') === $id)) : [];
        $media = $related(MediaAsset::class);
        usort($media, fn ($a,$b) => (($a->data['position'] ?? 10000) <=> ($b->data['position'] ?? 10000)) ?: ($a->createdAt <=> $b->createdAt) ?: strcmp($a->id,$b->id));
        $images = array_values(array_filter($media, fn ($m) => ($m->data['kind'] ?? '') !== 'document' && str_starts_with($m->data['mime'] ?? '', 'image/')));
        $documents = array_values(array_filter($media, fn ($m) => ($m->data['kind'] ?? '') === 'document' || !str_starts_with($m->data['mime'] ?? '', 'image/')));
        $tab = $request->query->get('tab', '');
        $initialSection = ['overview'=>'start','media'=>'images','documents'=>'documents','texts'=>'texts','marketing'=>'marketing','checklist'=>'marketing','activity'=>'activity'][$tab] ?? 'start';
        $activities = $saved ? array_values(array_filter($this->store->visible(AuditEvent::class, $this->user()), fn ($a) => ($a->data['entityId'] ?? '') === $id)) : [];
        return $this->page('properties/form.html.twig', [
            'item'=>$property, 'data'=>$data ?? array_merge($property->data,['title'=>$property->title]), 'saved'=>$saved,
            'errors'=>$errors, 'sections'=>PropertyFormSchema::sections(), 'statuses'=>PropertyFormSchema::STATUSES,
            'types'=>PropertyFormSchema::TYPES, 'modernisationComponents'=>PropertyFormSchema::MODERNISATION_COMPONENTS,
            'media'=>$media, 'images'=>$images, 'documents'=>$documents, 'tasks'=>$related(Task::class), 'posts'=>$related(ContentPost::class),
            'activities'=>$activities, 'checklist'=>self::CHECKLIST, 'initialSection'=>$initialSection,
            'valuations'=>array_values(array_filter($related(Valuation::class), fn ($v)=>($v->data['kind'] ?? '') === 'calculation')),
            'aiWorkflows'=>array_values(array_filter($this->store->visible(UserPreference::class,$this->user()),fn ($p)=>($p->data['kind'] ?? '') === 'media_workflow' && ($p->data['propertyId'] ?? '') === $id)),
            'imageCategories'=>self::IMAGE_CATEGORIES, 'documentCategories'=>self::DOCUMENT_CATEGORIES,
        ]);
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
        return $this->redirect('/properties/'.$id.($request->request->get('_workspace') === '1' ? '#marketing' : '?tab=checklist'));
    }

    #[Route('/properties/{id}/media', name: 'property_upload', methods: ['POST'])]
    public function upload(string $id, Request $request): Response
    {
        $this->token($request);
        $property = $this->owned(Property::class, $id);
        $files = $request->files->get('media');
        $files = is_array($files) ? array_values($files) : ($files ? [$files] : []);
        $kind = $request->request->get('kind', 'image');
        if (!in_array($kind, ['image','document'], true)) throw $this->createNotFoundException();
        $section = $kind === 'document' ? 'documents' : 'images';
        if (!$files || count($files) > 12) {
            $this->addFlash('error', 'Bitte eine bis zwölf Dateien auswählen (je höchstens 8 MB, zusammen höchstens 16 MB).');
            return $this->mediaRedirect($id, $request, $section);
        }
        $total = 0;
        foreach ($files as $file) if ($file instanceof UploadedFile && $file->isValid()) $total += $file->getSize();
        if ($total > 16*1024*1024) {
            $this->addFlash('error', 'Die Auswahl überschreitet insgesamt 16 MB. Bitte in kleineren Gruppen hochladen.');
            return $this->mediaRedirect($id, $request, $section);
        }
        $types = ['image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp'],'application/pdf'=>['pdf']];
        $existing = array_filter($this->store->visible(MediaAsset::class, $this->user()), fn ($a) => ($a->data['propertyId'] ?? '') === $id);
        $position = max([0,...array_map(fn ($a) => (int)($a->data['position'] ?? 0), $existing)]);
        $count = 0;
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                $this->addFlash('error', 'Eine ausgewählte Datei konnte nicht übertragen werden. Bitte Größe und Datei prüfen.');
                continue;
            }
            $original = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())),0,180);
            if (!mb_check_encoding($original,'UTF-8') || str_contains($original,"\0")) {
                $this->addFlash('error','Ein Dateiname enthält ungültige Zeichen. Bitte lokal umbenennen und erneut hochladen.');
                continue;
            }
            if ($file->getSize() > 8*1024*1024 || $file->getSize() < 1) {
                $this->addFlash('error', $original.': erlaubt sind nicht leere Dateien bis 8 MB.');
                continue;
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
            $extension = strtolower($file->getClientOriginalExtension());
            if (!isset($types[$mime]) || !in_array($extension,$types[$mime],true) || ($kind === 'image' && !str_starts_with($mime,'image/'))) {
                // The original single-file endpoint also accepted PDF without a kind field.
                if (!($request->request->has('kind') === false && $mime === 'application/pdf' && $extension === 'pdf')) {
                    $this->addFlash('error', $original.': Dateityp nicht erlaubt. Bilder: JPG, PNG, WebP; Unterlagen zusätzlich PDF.');
                    continue;
                }
            }
            if (str_starts_with($mime,'image/')) {
                $dimensions = @getimagesize($file->getPathname());
                if (!$dimensions || $dimensions[0]*$dimensions[1] > 80000000) {
                    $this->addFlash('error', $original.': kein lesbares Bild oder mehr als 80 Megapixel.');
                    continue;
                }
            }
            $filename = bin2hex(random_bytes(20)).'.'.$extension;
            $directory = $this->getParameter('kernel.project_dir').'/var/uploads';
            if (!is_dir($directory)) mkdir($directory,0700,true);
            $file->move($directory,$filename);
            chmod($directory.'/'.$filename,0600);
            $position += 10;
            $actualKind = $mime === 'application/pdf' ? 'document' : $kind;
            $asset = new MediaAsset([
                'title'=>$original, 'originalName'=>$original, 'propertyId'=>$id, 'filename'=>$filename, 'mime'=>$mime,
                'size'=>filesize($directory.'/'.$filename), 'kind'=>$actualKind, 'position'=>$position, 'caption'=>'',
                'category'=>'Sonstiges', 'factsConfirmed'=>false, 'isOriginal'=>true, 'isDemo'=>$property->data['isDemo'] ?? false,
            ],$property->ownerId,$property->tenantId);
            $this->save($asset);
            ++$count;
        }
        if ($count) {
            $this->audit($count.' private Objektdatei(en) hochgeladen',$id);
            $this->addFlash('success', $count.' Datei(en) privat gespeichert. Originale bleiben erhalten; kein externer Upload.');
        }
        return $this->mediaRedirect($id,$request,$section);
    }

    #[Route('/properties/{id}/media/{mediaId}/metadata', name: 'property_media_metadata', methods: ['POST'])]
    public function mediaMetadata(string $id, string $mediaId, Request $request): Response
    {
        $this->token($request);
        $asset = $this->propertyAsset($id,$mediaId);
        $title = $this->shortString($request,'title',180);
        $caption = $this->shortString($request,'caption',500);
        $kind = $request->request->get('kind',$asset->data['kind'] ?? 'image');
        $category = $this->shortString($request,'category',80);
        $position = $request->request->get('position','0');
        $valid = $title !== null && $title !== '' && $caption !== null && $category !== null && in_array($kind,['image','document'],true)
            && preg_match('/^\d{1,4}$/',(string)$position) && in_array($category,$kind === 'image' ? self::IMAGE_CATEGORIES : self::DOCUMENT_CATEGORIES,true)
            && ($kind === 'document' || str_starts_with($asset->data['mime'] ?? '','image/'));
        if (!$valid) {
            $this->addFlash('error','Bitte Titel (max. 180 Zeichen), Beschriftung (max. 500), Kategorie und Reihenfolge (0–9999) prüfen.');
        } else {
            $asset->title=$title;
            $asset->data=array_merge($asset->data,['title'=>$title,'caption'=>$caption,'kind'=>$kind,'category'=>$category,'position'=>(int)$position,'factsConfirmed'=>$request->request->get('factsConfirmed','0')==='1']);
            $this->save($asset);
            $this->audit('Objektdatei beschriftet und einsortiert',$id);
            $this->addFlash('success','Dateiangaben gespeichert. Der ursprüngliche Dateiname und das Original bleiben erhalten.');
        }
        return $this->mediaRedirect($id,$request, ($asset->data['kind'] ?? '') === 'document' ? 'documents':'images');
    }

    #[Route('/properties/{id}/media/{mediaId}/move', name: 'property_media_move', methods: ['POST'])]
    public function mediaMove(string $id, string $mediaId, Request $request): Response
    {
        $this->token($request);
        $asset=$this->propertyAsset($id,$mediaId);
        $direction=$request->request->get('direction','');
        if (!in_array($direction,['up','down'],true)) throw $this->createNotFoundException();
        $kind=($asset->data['kind'] ?? '') === 'document' || !str_starts_with($asset->data['mime'] ?? '', 'image/') ? 'document' : 'image';
        $items=array_values(array_filter($this->store->visible(MediaAsset::class,$this->user()),fn ($m)=>($m->data['propertyId'] ?? '')===$id && (($m->data['kind'] ?? '')==='document'||!str_starts_with($m->data['mime'] ?? '','image/') ? 'document':'image')===$kind));
        usort($items,fn ($a,$b)=>(($a->data['position'] ?? 10000)<=>($b->data['position'] ?? 10000)) ?: ($a->createdAt<=>$b->createdAt) ?: strcmp($a->id,$b->id));
        $index=array_search($mediaId,array_map(fn ($m)=>$m->id,$items),true);
        $target=$index+($direction==='up' ? -1 : 1);
        if ($index!==false && isset($items[$target])) {
            [$items[$index],$items[$target]]=[$items[$target],$items[$index]];
            foreach ($items as $i=>$item) {$item->data['position']=($i+1)*10;$item->updatedAt=new \DateTimeImmutable();$this->store->em()->persist($item);}
            $this->store->em()->flush();
            $this->audit('Reihenfolge der Objektdateien geändert',$id);
        }
        return $this->mediaRedirect($id,$request,$kind === 'document' ? 'documents':'images');
    }

    #[Route('/properties/{id}/media/{mediaId}/delete', name: 'property_media_delete', methods: ['POST'])]
    public function mediaDelete(string $id, string $mediaId, Request $request): Response
    {
        $this->token($request);
        $asset=$this->propertyAsset($id,$mediaId);
        $section=($asset->data['kind'] ?? '')==='document'||!str_starts_with($asset->data['mime'] ?? '','image/') ? 'documents':'images';
        $filename=$asset->data['filename'] ?? '';
        if (!preg_match('/^[a-f0-9]{40}\.(jpg|jpeg|png|webp|pdf)$/',$filename)) throw $this->createNotFoundException();
        $path=$this->getParameter('kernel.project_dir').'/var/uploads/'.$filename;
        $this->store->remove($asset);
        if (is_file($path)) unlink($path);
        $this->audit('Private Objektdatei entfernt',$id);
        $this->addFlash('success','Datei und gespeichertes Original entfernt.');
        return $this->mediaRedirect($id,$request,$section);
    }

    #[Route('/media/{id}', name: 'media_download', methods: ['GET'])]
    public function media(string $id): Response
    {
        $asset=$this->owned(MediaAsset::class,$id);
        $this->owned(Property::class,(string)($asset->data['propertyId'] ?? ''));
        $filename=(string)($asset->data['filename'] ?? '');
        if (!preg_match('/^[a-f0-9]{40}\.(jpg|jpeg|png|webp|pdf)$/',$filename)) throw $this->createNotFoundException();
        $directory=$this->getParameter('kernel.project_dir').'/var/uploads';
        $path=realpath($directory.'/'.$filename);
        if (!$path || dirname($path)!==realpath($directory) || !is_file($path)) throw $this->createNotFoundException('Datei nicht mehr vorhanden.');
        $name=preg_replace('/[\x00-\x1f\x7f\/\\\\]/u','-', $asset->title) ?: 'Objektdatei';
        $extension=pathinfo($filename,PATHINFO_EXTENSION);
        if (!str_ends_with(mb_strtolower($name),'.'.$extension)) $name.='.'.$extension;
        $response=$this->file($path,$name,str_starts_with((string)$asset->data['mime'],'image/') ? ResponseHeaderBag::DISPOSITION_INLINE:ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        $response->headers->set('Content-Type',(string)$asset->data['mime']);
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('Content-Security-Policy',"default-src 'none'; sandbox");
        $response->headers->set('Cache-Control','private, no-store');
        return $response;
    }

    private function propertyAsset(string $propertyId,string $mediaId): MediaAsset
    {
        $this->owned(Property::class,$propertyId);
        $asset=$this->owned(MediaAsset::class,$mediaId);
        if (($asset->data['propertyId'] ?? '')!==$propertyId) throw $this->createNotFoundException();
        return $asset;
    }
    private function mediaRedirect(string $id,Request $request,string $section): Response
    {
        return $this->redirect('/properties/'.$id.($request->request->get('_workspace')==='1' ? '#'.$section : '?tab=media'));
    }
    private function section(mixed $value): string
    {
        return is_string($value) && in_array($value,['start','base','equipment','condition','images','documents','pricing','texts','marketing','activity'],true) ? $value : '';
    }
    private function shortString(Request $request,string $field,int $max): ?string
    {
        $value=$request->request->all()[$field] ?? '';
        return is_string($value) && mb_check_encoding($value,'UTF-8') && !str_contains($value,"\0") && mb_strlen(trim($value))<=$max ? trim($value):null;
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
