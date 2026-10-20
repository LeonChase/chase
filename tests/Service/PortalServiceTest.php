<?php
namespace App\Tests\Service;

use App\Entity\{AcademyArticle, ContentPost, CreditLedger, Document, Lead, NewsPost, Property, TeamMembership, UsageEvent, User, UserPreference};
use App\Integration\DemoValuationProvider;
use App\Service\{DemoTextProvider, MockOnOfficeAdapter, PortalStore, UsageMeter};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PortalServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PortalStore $store;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $this->store = self::getContainer()->get(PortalStore::class);
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }

    private function user(string $id, array $roles = ['ROLE_AGENT'], string $tenant = 'test-tenant'): User
    {
        $user = new User();
        $user->id = $id;
        $user->tenantId = $tenant;
        $user->name = 'Synthetischer Demo-Nutzer '.$id;
        $user->email = $id.'@example.test';
        $user->region = 'Demo-Region';
        $user->roles = $roles;
        $user->password = password_hash('Only-used-in-isolated-tests!', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->store->save($user);
        return $user;
    }

    public function testOwnerTenantExplicitTeamAndAdminBoundaries(): void
    {
        $agent = $this->user('u-001');
        $peer = $this->user('peer'); // Same region intentionally grants no access.
        $manager = $this->user('manager', ['ROLE_REGION_MANAGER']);
        $admin = $this->user('admin', ['ROLE_ADMIN']);
        $own = new Property(['title'=>'Eigenes synthetisches Objekt'], $agent->id, $agent->tenantId);
        $foreign = new Property(['title'=>'Fremdes synthetisches Objekt'], $peer->id, $peer->tenantId);
        $crossTenant = new Property(['title'=>'Anderer Mandant'], $agent->id, 'another-tenant');
        foreach ([$own, $foreign, $crossTenant] as $record) $this->store->save($record);
        self::assertSame([$own->id], array_column($this->store->visible(Property::class, $agent), 'id'));
        self::assertFalse($this->store->canAccess($foreign, $agent));
        self::assertFalse($this->store->canAccess($crossTenant, $agent));
        self::assertSame([], $this->store->visible(Property::class, $admin));
        self::assertSame([], $this->store->visible(Property::class, $manager));
        $grant = new TeamMembership(['managerId'=>$manager->id, 'memberId'=>$agent->id, 'active'=>true], $manager->id, $agent->tenantId);
        $this->store->save($grant);
        self::assertTrue($this->store->canAccess($own, $manager));
        self::assertFalse($this->store->canAccess($foreign, $manager));
        self::assertFalse($this->store->canAccess($crossTenant, $manager));
        $grant->data['active'] = false;
        $this->store->save($grant);
        self::assertFalse($this->store->canAccess($own, $manager));
    }

    public function testSharedDocumentsAndPersonalUsageHaveDistinctAccessRules(): void
    {
        $agent = $this->user('agent');
        $manager = $this->user('manager', ['ROLE_REGION_MANAGER']);
        $admin = $this->user('admin', ['ROLE_ADMIN']);
        $this->store->save(new TeamMembership(['memberId'=>$agent->id], $manager->id, $agent->tenantId));
        $document = new Document(['title'=>'Internes Muster', 'audience'=>'Admin'], null, $agent->tenantId);
        self::assertFalse($this->store->canAccess($document, $agent));
        self::assertFalse($this->store->canAccess($document, $manager));
        self::assertTrue($this->store->canAccess($document, $admin));
        foreach ([new UsageEvent([], $agent->id, $agent->tenantId), new CreditLedger([], $agent->id, $agent->tenantId), new UserPreference([], $agent->id, $agent->tenantId)] as $record) {
            $this->store->save($record);
            self::assertTrue($this->store->canAccess($record, $agent));
            self::assertFalse($this->store->canAccess($record, $manager));
            self::assertFalse($this->store->canAccess($record, $admin));
        }
        $agent->active = false;
        self::assertFalse($this->store->canAccess(new AcademyArticle([], null, $agent->tenantId), $agent));
    }

    public static function textVariants(): iterable
    {
        foreach (array_keys(DemoTextProvider::TOOLS) as $tool) foreach (array_keys(DemoTextProvider::TONES) as $tone) yield $tool.' '.$tone => [$tool, $tone];
    }

    #[DataProvider('textVariants')]
    public function testOfflineTextIsDeterministicAndDoesNotInventPropertyFacts(string $tool, string $tone): void
    {
        $provider = new DemoTextProvider();
        $facts = ['title'=>'Synthetisches Testobjekt', 'city'=>'Demo-Ort', 'areaSqm'=>82.5, 'rooms'=>3, 'propertyType'=>'Eigentumswohnung', 'features'=>['Balkon']];
        $first = $provider->generate($tool, $facts, $tone);
        self::assertSame($first, $provider->generate($tool, $facts, $tone));
        self::assertSame('Regelbasierte Offline-Demo', $first['mode']);
        self::assertNotEmpty($first['headline']);
        self::assertNotEmpty($first['text']);
        self::assertNotEmpty($first['checklist']);
        foreach (['Wärmepumpe', 'A+', '500 Meter', 'Bahnhof in', '245.000', '2024 erbaut'] as $invented) self::assertStringNotContainsString($invented, $first['text']);
        self::assertContains('Energiekennwerte: Angabe fehlt.', $first['checklist']);
        if ($tool === 'expose') {
            self::assertStringContainsString('82,5 m²', $first['text']);
            self::assertStringContainsString('Balkon', $first['text']);
            self::assertStringContainsString('Preis: Angabe fehlt', $first['text']);
        }
    }

    public function testMissingLocationFactsRemainExplicitlyUnknownAndMarkupIsRemoved(): void
    {
        $provider = new DemoTextProvider();
        $result = $provider->generate('lage', ['city'=>'<script>alert(1)</script>Demo-Ort']);
        self::assertStringNotContainsString('<script>', $result['text']);
        self::assertStringContainsString('verifizierte Lagefakten: Angabe fehlt', $result['text']);
        self::assertStringContainsString('Entfernungen und Verkehrsanbindung: Angabe fehlt', $result['text']);
        $verified = $provider->generate('lage', ['city'=>'Demo-Ort', 'verifiedLocation'=>'Vom Makler geprüft: Park gegenüber.']);
        self::assertStringContainsString('Vom Makler geprüft: Park gegenüber.', $verified['text']);
    }

    public function testUnknownTextToolIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new DemoTextProvider())->generate('unapproved-api', []);
    }

    public function testMockImportIsIdempotentAndPreservesPortalOwnedFields(): void
    {
        $user = $this->user('u-001');
        $adapter = self::getContainer()->get(MockOnOfficeAdapter::class);
        $first = $adapter->syncProperties($user);
        self::assertSame(['created'=>1, 'updated'=>0, 'skipped'=>0], $first);
        $property = $this->store->visible(Property::class, $user)[0];
        self::assertSame('DEMO-OF-5001', $property->data['externalId']);
        $property->data['notes'] = 'Nur lokale Demo-Notiz';
        $property->data['checklist'] = ['Eigentümerfreigabe dokumentiert'];
        $property->data['priceEur'] = 1;
        $this->store->save($property);
        self::assertSame(['created'=>0, 'updated'=>1, 'skipped'=>0], $adapter->syncProperties($user));
        self::assertCount(1, $this->store->visible(Property::class, $user));
        self::assertSame('Nur lokale Demo-Notiz', $property->data['notes']);
        self::assertSame(['Eigentümerfreigabe dokumentiert'], $property->data['checklist']);
        self::assertSame(245000, $property->data['priceEur']);
        self::assertSame($property->id, $this->store->visible(Property::class, $user)[0]->id);
        self::assertSame([], $adapter->listProperties($this->user('admin', ['ROLE_ADMIN'])));
    }

    public function testImportNeverTransfersAnotherOwnersObjectOrTouchesAnotherTenant(): void
    {
        $user = $this->user('u-001');
        $wrongOwner = new Property(['title'=>'Geschütztes Objekt','externalSystem'=>'onoffice','externalId'=>'DEMO-OF-5001'], 'other-owner', $user->tenantId);
        $otherTenant = new Property(['title'=>'Anderer Mandant','externalSystem'=>'onoffice','externalId'=>'DEMO-OF-5001'], $user->id, 'other-tenant');
        $this->store->save($wrongOwner);
        $this->store->save($otherTenant);
        $result = self::getContainer()->get(MockOnOfficeAdapter::class)->syncProperties($user);
        self::assertSame(['created'=>0,'updated'=>0,'skipped'=>1], $result);
        self::assertSame('other-owner', $wrongOwner->ownerId);
        self::assertSame('Geschütztes Objekt', $wrongOwner->title);
        self::assertSame('Anderer Mandant', $otherTenant->title);
    }

    public function testCreditConsumptionAndGeneratedOutputCommitAtomically(): void
    {
        $user = $this->user('agent');
        $user->budgetCredits = 1;
        $this->store->save($user);
        $meter = self::getContainer()->get(UsageMeter::class);
        try {
            $meter->consume($user, 'expose', function () use ($user): void {
                $this->store->save(new ContentPost(['title'=>'Darf nicht überleben'], $user->id, $user->tenantId));
                throw new \RuntimeException('Simulierter Generatorfehler');
            });
            self::fail('Generator failure must be propagated.');
        } catch (\RuntimeException $error) { self::assertSame('Simulierter Generatorfehler', $error->getMessage()); }
        $user = $this->em->find(User::class, $user->id);
        self::assertSame(1, $meter->available($user));
        foreach ([ContentPost::class, CreditLedger::class, UsageEvent::class] as $class) self::assertSame(0, $this->em->getRepository($class)->count([]));
        $post = $meter->consume($user, 'social', function () use ($user): ContentPost {
            $post = new ContentPost(['title'=>'Erfolgreicher Demo-Entwurf'], $user->id, $user->tenantId);
            $this->store->save($post);
            return $post;
        });
        self::assertInstanceOf(ContentPost::class, $post);
        self::assertSame(0, $meter->available($user));
        self::assertSame(1, $this->em->getRepository(ContentPost::class)->count([]));
        self::assertSame(-1, $this->store->all(CreditLedger::class)[0]->data['delta']);
        self::assertSame('completed', $this->store->all(UsageEvent::class)[0]->data['status']);
        self::assertSame(0, $this->store->all(UsageEvent::class)[0]->data['costEur']);
        $called = false;
        try { $meter->consume($user, 'expose', function () use (&$called): void { $called=true; }); self::fail('Budget must be enforced on server.'); }
        catch (\DomainException) { self::assertFalse($called); }
        self::assertSame(1, $this->em->getRepository(UsageEvent::class)->count([]));
        self::assertSame(1, $this->em->getRepository(CreditLedger::class)->count([]));
    }

    public function testValuationUsesOnlyEnteredRentAndFactor(): void
    {
        $result = (new DemoValuationProvider())->calculate(['monthlyRent'=>1000, 'factor'=>20]);
        self::assertEquals(12000, $result['annualRent']);
        self::assertSame(240000.0, $result['result']);
        self::assertStringContainsString('Keine Markt-', $result['disclaimer']);
        $this->expectException(\InvalidArgumentException::class);
        (new DemoValuationProvider())->calculate(['monthlyRent'=>1000, 'factor'=>-1]);
    }
}
