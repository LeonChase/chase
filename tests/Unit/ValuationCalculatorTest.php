<?php
namespace App\Tests\Unit;

use App\Service\ValuationCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValuationCalculatorTest extends TestCase
{
    private function fixture(array $changes=[]): array
    {
        return array_replace(['bgf'=>300,'nhk'=>835,'modYear'=>1980,'landArea'=>700,'brw'=>100,'marketFactor'=>1.1,'livingArea'=>140,'grossRentAnnual'=>15000,'lz'=>4,'compQmPrice'=>2000,'adjustments'=>-10000,'commissionRate'=>3.57],$changes);
    }
    public function testSachwertMatchesExistingHtmlFormulaAndEffectiveDefaultIndex(): void
    {
        $result=(new ValuationCalculator())->calculate($this->fixture(),2026);
        self::assertTrue($result['sw']['ok']);
        self::assertSame(198.2,$result['input']['cfg_bpi']);
        self::assertEqualsWithDelta(496491,$result['sw']['normalHK'],0.000001);
        self::assertEqualsWithDelta(285482.325,$result['sw']['ageDepAmt'],0.000001);
        self::assertEqualsWithDelta(211008.675,$result['sw']['buildingValue'],0.000001);
        self::assertEqualsWithDelta(10550.43375,$result['sw']['outdoor'],0.000001);
        self::assertEqualsWithDelta(291559.10875,$result['sw']['preliminary'],0.000001);
        self::assertEqualsWithDelta(310715.019625,$result['sw']['value'],0.000001);
    }
    public function testErtragswertMatchesCapitalizationFormulaOfOriginal(): void
    {
        $ew=(new ValuationCalculator())->calculate($this->fixture(),2026)['ew'];
        self::assertTrue($ew['ok']);
        self::assertSame(15000.0,$ew['grossAnnual']);
        self::assertSame(3000.0,$ew['mgmtCost']);
        self::assertSame(2800.0,$ew['landInterest']);
        self::assertSame(9200.0,$ew['buildingNet']);
        self::assertSame(34.0,$ew['rnd']);
        self::assertEqualsWithDelta(18.41119776109,$ew['multiplier'],0.00000001);
        self::assertEqualsWithDelta(229383.01940203,$ew['value'],0.000001);
    }
    public function testComparisonAndOriginalMaximumOfferRuleRemainTransparent(): void
    {
        $result=(new ValuationCalculator())->calculate($this->fixture(),2026);
        self::assertSame(270000.0,$result['vw']['value']);
        self::assertFalse($result['offer']['provided']);
        self::assertSame($result['sw']['value'],$result['offer']['value']);
        $offer=(new ValuationCalculator())->calculate($this->fixture(['priceAssumption'=>300000]),2026)['offer'];
        self::assertTrue($offer['provided']);
        self::assertSame(300000.0,$offer['value']);
        self::assertSame(20.0,$offer['factor']);
        self::assertEqualsWithDelta(0.05,$offer['yieldRate'],0.000001);
        self::assertSame(10710.0,$offer['provision']);
    }
    public function testExplicitAnnualRentTakesPrecedenceAndSquareMeterFallbackWorks(): void
    {
        $calculator=new ValuationCalculator();
        $annual=$calculator->calculate($this->fixture(['rentQm'=>100]),2026);
        self::assertSame(15000.0,$annual['ew']['grossAnnual']);self::assertSame('annual',$annual['ew']['rentSource']);
        $sqm=$calculator->calculate($this->fixture(['grossRentAnnual'=>'','rentQm'=>10.5]),2026);
        self::assertSame(17640.0,$sqm['ew']['grossAnnual']);self::assertSame('sqm',$sqm['ew']['rentSource']);
    }
    public function testAgeDepreciationAndRemainingLifeAreClampedLikeOriginal(): void
    {
        $old=(new ValuationCalculator())->calculate($this->fixture(['modYear'=>1800]),2026);
        self::assertSame(1,$old['common']['ageDep']);
        self::assertSame(0.0,$old['sw']['buildingValue']);
        self::assertSame(0,$old['ew']['rnd']);
        $future=(new ValuationCalculator())->calculate($this->fixture(['modYear'=>2027,'cfg_totalLife'=>200]),2026);
        self::assertSame(0,$future['common']['ageDep']);self::assertSame(120,$future['ew']['rnd']);
    }
    public function testIndexCanBeEnteredAsIndexOrFactor(): void
    {
        $calculator=new ValuationCalculator();
        $index=$calculator->calculate($this->fixture(['cfg_bpi'=>198.2]),2026);
        $factor=$calculator->calculate($this->fixture(['cfg_bpi'=>1.982]),2026);
        self::assertSame($index['sw']['value'],$factor['sw']['value']);
    }
    public function testMissingMethodsCannotCreateAPriceFromAnAdjustmentAlone(): void
    {
        $result=(new ValuationCalculator())->calculate(['adjustments'=>10000],2026);
        foreach(['sw','ew','vw'] as $key){self::assertFalse($result[$key]['ok']);self::assertNotEmpty($result[$key]['missing']);}
        self::assertFalse($result['offer']['ok']);self::assertSame(0,$result['offer']['value']);
    }
    public function testZeroInterestRetainsOriginalMultiplierButMethodRemainsIncomplete(): void
    {
        $ew=(new ValuationCalculator())->calculate($this->fixture(['lz'=>0]),2026)['ew'];
        self::assertFalse($ew['ok']);self::assertSame(34.0,$ew['multiplier']);self::assertContains('Liegenschaftszins',$ew['missing']);
    }
    public static function numbers(): iterable
    {
        yield ['1.234,56',1234.56];yield ['1234.56',1234.56];yield [' -12,5 ',-12.5];yield ['',0.0];yield ['198.2',198.2];
    }
    #[DataProvider('numbers')]
    public function testNumberInputsAcceptGermanAndDecimalPointNotation(string $raw,float $expected):void{self::assertSame($expected,(new ValuationCalculator())->parseNumber($raw));}
    public static function invalidNumbers(): iterable{yield ['abc'];yield ['NAN'];yield ['1e999'];yield [array('nested')];}
    #[DataProvider('invalidNumbers')]
    public function testMalformedOrNonfiniteInputsAreRejected(mixed $raw):void{$this->expectException(\InvalidArgumentException::class);(new ValuationCalculator())->parseNumber($raw);}
    public function testInvalidNegativePropertyAreaIsRejected():void{$this->expectException(\InvalidArgumentException::class);(new ValuationCalculator())->calculate(['bgf'=>-1],2026);}
    public function testPropertyDataIsPrefilledWithoutInventingFactorsOrModifiedYear():void
    {
        $data=(new ValuationCalculator())->prefill(['bgfSqm'=>300,'areaSqm'=>140,'plotSqm'=>700,'yearBuilt'=>1980,'lastModernisationYear'=>2020,'rentEur'=>1000,'priceEur'=>300000,'outsideCommissionPercent'=>3.57]);
        self::assertSame('300',$data['bgf']);self::assertSame('140',$data['livingArea']);self::assertSame('700',$data['landArea']);
        self::assertSame('1980',$data['modYear']);self::assertSame('12000',$data['grossRentAnnual']);self::assertSame('3.57',$data['commissionRate']);
        self::assertSame('',$data['brw']);self::assertSame('',$data['marketFactor']);self::assertSame('',$data['lz']);
    }
}
