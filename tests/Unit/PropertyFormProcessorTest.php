<?php
namespace App\Tests\Unit;

use App\Service\PropertyFormProcessor;
use App\Service\PropertyFormSchema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PropertyFormProcessorTest extends TestCase
{
    private function minimal(): array
    {
        return ['title'=>'Privates Testobjekt','city'=>'Hameln','region'=>'Hameln-Pyrmont','propertyType'=>'Eigentumswohnung','marketingType'=>'Kauf','status'=>'Entwurf'];
    }

    public function testLegacyMinimalPostStillWorksAndRetainsUnsubmittedEnrichments(): void
    {
        $existing = ['notes'=>'Bisherige Notiz','features'=>['Balkon'],'isDemo'=>false,'externalId'=>'CRM-501','modernisations'=>[['component'=>'Fenster','year'=>2020,'details'=>'Bestätigt']]];
        $result = (new PropertyFormProcessor())->process(new Request([], $this->minimal()), $existing);
        self::assertSame([], $result['errors']);
        self::assertSame('Bisherige Notiz', $result['data']['notes']);
        self::assertSame(['Balkon'], $result['data']['features']);
        self::assertSame($existing['modernisations'], $result['data']['modernisations']);
        self::assertFalse($result['data']['isDemo']);
        self::assertSame('CRM-501', $result['data']['externalId']);
    }

    public function testActualContactDetailsAreAllowedButProtectedValuesAreNotMassAssigned(): void
    {
        $input = $this->minimal() + ['ownerName'=>'Private Eigentümerangabe','ownerEmail'=>'eigentuemer@example.org','contactPhone'=>'+49 123 456','ownerId'=>'another-owner','tenantId'=>'other-tenant','externalId'=>'injected','isDemo'=>true];
        $result = (new PropertyFormProcessor())->process(new Request([], $input));
        self::assertSame([], $result['errors']);
        self::assertSame('eigentuemer@example.org', $result['data']['ownerEmail']);
        foreach (['ownerId','tenantId','externalId','isDemo'] as $protected) self::assertArrayNotHasKey($protected, $result['data']);
    }

    public function testEveryRequiredFieldIsValidated(): void
    {
        $result = (new PropertyFormProcessor())->process(new Request());
        self::assertCount(6, $result['errors']);
    }

    public function testNumericFieldsSupportLocaleInputsAndZeroValuesWhereAppropriate(): void
    {
        $input = $this->minimal() + ['areaSqm'=>'83,25','priceEur'=>'245.000,50','floor'=>'-1','bathrooms'=>'0','parkingSpaces'=>'0','insideCommissionPercent'=>'0','outsideCommissionPercent'=>'3,57','yearBuilt'=>'1996'];
        $result = (new PropertyFormProcessor())->process(new Request([], $input));
        self::assertSame([], $result['errors']);
        self::assertSame(83.25, $result['data']['areaSqm']);
        self::assertSame(245000.5, $result['data']['priceEur']);
        self::assertSame(-1, $result['data']['floor']);
        self::assertSame(0, $result['data']['parkingSpaces']);
        self::assertSame(3.57, $result['data']['outsideCommissionPercent']);
    }

    public function testInvalidNumbersEnumsDatesAndEmailDoNotSilentlyCoerce(): void
    {
        $input = $this->minimal();
        $input['propertyType'] = 'Fantasietyp';
        $input += ['areaSqm'=>'83abc','priceEur'=>'0','floor'=>'-11','buildingFloors'=>'2.5','outsideCommissionPercent'=>'101','contactEmail'=>'keine E-Mail','energyValidUntil'=>'2026-02-31'];
        $result = (new PropertyFormProcessor())->process(new Request([], $input));
        self::assertGreaterThanOrEqual(8, count($result['errors']));
        self::assertSame('83abc', $result['data']['areaSqm']);
    }

    public function testMalformedNestedScalarValuesReturnErrorsWithoutThrowing(): void
    {
        $input = $this->minimal();
        $input['title'] = ['wrong'=>'shape'];
        $input['contactEmail'] = ['bad'];
        $input['features'] = ['Array statt Text'];
        $input['modernisations'] = ['named-key'=>['component'=>['bad'],'year'=>'2020']];
        $result = (new PropertyFormProcessor())->process(new Request([], $input));
        self::assertGreaterThanOrEqual(4, count($result['errors']));
    }

    public function testFeaturesAreTrimmedLimitedAndDeduplicated(): void
    {
        $result = (new PropertyFormProcessor())->process(new Request([], $this->minimal() + ['features'=>"Balkon, Garten\n  Balkon \nTerrasse"]));
        self::assertSame([], $result['errors']);
        self::assertSame(['Balkon','Garten','Terrasse'], $result['data']['features']);
        $tooMany = (new PropertyFormProcessor())->process(new Request([], $this->minimal() + ['features'=>implode("\n",array_map(fn($i)=>'Merkmal '.$i, range(1,31)))]));
        self::assertNotEmpty($tooMany['errors']);
    }

    public function testModernisationRowsValidateAndCanBeExplicitlyCleared(): void
    {
        $input = $this->minimal() + ['modernisations'=>[
            ['component'=>'Fenster','year'=>'2021','details'=>'Dreifachverglasung laut Eigentümer'],
            ['component'=>'Dach','year'=>'','details'=>'Jahr nicht bekannt'],
            ['component'=>'Heizung','year'=>'','details'=>''],
        ]];
        $result = (new PropertyFormProcessor())->process(new Request([], $input));
        self::assertSame([], $result['errors']);
        self::assertCount(2, $result['data']['modernisations']);
        self::assertSame(2021, $result['data']['modernisations'][0]['year']);
        self::assertNull($result['data']['modernisations'][1]['year']);
        $cleared = (new PropertyFormProcessor())->process(new Request([], $this->minimal()+['modernisations_present'=>'1']), ['modernisations'=>$result['data']['modernisations']]);
        self::assertSame([], $cleared['data']['modernisations']);
    }

    public function testExcessModernisationRowsAndLongNotesAreRejected(): void
    {
        $input = $this->minimal() + ['modernisations'=>array_fill(0,13,['component'=>'Fenster','year'=>'2020','details'=>'Test']),'notes'=>str_repeat('x',5001)];
        $result = (new PropertyFormProcessor())->process(new Request([], $input));
        self::assertGreaterThanOrEqual(2, count($result['errors']));
    }

    public function testDatesAreCheckedForRealCalendarDatesAndOrdering(): void
    {
        $input = $this->minimal() + ['energyCreatedAt'=>'2026-10-20','energyValidUntil'=>'2020-10-20'];
        $result = (new PropertyFormProcessor())->process(new Request([], $input));
        self::assertContains('Die Gültigkeit des Energieausweises darf nicht vor dem Ausstellungsdatum enden.', $result['errors']);
    }

    public function testCheckboxPresenceCanIntentionallyClearAValue(): void
    {
        $result = (new PropertyFormProcessor())->process(new Request([], $this->minimal()+['listedMonument'=>'0','negotiable'=>'on','readyForReview'=>'1']), ['listedMonument'=>true,'leasehold'=>true]);
        self::assertSame([], $result['errors']);
        self::assertFalse($result['data']['listedMonument']);
        self::assertTrue($result['data']['leasehold']);
        self::assertTrue($result['data']['negotiable']);
        self::assertTrue($result['data']['readyForReview']);
    }

    public function testFormHasOnlyTheFiveGroupedDataSectionsAndRequiredFieldsAreVisible(): void
    {
        $sections = PropertyFormSchema::sections();
        self::assertSame(['start','base','equipment','condition','pricing'], array_keys($sections));
        foreach ($sections as $section) {
            self::assertNotEmpty($section['title']);
            foreach ($section['fields'] as $field) {
                self::assertNotEmpty($field['label']);
                self::assertNotEmpty($field['group']);
                self::assertArrayHasKey('help', $field);
            }
        }
        self::assertSame('basic',$sections['start']['fields']['city']['group']);
    }
}
