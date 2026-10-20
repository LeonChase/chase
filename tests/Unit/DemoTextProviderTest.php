<?php
namespace App\Tests\Unit;

use App\Service\DemoTextProvider;
use App\Integration\DemoValuationProvider;
use App\Integration\DisabledExternalProvider;
use PHPUnit\Framework\TestCase;

final class DemoTextProviderTest extends TestCase
{
    public function testOnlySuppliedFactsAreUsedAndMissingInformationIsExplicit(): void
    {
        $provider = new DemoTextProvider();
        $result = $provider->generate('expose', ['title' => 'Testobjekt (Demo)', 'city' => 'Hameln', 'areaSqm' => 83, 'rooms' => 3, 'features' => ['Balkon']]);
        self::assertStringContainsString('83 m²', $result['text']);
        self::assertStringContainsString('3', $result['text']);
        self::assertStringContainsString('Balkon', $result['text']);
        self::assertStringContainsString('Preis: Angabe fehlt', $result['text']);
        self::assertStringNotContainsString('Garten', $result['text']);
        self::assertStringNotContainsString('Bus', $result['text']);
        self::assertStringNotContainsString('Energieeffizienzklasse A', $result['text']);
        self::assertContains('Energiekennwerte: Angabe fehlt.', $result['checklist']);
        self::assertSame($result, $provider->generate('expose', ['title' => 'Testobjekt (Demo)', 'city' => 'Hameln', 'areaSqm' => 83, 'rooms' => 3, 'features' => ['Balkon']]));
    }

    public function testLocationNeverInventsInfrastructure(): void
    {
        $result = (new DemoTextProvider())->generate('lage', ['city' => 'Hameln']);
        self::assertStringContainsString('Weitere verifizierte Lagefakten: Angabe fehlt.', $result['text']);
        self::assertStringNotContainsString('Minuten', $result['text']);
        self::assertStringNotContainsString('Kilometer', $result['text']);
        $withFacts = (new DemoTextProvider())->generate('lage', ['city' => 'Hameln', 'verifiedLocation' => 'Buslinie X (fiktives Nutzerbeispiel)']);
        self::assertStringContainsString('Vom Nutzer bestätigte Lagefakten: Buslinie X (fiktives Nutzerbeispiel)', $withFacts['text']);
    }

    public function testEveryTextToolProducesAnEditableLocalDraft(): void
    {
        foreach (array_keys(DemoTextProvider::TOOLS) as $tool) {
            foreach (array_keys(DemoTextProvider::TONES) as $tone) {
                $result = (new DemoTextProvider())->generate($tool, [], $tone);
                self::assertNotEmpty($result['headline']);
                self::assertNotEmpty($result['text']);
                self::assertSame('Regelbasierte Offline-Demo', $result['mode']);
                self::assertNotEmpty($result['checklist']);
            }
        }
    }

    public function testUnsupportedToolIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DemoTextProvider())->generate('internet', []);
    }

    public function testValuationIsOnlyTransparentUserInputArithmetic(): void
    {
        $result = (new DemoValuationProvider())->calculate(['monthlyRent' => 1000, 'factor' => 20]);
        self::assertEquals(12000, $result['annualRent']);
        self::assertEquals(240000, $result['result']);
        self::assertStringContainsString('Keine Markt-', $result['disclaimer']);
    }

    public function testInvalidValuationNeverSilentlyCoercesTextToNumbers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DemoValuationProvider())->calculate(['monthlyRent' => '1000abc', 'factor' => 20]);
    }

    public function testPublishingIsExplicitlyDisabled(): void
    {
        $this->expectException(\LogicException::class);
        (new DisabledExternalProvider())->publish('Instagram', ['caption' => 'Demo']);
    }
}
