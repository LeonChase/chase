<?php
namespace App\Controller;

use App\Entity\{ContentPost,MediaAsset,Property};
use App\Service\{DemoTextProvider,UsageMeter};
use Symfony\Component\HttpFoundation\{Request,Response,ResponseHeaderBag};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;

/** Small independent tools; no provider is contacted and no AI processing is claimed. */
final class ToolboxController extends PortalController
{
    private const CHECKS=['source'=>'Quelle und Berechtigung geklärt','pages'=>'Seiten und Anhänge vollständig','facts'=>'Angaben mit den Originalnachweisen abgeglichen','dates'=>'Datum und Aktualität geprüft','sensitive'=>'Personenbezogene Angaben vor Weitergabe geprüft'];

    #[Route('/tools',name:'toolbox',methods:['GET'])]
    public function index():Response
    {
        return $this->page('toolbox/index.html.twig',['properties'=>$this->store->visible(Property::class,$this->user())]);
    }

    #[Route('/tools/texts',name:'toolbox_texts',methods:['GET'])]
    public function texts(Request $request,UsageMeter $meter):Response
    {
        $tool=$request->query->getString('tool','expose');if(!isset(DemoTextProvider::TOOLS[$tool]))$tool='expose';
        $id=$request->query->getString('property');$property=$id?$this->owned(Property::class,$id):null;
        return $this->page('toolbox/texts.html.twig',['tool'=>$tool,'tools'=>DemoTextProvider::TOOLS,'tones'=>DemoTextProvider::TONES,'property'=>$property,'properties'=>$this->store->visible(Property::class,$this->user()),'balance'=>$meter->available($this->user()),'posts'=>array_slice($this->store->visible(ContentPost::class,$this->user()),0,8)]);
    }

    #[Route('/tools/images',name:'toolbox_images',methods:['GET'])]
    public function images():Response {return $this->page('toolbox/images.html.twig');}

    #[Route('/tools/workflow/{type}',name:'toolbox_workflow',methods:['GET'])]
    public function workflow(string $type,Request $request):Response
    {
        $types=['staging'=>['Home Staging','Einrichtungswünsche mit vorhandenen Objektfotos vorbereiten.','images'],'floorplan'=>['Grundrisse','Vorhandenen Grundriss auswählen und gewünschte Anpassungen dokumentieren.','documents'],'redaction'=>['Dokumentenschwärzung','Schwärzungsbedarf an einer Originaldatei dokumentieren.','documents'],'video'=>['Bild zu Video','Vorhandene Objektbilder für ein Storyboard auswählen.','marketing'],'social'=>['Social Media','Kurzen Beitrag mit gespeicherten Objektdaten und vorhandenen Bildern vorbereiten.','marketing']];
        if(!isset($types[$type]))throw $this->createNotFoundException();
        if($id=$request->query->getString('property')){$this->owned(Property::class,$id);return $this->redirect('/properties/'.rawurlencode($id).'#'.$types[$type][2]);}
        return $this->page('toolbox/workflow.html.twig',['type'=>$type,'details'=>$types[$type],'properties'=>$this->store->visible(Property::class,$this->user())]);
    }

    #[Route('/tools/documents',name:'toolbox_documents',methods:['GET','POST'])]
    #[Route('/tools/documents/{id}',name:'toolbox_document',methods:['GET','POST'])]
    public function documents(Request $request,?string $id=null):Response
    {
        $asset=$id?$this->document($id):null;$error=null;
        if($request->isMethod('POST')){
            $this->token($request);
            if($asset){
                $notes=trim($request->request->getString('notes'));$checks=$request->request->all('checks');
                if(mb_strlen($notes)>5000||!mb_check_encoding($notes,'UTF-8')||str_contains($notes,"\0")||count(array_filter($checks,'is_string'))!==count($checks)||array_diff($checks,array_keys(self::CHECKS)))$error='Bitte die Prüfnotizen und die ausgewählten Prüfpunkte prüfen.';
                else{$asset->data['notes']=$notes;$asset->data['checks']=array_values(array_unique($checks));$asset->data['reviewedAt']=(new \DateTimeImmutable())->format(DATE_ATOM);$this->save($asset);$this->audit('toolbox.document.notes.saved',$asset->id);return $this->redirectToRoute('toolbox_document',['id'=>$asset->id]);}
            }else{
                $file=$request->files->get('document');
                try{$asset=$this->upload($file);$this->save($asset);$this->audit('toolbox.document.uploaded',$asset->id);return $this->redirectToRoute('toolbox_document',['id'=>$asset->id]);}
                catch(\InvalidArgumentException $exception){$error=$exception->getMessage();}
            }
        }
        $documents=array_values(array_filter($this->store->visible(MediaAsset::class,$this->user()),fn($m)=>$m->ownerId===$this->user()->id&&($m->data['kind']??'')==='toolbox_document'));
        return $this->page('toolbox/documents.html.twig',['asset'=>$asset,'documents'=>$documents,'checks'=>self::CHECKS,'error'=>$error]);
    }

    #[Route('/tools/documents/{id}/download',name:'toolbox_document_download',methods:['GET'])]
    public function download(string $id):Response
    {
        $asset=$this->document($id);$path=$this->documentPath($asset);
        $response=$this->file($path,$asset->title,ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        $response->headers->set('Content-Type',$asset->data['mime']);$response->headers->set('Content-Security-Policy',"default-src 'none'; sandbox");return $response;
    }

    #[Route('/tools/documents/{id}/delete',name:'toolbox_document_delete',methods:['POST'])]
    public function delete(string $id,Request $request):Response
    {
        $this->token($request);$asset=$this->document($id);$path=$this->documentPath($asset);$this->store->remove($asset);unlink($path);$this->audit('toolbox.document.deleted',$id);return $this->redirectToRoute('toolbox_documents');
    }

    private function document(string $id):MediaAsset
    {
        $asset=$this->owned(MediaAsset::class,$id);if($asset->ownerId!==$this->user()->id||($asset->data['kind']??'')!=='toolbox_document')throw $this->createNotFoundException();return $asset;
    }
    private function directory():string
    {
        return $this->getParameter('kernel.project_dir').'/var/toolbox/'.substr(hash('sha256',$this->user()->tenantId),0,16).'/'.substr(hash('sha256',$this->user()->id),0,16);
    }
    private function documentPath(MediaAsset $asset):string
    {
        $name=$asset->data['filename']??'';if(!preg_match('/^[a-f0-9]{40}\.(pdf|jpg|png|webp)$/D',$name))throw $this->createNotFoundException();
        $path=$this->directory().'/'.$name;if(is_link($path)||!is_file($path)||!hash_equals($asset->data['sha256']??'',hash_file('sha256',$path)))throw $this->createNotFoundException();return $path;
    }
    private function upload(mixed $file):MediaAsset
    {
        if(!$file instanceof UploadedFile||!$file->isValid()||$file->getSize()>8*1024*1024||$file->getSize()===0)throw new \InvalidArgumentException('Bitte PDF, JPG, PNG oder WebP bis 8 MB auswählen.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());$ext=strtolower($file->getClientOriginalExtension());$allowed=['application/pdf'=>['pdf'],'image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp']];
        if(!isset($allowed[$mime])||!in_array($ext,$allowed[$mime],true))throw new \InvalidArgumentException('Dateiendung und Dateiinhalt müssen zusammenpassen.');
        if($mime==='application/pdf'){if(!str_starts_with((string)file_get_contents($file->getPathname(),false,null,0,5),'%PDF-'))throw new \InvalidArgumentException('Die Datei hat keine gültige PDF-Kennung.');}
        else{$size=@getimagesize($file->getPathname());if(!$size||$size[0]*$size[1]>25000000)throw new \InvalidArgumentException('Bitte ein lesbares Bild mit höchstens 25 Megapixeln verwenden.');}
        $name=basename(str_replace('\\','/',$file->getClientOriginalName()));$name=preg_replace('/[\x00-\x1f\x7f]/u','',$name)??'';if($name===''||mb_strlen($name)>180)throw new \InvalidArgumentException('Bitte einen Dateinamen mit höchstens 180 Zeichen verwenden.');
        $dir=$this->directory();if(!is_dir($dir))mkdir($dir,0700,true);$filename=bin2hex(random_bytes(20)).'.'.($ext==='jpeg'?'jpg':$ext);$hash=hash_file('sha256',$file->getPathname());$bytes=$file->getSize();$file->move($dir,$filename);chmod($dir.'/'.$filename,0600);
        return new MediaAsset(['title'=>$name,'kind'=>'toolbox_document','filename'=>$filename,'mime'=>$mime,'bytes'=>$bytes,'sha256'=>$hash,'notes'=>'','checks'=>[],'originalPreserved'=>true,'automatedAnalysis'=>false],$this->user()->id,$this->user()->tenantId);
    }
}
