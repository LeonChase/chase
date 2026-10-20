<?php
namespace App\Tests\Functional;

use App\Entity\{AcademyArticle, AdvertisingProduct, ContentPost, CreditLedger, Document, Lead, MediaAsset, NewsPost, Property, Record, Task, TeamMembership, UsageEvent, User, UserPreference};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** BrowserKit exercises the real firewall, sessions, controllers, Twig and SQLite. */
final class PortalTest extends WebTestCase
{
    private const PASSWORD = 'Isolated-test-only-password!';
    private KernelBrowser $client;
    /** @var array<string,User> */
    private array $users = [];
    private array $uploadedFiles = [];

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $em = $this->em();
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $hash = password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost'=>4]);
        foreach ([
            'u-001'=>['ROLE_AGENT'], 'u-002'=>['ROLE_AGENT'],
            'u-peer'=>['ROLE_AGENT'], 'u-manager'=>['ROLE_REGION_MANAGER'],
            'u-admin'=>['ROLE_ADMIN'], 'u-disabled'=>['ROLE_AGENT'],
        ] as $id=>$roles) {
            $u = new User();
            $u->id=$id; $u->name='Demo '.$id; $u->email=$id.'@example.test';
            $u->tenantId='phpunit-demo'; $u->region='Gleiche Demo-Region';
            $u->password=$hash; $u->roles=$roles; $u->active=$id!=='u-disabled';
            $em->persist($u); $this->users[$id]=$u;
        }
        $own = $this->property('p-own', 'ScopeDemo eigenes Objekt', 'u-001');
        $foreign = $this->property('p-foreign', 'ScopeDemo GEHEIM fremdes Objekt', 'u-002');
        $peer = $this->property('p-peer', 'ScopeDemo GEHEIM gleiche Region', 'u-peer');
        $cross = $this->property('p-cross', 'ScopeDemo GEHEIM anderer Mandant', 'u-001', 'other-tenant');
        $records = [$own,$foreign,$peer,$cross,
            $this->recordWithId(new Lead(['name'=>'Eigener Demo-Lead','interest'=>'Demo-Anfrage','status'=>'Neu'], 'u-001','phpunit-demo'), 'l-own'),
            $this->recordWithId(new Lead(['name'=>'GEHEIM fremder Demo-Lead','interest'=>'Demo-Anfrage','status'=>'Neu'], 'u-002','phpunit-demo'), 'l-foreign'),
            $this->recordWithId(new Task(['title'=>'Eigene Demo-Aufgabe','status'=>'Offen','priority'=>'Mittel','dueDate'=>date('Y-m-d')], 'u-001','phpunit-demo'), 't-own'),
            $this->recordWithId(new Task(['title'=>'GEHEIM fremde Aufgabe','status'=>'Offen'], 'u-002','phpunit-demo'), 't-foreign'),
            $this->recordWithId(new ContentPost(['title'=>'GEHEIM fremder Text','content'=>'GEHEIM fremde Inhalte','kind'=>'text','tool'=>'expose','propertyId'=>'p-foreign'], 'u-002','phpunit-demo'), 'c-foreign'),
            $this->recordWithId(new Document(['title'=>'ScopeDemo öffentliches Muster','audience'=>'Alle Makler','category'=>'Demo','content'=>'Unverbindlicher synthetischer Mustertext'], null,'phpunit-demo'), 'doc-public'),
            $this->recordWithId(new Document(['title'=>'ScopeDemo GEHEIM Admin-Muster','audience'=>'Admin','category'=>'Demo','content'=>'GEHEIM Administrationsinhalt'], null,'phpunit-demo'), 'doc-admin'),
            $this->recordWithId(new AcademyArticle(['title'=>'ScopeDemo Akademie','category'=>'Demo','content'=>'Synthetischer Lerntext'], null,'phpunit-demo'), 'academy-demo'),
            $this->recordWithId(new AdvertisingProduct(['title'=>'Synthetischer Demo-Flyer','category'=>'Demo','unit'=>'Stück'], null,'phpunit-demo'), 'ad-demo'),
            $this->recordWithId(new NewsPost(['title'=>'ScopeDemo veröffentlichte Nachricht','status'=>'Veröffentlicht','category'=>'System','publishedAt'=>date(DATE_ATOM),'summary'=>'Synthetische Testnachricht','body'=>'Lokaler Testinhalt.'], null,'phpunit-demo'), 'news-public'),
            $this->recordWithId(new NewsPost(['title'=>'ScopeDemo GEHEIM Newsentwurf','status'=>'Entwurf','category'=>'System'], null,'phpunit-demo'), 'news-draft'),
            new TeamMembership(['managerId'=>'u-manager','memberId'=>'u-001','active'=>true], 'u-manager','phpunit-demo'),
        ];
        foreach ($records as $record) $em->persist($record);
        $em->flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->uploadedFiles as $path) if (is_file($path)) unlink($path);
        parent::tearDown();
    }

    private function em(): EntityManagerInterface { return self::getContainer()->get(EntityManagerInterface::class); }
    private function property(string $id,string $title,string $owner,string $tenant='phpunit-demo'): Property
    {
        return $this->recordWithId(new Property(['title'=>$title,'propertyType'=>'Eigentumswohnung','marketingType'=>'Kauf','status'=>'Aktiv','city'=>'Demo-Ort','region'=>'Gleiche Demo-Region','areaSqm'=>85,'rooms'=>3,'features'=>['Balkon'],'isDemo'=>true], $owner,$tenant), $id);
    }
    private function recordWithId(Record $record,string $id): Record { $record->id=$id; return $record; }
    private function auth(string $id='u-001'): void { $this->client->loginUser($this->users[$id]); }
    private function token(string $path='/properties/new'): string
    {
        $crawler=$this->client->request('GET',$path);
        self::assertResponseIsSuccessful();
        return (string)$crawler->filter('meta[name="portal-csrf"]')->attr('content');
    }
    private function fresh(string $class,string $id): ?object { $this->em()->clear(); return $this->em()->find($class,$id); }
    private function validProperty(array $changes=[]): array
    {
        return array_replace(['title'=>'Neues synthetisches Testobjekt','city'=>'Demo-Stadt','region'=>'Demo-Region','propertyType'=>'Einfamilienhaus','marketingType'=>'Kauf','status'=>'Entwurf','areaSqm'=>'120,5','rooms'=>'4','priceEur'=>'250000','features'=>"Balkon\nGarten"],$changes);
    }

    public static function protectedUrls(): iterable
    {
        foreach (['/','/properties','/studio','/leads','/tasks','/documents/doc-public/download','/admin'] as $url) yield $url=>[$url];
    }
    #[DataProvider('protectedUrls')]
    public function testAnonymousRequestsRequireRealAuthentication(string $url): void
    {
        $this->client->request('GET',$url);
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testRealFormLoginRejectsInvalidCsrfAndPasswordThenLogsInAndOut(): void
    {
        $this->client->request('POST','/login',['email'=>$this->users['u-001']->email,'password'=>self::PASSWORD,'_csrf_token'=>'invalid']);
        self::assertResponseRedirects('http://localhost/login');
        $crawler=$this->client->followRedirect();
        self::assertSelectorExists('[role="alert"]');
        $form=$crawler->filter('form.login-form')->form(['email'=>$this->users['u-001']->email,'password'=>'wrong-password']);
        $this->client->submit($form);
        self::assertResponseRedirects('http://localhost/login');
        $crawler=$this->client->followRedirect();
        self::assertSelectorExists('[role="alert"]');
        $this->client->submit($crawler->filter('form.login-form')->form(['email'=>$this->users['u-001']->email,'password'=>self::PASSWORD]));
        self::assertResponseRedirects('http://localhost/');
        $crawler=$this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(6,'.module-grid .module-card');
        self::assertSelectorTextContains('h1','Demo');
        self::assertStringContainsString('u-001', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('GEHEIM fremd',$this->client->getResponse()->getContent());
        $logout=$crawler->filter('form[action="/logout"]')->form();
        $this->client->submit($logout);
        self::assertResponseRedirects('http://localhost/login');
        $this->client->request('GET','/properties');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testDisabledAccountCannotLogIn(): void
    {
        $crawler=$this->client->request('GET','/login');
        $this->client->submit($crawler->filter('form.login-form')->form(['email'=>$this->users['u-disabled']->email,'password'=>self::PASSWORD]));
        self::assertResponseRedirects('http://localhost/login');
        $this->client->request('GET','/properties');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testDeactivatingAnAlreadyLoggedInAccountRevokesItsSession(): void
    {
        $this->auth();
        $this->client->request('GET','/properties'); self::assertResponseIsSuccessful();
        $user=$this->em()->find(User::class,'u-001');
        $user->active=false; $this->em()->flush();
        $this->client->request('GET','/properties');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testRevokingAnAdminRoleRemovesAccessFromAnExistingSession(): void
    {
        $this->auth('u-admin');
        $this->client->request('GET','/admin'); self::assertResponseIsSuccessful();
        $user=$this->em()->find(User::class,'u-admin');
        $user->roles=['ROLE_AGENT']; $this->em()->flush();
        $this->client->request('GET','/admin');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testDirectForeignUrlsAndForgedRelationsNeverExposeData(): void
    {
        $this->auth();
        foreach (['/properties/p-foreign','/properties/p-peer','/properties/p-cross','/properties/p-foreign/edit','/leads/l-foreign','/tasks/t-foreign/edit','/studio?property=p-foreign','/studio/content/c-foreign','/studio/content/c-foreign/export','/documents/doc-admin/download','/news/news-draft'] as $url) {
            $this->client->request('GET',$url);
            self::assertResponseStatusCodeSame(404,$url);
            self::assertStringNotContainsString('GEHEIM',$this->client->getResponse()->getContent(),$url);
        }
        $token=$this->token('/tasks/new');
        $this->client->request('POST','/tasks/new',['_token'=>$token,'title'=>'Verbotene Verknüpfung','status'=>'Offen','priority'=>'Mittel','propertyId'=>'p-foreign']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(2,$this->em()->getRepository(Task::class)->count([]));
    }

    public function testManagerNeedsExplicitGrantAndAdminGetsNoPrivateCustomerAccess(): void
    {
        $this->auth('u-manager');
        $this->client->request('GET','/properties/p-own'); self::assertResponseIsSuccessful();
        $this->client->request('GET','/properties/p-peer'); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/leads/l-own'); self::assertResponseIsSuccessful();
        $this->client->request('GET','/properties/p-cross'); self::assertResponseStatusCodeSame(404);
        $this->auth('u-admin');
        $this->client->request('GET','/properties'); self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('ScopeDemo eigenes Objekt',$this->client->getResponse()->getContent());
        $this->client->request('GET','/properties/p-own'); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/leads/l-own'); self::assertResponseStatusCodeSame(404);
    }

    public function testPropertyCreateEditArchiveFiltersAndCsrfProtection(): void
    {
        $this->auth();
        $before=$this->em()->getRepository(Property::class)->count([]);
        $this->client->request('POST','/properties/new',$this->validProperty(['_token'=>'forged']));
        self::assertResponseStatusCodeSame(403);
        self::assertSame($before,$this->em()->getRepository(Property::class)->count([]));
        $token=$this->token();
        $this->client->request('POST','/properties/new',$this->validProperty(['_token'=>$token,'ownerId'=>'u-002','tenantId'=>'other-tenant']));
        self::assertResponseStatusCodeSame(302);
        $url=$this->client->getResponse()->headers->get('Location');
        $id=basename($url);
        $property=$this->fresh(Property::class,$id);
        self::assertSame('u-001',$property->ownerId); self::assertSame('phpunit-demo',$property->tenantId);
        self::assertSame(120.5,$property->data['areaSqm']);
        $token=$this->token($url.'/edit');
        $this->client->request('POST',$url.'/edit',$this->validProperty(['_token'=>$token,'title'=>'Bearbeitetes synthetisches Objekt','status'=>'Reserviert']));
        self::assertResponseRedirects($url);
        self::assertSame('Reserviert',$this->fresh(Property::class,$id)->data['status']);
        $this->client->request('GET','/properties?status=Reserviert&q=Bearbeitetes');
        self::assertResponseIsSuccessful(); self::assertSelectorTextContains('main','Bearbeitetes synthetisches Objekt');
        $token=$this->token($url);
        $this->client->request('POST',$url.'/archive',['_token'=>$token]); self::assertResponseRedirects($url);
        self::assertSame('Archiviert',$this->fresh(Property::class,$id)->data['status']);
        $this->client->request('POST','/properties/p-foreign/archive',['_token'=>$token]); self::assertResponseStatusCodeSame(404);
        self::assertSame('Aktiv',$this->fresh(Property::class,'p-foreign')->data['status']);
    }

    public function testInvalidPropertyInputDoesNotPersist(): void
    {
        $this->auth(); $token=$this->token();
        $before=$this->em()->getRepository(Property::class)->count([]);
        $this->client->request('POST','/properties/new',$this->validProperty(['_token'=>$token,'title'=>'','areaSqm'=>'-12','status'=>'Injected']));
        self::assertResponseIsSuccessful();
        self::assertSame($before,$this->em()->getRepository(Property::class)->count([]));
    }

    public function testLeadAndLinkedTaskCrudAndPipeline(): void
    {
        $this->auth(); $token=$this->token('/leads/new');
        $leadData=['_token'=>$token,'name'=>'Alex Beispiel (Demo)','email'=>'alex@example.test','interest'=>'Synthetische Anfrage','status'=>'Neu','propertyId'=>'p-own','activityNote'=>'Nur synthetischer Kontakt'];
        $this->client->request('POST','/leads/new',$leadData); self::assertResponseStatusCodeSame(302);
        $leadUrl=$this->client->getResponse()->headers->get('Location'); $leadId=basename($leadUrl);
        $lead=$this->fresh(Lead::class,$leadId); self::assertSame('u-001',$lead->ownerId); self::assertCount(1,$lead->data['activities']);
        $token=$this->token($leadUrl);
        $this->client->request('POST',$leadUrl,array_replace($leadData,['_token'=>$token,'status'=>'Termin','activityNote'=>'Demo-Termin vereinbart']));
        self::assertResponseRedirects($leadUrl); self::assertSame('Termin',$this->fresh(Lead::class,$leadId)->data['status']);
        $token=$this->token('/tasks/new?lead='.$leadId);
        $this->client->request('POST','/tasks/new',['_token'=>$token,'title'=>'Demo-Follow-up','status'=>'Offen','priority'=>'Hoch','dueDate'=>date('Y-m-d'),'propertyId'=>'p-own','leadId'=>$leadId]);
        self::assertResponseRedirects('/tasks');
        $task=$this->em()->getRepository(Task::class)->findOneBy(['title'=>'Demo-Follow-up']); $taskId=$task->id;
        self::assertSame($leadId,$task->data['leadId']);
        $token=$this->token('/tasks/'.$taskId.'/edit');
        $this->client->request('POST','/tasks/'.$taskId.'/edit',['_token'=>$token,'title'=>'Demo-Follow-up bearbeitet','status'=>'In Arbeit','priority'=>'Mittel','leadId'=>$leadId]);
        self::assertResponseRedirects('/tasks');
        $this->client->request('POST','/tasks/'.$taskId.'/status',['_token'=>$token,'status'=>'Erledigt']);
        self::assertResponseRedirects('/tasks'); self::assertSame('Erledigt',$this->fresh(Task::class,$taskId)->data['status']);
        $this->client->request('POST',$leadUrl.'/delete',['_token'=>$token]); self::assertResponseRedirects('/leads');
        self::assertNull($this->fresh(Lead::class,$leadId)); self::assertNull($this->fresh(Task::class,$taskId)->data['leadId']);
        $this->client->request('POST','/tasks/'.$taskId.'/delete',['_token'=>$token]); self::assertResponseRedirects('/tasks');
        self::assertNull($this->fresh(Task::class,$taskId));
    }

    public function testDemoLeadRejectsRealEmailAddress(): void
    {
        $this->auth(); $token=$this->token('/leads/new');
        $this->client->request('POST','/leads/new',['_token'=>$token,'name'=>'Demo','email'=>'real@example.com','interest'=>'Test','status'=>'Neu']);
        self::assertResponseIsSuccessful(); self::assertSame(2,$this->em()->getRepository(Lead::class)->count([]));
    }

    public function testObjectTextGenerationVersionHistoryAndMarketingCalendar(): void
    {
        $this->auth(); $token=$this->token('/studio?property=p-own');
        $this->client->request('POST','/studio/generate',['_token'=>$token,'tool'=>'expose','tone'=>'sachlich','propertyId'=>'p-own','channel'=>'Instagram','format'=>'Feed']);
        self::assertResponseStatusCodeSame(302);
        $editUrl=$this->client->getResponse()->headers->get('Location'); $id=basename($editUrl);
        $post=$this->fresh(ContentPost::class,$id);
        self::assertSame('p-own',$post->data['propertyId']); self::assertSame(1,$post->data['version']);
        self::assertStringContainsString('85 m²',$post->data['content']); self::assertStringContainsString('Preis: Angabe fehlt',$post->data['content']);
        self::assertSame('property',$post->data['source']['type']);
        self::assertSame(1,$this->em()->getRepository(UsageEvent::class)->count([]));
        $token=$this->token($editUrl);
        $this->client->request('POST',$editUrl,['_token'=>$token,'title'=>'Freigegebener Demo-Text','content'=>$post->data['content']."\nManuell ergänzt.",'status'=>'Freigegeben','channel'=>'Instagram','format'=>'Feed']);
        self::assertResponseRedirects($editUrl);
        $post=$this->fresh(ContentPost::class,$id); self::assertSame(2,$post->data['version']); self::assertCount(2,$post->data['versions']); self::assertTrue($post->data['approved']);
        $this->client->request('GET','/properties/p-own?tab=texts'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('main','Freigegebener Demo-Text');
        $token=$this->token('/marketing/new?property=p-own');
        $this->client->request('POST','/studio/generate',['_token'=>$token,'tool'=>'social','tone'=>'kurz','propertyId'=>'p-own','channel'=>'LinkedIn','format'=>'Feed']);
        self::assertResponseStatusCodeSame(302); $marketingUrl=$this->client->getResponse()->headers->get('Location'); $marketingId=basename(dirname($marketingUrl));
        $token=$this->token($marketingUrl);
        $this->client->request('POST',$marketingUrl,['_token'=>$token,'title'=>'Demo-Kampagne im Kalender','content'=>'Synthetischer Social-Entwurf','status'=>'Geplant intern','channel'=>'LinkedIn','format'=>'Feed','plannedAt'=>'2026-11-12T10:30']);
        self::assertResponseRedirects($marketingUrl);
        $marketing=$this->fresh(ContentPost::class,$marketingId); self::assertFalse($marketing->data['isPublished']); self::assertNotEmpty($marketing->data['plannedAt']);
        $this->client->request('GET','/marketing?month=2026-11'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('main','Demo-Kampagne im Kalender');
    }

    public function testGenerationRequiresCsrfAndCannotUseForeignObjectOrExceedBudget(): void
    {
        $this->auth();
        $this->client->request('POST','/studio/generate',['_token'=>'invalid','tool'=>'expose','propertyId'=>'p-own']); self::assertResponseStatusCodeSame(403);
        $token=$this->token('/studio');
        $this->client->request('POST','/studio/generate',['_token'=>$token,'tool'=>'expose','propertyId'=>'p-foreign']); self::assertResponseStatusCodeSame(404);
        $user=$this->em()->find(User::class,'u-001'); $user->budgetCredits=0; $this->em()->flush();
        $this->client->request('POST','/studio/generate',['_token'=>$token,'tool'=>'expose','propertyId'=>'p-own']);
        self::assertResponseRedirects('/studio?tool=expose');
        self::assertSame(0,$this->em()->getRepository(UsageEvent::class)->count([])); self::assertSame(0,$this->em()->getRepository(CreditLedger::class)->count([]));
        self::assertSame(1,$this->em()->getRepository(ContentPost::class)->count([]));
    }

    public function testCrmRouteImportsLocallyTwiceWithoutDuplicates(): void
    {
        $this->auth(); $token=$this->token('/crm');
        for ($i=0;$i<2;++$i) { $this->client->request('POST','/crm/import',['_token'=>$token]); self::assertResponseRedirects('/crm'); }
        $properties=$this->em()->getRepository(Property::class)->findBy(['ownerId'=>'u-001','tenantId'=>'phpunit-demo']);
        self::assertCount(2,$properties);
        self::assertCount(1,array_filter($properties,fn($p)=>($p->data['externalId']??'')==='DEMO-OF-5001'));
    }

    public function testSearchAndProtectedDocumentDownloadRespectScope(): void
    {
        $this->auth(); $this->client->request('GET','/search?q=ScopeDemo'); self::assertResponseIsSuccessful();
        $html=$this->client->getResponse()->getContent();
        foreach (['ScopeDemo eigenes Objekt','ScopeDemo öffentliches Muster','ScopeDemo Akademie','ScopeDemo veröffentlichte Nachricht'] as $visible) self::assertStringContainsString($visible,$html);
        self::assertStringNotContainsString('GEHEIM',$html);
        $this->client->request('GET','/documents/doc-public/download'); self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Content-Type-Options','nosniff');
        self::assertStringContainsString('UNVERBINDLICHES DEMO-MUSTER',$this->client->getResponse()->getContent());
        self::assertStringContainsString('private',$this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testNewsUnreadAcademyProgressFavoritesAndSavedDarkTheme(): void
    {
        $this->auth(); $token=$this->token('/news');
        self::assertSelectorTextContains('.notification-button[href="/news"] .header-news-count','1');
        $this->client->request('POST','/preferences/read/news/news-public',['_token'=>$token]); self::assertResponseRedirects('/news/news-public');
        $this->client->followRedirect(); self::assertResponseIsSuccessful(); self::assertSelectorNotExists('.notification-button[href="/news"] .header-news-count');
        foreach ([['progress','academy','academy-demo'],['favorite','document','doc-public']] as [$kind,$type,$id]) {
            $this->client->request('POST',"/preferences/$kind/$type/$id",['_token'=>$token]); self::assertResponseStatusCodeSame(302);
        }
        self::assertSame(3,$this->em()->getRepository(UserPreference::class)->count(['ownerId'=>'u-001']));
        $this->client->request('POST','/profile/theme',['_token'=>$token,'theme'=>'dark'],[],['HTTP_ACCEPT'=>'application/json']);
        self::assertResponseIsSuccessful(); self::assertSame(['theme'=>'dark','saved'=>true],json_decode($this->client->getResponse()->getContent(),true));
        self::assertSame('dark',$this->fresh(User::class,'u-001')->preferences['theme']);
        $this->client->request('GET','/'); self::assertResponseIsSuccessful(); self::assertSelectorExists('html[data-theme="dark"]');
    }

    public function testAdvertisingRequestIsAnOwnedLocalDraftAndNeverARealOrder(): void
    {
        $this->auth(); $token=$this->token('/advertising');
        $this->client->request('POST','/advertising/request',['_token'=>$token,'quantities'=>['ad-demo'=>'25'],'note'=>'Synthetische lokale Anfrage']);
        self::assertResponseRedirects('/advertising?tab=requests');
        $draft=$this->em()->getRepository(UserPreference::class)->findOneBy(['ownerId'=>'u-001']);
        self::assertSame('advertising_request',$draft->data['kind']); self::assertFalse($draft->data['sent']); self::assertSame(25,$draft->data['items'][0]['quantity']);
        $this->client->request('GET','/advertising/request/'.$draft->id.'/download'); self::assertResponseIsSuccessful();
        self::assertStringContainsString('Keine Bestellung',$this->client->getResponse()->getContent());
        $this->auth('u-manager');
        $this->client->request('GET','/advertising/request/'.$draft->id.'/download'); self::assertResponseStatusCodeSame(404);
    }

    public function testAdminNewsPublicationAndRoleRestriction(): void
    {
        $this->auth(); $this->client->request('GET','/admin/users'); self::assertResponseStatusCodeSame(403);
        $this->auth('u-admin'); $token=$this->token('/admin/news/new');
        $this->client->request('POST','/admin/news/new',['_token'=>$token,'title'=>'Neue synthetische Admin-News','category'=>'System','status'=>'Veröffentlicht','summary'=>'Lokale Testmeldung','body'=>'Ausschließlich synthetischer Inhalt.','priority'=>'Normal']);
        self::assertResponseStatusCodeSame(302);
        $post=$this->em()->getRepository(NewsPost::class)->findOneBy(['title'=>'Neue synthetische Admin-News']); self::assertNotNull($post); self::assertSame('Veröffentlicht',$post->data['status']);
        $this->auth(); $this->client->request('GET','/news'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('main','Neue synthetische Admin-News');
        self::assertSelectorTextContains('.notification-button[href="/news"] .header-news-count','2');
    }

    public function testPrivateUploadDownloadChecksOwnerAndRejectsSpoofedMime(): void
    {
        $this->auth(); $token=$this->token('/properties/p-own?tab=media');
        $path=tempnam(sys_get_temp_dir(),'chase-upload-');
        file_put_contents($path,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $file=new UploadedFile($path,'synthetic-demo.png','image/png',null,true);
        $this->client->request('POST','/properties/p-own/media',['_token'=>$token],['media'=>$file]); self::assertResponseRedirects('/properties/p-own?tab=media');
        $asset=$this->em()->getRepository(MediaAsset::class)->findOneBy(['ownerId'=>'u-001']); self::assertNotNull($asset);
        $this->uploadedFiles[]=dirname(__DIR__,2).'/var/uploads/'.$asset->data['filename'];
        $this->client->request('GET','/media/'.$asset->id); self::assertResponseIsSuccessful(); self::assertResponseHeaderSame('Content-Type','image/png');
        $this->auth('u-002'); $this->client->request('GET','/media/'.$asset->id); self::assertResponseStatusCodeSame(404);
        $this->auth(); $token=$this->token('/properties/p-own?tab=media');
        $path=tempnam(sys_get_temp_dir(),'chase-upload-'); file_put_contents($path,'<?php echo "not-an-image";');
        try { $this->client->request('POST','/properties/p-own/media',['_token'=>$token],['media'=>new UploadedFile($path,'disguised.png','image/png',null,true)]); self::assertResponseRedirects('/properties/p-own?tab=media'); self::assertSame(1,$this->em()->getRepository(MediaAsset::class)->count([])); }
        finally { if(is_file($path))unlink($path); }
    }
}
