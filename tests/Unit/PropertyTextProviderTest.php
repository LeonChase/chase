<?php
namespace App\Tests\Unit;

use App\Entity\{Property, MediaAsset};
use App\Service\PropertyTextProvider;
use PHPUnit\Framework\TestCase;

final class PropertyTextProviderTest extends TestCase
{
    private function image(Property $property,string $caption,bool $confirmed=true):MediaAsset
    {
        return new MediaAsset(['propertyId'=>$property->id,'mime'=>'image/png','kind'=>'image','caption'=>$caption,'factsConfirmed'=>$confirmed],$property->ownerId,$property->tenantId);
    }

    public function testThreeFixedSectionsUseFactsAndOnlyConfirmedImageCaptions():void
    {
        $p=new Property(['title'=>'Demo-Wohnung','propertyType'=>'Eigentumswohnung','city'=>'Demo-Ort','areaSqm'=>82.5,'rooms'=>3,'floor'=>0,'features'=>['Balkon'],'ownerEmail'=>'private@example.test','ownerName'=>'Privater Eigentümer','notes'=>'GEHEIME interne Notiz'],'owner','tenant');
        $known=$this->image($p,'Manuell bestätigtes Tageslichtbad.');
        $unknown=$this->image($p,'Nicht bestätigtes Schwimmbad.',false);
        $other=new Property([], 'owner','tenant');$wrong=$this->image($other,'Fremde Sauna.');
        $result=(new PropertyTextProvider())->generate($p,[$known,$unknown,$wrong]);
        self::assertSame(['description','equipment','location'],array_keys($result['sections']));
        self::assertStringContainsString('82,5 m²',$result['sections']['description']);
        self::assertStringContainsString('Etage: 0.',$result['sections']['description']);
        self::assertStringContainsString('Tageslichtbad',$result['sections']['equipment']);
        self::assertStringNotContainsString('Schwimmbad',implode('',$result['sections']));
        self::assertStringNotContainsString('Sauna',implode('',$result['sections']));
        self::assertSame([$known->id],array_column($result['source']['confirmedImageCaptions'],'id'));
        self::assertArrayNotHasKey('ownerEmail',$result['source']['facts']);
        self::assertArrayNotHasKey('ownerName',$result['source']['facts']);
        self::assertArrayNotHasKey('notes',$result['source']['facts']);
        self::assertFalse($result['source']['imageAnalysis']);
        self::assertSame($result,(new PropertyTextProvider())->generate($p,[$known,$unknown,$wrong]));
    }

    public function testMissingLocationAndEnergyRemainUnknown():void
    {
        $result=(new PropertyTextProvider())->generate(new Property(['title'=>'Demo'],'owner','tenant'));
        self::assertStringContainsString('Wohnfläche: Angabe fehlt',$result['sections']['description']);
        self::assertStringContainsString('Energiekennwert: Angabe fehlt',$result['sections']['equipment']);
        self::assertStringContainsString('Weitere verifizierte Lagefakten: Angabe fehlt',$result['sections']['location']);
        self::assertStringNotContainsString('Minuten',implode('',$result['sections']));
        self::assertStringNotContainsString('zentral gelegen',implode('',$result['sections']));
    }

    public function testEveryGeneratedSectionStaysWithinTwoThousandCharacters():void
    {
        $p=new Property(['title'=>str_repeat('Ä',250),'city'=>str_repeat('Ö',250),'features'=>array_fill(0,30,str_repeat('ü',250)),'locationFacts'=>str_repeat('Lagefakt ',1000)],'owner','tenant');
        $images=[];for($i=0;$i<20;$i++)$images[]=$this->image($p,str_repeat('Bildfakt ',60));
        $provider=new PropertyTextProvider();$result=$provider->generate($p,$images);
        foreach($result['sections'] as $text){self::assertLessThanOrEqual(2000,mb_strlen($text));self::assertNotSame('',$text);}
        self::assertLessThanOrEqual(650,mb_strlen($provider->social($p,$images)['caption']));
    }

    public function testDocumentCaptionOrAnotherTenantNeverBecomesImageFact():void
    {
        $p=new Property(['title'=>'Demo'],'owner','tenant');
        $doc=$this->image($p,'Geheime Dokumenteninformation.');$doc->data['kind']='document';
        $cross=$this->image($p,'Anderer Mandant.');$cross->tenantId='other';
        $result=(new PropertyTextProvider())->generate($p,[$doc,$cross]);
        self::assertSame([],$result['source']['confirmedImageCaptions']);
    }
}
