<?php
namespace App\Tests\Functional;

use App\Entity\{User,Property,MediaAsset,ContentPost};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser,Test\WebTestCase};
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ToolboxTest extends WebTestCase
{
    private KernelBrowser $client;
    private array $files=[];
    protected function setUp():void
    {
        self::ensureKernelShutdown();$this->client=self::createClient();$em=$this->em();$meta=$em->getMetadataFactory()->getAllMetadata();$schema=new SchemaTool($em);$schema->dropSchema($meta);$schema->createSchema($meta);
        foreach(['tool-owner','tool-peer','tool-cross'] as $id){$u=new User();$u->id=$id;$u->tenantId=$id==='tool-cross'?'other-tenant':'toolbox-test';$u->email=$id.'@example.test';$u->name='Synthetischer Tooltest';$u->password=password_hash('IsolatedToolboxOnly!',PASSWORD_BCRYPT,['cost'=>4]);$em->persist($u);}
        foreach(['tool-owner','tool-peer'] as $id){$p=new Property(['title'=>$id==='tool-owner'?'Eigene Testwohnung':'GEHEIM fremde Wohnung','city'=>'Demo-Ort','areaSqm'=>83,'rooms'=>3,'propertyType'=>'Wohnung','features'=>['Balkon']],$id,'toolbox-test');$p->id='property-'.$id;$em->persist($p);}$em->flush();
    }
    protected function tearDown():void{foreach($this->files as $file)if(is_file($file))unlink($file);parent::tearDown();}
    private function em():EntityManagerInterface{return self::getContainer()->get(EntityManagerInterface::class);}
    private function auth(string $id='tool-owner'):void{$this->client->loginUser($this->em()->find(User::class,$id));}
    private function token(string $route='/tools'):string{$crawler=$this->client->request('GET',$route);self::assertResponseIsSuccessful();return $crawler->filter('meta[name="portal-csrf"]')->attr('content');}
    private function upload():MediaAsset
    {
        $path=tempnam(sys_get_temp_dir(),'toolbox-pdf-');$this->files[]=$path;file_put_contents($path,"%PDF-1.4\n% Synthetic test file, not legal content\n%%EOF\n");
        $this->client->request('POST','/tools/documents',['_token'=>$this->token('/tools/documents')],['document'=>new UploadedFile($path,'Testunterlage.pdf','application/pdf',null,true)]);self::assertResponseStatusCodeSame(302);
        $asset=$this->em()->getRepository(MediaAsset::class)->findOneBy(['ownerId'=>'tool-owner']);self::assertNotNull($asset);$this->files[]=dirname(__DIR__,2).'/var/toolbox/'.substr(hash('sha256','toolbox-test'),0,16).'/'.substr(hash('sha256','tool-owner'),0,16).'/'.$asset->data['filename'];return $asset;
    }
    public function testToolboxIsAuthenticatedAndHasWorkingScopedEntryPoints():void
    {
        $this->client->request('GET','/tools');self::assertResponseRedirects('http://localhost/login');$this->auth();$this->client->request('GET','/tools');self::assertResponseIsSuccessful();self::assertSelectorCount(8,'.toolbox-card');
        foreach(['/tools/images','/tools/texts','/tools/documents','/tools/workflow/staging','/tools/workflow/floorplan','/tools/workflow/video','/tools/workflow/redaction'] as $route){$this->client->request('GET',$route);self::assertResponseIsSuccessful();self::assertStringNotContainsString('GEHEIM',$this->client->getResponse()->getContent());}
        $this->client->request('GET','/tools/workflow/unknown');self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/tools/workflow/staging?property=property-tool-peer');self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/tools/workflow/staging?property=property-tool-owner');self::assertResponseRedirects('/properties/property-tool-owner#images');
    }
    public function testStandaloneTextCreationUsesExistingProviderAndOwnFactsAndKeepsVersionedEditor():void
    {
        $this->auth();$token=$this->token('/tools/texts');$data=['_token'=>$token,'tool'=>'expose','tone'=>'sachlich','propertyId'=>'property-tool-owner','channel'=>'Instagram','format'=>'Feed'];
        $this->client->request('POST','/tools/texts/generate',array_replace($data,['_token'=>'bad']));self::assertResponseStatusCodeSame(403);
        $this->client->request('POST','/tools/texts/generate',array_replace($data,['propertyId'=>'property-tool-peer']));self::assertResponseStatusCodeSame(404);
        $this->client->request('POST','/tools/texts/generate',$data);$post=$this->em()->getRepository(ContentPost::class)->findOneBy(['ownerId'=>'tool-owner']);self::assertNotNull($post);self::assertResponseRedirects('/tools/texts/'.$post->id.'/edit');$this->client->followRedirect();self::assertSelectorTextContains('#content','83');self::assertSelectorTextContains('#content','Balkon');
        $this->client->request('POST','/tools/texts/'.$post->id.'/edit',['_token'=>$token,'title'=>'Überarbeiteter Testtext','content'=>'Nur verifizierte Testfakten.','status'=>'Freigegeben','channel'=>'Instagram','format'=>'Feed']);self::assertResponseRedirects('/tools/texts/'.$post->id.'/edit');$this->em()->clear();self::assertSame(2,$this->em()->find(ContentPost::class,$post->id)->data['version']);
        $this->auth('tool-peer');$this->client->request('GET','/tools/texts/'.$post->id.'/edit');self::assertResponseStatusCodeSame(404);
    }
    public function testReviewStoresActualPrivateOriginalAndManualNotesWithoutClaimingAnalysis():void
    {
        $this->auth();$asset=$this->upload();$id=$asset->id;$token=$this->token('/tools/documents/'.$id);self::assertFalse($asset->data['automatedAnalysis']);self::assertSelectorTextContains('h2','Testunterlage.pdf');
        $this->client->request('POST','/tools/documents/'.$id,['_token'=>'bad','notes'=>'Not saved']);self::assertResponseStatusCodeSame(403);
        $this->client->request('POST','/tools/documents/'.$id,['_token'=>$token,'notes'=>'<script>Escaped note</script>','checks'=>['source','pages']]);self::assertResponseRedirects('/tools/documents/'.$id);$this->client->followRedirect();self::assertSelectorTextContains('#review-notes','<script>Escaped note</script>');self::assertStringContainsString('&lt;script&gt;Escaped note&lt;/script&gt;',$this->client->getResponse()->getContent());
        $this->client->request('GET','/tools/documents/'.$id.'/download');self::assertResponseIsSuccessful();self::assertResponseHeaderSame('Content-Type','application/pdf');self::assertResponseHeaderSame('Content-Security-Policy',"default-src 'none'; sandbox");self::assertStringContainsString('attachment',$this->client->getResponse()->headers->get('Content-Disposition'));
        foreach(['tool-peer','tool-cross'] as $user){$this->auth($user);$tokenOther=$this->token();foreach(['/tools/documents/'.$id,'/tools/documents/'.$id.'/download'] as $route){$this->client->request('GET',$route);self::assertResponseStatusCodeSame(404);}$this->client->request('POST','/tools/documents/'.$id.'/delete',['_token'=>$tokenOther]);self::assertResponseStatusCodeSame(404);}
        $this->auth();$this->client->request('POST','/tools/documents/'.$id.'/delete',['_token'=>$this->token()]);self::assertResponseRedirects('/tools/documents');$this->em()->clear();self::assertNull($this->em()->find(MediaAsset::class,$id));
    }
    public function testSpoofedDocumentIsRejectedAndDoesNotCreateRecord():void
    {
        $this->auth();$path=tempnam(sys_get_temp_dir(),'toolbox-fake-');$this->files[]=$path;file_put_contents($path,'<?php echo "must not execute";');$this->client->request('POST','/tools/documents',['_token'=>$this->token()],['document'=>new UploadedFile($path,'falsch.pdf','application/pdf',null,true)]);self::assertResponseIsSuccessful();self::assertSelectorTextContains('[role="alert"]','Dateiendung und Dateiinhalt');self::assertSame(0,$this->em()->getRepository(MediaAsset::class)->count([]));
    }
}
