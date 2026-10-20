<?php
namespace App\Tests\Functional;

use App\Entity\{Property,User,Valuation};
use App\Service\ValuationCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ValuationTest extends WebTestCase
{
    private KernelBrowser $browser;
    protected function setUp():void
    {
        self::ensureKernelShutdown();$this->browser=self::createClient();
        $em=$this->em();$schema=new SchemaTool($em);$metadata=$em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        foreach(['valuation-owner','valuation-peer'] as $id){$u=new User();$u->id=$id;$u->name='Synthetischer Nutzer';$u->tenantId='valuation-test';$u->region='Demo-Region';$u->email=$id.'@example.test';$u->password=password_hash('Isolated-tests-only!',PASSWORD_BCRYPT,['cost'=>4]);$em->persist($u);}
        foreach([['v-own','valuation-owner','valuation-test'],['v-foreign','valuation-peer','valuation-test'],['v-cross','valuation-owner','other-tenant']] as [$id,$owner,$tenant]){$p=new Property(['title'=>'Synthetisches Bewertungsobjekt','city'=>'Demo-Ort','bgfSqm'=>300,'areaSqm'=>140,'plotSqm'=>700,'yearBuilt'=>1980,'rentEur'=>1250,'priceEur'=>300000,'outsideCommissionPercent'=>3.57],$owner,$tenant);$p->id=$id;$em->persist($p);}
        $em->flush();
    }
    private function em():EntityManagerInterface{return self::getContainer()->get(EntityManagerInterface::class);}
    private function auth(string $id='valuation-owner'):void{$this->browser->loginUser($this->em()->find(User::class,$id));}
    private function token(string $url='/properties/v-own/valuation'):string{$c=$this->browser->request('GET',$url);self::assertResponseIsSuccessful();return(string)$c->filter('meta[name="portal-csrf"]')->attr('content');}
    private function inputs(array $changes=[]):array{return array_replace(ValuationCalculator::DEFAULTS,['valuationDate'=>'2026-10-20','bgf'=>'300','nhk'=>'835','modYear'=>'1980','landArea'=>'700','brw'=>'100','marketFactor'=>'1.1','livingArea'=>'140','grossRentAnnual'=>'15000','lz'=>'4','compQmPrice'=>'2000','adjustments'=>'-10000','commissionRate'=>'3.57','action'=>'save'],$changes);}
    private function sourceData(array $changes=[]):array{return array_replace(['factor'=>'brw','value'=>'100','region'=>'Synthetische Testzone','quarter'=>'2026-Q4','source_title'=>'Synthetischer Quellenbeleg für den Test','source_url'=>'https://www.bodenrichtwerte-boris.de/','source_date'=>'2026-01-01','note'=>'Fiktiver Testwert, keine Marktdaten.'],$changes);}

    public function testValuationAndSourcesRequireLoginAndPropertyScope():void
    {
        foreach(['/valuation','/properties/v-own/valuation','/valuation/sources'] as $url){$this->browser->request('GET',$url);self::assertResponseRedirects('http://localhost/login');}
        $this->auth();foreach(['/properties/v-foreign/valuation','/valuation?property=v-cross'] as $url){$this->browser->request('GET',$url);self::assertResponseStatusCodeSame(404);}
    }
    public function testPropertyContextPrefillsKnownFactsButLeavesMarketFactorsEmpty():void
    {
        $this->auth();$c=$this->browser->request('GET','/properties/v-own/valuation');self::assertResponseIsSuccessful();
        foreach(['bgf'=>'300','livingArea'=>'140','modYear'=>'1980','landArea'=>'700','grossRentAnnual'=>'15000','priceAssumption'=>'300000','brw'=>'','marketFactor'=>''] as $key=>$value)self::assertSame($value,$c->filter('input[name="'.$key.'"]')->attr('value'));
        self::assertSelectorTextContains('main','Synthetisches Bewertungsobjekt');self::assertSelectorTextContains('main','amtliche Faktoren sind nicht verbunden');
    }
    public function testThreeMethodsAndExactInputsAreSavedAndScopedToObjectAndUser():void
    {
        $this->auth();$token=$this->token();
        $this->browser->request('POST','/properties/v-own/valuation',$this->inputs(['_token'=>$token]));self::assertResponseStatusCodeSame(302);
        $url=$this->browser->getResponse()->headers->get('Location');$this->browser->followRedirect();self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-result="sw"]','310.715');self::assertSelectorTextContains('[data-result="ew"]','229.383');self::assertSelectorTextContains('[data-result="vw"]','270.000');
        $record=$this->em()->getRepository(Valuation::class)->findOneBy(['ownerId'=>'valuation-owner']);
        self::assertSame('v-own',$record->data['propertyId']);self::assertSame('valuation-test',$record->tenantId);self::assertSame('2026-10-20',$record->data['valuationDate']);self::assertSame('835',$record->data['inputs']['nhk']);
        self::assertEqualsWithDelta(310715.019625,$record->data['calculation']['sw']['value'],0.000001);
        $this->auth('valuation-peer');$this->browser->request('GET',$url);self::assertResponseStatusCodeSame(404);
    }
    public function testCalculateWithoutSaveAndCsrfOrInvalidInputsDoNotPersist():void
    {
        $this->auth();$token=$this->token();
        $this->browser->request('POST','/properties/v-own/valuation',$this->inputs(['_token'=>'invalid']));self::assertResponseStatusCodeSame(403);
        $this->browser->request('POST','/properties/v-own/valuation',$this->inputs(['_token'=>$token,'bgf'=>'-1']));self::assertResponseIsSuccessful();self::assertSelectorExists('[role="alert"]');
        $this->browser->request('POST','/properties/v-own/valuation',$this->inputs(['_token'=>$token,'action'=>'calculate']));self::assertResponseIsSuccessful();self::assertSelectorTextContains('[data-result="sw"]','310.715');
        self::assertSame(0,$this->em()->getRepository(Valuation::class)->count([]));
    }
    public function testManualFactorSourcesAreSnapshottedAndForeignSourcesRejected():void
    {
        $this->auth();$token=$this->token('/valuation/sources');
        $this->browser->request('POST','/valuation/sources',$this->sourceData(['_token'=>$token]));self::assertResponseRedirects('/valuation/sources');
        $source=$this->em()->getRepository(Valuation::class)->findOneBy(['ownerId'=>'valuation-owner']);self::assertSame('factor_source',$source->data['kind']);self::assertSame('Nicht verbunden',$source->data['connection']);
        $this->browser->request('POST','/properties/v-own/valuation',$this->inputs(['_token'=>$token,'sources'=>['brw'=>$source->id]]));self::assertResponseStatusCodeSame(302);
        $all=$this->em()->getRepository(Valuation::class)->findAll();$calculation=array_values(array_filter($all,fn($v)=>($v->data['kind']??'')==='calculation'))[0];
        self::assertSame('2026-Q4',$calculation->data['sourceSnapshots']['brw']['quarter']);self::assertSame('https://www.bodenrichtwerte-boris.de/',$calculation->data['sourceSnapshots']['brw']['sourceUrl']);
        $this->auth('valuation-peer');$token=$this->token('/valuation');
        $this->browser->request('POST','/valuation',$this->inputs(['_token'=>$token,'sources'=>['brw'=>$source->id]]));self::assertResponseStatusCodeSame(404);
    }
    public function testFactorOverrideCannotKeepAnUnchangedSourceClaim():void
    {
        $this->auth();$token=$this->token('/valuation/sources');$this->browser->request('POST','/valuation/sources',$this->sourceData(['_token'=>$token]));
        $source=$this->em()->getRepository(Valuation::class)->findOneBy(['ownerId'=>'valuation-owner']);
        $this->browser->request('POST','/properties/v-own/valuation',$this->inputs(['_token'=>$token,'brw'=>'200','sources'=>['brw'=>$source->id]]));self::assertResponseIsSuccessful();self::assertSelectorExists('[role="alert"]');
        self::assertSame(1,$this->em()->getRepository(Valuation::class)->count([]));
    }
    public function testCsvImportsRealLocalRowsWithMetadataAndRejectsIncompleteBatches():void
    {
        $this->auth();$token=$this->token('/valuation/sources');
        $header="factor;value;region;quarter;source_title;source_url;source_date;note\n";
        $row="lz;4,5;Demo-Region;2026-Q4;Synthetische Testquelle;https://www.bodenrichtwerte-boris.de/;2026-01-01;Kein echter Marktfaktor\n";
        $path=tempnam(sys_get_temp_dir(),'valuation-csv-');
        try{
            file_put_contents($path,$header.$row);$this->browser->request('POST','/valuation/sources/import',['_token'=>$token],['csv'=>new UploadedFile($path,'sources.csv','text/csv',null,true)]);self::assertResponseRedirects('/valuation/sources');
            self::assertSame(1,$this->em()->getRepository(Valuation::class)->count([]));
            $source=$this->em()->getRepository(Valuation::class)->findOneBy(['ownerId'=>'valuation-owner']);self::assertSame(4.5,$source->data['value']);
            file_put_contents($path,"\xEF\xBB\xBF".$header.$row);$this->browser->request('POST','/valuation/sources/import',['_token'=>$token],['csv'=>new UploadedFile($path,'sources.csv','text/csv',null,true)]);self::assertResponseRedirects('/valuation/sources');self::assertSame(1,$this->em()->getRepository(Valuation::class)->count([]));
            file_put_contents($path,$header.$row."broken;row\n");$this->browser->request('POST','/valuation/sources/import',['_token'=>$token],['csv'=>new UploadedFile($path,'sources.csv','text/csv',null,true)]);self::assertResponseRedirects('/valuation/sources');
            self::assertSame(1,$this->em()->getRepository(Valuation::class)->count([]));
        }finally{if(is_file($path))unlink($path);}
    }
    public function testRegistryRejectsUnsafeSourceUrlsAndShowsUnconnectedStatus():void
    {
        $this->auth();$token=$this->token('/valuation/sources');self::assertSelectorTextContains('main','Kein Live-Datenfeed verbunden');
        $this->browser->request('POST','/valuation/sources',$this->sourceData(['_token'=>$token,'source_url'=>'javascript:alert(1)']));self::assertResponseIsSuccessful();self::assertSelectorExists('[role="alert"]');self::assertSame(0,$this->em()->getRepository(Valuation::class)->count([]));
    }
}
