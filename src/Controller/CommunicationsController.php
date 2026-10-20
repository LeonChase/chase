<?php
namespace App\Controller;
use App\Entity\{Message,User,Property,PartnerProfile,AcademyArticle};
use App\Service\ContactDirectory;
use Symfony\Component\HttpFoundation\{Request,Response,ResponseHeaderBag};
use Symfony\Component\Routing\Attribute\Route;
final class CommunicationsController extends PortalController {
 #[Route('/messages',name:'messages',methods:['GET','POST'])]
 public function messages(Request $request,ContactDirectory $directory):Response {
  $user=$this->user();$contacts=$directory->contacts($user);$error=null;
  $property=null;$propertyId=$request->isMethod('POST')?$request->request->getString('propertyId'):$request->query->getString('property');
  if($propertyId)$property=$this->owned(Property::class,$propertyId);
  if($request->isMethod('POST')){
   $this->token($request);$recipient=$directory->recipient($request->request->getString('recipientId'),$user);
   $title=trim($request->request->getString('title'));$body=trim($request->request->getString('body'));
   if(!$recipient)$error='Bitte einen verfügbaren internen Kontakt auswählen.';
   elseif(!$title||mb_strlen($title)>180||!$body||mb_strlen($body)>5000)$error='Bitte Betreff (maximal 180 Zeichen) und Nachricht (maximal 5.000 Zeichen) ausfüllen.';
   if(!$error){
    // Link a property only when recipient already has explicit access. Sending a message is not an ACL grant.
    if($property&&!$this->store->canAccess($property,$recipient)){$error='Der Empfänger hat keine Objektfreigabe. Bitte im Admin-Center eine ausdrückliche Teamfreigabe einrichten oder eine allgemeine Nachricht ohne Objektbezug schreiben.';}
    else{$m=new Message(['title'=>$title,'body'=>$body,'recipientId'=>$recipient->id,'propertyId'=>$property?->id,'readAt'=>null],$user->id,$user->tenantId);$this->save($m);$this->audit('Interne Nachricht gesendet',$m->id);$this->addFlash('success','Interne Nachricht gespeichert. Es wurde keine E-Mail versendet.');return $this->redirect('/messages/'.$m->id);}
   }
  }
  $messages=$this->store->visible(Message::class,$user);$names=[];foreach($this->store->em()->getRepository(User::class)->findBy(['tenantId'=>$user->tenantId]) as $u)$names[$u->id]=$u->name;
  return $this->page('messages/index.html.twig',compact('contacts','messages','names','property','error'));
 }
 #[Route('/messages/{id}',name:'message_show',methods:['GET'])]
 public function show(string $id):Response {
  $message=$this->owned(Message::class,$id);$user=$this->user();
  $otherId=$message->ownerId===$user->id?$message->recipientId:$message->ownerId;
  $other=$this->store->em()->find(User::class,$otherId);
  return $this->page('messages/show.html.twig',compact('message','other'));
 }
 #[Route('/messages/{id}/read',name:'message_read',methods:['POST'])]
 public function read(string $id,Request $request):Response {
  $this->token($request);$m=$this->owned(Message::class,$id);if($m->recipientId!==$this->user()->id)throw $this->createAccessDeniedException();
  $m->data['readAt']=(new \DateTimeImmutable())->format(DATE_ATOM);$this->save($m);return $this->redirect('/messages/'.$id);
 }
 #[Route('/profile/avatar',name:'profile_avatar_upload',methods:['POST'])]
 public function avatarUpload(Request $request):Response {
  $this->token($request);$user=$this->user();$file=$request->files->get('avatar');
  $allowed=['image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp']];$error=null;
  if(!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile||!$file->isValid())$error='Bitte ein gültiges Profilbild auswählen.';
  elseif($file->getSize()>4*1024*1024)$error='Das Profilbild darf höchstens 4 MB groß sein.';
  else{$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());$ext=strtolower($file->getClientOriginalExtension());$dimensions=@getimagesize($file->getPathname());if(!isset($allowed[$mime])||!in_array($ext,$allowed[$mime],true)||!$dimensions||$dimensions[0]>8000||$dimensions[1]>8000)$error='Bitte JPG, PNG oder WebP mit passendem Bildinhalt und maximal 8.000 Pixeln pro Seite verwenden.';}
  if($error){$this->addFlash('error',$error);return $this->redirect('/profile');}
  $dir=$this->getParameter('kernel.project_dir').'/var/avatars';if(!is_dir($dir))mkdir($dir,0700,true);
  $filename=bin2hex(random_bytes(20)).'.'.$ext;$file->move($dir,$filename);chmod($dir.'/'.$filename,0600);
  $old=$user->preferences['avatarFilename']??null;$user->preferences['avatarFilename']=$filename;$user->preferences['avatarMime']=$mime;$this->save($user);
  if(is_string($old)&&preg_match('/^[a-f0-9]{40}\.(jpg|jpeg|png|webp)$/',$old)&&is_file($dir.'/'.$old))unlink($dir.'/'.$old);
  $this->audit('Profilbild aktualisiert',$user->id);$this->addFlash('success','Profilbild gespeichert. Es wird nur lokal verarbeitet.');return $this->redirect('/profile');
 }
 #[Route('/profile/avatar/{id}',name:'profile_avatar',methods:['GET'])]
 public function avatar(string $id):Response {
  $viewer=$this->user();$user=$this->store->em()->find(User::class,$id);if(!$user||!$user->active||$user->tenantId!==$viewer->tenantId)throw $this->createNotFoundException();
  if($viewer->id!==$id){$visible=false;foreach($this->store->visible(PartnerProfile::class,$viewer) as $p)if(($p->data['userId']??'')===$id)$visible=true;if(!$visible)throw $this->createNotFoundException();}
  $filename=$user->preferences['avatarFilename']??'';if(!preg_match('/^[a-f0-9]{40}\.(jpg|jpeg|png|webp)$/',$filename))throw $this->createNotFoundException();
  $path=$this->getParameter('kernel.project_dir').'/var/avatars/'.$filename;if(!is_file($path))throw $this->createNotFoundException();
  $response=$this->file($path,'Profilbild',ResponseHeaderBag::DISPOSITION_INLINE);$response->headers->set('Content-Type',$user->preferences['avatarMime']);return $response;
 }
 #[Route('/profile/avatar/remove',name:'profile_avatar_remove',methods:['POST'],priority:10)]
 public function removeAvatar(Request $request):Response {
  $this->token($request);$u=$this->user();$f=$u->preferences['avatarFilename']??'';unset($u->preferences['avatarFilename'],$u->preferences['avatarMime']);$this->save($u);
  $dir=$this->getParameter('kernel.project_dir').'/var/avatars/';if(preg_match('/^[a-f0-9]{40}\.(jpg|jpeg|png|webp)$/',$f)&&is_file($dir.$f))unlink($dir.$f);
  $this->addFlash('success','Profilbild entfernt.');return $this->redirect('/profile');
 }
 #[Route('/help/assistant',name:'help_assistant',methods:['GET','POST'])]
 public function assistant(Request $request,\App\Service\Lexicon $lexicon):Response {
  $question=mb_substr(trim($request->isMethod('POST')?$request->request->getString('question'):$request->query->getString('q')),0,500);$answers=[];
  if($request->isMethod('POST'))$this->token($request);
  $topics=[
   ['muster'=>['werbe','katalog','bestell','warenkorb','flyer'],'title'=>'Werbemittel auswählen','text'=>'Öffnen Sie den Werbekatalog, blättern Sie zu einem Design und legen Sie die gewünschte Menge in den Warenkorb. Die Anfrage bleibt ein lokaler Entwurf für das Partnermanagement; sie ist keine Bestellung.','url'=>'/advertising'],
   ['muster'=>['objekt','immobil','exposé','expose','bild','grundriss','staging'],'title'=>'In der Objektakte arbeiten','text'=>'Unter Meine Immobilien finden Sie die Objektakte. Die Abschnitte führen von Grunddaten über Bilder und Unterlagen zu Preis, Exposé-Texten und Vermarktung. Bilder und Texte werden nicht ohne eingerichtete API an Drittanbieter gesendet.','url'=>'/properties'],
   ['muster'=>['dokument','unterlag','vertrag','vorlag'],'title'=>'Unterlagen schnell finden','text'=>'Im Dokumentencenter wählen Sie zuerst den Arbeitsbereich und bei Bedarf einen Unterordner. Suche, Dateityp und Sortierung helfen beim Finden. Downloads verlangen Ihre Anmeldung.','url'=>'/documents'],
   ['muster'=>['aufgab','termin','kalender','to-do','todo'],'title'=>'Den Tag planen','text'=>'Aufgaben zeigt Ihre To-dos neben dem Monatskalender. Aufgaben können erledigt, terminiert und intern zur Übernahme angeboten werden. Kalenderexport ist lokal; Google/Outlook-Synchronisation ist noch nicht verbunden.','url'=>'/tasks'],
   ['muster'=>['nachricht','kontakt','chat','partner'],'title'=>'Intern Kontakt aufnehmen','text'=>'Über die Chatblase erreichen Sie sichtbare interne Partner und die Administration. Nachrichten bleiben im Portal; ein Objektlink gibt allein noch keine Berechtigung auf ein fremdes Objekt.','url'=>'/messages'],
   ['muster'=>['bewert','sachwert','ertragswert','vergleichswert','bodenricht'],'title'=>'Bewertung vorbereiten','text'=>'Die Bewertung verwendet die bereitgestellten Rechenregeln. Faktoren und Quellen müssen fachlich geprüft werden. Es werden keine aktuellen Marktwerte oder Quartalsdaten automatisch behauptet.','url'=>'/valuation'],
   ['muster'=>['profil','foto','passwort','name','dunkel'],'title'=>'Profil und Darstellung','text'=>'Im Profil können Sie Ihr Bild hochladen, den Namen ändern und ein neues Passwort festlegen. Für einen Passwortwechsel ist das bisherige Passwort erforderlich.','url'=>'/profile'],
   ['muster'=>['wissen','lexikon','begriff','academy','video'],'title'=>'Wissen nachschlagen','text'=>'In Wissen / Academy finden Sie Lerninhalte und das Immobilienlexikon. Die globale Suche sucht auch nach Fachbegriffen.','url'=>'/academy/lexicon'],
  ];
  if($question!==''){$q=mb_strtolower($question);foreach($topics as $topic)foreach($topic['muster'] as $word)if(str_contains($q,$word)){$answers[]=$topic;break;}}
  if($question!==''){
   $lookup=trim(preg_replace('/\b(was|ist|sind|ein|eine|der|die|das|bedeutet|wie|ich|finde|wo|bitte|mir)\b/ui','',$question));
   if(mb_strlen($lookup)>2)foreach(array_slice($lexicon->search($lookup),0,2) as $entry)$answers[]=['title'=>$entry['term'],'text'=>$entry['definition'].' (Bereitgestelltes CHASE-Lexikon, Seite '.$entry['page'].')','url'=>'/academy/lexicon/'.$entry['id']];
  }
  if(!$answers&&$question!=='')$answers[]=['title'=>'Gemeinsam die richtige Stelle finden','text'=>'Dazu habe ich keine sichere lokale Anleitung gefunden. Beschreiben Sie den Arbeitsbereich genauer oder kontaktieren Sie das Partnermanagement. Diese Hilfe ist eine lokale Wegweiserfunktion, kein externes KI-Modell.','url'=>'/messages'];
  return $this->page('help/assistant.html.twig',compact('question','answers','topics'));
 }
}
