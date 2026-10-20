<?php
namespace App\Tests\Functional;
use App\Entity\{User,PartnerProfile,Message,Property,Document};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser,Test\WebTestCase};
use Symfony\Component\HttpFoundation\File\UploadedFile;
final class CommunicationsTest extends WebTestCase {
 private KernelBrowser $browser;
 private array $files=[];
 protected function setUp():void {
  self::ensureKernelShutdown();$this->browser=self::createClient();$em=$this->em();$schema=new SchemaTool($em);$meta=$em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($meta);$schema->createSchema($meta);
  foreach(['sender','recipient','stranger','admin','other'] as $id){$u=new User();$u->id=$id;$u->tenantId=$id==='other'?'other':'communications-test';$u->name='Interner Testkontakt '.$id;$u->email=$id.'@example.test';$u->password=password_hash('Synthetic-only-test!',PASSWORD_BCRYPT,['cost'=>4]);if($id==='admin')$u->roles=['ROLE_ADMIN'];$em->persist($u);if($id==='recipient')$em->persist(new PartnerProfile(['title'=>'Testpartner','userId'=>$id,'visibleToPartners'=>true],null,$u->tenantId));}
  $em->flush();$this->auth('sender');
 }
 protected function tearDown():void {foreach($this->files as $p)if(is_file($p))unlink($p);parent::tearDown();}
 private function em():EntityManagerInterface{return self::getContainer()->get(EntityManagerInterface::class);}
 private function auth(string $id):void {$this->browser->loginUser($this->em()->find(User::class,$id));}
 private function token(string $path='/messages'):string {$c=$this->browser->request('GET',$path);self::assertResponseIsSuccessful();return $c->filter('meta[name="portal-csrf"]')->attr('content');}
 public function testInternalMessagesHaveParticipantAndTenantAclAndExplicitRead():void {
  $token=$this->token();$this->browser->request('POST','/messages',['_token'=>$token,'recipientId'=>'recipient','title'=>'Nur intern','body'=>'Synthetische interne Testnachricht']);self::assertResponseStatusCodeSame(302);
  $m=$this->em()->getRepository(Message::class)->findOneBy(['ownerId'=>'sender']);self::assertNotNull($m);self::assertSame('recipient',$m->recipientId);
  $this->browser->followRedirect();self::assertSelectorTextContains('article','Synthetische interne Testnachricht');
  foreach(['stranger','admin','other'] as $id){$this->auth($id);$this->browser->request('GET','/messages/'.$m->id);self::assertResponseStatusCodeSame(404);}
  $this->auth('recipient');$token=$this->token();$this->browser->request('GET','/messages/'.$m->id);self::assertResponseIsSuccessful();self::assertSelectorTextContains('main','Als gelesen markieren');
  $this->browser->request('POST','/messages/'.$m->id.'/read',['_token'=>$token]);self::assertResponseStatusCodeSame(302);$this->em()->clear();self::assertNotEmpty($this->em()->find(Message::class,$m->id)->data['readAt']);
 }
 public function testMessagesValidateCsrfRecipientAndObjectGrant():void {
  $token=$this->token();$data=['recipientId'=>'recipient','title'=>'Prüfung','body'=>'Interne Notiz'];
  $this->browser->request('POST','/messages',$data+['_token'=>'bad']);self::assertResponseStatusCodeSame(403);
  $this->browser->request('POST','/messages',array_replace($data,['_token'=>$token,'recipientId'=>'other']));self::assertResponseIsSuccessful();self::assertSelectorTextContains('main','verfügbaren internen Kontakt');
  $p=new Property(['title'=>'Privates Testobjekt'],'sender','communications-test');$this->em()->persist($p);$this->em()->flush();
  $this->browser->request('POST','/messages',$data+['_token'=>$token,'propertyId'=>$p->id]);self::assertResponseIsSuccessful();self::assertSelectorTextContains('main','keine Objektfreigabe');self::assertSame(0,$this->em()->getRepository(Message::class)->count([]));
 }
 public function testProfilePictureUploadHasMimeOwnershipVisibilityAndDeletion():void {
  $token=$this->token('/profile');$path=tempnam(sys_get_temp_dir(),'avatar-test-');file_put_contents($path,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aPQAAAABJRU5ErkJggg=='));$this->files[]=$path;
  $this->browser->request('POST','/profile/avatar',['_token'=>$token],['avatar'=>new UploadedFile($path,'profil.png','image/png',null,true)]);self::assertResponseRedirects('/profile');
  $this->em()->clear();$u=$this->em()->find(User::class,'sender');$this->files[]=dirname(__DIR__,2).'/var/avatars/'.$u->preferences['avatarFilename'];
  $this->browser->request('GET','/profile/avatar/sender');self::assertResponseIsSuccessful();self::assertResponseHeaderSame('Content-Type','image/png');
  $this->auth('recipient');$this->browser->request('GET','/profile/avatar/sender');self::assertResponseStatusCodeSame(404);
  $this->auth('sender');$token=$this->token('/profile');$this->browser->request('POST','/profile/avatar/remove',['_token'=>$token]);self::assertResponseRedirects('/profile');$this->browser->request('GET','/profile/avatar/sender');self::assertResponseStatusCodeSame(404);
 }
 public function testProfileRejectsDisguisedExecutableAndShowsNoCreditLink():void {
  $token=$this->token('/profile');self::assertSelectorNotExists('a[href="/usage"]');$p=tempnam(sys_get_temp_dir(),'avatar-bad-');file_put_contents($p,'<?php echo "not an image";');$this->files[]=$p;
  $this->browser->request('POST','/profile/avatar',['_token'=>$token],['avatar'=>new UploadedFile($p,'falsch.jpg','image/jpeg',null,true)]);self::assertResponseRedirects('/profile');$this->browser->followRedirect();self::assertSelectorTextContains('main','passendem Bildinhalt');
 }
 public function testHelpFindsWorkflowsAndDefinitionsWithoutExternalProvider():void {
  $token=$this->token('/help/assistant');$this->browser->request('POST','/help/assistant',['_token'=>$token,'question'=>'Wie bestelle ich Werbemittel?']);self::assertResponseIsSuccessful();self::assertSelectorTextContains('main','Werbemittel auswählen');self::assertSelectorExists('a[href="/advertising"]');
  $this->browser->request('GET','/help/assistant?q=Was%20bedeutet%20Bodenrichtwert');self::assertResponseIsSuccessful();self::assertSelectorTextContains('main','CHASE-Lexikon, Seite');
 }
 public function testOnlyAdminCanImportPrivateArchiveAndInvalidZipDoesNotEscape():void {
  $this->browser->request('GET','/admin/materials');self::assertResponseStatusCodeSame(403);$this->auth('admin');$token=$this->token('/admin/materials');
  $this->browser->request('POST','/admin/materials',['_token'=>'bad']);self::assertResponseStatusCodeSame(403);
  $p=tempnam(sys_get_temp_dir(),'zip-invalid-');$zip=new \ZipArchive();$zip->open($p,\ZipArchive::CREATE|\ZipArchive::OVERWRITE);$zip->addFromString('../escape.txt','no');$zip->close();$this->files[]=$p;
  $this->browser->request('POST','/admin/materials',['_token'=>$token],['archive'=>new UploadedFile($p,'privat.zip','application/zip',null,true)]);self::assertResponseStatusCodeSame(422);self::assertSame(0,$this->em()->getRepository(Document::class)->count([]));
 }
}
