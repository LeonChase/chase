<?php
namespace App\Controller;

use App\Entity\{Property, MediaAsset, ContentPost, UserPreference};
use App\Service\{PropertyTextProvider, UsageMeter};
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

/** Every tool is rooted in the current, access-checked property and its actual media. */
final class PropertyAiController extends PortalController
{
    #[Route('/properties/{id}/ai/texts', name:'property_ai_generate', methods:['POST'])]
    public function generate(string $id, Request $request, PropertyTextProvider $provider, UsageMeter $meter): Response
    {
        $this->token($request); $property=$this->property($id);
        $images=$this->images($property);
        try {
            $meter->consume($this->user(),'expose',function() use($property,$images,$provider): void {
                $generated=$provider->generate($property,$images);
                $post=new ContentPost(['kind'=>'text','tool'=>'property_expose','propertyId'=>$property->id,'sections'=>$generated['sections'],'source'=>$generated['source'],'mode'=>$generated['mode'],'status'=>'Entwurf','approved'=>false,'isPublished'=>false,'version'=>1,'content'=>$provider->combine($generated['sections']),'versions'=>[]],$property->ownerId,$property->tenantId);
                $post->title=$property->title.' – Exposé';
                $post->data['versions'][]=$this->version($post);
                $this->save($post);$this->audit('Drei Exposé-Abschnitte lokal erstellt',$property->id);
            });
            $this->addFlash('success','Drei Textabschnitte aus den gespeicherten Objektdaten erstellt. Nur manuell bestätigte Bildbeschriftungen wurden übernommen. Kein Bild wurde analysiert.');
        } catch (\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        return $this->back($id,'texts');
    }

    #[Route('/properties/{id}/ai/texts/{postId}', name:'property_ai_save', methods:['POST'])]
    public function saveTexts(string $id, string $postId, Request $request, PropertyTextProvider $provider): Response
    {
        $this->token($request);$this->property($id);
        $post=$this->propertyPost($id,$postId,'property_expose');
        $sections=[];
        foreach (PropertyTextProvider::SECTIONS as $key=>$label) {
            $value=trim($request->request->getString($key));
            if ($value==='' || !mb_check_encoding($value,'UTF-8') || mb_strlen($value)>2000 || str_contains($value,"\0")) {
                $this->addFlash('error',$label.': Bitte 1 bis 2.000 Zeichen eingeben. Der Text wurde nicht gekürzt und nicht gespeichert.');
                return $this->back($id,'texts');
            }
            $sections[$key]=$value;
        }
        $post->data['sections']=$sections;$post->data['content']=$provider->combine($sections);
        $post->data['approved']=$request->request->getString('approved')==='1';
        $post->data['status']=$post->data['approved']?'Freigegeben':'Entwurf';
        $post->data['version']=(int)($post->data['version']??1)+1;
        $post->data['versions'][]=$this->version($post);
        $this->save($post);$this->audit('Exposé-Textversion gespeichert',$id);
        $this->addFlash('success','Version '.$post->data['version'].' gespeichert. Die Faktenquelle der Erstellung bleibt nachvollziehbar.');
        return $this->back($id,'texts');
    }

    #[Route('/properties/{id}/ai/social', name:'property_social_generate', methods:['POST'])]
    public function social(string $id, Request $request, PropertyTextProvider $provider, UsageMeter $meter): Response
    {
        $this->token($request);$property=$this->property($id);
        try {$images=$this->selected($request,$property,false);}
        catch(\InvalidArgumentException $error){$this->addFlash('error',$error->getMessage());return $this->back($id,'marketing');}
        $channel=$request->request->getString('channel','Instagram');
        if (!in_array($channel,['Instagram','Facebook','LinkedIn'],true))throw $this->createNotFoundException();
        try {
            $meter->consume($this->user(),'social',function()use($property,$images,$provider,$channel):void{
                $generated=$provider->social($property,$images);
                $post=new ContentPost(['kind'=>'marketing','tool'=>'property_social','propertyId'=>$property->id,'source'=>$generated['source'],'mediaIds'=>array_map(fn($m)=>$m->id,$images),'content'=>$generated['caption'],'caption'=>$generated['caption'],'channel'=>$channel,'format'=>'Feed','status'=>'Entwurf','approved'=>false,'isPublished'=>false,'plannedAt'=>null,'version'=>1,'versions'=>[],'mode'=>'Lokaler CHASE-Textentwurf'],$property->ownerId,$property->tenantId);
                $post->title=$property->title.' – Social';$post->data['versions'][]=$this->version($post);$this->save($post);$this->audit('Objektbezogenen Social-Entwurf erstellt',$property->id);
            });
            $this->addFlash('success','Kurzer CHASE-Beitrag lokal gespeichert. Die gewählten Bilder sind verknüpft; kein Bild wurde erzeugt und nichts veröffentlicht.');
        }catch(\DomainException $error){$this->addFlash('error',$error->getMessage());}
        return $this->back($id,'marketing');
    }

    #[Route('/properties/{id}/ai/social/{postId}', name:'property_social_save', methods:['POST'])]
    public function saveSocial(string $id,string $postId,Request $request):Response
    {
        $this->token($request);$this->property($id);$post=$this->propertyPost($id,$postId,'property_social');
        $text=trim($request->request->getString('caption'));
        if(!$text||mb_strlen($text)>2000||!mb_check_encoding($text,'UTF-8')||str_contains($text,"\0")){$this->addFlash('error','Der Beitrag benötigt 1 bis 2.000 Zeichen. Es wurde nichts gespeichert.');return $this->back($id,'marketing');}
        $post->data['caption']=$post->data['content']=$text;$post->data['approved']=$request->request->getString('approved')==='1';$post->data['status']=$post->data['approved']?'Freigegeben':'Entwurf';$post->data['version']=(int)($post->data['version']??1)+1;$post->data['versions'][]=$this->version($post);$this->save($post);$this->audit('Social-Textversion gespeichert',$id);$this->addFlash('success','Beitrag gespeichert. Es wurde nichts veröffentlicht.');return $this->back($id,'marketing');
    }

    #[Route('/properties/{id}/ai/media-workflow', name:'property_media_workflow', methods:['POST'])]
    public function workflow(string $id,Request $request):Response
    {
        $this->token($request);$property=$this->property($id);$type=$request->request->getString('type');
        if(!in_array($type,['image','staging','groundplan','doccheck','redaction','storyboard'],true))throw $this->createNotFoundException();
        $section=in_array($type,['groundplan','doccheck','redaction'],true)?'documents':($type==='storyboard'?'marketing':'images');
        try {
            $assets=$this->selected($request,$property,true);
            $isDocument=in_array($type,['groundplan','doccheck','redaction'],true);
            foreach($assets as $asset){
                $document=($asset->data['kind']??'')==='document'||!str_starts_with($asset->data['mime']??'','image/');
                if($isDocument!==$document)throw new \InvalidArgumentException($isDocument?'Bitte eine hochgeladene Unterlage auswählen.':'Bitte nur gespeicherte Objektbilder auswählen.');
                if($type==='groundplan'&&!in_array($asset->data['category']??'',['Grundriss','Skizze'],true))throw new \InvalidArgumentException('Für einen Grundriss bitte eine unter „Grundriss“ oder „Skizze“ einsortierte Originaldatei auswählen.');
            }
            $actions=$this->choices($request,'actions',['brightness','contrast','redact_people','redact_personal','sky','perspective']);
            $styles=$this->choices($request,'styles',['modern','scandinavian','minimal','classic']);
            $checks=$this->choices($request,'checks',['complete','property','energy','sensitive','expert']);
            if($type==='image'&&!$actions)throw new \InvalidArgumentException('Bitte mindestens eine gewünschte Bildbearbeitung auswählen.');
            if($type==='staging'&&!$styles)throw new \InvalidArgumentException('Bitte mindestens einen Einrichtungsstil auswählen.');
            $brightness=$request->request->getString('brightness','100');$contrast=$request->request->getString('contrast','100');
            foreach([$brightness,$contrast] as $v)if(!ctype_digit($v)||(int)$v<60||(int)$v>140)throw new \InvalidArgumentException('Helligkeit und Kontrast müssen zwischen 60 und 140 Prozent liegen.');
            $notes=trim($request->request->getString('notes'));if(mb_strlen($notes)>2000||!mb_check_encoding($notes,'UTF-8'))throw new \InvalidArgumentException('Der Vorbereitungshinweis darf höchstens 2.000 Zeichen enthalten.');
            $job=new UserPreference(['kind'=>'media_workflow','propertyId'=>$id,'type'=>$type,'mediaIds'=>array_map(fn($m)=>$m->id,$assets),'sources'=>array_map(fn($m)=>['id'=>$m->id,'title'=>$m->title,'category'=>$m->data['category']??'Sonstiges','originalName'=>$m->data['originalName']??$m->title],$assets),'actions'=>$actions,'styles'=>$styles,'manualChecks'=>$checks,'brightness'=>(int)$brightness,'contrast'=>(int)$contrast,'notes'=>$notes,'status'=>'Vorbereitet – externe API nicht verbunden','processed'=>false,'originalsPreserved'=>true,'isPublished'=>false,'mode'=>$type==='storyboard'?'Lokales Storyboard, kein Videoexport':'Vorbereitung, keine KI-Verarbeitung'],$this->user()->id,$this->user()->tenantId);
            $job->title=['image'=>'Bildbearbeitung','staging'=>'Home Staging','groundplan'=>'Grundriss','doccheck'=>'Manuelle Dokumentenprüfung','redaction'=>'Schwärzung vorbereiten','storyboard'=>'Bildersequenz'][$type];
            $this->save($job);$this->audit('Medienworkflow vorbereitet: '.$type,$id);$this->addFlash('success','Auswahl und gewünschte Schritte lokal gespeichert. Originale unverändert; keine KI-Verarbeitung oder externe Übertragung.');
        }catch(\InvalidArgumentException $error){$this->addFlash('error',$error->getMessage());}
        return $this->back($id,$section);
    }

    private function property(string $id):Property{$property=$this->owned(Property::class,$id);$this->denyAccessUnlessGranted('EDIT',$property);return $property;}
    private function images(Property $property):array{return array_values(array_filter($this->store->visible(MediaAsset::class,$this->user()),fn($m)=>($m->data['propertyId']??'')===$property->id&&($m->data['kind']??'')!=='document'&&str_starts_with($m->data['mime']??'','image/')));}
    private function propertyPost(string $id,string $postId,string $tool):ContentPost{$post=$this->owned(ContentPost::class,$postId);if(($post->data['propertyId']??'')!==$id||($post->data['tool']??'')!==$tool)throw $this->createNotFoundException();$this->denyAccessUnlessGranted('EDIT',$post);return $post;}
    private function selected(Request $request,Property $property,bool $required):array
    {
        $ids=$request->request->all('mediaIds');
        if(count($ids)>20||($required&&!$ids))throw new \InvalidArgumentException('Bitte eine bis 20 passende Originaldateien auswählen.');
        $assets=[];
        foreach($ids as $id)if(!is_string($id))throw $this->createNotFoundException();
        foreach(array_unique($ids) as $id){$asset=$this->owned(MediaAsset::class,$id);if(($asset->data['propertyId']??'')!==$property->id)throw $this->createNotFoundException();
            $filename=$asset->data['filename']??'';if(!is_string($filename)||!preg_match('/^[a-f0-9]{40}\.(jpg|jpeg|png|webp|pdf)$/D',$filename))throw $this->createNotFoundException('Originaldatei nicht verfügbar.');
            $directory=$this->getParameter('kernel.project_dir').'/var/uploads';$path=realpath($directory.'/'.$filename);if(!$path||dirname($path)!==realpath($directory)||!is_file($path))throw $this->createNotFoundException('Originaldatei nicht verfügbar.');
            if(!$required&&(($asset->data['kind']??'')==='document'||!str_starts_with($asset->data['mime']??'','image/')))throw $this->createNotFoundException();
            $assets[]=$asset;
        }return $assets;
    }
    private function choices(Request $request,string $field,array $allowed):array{$values=$request->request->all($field);foreach($values as $value)if(!is_string($value)||!in_array($value,$allowed,true))throw new \InvalidArgumentException('Bitte nur die angebotenen Optionen auswählen.');return array_values(array_unique($values));}
    private function version(ContentPost $post):array{return ['number'=>$post->data['version'],'title'=>$post->title,'text'=>$post->data['content'],'sections'=>$post->data['sections']??[],'time'=>(new \DateTimeImmutable())->format(DATE_ATOM),'approved'=>$post->data['approved']??false];}
    private function back(string $id,string $section):Response{return $this->redirect('/properties/'.$id.'#'.$section);}
}
