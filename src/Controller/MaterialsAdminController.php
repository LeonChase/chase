<?php
namespace App\Controller;
use App\Entity\Document;
use App\Service\MaterialsImporter;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_ADMIN')]
final class MaterialsAdminController extends PortalController {
 #[Route('/admin/materials',name:'admin_materials',methods:['GET','POST'])]
 public function import(Request $request,MaterialsImporter $importer):Response {
  $error=null;
  if($request->isMethod('POST')){
   $this->token($request);$file=$request->files->get('archive');
   if(!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile||!$file->isValid())$error='Bitte eine gültige ZIP-Datei auswählen. Falls die Datei zu groß war, den Server mit dem aktualisierten scripts/start neu starten.';
   elseif($file->getSize()>32*1024*1024||strtolower($file->getClientOriginalExtension())!=='zip')$error='Bitte eine ZIP-Datei mit höchstens 32 MB auswählen.';
   else{
    $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
    if(!in_array($mime,['application/zip','application/x-zip','application/x-zip-compressed','application/octet-stream'],true))$error='Der Dateiinhalt ist kein unterstütztes ZIP-Archiv.';
    else{
     $directory=$this->getParameter('kernel.project_dir').'/var/materials/incoming';if(!is_dir($directory))mkdir($directory,0700,true);
     $path=$directory.'/'.bin2hex(random_bytes(16)).'.zip';
     try{
      $file->move($directory,basename($path));chmod($path,0600);$result=$importer->import($path,$this->user()->tenantId);
      $this->audit('Unterlagen importiert: '.$result['created'].' neu, '.$result['updated'].' aktualisiert');
      $this->addFlash('success',sprintf('Unterlagen bereit: %d neu, %d aktualisiert, %d unverändert. Originale bleiben privat in Ihrer lokalen Bibliothek.',$result['created'],$result['updated'],$result['unchanged']));return $this->redirect('/admin/materials');
     }catch(\InvalidArgumentException|\RuntimeException $exception){$error=$exception->getMessage();}
     finally{if(is_file($path))unlink($path);}
    }
   }
  }
  $documents=array_values(array_filter($this->store->all(Document::class),fn($d)=>$d->tenantId===$this->user()->tenantId&&!empty($d->data['provided'])));
  $response=$this->page('admin/materials.html.twig',compact('documents','error'));if($error)$response->setStatusCode(422);return $response;
 }
}
