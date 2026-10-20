<?php
namespace App\Tests\Functional;

use App\Entity\{Property,MediaAsset,ContentPost,UserPreference,User,CreditLedger};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser,Test\WebTestCase};

final class PropertyAiTest extends WebTestCase
{
    private KernelBrowser $client;
    private array $files=[];

    protected function setUp():void
    {
        self::ensureKernelShutdown();$this->client=self::createClient();$em=$this->em();$metadata=$em->getMetadataFactory()->getAllMetadata();$schema=new SchemaTool($em);$schema->dropSchema($metadata);$schema->createSchema($metadata);
        foreach(['ai-owner','ai-peer'] as $id){$u=new User();$u->id=$id;$u->tenantId='ai-test';$u->email=$id.'@example.test';$u->name='Synthetischer Demo-Nutzer';$u->password=password_hash('Only-in-isolated-ai-tests!',PASSWORD_BCRYPT,['cost'=>4]);$em->persist($u);}
        foreach([['ai-property','ai-owner'],['ai-other-property','ai-owner'],['ai-foreign','ai-peer']] as [$id,$owner]){$p=new Property(['title'=>'Demo-Immobilie','propertyType'=>'Eigentumswohnung','city'=>'Demo-Ort','areaSqm'=>83,'rooms'=>3,'features'=>['Balkon'],'status'=>'Entwurf','region'=>'Demo-Region','marketingType'=>'Kauf','notes'=>'GEHEIME NOTIZ','ownerEmail'=>'owner-secret@example.test'],$owner,'ai-test');$p->id=$id;$em->persist($p);}
        $this->asset('ai-image','ai-property','image','Wohnbereich','Bestätigtes Tageslichtbad.',true);
        $this->asset('ai-unconfirmed','ai-property','image','Garten','NICHT BESTÄTIGTES SCHWIMMBAD.',false);
        $this->asset('ai-plan','ai-property','document','Grundriss','Originalgrundriss.',false);
        $this->asset('ai-wrong-property','ai-other-property','image','Sonstiges','Fremde Quelle.',true);
        $em->flush();
    }
    protected function tearDown():void{foreach($this->files as $file)if(is_file($file))unlink($file);parent::tearDown();}
    private function em():EntityManagerInterface{return self::getContainer()->get(EntityManagerInterface::class);}
    private function auth(string $id='ai-owner'):void{$this->client->loginUser($this->em()->find(User::class,$id));}
    private function token():string{$crawler=$this->client->request('GET','/profile');self::assertResponseIsSuccessful();return (string)$crawler->filter('meta[name="portal-csrf"]')->attr('content');}
    private function asset(string $id,string $property,string $kind,string $category,string $caption,bool $confirmed):void
    {
        $dir=self::getContainer()->getParameter('kernel.project_dir').'/var/uploads';if(!is_dir($dir))mkdir($dir,0700,true);$filename=bin2hex(random_bytes(20)).'.png';$file=$dir.'/'.$filename;file_put_contents($file,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jGZkAAAAASUVORK5CYII='));$this->files[]=$file;
        $m=new MediaAsset(['title'=>$id,'propertyId'=>$property,'kind'=>$kind,'category'=>$category,'mime'=>'image/png','filename'=>$filename,'caption'=>$caption,'factsConfirmed'=>$confirmed,'isOriginal'=>true],'ai-owner','ai-test');$m->id=$id;$this->em()->persist($m);
    }
    private function generated():ContentPost{return $this->em()->getRepository(ContentPost::class)->findOneBy(['ownerId'=>'ai-owner']);}

    public function testAnonymousAndInvalidCsrfCannotGenerate():void
    {
        $this->client->request('POST','/properties/ai-property/ai/texts');self::assertResponseRedirects('http://localhost/login');
        $this->auth();$this->client->request('POST','/properties/ai-property/ai/texts',['_token'=>'forged']);self::assertResponseStatusCodeSame(403);self::assertSame(0,$this->em()->getRepository(ContentPost::class)->count([]));
    }
    public function testGenerateThreeSourceBoundSectionsVersionApproveAndPdf():void
    {
        $this->auth();$token=$this->token();$this->client->request('POST','/properties/ai-property/ai/texts',['_token'=>$token]);self::assertResponseRedirects('/properties/ai-property#texts');
        $post=$this->generated();$id=$post->id;self::assertSame('property_expose',$post->data['tool']);self::assertSame('ai-property',$post->data['propertyId']);self::assertCount(3,$post->data['sections']);self::assertCount(1,$post->data['versions']);
        self::assertStringContainsString('83 m²',$post->data['content']);self::assertStringContainsString('Bestätigtes Tageslichtbad',$post->data['content']);self::assertStringNotContainsString('SCHWIMMBAD',$post->data['content']);self::assertArrayNotHasKey('ownerEmail',$post->data['source']['facts']);
        foreach($post->data['sections'] as $section)self::assertLessThanOrEqual(2000,mb_strlen($section));
        $ledger=$this->em()->getRepository(CreditLedger::class)->findOneBy(['ownerId'=>'ai-owner']);self::assertSame(-1,$ledger->data['delta']);
        $this->client->request('POST','/properties/ai-property/ai/texts/'.$id,['_token'=>$token,'description'=>'Geprüfte Objektbeschreibung.','equipment'=>'Geprüfte Ausstattung.','location'=>'Geprüfte Lageangaben.','approved'=>'1']);self::assertResponseRedirects('/properties/ai-property#texts');
        $this->em()->clear();$post=$this->em()->find(ContentPost::class,$id);self::assertSame(2,$post->data['version']);self::assertTrue($post->data['approved']);self::assertCount(2,$post->data['versions']);
        $this->client->request('GET','/properties/ai-property');self::assertResponseIsSuccessful();self::assertSelectorExists('[data-property-ai-section="texts"]');self::assertSelectorTextContains('textarea[name="description"]','Geprüfte Objektbeschreibung.');
        $this->client->request('GET','/studio/content/'.$id.'/pdf');self::assertResponseIsSuccessful();self::assertStringStartsWith('%PDF-',$this->client->getResponse()->getContent());
    }
    public function testOversizeEditsAreRejectedWithoutSilentTruncation():void
    {
        $this->auth();$token=$this->token();$this->client->request('POST','/properties/ai-property/ai/texts',['_token'=>$token]);$post=$this->generated();$id=$post->id;$before=$post->data;
        $this->client->request('POST','/properties/ai-property/ai/texts/'.$id,['_token'=>$token,'description'=>str_repeat('Ä',2001),'equipment'=>'Okay','location'=>'Okay']);self::assertResponseRedirects('/properties/ai-property#texts');$this->em()->clear();self::assertSame($before,$this->em()->find(ContentPost::class,$id)->data);
    }
    public function testForeignPropertiesAndForgedRelationsAreDenied():void
    {
        $this->auth();$token=$this->token();$this->client->request('POST','/properties/ai-foreign/ai/texts',['_token'=>$token]);self::assertResponseStatusCodeSame(404);
        $this->client->request('POST','/properties/ai-property/ai/media-workflow',['_token'=>$token,'type'=>'image','mediaIds'=>['ai-wrong-property'],'actions'=>['brightness']]);self::assertResponseStatusCodeSame(404);self::assertSame(0,$this->em()->getRepository(UserPreference::class)->count([]));
        $this->client->request('POST','/properties/ai-property/ai/texts',['_token'=>$token]);$post=$this->generated();$this->client->request('POST','/properties/ai-other-property/ai/texts/'.$post->id,['_token'=>$token,'description'=>'Wrong','equipment'=>'Wrong','location'=>'Wrong']);self::assertResponseStatusCodeSame(404);
    }
    public function testWorkflowUsesRealSelectedOriginalAndNeverClaimsProcessing():void
    {
        $this->auth();$token=$this->token();$this->client->request('POST','/properties/ai-property/ai/media-workflow',['_token'=>$token,'type'=>'image','mediaIds'=>['ai-image'],'actions'=>['brightness','redact_people'],'brightness'=>'120','contrast'=>'95']);self::assertResponseRedirects('/properties/ai-property#images');
        $job=$this->em()->getRepository(UserPreference::class)->findOneBy(['ownerId'=>'ai-owner']);self::assertSame(['ai-image'],$job->data['mediaIds']);self::assertSame(120,$job->data['brightness']);self::assertFalse($job->data['processed']);self::assertTrue($job->data['originalsPreserved']);self::assertStringContainsString('nicht verbunden',$job->data['status']);
        $asset=$this->em()->find(MediaAsset::class,'ai-image');self::assertTrue($asset->data['isOriginal']);self::assertFileExists(self::getContainer()->getParameter('kernel.project_dir').'/var/uploads/'.$asset->data['filename']);
        $this->client->request('POST','/properties/ai-property/ai/media-workflow',['_token'=>$token,'type'=>'staging','mediaIds'=>['ai-image'],'styles'=>['scandinavian']]);self::assertResponseRedirects('/properties/ai-property#images');self::assertSame(2,$this->em()->getRepository(UserPreference::class)->count([]));
    }
    public function testGroundplanRequiresAnActualSelectedCategorisedOriginal():void
    {
        $this->auth();$token=$this->token();
        foreach([[],['ai-image']] as $ids){$this->client->request('POST','/properties/ai-property/ai/media-workflow',['_token'=>$token,'type'=>'groundplan','mediaIds'=>$ids]);self::assertResponseRedirects('/properties/ai-property#documents');self::assertSame(0,$this->em()->getRepository(UserPreference::class)->count([]));}
        $this->client->request('POST','/properties/ai-property/ai/media-workflow',['_token'=>$token,'type'=>'groundplan','mediaIds'=>['ai-plan']]);self::assertResponseRedirects('/properties/ai-property#documents');$job=$this->em()->getRepository(UserPreference::class)->findOneBy(['ownerId'=>'ai-owner']);self::assertSame(['ai-plan'],$job->data['mediaIds']);self::assertFalse($job->data['processed']);
        $this->client->request('POST','/properties/ai-property/ai/media-workflow',['_token'=>$token,'type'=>'doccheck','mediaIds'=>['ai-plan'],'checks'=>['complete','sensitive'],'notes'=>'Eigene Prüfung: Seite 1 abgeglichen.']);self::assertResponseRedirects('/properties/ai-property#documents');self::assertSame(2,$this->em()->getRepository(UserPreference::class)->count([]));
    }
    public function testSocialDraftAndStoryboardUseSelectedObjectImagesOnly():void
    {
        $this->auth();$token=$this->token();$this->client->request('POST','/properties/ai-property/ai/social',['_token'=>$token,'mediaIds'=>['ai-image'],'channel'=>'Instagram']);self::assertResponseRedirects('/properties/ai-property#marketing');$post=$this->generated();self::assertSame('property_social',$post->data['tool']);self::assertSame(['ai-image'],$post->data['mediaIds']);self::assertFalse($post->data['isPublished']);self::assertLessThanOrEqual(650,mb_strlen($post->data['caption']));self::assertStringContainsString('CHASE Immobilien',$post->data['caption']);
        $this->client->request('POST','/properties/ai-property/ai/media-workflow',['_token'=>$token,'type'=>'storyboard','mediaIds'=>['ai-image']]);self::assertResponseRedirects('/properties/ai-property#marketing');$job=$this->em()->getRepository(UserPreference::class)->findOneBy(['ownerId'=>'ai-owner']);self::assertSame('storyboard',$job->data['type']);self::assertFalse($job->data['processed']);self::assertFalse($job->data['isPublished']);
    }
}
