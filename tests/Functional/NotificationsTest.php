<?php
namespace App\Tests\Functional;

use App\Entity\{User,Task,Lead,NewsPost,Message};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser,Test\WebTestCase};

final class NotificationsTest extends WebTestCase
{
 private KernelBrowser $browser;
 protected function setUp():void
 {
  self::ensureKernelShutdown();$this->browser=self::createClient();$em=$this->em();$schema=new SchemaTool($em);$meta=$em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($meta);$schema->createSchema($meta);
  foreach(['owner','foreign','cross'] as $id){$u=new User();$u->id=$id;$u->name='Test '.$id;$u->email=$id.'@example.test';$u->tenantId=$id==='cross'?'other-tenant':'notifications-test';$u->password=password_hash('OnlySyntheticTest!',PASSWORD_BCRYPT,['cost'=>4]);$em->persist($u);}
  $today=(new \DateTimeImmutable('today',new \DateTimeZone('Europe/Berlin')))->format('Y-m-d');
  $records=[new Task(['title'=>'Eigene heutige Aufgabe','dueDate'=>$today,'status'=>'Offen'],'owner','notifications-test'),new Task(['title'=>'GEHEIM fremde Aufgabe','dueDate'=>$today,'status'=>'Offen'],'foreign','notifications-test'),new Lead(['title'=>'Eigener neuer Lead','status'=>'Neu'],'owner','notifications-test'),new Lead(['title'=>'GEHEIM anderer Mandant','status'=>'Neu'],'owner','other-tenant'),new NewsPost(['title'=>'Eigene interne News','status'=>'Veröffentlicht','body'=>'<script>Kein HTML</script>','publishedAt'=>'2026-01-01'],'owner','notifications-test'),new NewsPost(['title'=>'GEHEIM Newsentwurf','status'=>'Entwurf'],'owner','notifications-test'),new NewsPost(['title'=>'GEHEIM zukünftige News','status'=>'Veröffentlicht','publishedAt'=>'2099-01-01'],'owner','notifications-test'),new Message(['title'=>'Test','body'=>'Eigene neue Chatnachricht','recipientId'=>'owner'],'foreign','notifications-test')];
  foreach($records as $r)$em->persist($r);$em->flush();$this->browser->loginUser($em->find(User::class,'owner'));
 }
 private function em():EntityManagerInterface{return self::getContainer()->get(EntityManagerInterface::class);}
 private function token():string{$c=$this->browser->request('GET','/');self::assertResponseIsSuccessful();return $c->filter('meta[name="portal-csrf"]')->attr('content');}
 public function testDrawerFeedContainsRealVisibleRecordsAndNoPrivateOrFutureData():void
 {
  $this->browser->request('GET','/notifications');self::assertResponseIsSuccessful();self::assertSelectorCount(5,'[data-notice-id]');self::assertSelectorTextContains('.notice','1 Termin / Aufgabe heute');$html=$this->browser->getResponse()->getContent();self::assertStringContainsString('Eigener neuer Lead',$html);self::assertStringContainsString('Eigene heutige Aufgabe',$html);self::assertStringContainsString('Eigene neue Chatnachricht',$html);self::assertStringNotContainsString('GEHEIM',$html);self::assertStringContainsString('&lt;script&gt;Kein HTML&lt;/script&gt;',$html);self::assertSelectorNotExists('script');
 }
 public function testReadAcknowledgementIsCsrfProtectedOwnPersistentAndIdempotent():void
 {
  $token=$this->token();$this->browser->request('POST','/notifications/read',['_token'=>'bad']);self::assertResponseStatusCodeSame(403);
  $this->browser->request('POST','/notifications/read',['_token'=>$token,'id'=>'task:foreign-secret']);self::assertResponseStatusCodeSame(404);
  $this->browser->request('POST','/notifications/read',['_token'=>$token],[],['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest']);self::assertResponseIsSuccessful();self::assertSame(0,json_decode($this->browser->getResponse()->getContent(),true)['unread']);$this->em()->clear();$u=$this->em()->find(User::class,'owner');self::assertCount(5,$u->preferences['seenNotifications']);self::assertSame([],$this->em()->find(User::class,'foreign')->preferences);
  $this->browser->request('POST','/notifications/read',['_token'=>$token],[],['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest']);self::assertResponseIsSuccessful();$this->browser->request('GET','/notifications');self::assertSelectorCount(0,'.notice-unread');
  $t=new Task(['title'=>'Neu nach Gelesen-Markierung','status'=>'Offen'],'owner','notifications-test');$this->em()->persist($t);$this->em()->flush();$this->browser->request('GET','/notifications');self::assertSelectorCount(1,'.notice-unread');
 }
 public function testAnonymousCannotReadNotifications():void
 {
  $this->browser->request('POST','/logout',['_csrf_token'=>'invalid']);$this->browser->getCookieJar()->clear();$this->browser->request('GET','/notifications');self::assertResponseRedirects('http://localhost/login');
 }
}
