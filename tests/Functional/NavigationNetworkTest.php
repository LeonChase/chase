<?php
namespace App\Tests\Functional;
use App\Entity\{User,PartnerProfile,UserPreference};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser,Test\WebTestCase};
final class NavigationNetworkTest extends WebTestCase
{
 private KernelBrowser $browser;
 protected function setUp():void{
  self::ensureKernelShutdown();$this->browser=self::createClient();$em=$this->em();$schema=new SchemaTool($em);$meta=$em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($meta);$schema->createSchema($meta);
  foreach(['nav-owner','nav-peer','nav-disabled','nav-cross'] as $id){$u=new User();$u->id=$id;$u->name='Demo '.$id;$u->email=$id.'@example.test';$u->tenantId=$id==='nav-cross'?'nav-other':'nav-test';$u->active=$id!=='nav-disabled';$u->password=password_hash('SyntheticNavTest!',PASSWORD_BCRYPT,['cost'=>4]);$em->persist($u);}
  foreach([
   ['public-own',['title'=>'Demo Hameln','userId'=>'nav-owner','visibleToPartners'=>true,'region'=>'Hameln-Pyrmont','isDemo'=>true],'nav-test'],
   ['public-peer',['title'=>'Demo Partner','userId'=>'nav-peer','visibleToPartners'=>true,'region'=>'Hildesheim','isDemo'=>true],'nav-test'],
   ['disabled',['title'=>'GEHEIM deaktiviert','userId'=>'nav-disabled','visibleToPartners'=>true,'region'=>'Kassel','isDemo'=>true],'nav-test'],
   ['private',['title'=>'GEHEIM privat','visibleToPartners'=>false,'latitude'=>51,'longitude'=>9],'nav-test'],
   ['cross',['title'=>'GEHEIM anderer Mandant','visibleToPartners'=>true,'latitude'=>51,'longitude'=>9],'nav-other']
  ] as [$id,$data,$tenant]){$p=new PartnerProfile($data,null,$tenant);$p->id=$id;$em->persist($p);}$em->flush();$this->auth();
 }
 private function em():EntityManagerInterface{return self::getContainer()->get(EntityManagerInterface::class);}
 private function auth(string $id='nav-owner'):void{$this->browser->loginUser($this->em()->find(User::class,$id));}
 private function token():string{$c=$this->browser->request('GET','/links');self::assertResponseIsSuccessful();return $c->filter('meta[name="portal-csrf"]')->attr('content');}
 public function testDashboardAndNavigationFollowTheNewOrderWithoutValuationHighlight():void{
  $c=$this->browser->request('GET','/');self::assertResponseIsSuccessful();self::assertSame(['Immobilien','Immobilienbewertung','KI-Tools','Dokumente','Wissen / Academy','Werbekatalog'],$c->filter('.module-card h2')->each(fn($node)=>$node->text()));self::assertSelectorNotExists('.module-highlight');self::assertSelectorExists('.main-nav a[href="/tools"]');self::assertSelectorExists('.main-nav a[href="/network"]');self::assertSelectorExists('.main-nav a[href="/links"]');self::assertSelectorNotExists('.main-nav a[href="/admin"]');self::assertSelectorExists('[data-pin-sidebar]');
 }
 public function testMapUsesOnlyActiveOptInTenantProfilesAndClearlyLabelsDemoRegions():void{
  $this->browser->request('GET','/network');self::assertResponseIsSuccessful();self::assertSelectorCount(2,'[data-partner-marker]');self::assertSelectorCount(2,'[data-partner-card]');self::assertStringNotContainsString('GEHEIM',$this->browser->getResponse()->getContent());self::assertSelectorTextContains('.map-caption','keine Bürostandorte');self::assertSelectorExists('a[href="/messages?recipientId=nav-peer"]');
  $this->browser->request('GET','/network?q=Hildesheim');self::assertSelectorCount(1,'[data-partner-marker]');self::assertSelectorTextContains('.partner-list','Demo Partner');
 }
 public function testLinkFavoritesArePersonalCsrfProtectedAndExternalLinksAreSafe():void{
  $token=$this->token();self::assertSelectorCount(14,'.tool-link-card');self::assertSelectorCount(14,'.tool-link-card a[target="_blank"][rel="noopener noreferrer"]');
  $this->browser->request('POST','/links/onoffice/favorite',['_token'=>'bad']);self::assertResponseStatusCodeSame(403);
  $this->browser->request('POST','/links/onoffice/favorite',['_token'=>$token]);self::assertResponseRedirects('/links');$this->browser->request('GET','/links?favorites=1');self::assertSelectorCount(1,'.tool-link-card');
  $this->auth('nav-peer');$this->browser->request('GET','/links?favorites=1');self::assertSelectorCount(0,'.tool-link-card');self::assertSame(1,$this->em()->getRepository(UserPreference::class)->count(['ownerId'=>'nav-owner']));
  $this->browser->request('POST','/links/missing/favorite',['_token'=>$this->token()]);self::assertResponseStatusCodeSame(404);
 }
 public function testContactCardSeparatesNamedContactFromGeneralVerifiedMailbox():void{
  $this->browser->request('GET','/help');self::assertResponseIsSuccessful();self::assertSelectorTextContains('.partner-support-card','Linda Feinberg');self::assertSelectorTextContains('.partner-support-card','Kontakt über das Partnermanagement');self::assertSelectorExists('.partner-support-card a[href="mailto:partner@chase-immobilien.de"]');self::assertSelectorNotExists('.partner-support-card img');self::assertSelectorTextContains('.support-source','persönliche Kontaktdaten und Foto konnten noch nicht bestätigt');
 }
 public function testOriginalDocumentsHideOnlyDemoSamplesAndKeepCustomDocumentsVisible():void{
  foreach([['Gelieferte Unterlage',['provided'=>true,'isDemo'=>false]],['Eigene Unterlage',['isDemo'=>false]],['Synthetisches altes Muster',['isDemo'=>true]]] as [$title,$data])$this->em()->persist(new \App\Entity\Document(['title'=>$title]+$data,null,'nav-test'));
  $this->em()->flush();$this->browser->request('GET','/documents');self::assertResponseIsSuccessful();self::assertSelectorTextContains('.document-list','Gelieferte Unterlage');self::assertSelectorTextContains('.document-list','Eigene Unterlage');self::assertStringNotContainsString('Synthetisches altes Muster',$this->browser->getResponse()->getContent());
  $this->browser->request('GET','/documents?demo=1');self::assertSelectorTextContains('.document-list','Synthetisches altes Muster');
 }

}
