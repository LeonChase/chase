<?php
namespace App\Tests\Functional;

use App\Entity\{AdvertisingProduct,Document,User,UserPreference};
use App\Service\MaterialsImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Functional cart/quote isolation and original-catalogue access, on the isolated test database. */
final class CatalogTest extends WebTestCase
{
    private KernelBrowser $browser;
    private AdvertisingProduct $product;
    private AdvertisingProduct $second;
    private AdvertisingProduct $foreign;
    protected function setUp():void
    {
        $this->browser=self::createClient();$em=$this->em();$metadata=$em->getMetadataFactory()->getAllMetadata();$schema=new SchemaTool($em);$schema->dropSchema($metadata);$schema->createSchema($metadata);
        foreach(['catalog-agent','catalog-peer','catalog-foreign'] as $id){$user=new User();$user->id=$id;$user->tenantId=$id==='catalog-foreign'?'catalog-other':'catalog-test';$user->name='Synthetischer Katalogtest '.$id;$user->email=$id.'@example.test';$user->region='Demo-Region';$user->password=password_hash('OnlyForLocalCatalogTests!',PASSWORD_BCRYPT,['cost'=>4]);$em->persist($user);}
        $this->product=new AdvertisingProduct(['title'=>'Synthetisches Testdesign A','category'=>'Lokaler Test','unit'=>'Stück','catalogPage'=>4],null,'catalog-test');$this->second=new AdvertisingProduct(['title'=>'Synthetisches Testdesign B','category'=>'Lokaler Test'],null,'catalog-test');$this->foreign=new AdvertisingProduct(['title'=>'SECRET anderer Mandant'],null,'catalog-other');
        foreach([$this->product,$this->second,$this->foreign] as $record)$em->persist($record);$em->flush();$this->login();
    }
    private function em():EntityManagerInterface{return self::getContainer()->get(EntityManagerInterface::class);}
    private function login(string $id='catalog-agent'):void{$this->browser->loginUser($this->em()->find(User::class,$id));}
    private function token(string $page='/advertising'):string{return (string)$this->browser->request('GET',$page)->filter('meta[name="portal-csrf"]')->attr('content');}
    private function cartRecord(string $owner='catalog-agent'):?UserPreference{foreach($this->em()->getRepository(UserPreference::class)->findBy(['ownerId'=>$owner]) as $record)if(($record->data['kind']??'')==='advertising_cart')return $record;return null;}

    public function testCartPersistsQuantitiesAndNeverSharesSelectionBetweenPartners():void
    {
        $token=$this->token();self::assertStringNotContainsString('SECRET anderer Mandant',$this->browser->getResponse()->getContent());
        $this->browser->request('POST','/advertising/cart/item/'.$this->product->id,['_token'=>$token,'quantity'=>'25','page'=>4]);self::assertResponseRedirects('/advertising?page=4');
        $this->em()->clear();self::assertSame(25,$this->cartRecord()->data['items'][$this->product->id]);
        $this->browser->request('POST','/advertising/cart/item/'.$this->product->id,['_token'=>$this->token(),'quantity'=>'5']);$this->em()->clear();self::assertSame(30,$this->cartRecord()->data['items'][$this->product->id]);
        $this->browser->request('GET','/advertising/cart');self::assertResponseIsSuccessful();self::assertSelectorExists('input[name="quantities['.$this->product->id.']"][value="30"]');
        $this->browser->request('POST','/advertising/favorite/'.$this->product->id,['_token'=>$this->token(),'page'=>4,'q'=>'Testdesign']);self::assertResponseRedirects('/advertising?page=4&q=Testdesign');
        $this->browser->request('GET','/advertising?favorites=1');self::assertSelectorCount(1,'.catalog-product-card');self::assertSelectorTextContains('.catalog-product-card h3','Testdesign A');
        $this->login('catalog-peer');$this->browser->request('GET','/advertising?favorites=1');self::assertSelectorNotExists('.catalog-product-card');$this->browser->request('GET','/advertising/cart');self::assertResponseIsSuccessful();self::assertSelectorNotExists('input[name="quantities['.$this->product->id.']"]');self::assertNull($this->cartRecord('catalog-peer'));
        $this->login();$this->browser->request('GET','/advertising/cart');self::assertSelectorExists('input[value="30"]');
    }

    public function testInvalidCartUpdateIsAtomicAndForeignDesignAndCsrfAreRejected():void
    {
        $this->browser->request('POST','/advertising/cart/item/'.$this->product->id,['_token'=>$this->token(),'quantity'=>12]);self::assertResponseRedirects();
        $token=$this->token('/advertising/cart');$this->browser->request('POST','/advertising/cart',['_token'=>$token,'quantities'=>[$this->product->id=>'2',$this->second->id=>'-1']]);self::assertResponseRedirects('/advertising/cart');$this->em()->clear();self::assertSame([$this->product->id=>12],$this->cartRecord()->data['items']);
        $this->browser->request('POST','/advertising/cart/item/'.$this->foreign->id,['_token'=>$this->token(),'quantity'=>1]);self::assertResponseStatusCodeSame(404);
        $this->browser->request('POST','/advertising/cart/item/'.$this->product->id,['_token'=>'invalid','quantity'=>5]);self::assertResponseStatusCodeSame(403);$this->em()->clear();self::assertSame(12,$this->cartRecord()->data['items'][$this->product->id]);
        $this->browser->request('POST','/advertising/render',['_token'=>$this->token()]);self::assertResponseStatusCodeSame(403);
        $this->browser->request('POST','/advertising/cart/item/'.$this->product->id,['_token'=>$this->token(),'quantity'=>0,'mode'=>'replace']);self::assertResponseRedirects();$this->em()->clear();self::assertSame([],$this->cartRecord()->data['items']);
    }

    public function testQuoteSnapshotsTheCartWithoutPretendingToSendAndRemainsPrivate():void
    {
        $this->browser->request('POST','/advertising/cart/item/'.$this->product->id,['_token'=>$this->token(),'quantity'=>250]);
        $this->browser->request('POST','/advertising/request',['_token'=>$this->token('/advertising/cart'),'note'=>'Synthetischer Wunsch, keine Bestellung.','personalize'=>'1']);self::assertResponseRedirects('/advertising?tab=requests');
        $draft=null;foreach($this->em()->getRepository(UserPreference::class)->findBy(['ownerId'=>'catalog-agent']) as $record)if(($record->data['kind']??'')==='advertising_request')$draft=$record;
        self::assertNotNull($draft);self::assertFalse($draft->data['sent']);self::assertSame('Lokaler Entwurf',$draft->data['status']);self::assertSame(250,$draft->data['items'][0]['quantity']);self::assertSame(4,$draft->data['items'][0]['catalogPage']);self::assertTrue($draft->data['personalizationPreview']);self::assertArrayNotHasKey('priceEur',$draft->data['items'][0]);$id=$draft->id;
        $this->browser->request('GET','/advertising/request/'.$id.'/download');self::assertResponseIsSuccessful();self::assertStringContainsString('Keine Bestellung · nicht versendet',$this->browser->getResponse()->getContent());self::assertStringContainsString('250 × Synthetisches Testdesign A',$this->browser->getResponse()->getContent());self::assertStringContainsString('Flyeralarm',$this->browser->getResponse()->getContent());
        foreach(['catalog-peer','catalog-foreign'] as $user){$this->login($user);$this->browser->request('GET','/advertising/request/'.$id.'/download');self::assertResponseStatusCodeSame(404);$this->browser->request('POST','/advertising/request/'.$id.'/delete',['_token'=>$this->token()]);self::assertResponseStatusCodeSame(404);}
        $this->em()->clear();self::assertNotNull($this->em()->find(UserPreference::class,$id));
    }

    public function testOriginalAndRenderedPagesRequireAnIntactVisiblePrivateSource():void
    {
        $project=self::getContainer()->getParameter('kernel.project_dir');$tenant='catalog-test';$source=tempnam(sys_get_temp_dir(),'chase-catalog-test-');file_put_contents($source,"%PDF-1.4\n% isolated synthetic test ".bin2hex(random_bytes(8))."\n%%EOF\n");
        $stored=self::getContainer()->get(MaterialsImporter::class)->storeUploadedFile($source,'pdf',$tenant);unlink($source);
        $document=new Document($stored+['title'=>'Synthetischer Originalkatalog','catalogOriginal'=>true,'provided'=>true,'mimeType'=>'application/pdf'],null,$tenant);$this->em()->persist($document);$this->em()->flush();
        $base=$project.'/var/materials/catalog/'.substr(hash('sha256',$tenant),0,16).'/'.$stored['sourceHash'];if(!is_dir($base))mkdir($base,0700,true);$file=$project.'/var/materials/library/'.$stored['filePath'];
        // This synthetic test byte stream tests routing and private headers; it is not displayed as a real rendered page.
        file_put_contents($base.'/page-001.jpg','synthetic-local-test-preview');file_put_contents($base.'/manifest.json',json_encode(['documentId'=>$document->id,'sourceHash'=>$stored['sourceHash'],'pages'=>1]));
        try {
            $this->browser->request('GET','/advertising/original');self::assertResponseIsSuccessful();self::assertResponseHeaderSame('Content-Type','application/pdf');self::assertStringContainsString('private',$this->browser->getResponse()->headers->get('Cache-Control'));
            $this->browser->request('GET','/advertising/pages/1');self::assertResponseIsSuccessful();self::assertResponseHeaderSame('Content-Type','image/jpeg');self::assertResponseHeaderSame('X-Content-Type-Options','nosniff');self::assertStringContainsString('no-store',$this->browser->getResponse()->headers->get('Cache-Control'));
            $this->browser->request('GET','/advertising/pages/2');self::assertResponseStatusCodeSame(404);
            $this->login('catalog-foreign');$this->browser->request('GET','/advertising/pages/1');self::assertResponseStatusCodeSame(404);$this->browser->request('GET','/advertising/original');self::assertResponseStatusCodeSame(404);
            $this->login();file_put_contents($file,"\ncorrupted",FILE_APPEND);$this->browser->request('GET','/advertising/pages/1');self::assertResponseStatusCodeSame(404);$this->browser->request('GET','/advertising/original');self::assertResponseStatusCodeSame(404);
        }finally{foreach(glob($base.'/*')?:[] as $path)if(is_file($path))unlink($path);if(is_dir($base))rmdir($base);if(is_file($file))unlink($file);}
    }
}
