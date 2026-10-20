<?php
namespace App\Command;
use App\Entity\{User,Record,Property,Lead,Task,Document,AcademyArticle,AdvertisingProduct,NewsPost,ContentPost,UsageEvent,AuditEvent,PartnerProfile,Organization,Region,IntegrationConnection};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
#[AsCommand(name:'app:demo:seed',description:'Synthetische Fixtures idempotent anlegen; ausschließlich in dev.')]
class DemoSeedCommand extends Command {
 public function __construct(private EntityManagerInterface $em,private UserPasswordHasherInterface $hasher,private string $projectDir,private string $environment){parent::__construct();}
 protected function execute(InputInterface $input,OutputInterface $out):int {
  if($this->environment!=='dev'){$out->writeln('Demo-Konten dürfen nur in APP_ENV=dev angelegt werden.');return Command::FAILURE;}
  $created=0;$credentials=[];
  foreach($this->json('demo_users') as $row){
   if($this->em->find(User::class,$row['id']))continue;
   $u=new User();$u->id=$row['id'];$u->name=$row['name'];$u->email=$row['email'];$u->region=$row['region'];$u->roles=[match($row['role']){'ADMIN'=>'ROLE_ADMIN','REGIONALLEITUNG'=>'ROLE_REGION_MANAGER',default=>'ROLE_AGENT'}];
   $password=bin2hex(random_bytes(10));$u->password=$this->hasher->hashPassword($u,$password);$this->em->persist($u);$created++;
   $credentials[]=$u->name."\nE-Mail: ".$u->email."\nPasswort: ".$password."\n";
  }
  $mapping=['demo_properties'=>Property::class,'demo_leads'=>Lead::class,'demo_tasks'=>Task::class,'demo_documents'=>Document::class,'demo_academy'=>AcademyArticle::class,'demo_advertising'=>AdvertisingProduct::class,'demo_news'=>NewsPost::class,'demo_social_posts'=>ContentPost::class,'demo_usage'=>UsageEvent::class,'demo_activity'=>AuditEvent::class,'demo_partner_directory'=>PartnerProfile::class];
  foreach($mapping as $file=>$class){foreach($this->json($file) as $row){
   $id=$row['id']??('partner-'.$row['userId']);if($this->em->find($class,$id))continue;
   $owner=$row['ownerUserId']??$row['userId']??$row['actorUserId']??null;
   if($class===PartnerProfile::class)$owner=null;
   if($class===Property::class)$row+=['status'=>'Aktiv','source'=>'Lokale Demo','marketingType'=>'Kauf','checklist'=>[]];
   if($class===Lead::class)$row['title']=$row['name'];
   if($class===ContentPost::class)$row+=['tool'=>'social','kind'=>'social','body'=>$row['caption'],'text'=>$row['caption'],'version'=>1,'versions'=>[],'approved'=>false];
   if($class===UsageEvent::class)$row+=['creditUnits'=>0,'title'=>'Historische Demo-Nutzung','actualCostEur'=>0];
   if($class===PartnerProfile::class)$row['title']=$row['displayName'];
   if($class===AuditEvent::class)$row['title']=$row['verb'];
   $record=new $class($row,$owner);$record->id=$id;$this->em->persist($record);$created++;
  }}
  if(!$this->em->find(Organization::class,'chase-demo')){$org=new Organization(['title'=>'CHASE Immobilien – Demo']);$org->id='chase-demo';$this->em->persist($org);}
  foreach(['Landkreis Hameln-Pyrmont','Landkreis Hildesheim','Stadt und Landkreis Kassel','Stadt und Landkreis Passau'] as $i=>$title){$id='region-'.($i+1);if(!$this->em->find(Region::class,$id)){$r=new Region(['title'=>$title,'active'=>true]);$r->id=$id;$this->em->persist($r);}}
  if(!$this->em->find(IntegrationConnection::class,'onoffice-demo')){$r=new IntegrationConnection(['title'=>'onOffice','status'=>'Demo – nicht verbunden','provider'=>'onoffice','enabled'=>false]);$r->id='onoffice-demo';$this->em->persist($r);}
  $this->em->flush();
  if($credentials){$file=$this->projectDir.'/var/demo-credentials.txt';$prefix=is_file($file)?"\nWeitere lokal erzeugte Konten:\n":"CHASE PARTNERPORTAL · NUR LOKALE DEMOKONTEN\nNicht für Produktivsysteme verwenden.\n\n";file_put_contents($file,$prefix.implode("\n",$credentials),FILE_APPEND|LOCK_EX);chmod($file,0600);}
  $out->writeln($created.' Demo-Datensätze angelegt. Bestehende Daten und Passwörter bleiben erhalten.');$out->writeln('Demo-Zugänge: var/demo-credentials.txt (nur lokal, nicht versioniert).');return Command::SUCCESS;
 }
 private function json(string $name):array{return json_decode(file_get_contents($this->projectDir.'/data/'.$name.'.json'),true,512,JSON_THROW_ON_ERROR);}
}
