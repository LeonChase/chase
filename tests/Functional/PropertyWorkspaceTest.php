<?php
namespace App\Tests\Functional;

use App\Entity\{MediaAsset, Property, User};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Isolated integration coverage for the single-page property workspace. */
final class PropertyWorkspaceTest extends WebTestCase
{
    private KernelBrowser $client;
    private array $users=[];
    private array $files=[];
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client=self::createClient();
        $em=$this->em();$metadata=$em->getMetadataFactory()->getAllMetadata();$schema=new SchemaTool($em);$schema->dropSchema($metadata);$schema->createSchema($metadata);
        foreach (['work-owner','work-other'] as $id) {
            $u=new User();$u->id=$id;$u->name='Synthetischer Workspace-Test';$u->email=$id.'@example.test';$u->password=password_hash('test-only-password',PASSWORD_BCRYPT,['cost'=>4]);$u->roles=['ROLE_AGENT'];$u->region='Testregion';$u->tenantId='workspace-test';$em->persist($u);$this->users[$id]=$u;
        }
        foreach (['work-own'=>'work-owner','work-foreign'=>'work-other'] as $id=>$owner) {
            $p=new Property(['title'=>'Synthetisches Testobjekt','propertyType'=>'Einfamilienhaus','marketingType'=>'Kauf','status'=>'Entwurf','city'=>'Testort','region'=>'Testregion','areaSqm'=>120,'features'=>['Garten'],'isDemo'=>true,'notes'=>'Bestehende interne Notiz'], $owner,'workspace-test');$p->id=$id;$em->persist($p);
        }
        $em->flush();$this->auth();
    }
    protected function tearDown(): void
    {
        foreach ($this->files as $path) if (is_file($path)) unlink($path);
        parent::tearDown();
    }
    private function em(): EntityManagerInterface { return self::getContainer()->get(EntityManagerInterface::class); }
    private function auth(string $id='work-owner'): void { $this->client->loginUser($this->users[$id]); }
    private function token(string $path='/properties/work-own'): string
    {
        $crawler=$this->client->request('GET',$path);self::assertResponseIsSuccessful();return $crawler->filter('meta[name="portal-csrf"]')->attr('content');
    }
    private function valid(array $extra=[]): array
    {
        return array_replace(['title'=>'Neues privates Testobjekt','propertyType'=>'Einfamilienhaus','marketingType'=>'Kauf','status'=>'Vorbereitung','city'=>'Testort','region'=>'Testregion'],$extra);
    }
    private function fresh(string $class,string $id): ?object { $this->em()->clear();return $this->em()->find($class,$id); }
    private function image(string $name): UploadedFile
    {
        $path=tempnam(sys_get_temp_dir(),'chase-workspace-');$this->files[]=$path;
        file_put_contents($path,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN5cAAAAASUVORK5CYII='));
        return new UploadedFile($path,$name,'image/png',null,true);
    }
    private function rememberMediaFiles(): array
    {
        $items=$this->em()->getRepository(MediaAsset::class)->findAll();
        foreach ($items as $item) $this->files[]=dirname(__DIR__,2).'/var/uploads/'.$item->data['filename'];
        return $items;
    }

    public function testWorkspaceHasOneDataFormNineAnchoredSectionsAndLegacyEntry(): void
    {
        $crawler=$this->client->request('GET','/properties/work-own');self::assertResponseIsSuccessful();
        self::assertCount(1,$crawler->filter('form#property-data-form'));
        self::assertCount(0,$crawler->filter('form form'));
        foreach (['start','base','equipment','condition','images','documents','pricing','texts','marketing'] as $id) self::assertCount(1,$crawler->filter('details.property-section#'.$id));
        self::assertSelectorExists('input[form="property-data-form"][name="ownerName"]');
        self::assertSelectorExists('input[form="property-data-form"][name="bgfSqm"]');
        self::assertSelectorExists('input[form="property-data-form"][name="energyValue"]');
        self::assertSelectorExists('input[form="property-data-form"][name="outsideCommissionPercent"]');
        self::assertSelectorExists('select[name="modernisations[0][component]"]');
        $this->client->request('GET','/properties/work-own?tab=documents');self::assertResponseIsSuccessful();self::assertSelectorExists('[data-initial-section="documents"]');
        $this->client->request('GET','/properties?focus=texts');self::assertResponseIsSuccessful();self::assertSelectorExists('a[href="/properties/work-own#texts"]');
    }

    public function testExtendedInputPersistsAndPartialUpdatesPreservePrivateEnrichments(): void
    {
        $token=$this->token('/properties/new');
        $this->client->request('POST','/properties/new',$this->valid([
            '_token'=>$token,'returnSection'=>'images','street'=>'Synthetische Straße','houseNumber'=>'12a','postalCode'=>'12345','contactName'=>'Synthetischer Ansprechpartner','ownerName'=>'Synthetischer Eigentümer','ownerEmail'=>'test-owner@example.org',
            'usageType'=>'Wohnen','bgfSqm'=>'220,5','areaSqm'=>'140','yearBuilt'=>'1994','floor'=>'0','units'=>'2','heatingType'=>'Zentralheizung','energyCarrier'=>'Gas','energyType'=>'Bedarfsausweis','energyValue'=>'91,3','energyClass'=>'C','condition'=>'gepflegt',
            'modernisations'=>[['component'=>'Fenster','year'=>'2021','details'=>'Synthetische dokumentierte Maßnahme']],'priceEur'=>'300000','outsideCommissionPercent'=>'3,57','commissionVatIncluded'=>'1','readyForReview'=>'1','ownerId'=>'work-other','tenantId'=>'foreign',
        ]));
        self::assertResponseStatusCodeSame(302);$url=$this->client->getResponse()->headers->get('Location');self::assertStringEndsWith('#images',$url);$path=parse_url($url,PHP_URL_PATH);$id=basename($path);
        $property=$this->fresh(Property::class,$id);self::assertSame('work-owner',$property->ownerId);self::assertSame('workspace-test',$property->tenantId);self::assertSame(220.5,$property->data['bgfSqm']);self::assertSame(0,$property->data['floor']);self::assertSame('test-owner@example.org',$property->data['ownerEmail']);self::assertSame(3.57,$property->data['outsideCommissionPercent']);self::assertTrue($property->data['readyForReview']);self::assertFalse($property->data['isDemo']);self::assertSame(2021,$property->data['modernisations'][0]['year']);
        $token=$this->token($path.'/edit');$this->client->request('POST',$path.'/edit',$this->valid(['_token'=>$token,'title'=>'Geändertes privates Testobjekt']));self::assertResponseRedirects($path);
        $property=$this->fresh(Property::class,$id);self::assertSame('Synthetischer Eigentümer',$property->data['ownerName']);self::assertSame(220.5,$property->data['bgfSqm']);self::assertTrue($property->data['readyForReview']);self::assertCount(1,$property->data['modernisations']);
    }

    public function testInvalidExtendedFieldsAndForeignMutationAreRejected(): void
    {
        $token=$this->token();$before=$this->fresh(Property::class,'work-own')->data;
        $this->client->request('POST','/properties/work-own/edit',$this->valid(['_token'=>$token,'bgfSqm'=>'-5','outsideCommissionPercent'=>'101','ownerEmail'=>'invalid','modernisations'=>[['component'=>'Unknown','year'=>'1800','details'=>'Test']]]));self::assertResponseIsSuccessful();self::assertSelectorExists('.property-validation');self::assertSame($before,$this->fresh(Property::class,'work-own')->data);
        $this->client->request('POST','/properties/work-foreign/edit',$this->valid(['_token'=>$token]));self::assertResponseStatusCodeSame(404);
        $this->client->request('POST','/properties/work-own/edit',$this->valid(['_token'=>'forged']));self::assertResponseStatusCodeSame(403);
    }

    public function testBatchImagesCanBeLabelledReorderedDownloadedAndRemovedWithAcl(): void
    {
        $token=$this->token();$this->client->request('POST','/properties/work-own/media',['_token'=>$token,'_workspace'=>'1','kind'=>'image'],['media'=>[$this->image('original-fassade.png'),$this->image('original-garten.png')]]);self::assertResponseRedirects('/properties/work-own#images');
        $assets=$this->rememberMediaFiles();self::assertCount(2,$assets);$first=$assets[0];$second=$assets[1];$firstId=$first->id;$filename=$first->data['filename'];self::assertSame('original-fassade.png',$first->data['originalName']);
        $this->client->request('POST','/properties/work-own/media/'.$firstId.'/metadata',['_token'=>$token,'_workspace'=>'1','title'=>'Fassade','caption'=>'Manuell bestätigte Außenansicht','kind'=>'image','category'=>'Außenansicht','position'=>'30','factsConfirmed'=>'1','filename'=>'../../.env','originalName'=>'tampered']);self::assertResponseRedirects('/properties/work-own#images');
        $first=$this->fresh(MediaAsset::class,$firstId);self::assertSame('Fassade',$first->title);self::assertTrue($first->data['factsConfirmed']);self::assertSame('original-fassade.png',$first->data['originalName']);self::assertSame($filename,$first->data['filename']);
        $this->client->request('POST','/properties/work-own/media/'.$firstId.'/move',['_token'=>$token,'_workspace'=>'1','direction'=>'up']);self::assertResponseRedirects('/properties/work-own#images');self::assertLessThan($this->fresh(MediaAsset::class,$second->id)->data['position'],$this->fresh(MediaAsset::class,$firstId)->data['position']);
        $this->client->request('GET','/media/'.$firstId);self::assertResponseIsSuccessful();self::assertResponseHeaderSame('Content-Type','image/png');self::assertResponseHeaderSame('X-Content-Type-Options','nosniff');
        $this->auth('work-other');$otherToken=$this->token('/properties/work-foreign');$this->client->request('GET','/media/'.$firstId);self::assertResponseStatusCodeSame(404);$this->client->request('POST','/properties/work-own/media/'.$firstId.'/delete',['_token'=>$otherToken,'_workspace'=>'1']);self::assertResponseStatusCodeSame(404);self::assertNotNull($this->fresh(MediaAsset::class,$firstId));
        $this->auth();$token=$this->token();$this->client->request('POST','/properties/work-own/media/'.$firstId.'/delete',['_token'=>$token,'_workspace'=>'1']);self::assertResponseRedirects('/properties/work-own#images');self::assertNull($this->fresh(MediaAsset::class,$firstId));self::assertFileDoesNotExist(dirname(__DIR__,2).'/var/uploads/'.$filename);
    }

    public function testDocumentClassificationAndMimeValidation(): void
    {
        $token=$this->token();$path=tempnam(sys_get_temp_dir(),'chase-doc-test-');$this->files[]=$path;file_put_contents($path,"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
        $this->client->request('POST','/properties/work-own/media',['_token'=>$token,'_workspace'=>'1','kind'=>'document'],['media'=>[new UploadedFile($path,'alter-grundriss.pdf','application/pdf',null,true)]]);self::assertResponseRedirects('/properties/work-own#documents');$assets=$this->rememberMediaFiles();self::assertCount(1,$assets);$id=$assets[0]->id;
        $this->client->request('POST','/properties/work-own/media/'.$id.'/metadata',['_token'=>$token,'_workspace'=>'1','title'=>'Grundriss','caption'=>'Manuelle Quelle','kind'=>'document','category'=>'Grundriss','position'=>'10']);self::assertResponseRedirects('/properties/work-own#documents');self::assertSame('Grundriss',$this->fresh(MediaAsset::class,$id)->data['category']);
        $this->client->request('GET','/media/'.$id);self::assertResponseIsSuccessful();self::assertStringStartsWith('attachment;',$this->client->getResponse()->headers->get('Content-Disposition'));
        $spoof=tempnam(sys_get_temp_dir(),'chase-spoof-test-');$this->files[]=$spoof;file_put_contents($spoof,'<script>not an image</script>');$this->client->request('POST','/properties/work-own/media',['_token'=>$token,'_workspace'=>'1','kind'=>'image'],['media'=>[new UploadedFile($spoof,'forged.png','image/png',null,true)]]);self::assertResponseRedirects('/properties/work-own#images');self::assertSame(1,$this->em()->getRepository(MediaAsset::class)->count([]));
    }
}
