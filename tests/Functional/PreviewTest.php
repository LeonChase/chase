<?php
namespace App\Tests\Functional;

use App\Entity\{ContentPost, Lead, User, UserPreference};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};

final class PreviewTest extends WebTestCase
{
    private KernelBrowser $browser;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->browser = self::createClient();
        $em = $this->em();
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        foreach (['preview-agent', 'preview-peer'] as $id) {
            $user = new User();
            $user->id = $id; $user->tenantId = 'preview-test'; $user->name = 'Synthetischer Demo-Nutzer';
            $user->email = $id.'@example.test'; $user->region = 'Demo-Region';
            $user->password = password_hash('Only-for-isolated-preview-tests!', PASSWORD_BCRYPT, ['cost' => 4]);
            $em->persist($user);
        }
        foreach ([['pdf-own', 'preview-agent', 'preview-test'], ['pdf-foreign', 'preview-peer', 'preview-test'], ['pdf-cross', 'preview-agent', 'other-test']] as [$id, $owner, $tenant]) {
            $post = new ContentPost(['title' => 'Synthetisches Exposé äöüß (Demo)', 'content' => "83 m² · 3 Zimmer · Balkon\nPreis: Angabe fehlt.\n<img src=\"https://example.test/never-load.png\"><script>throw new Error('must never execute')</script>", 'version' => 2, 'status' => 'Entwurf'], $owner, $tenant);
            $post->id = $id; $em->persist($post);
        }
        $em->flush();
    }

    private function em(): EntityManagerInterface { return self::getContainer()->get(EntityManagerInterface::class); }
    private function auth(string $id = 'preview-agent'): void { $this->browser->loginUser($this->em()->find(User::class, $id)); }
    private function token(): string
    {
        $crawler = $this->browser->request('GET', '/marketing/landing-preview');
        self::assertResponseIsSuccessful();
        return (string)$crawler->filter('meta[name="portal-csrf"]')->attr('content');
    }

    public function testAnonymousPdfAndPreviewRequestsRequireLogin(): void
    {
        foreach (['/studio/content/pdf-own/pdf', '/marketing/landing-preview'] as $url) {
            $this->browser->request('GET', $url);
            self::assertResponseRedirects('http://localhost/login');
        }
    }

    public function testActualPrivatePdfIsGeneratedWithDemoFilename(): void
    {
        $this->auth();
        $this->browser->request('GET', '/studio/content/pdf-own/pdf');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString('private', $this->browser->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', $this->browser->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('CHASE-DEMO-MUSTER-pdf-own.pdf', $this->browser->getResponse()->headers->get('Content-Disposition'));
        $bytes = $this->browser->getResponse()->getContent();
        self::assertStringStartsWith('%PDF-1.', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
        self::assertGreaterThan(4000, strlen($bytes));
    }

    public function testPdfNeverExposesAnotherOwnerOrTenant(): void
    {
        $this->auth();
        foreach (['pdf-foreign', 'pdf-cross', 'not-existing'] as $id) {
            $this->browser->request('GET', '/studio/content/'.$id.'/pdf');
            self::assertResponseStatusCodeSame(404);
            self::assertStringNotContainsString('%PDF-', $this->browser->getResponse()->getContent());
            self::assertStringNotContainsString('83 m²', $this->browser->getResponse()->getContent());
        }
    }

    public function testAllFourPreviewTypesHaveNoActiveContactInputsOrSubmissionForm(): void
    {
        $this->auth();
        foreach (['valuation', 'callback', 'guide', 'buyer'] as $type) {
            $this->browser->request('GET', '/marketing/landing-preview?type='.$type);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('#landing-preview', 'VORSCHAU · NICHT LIVE');
            self::assertSelectorCount(0, '#landing-preview form');
            self::assertSelectorCount(0, '#landing-preview input:not([disabled])');
            self::assertSelectorExists('#landing-preview button[disabled]');
            self::assertSelectorExists('#preview-editor button[type="submit"]');
        }
        self::assertSame(0, $this->em()->getRepository(Lead::class)->count([]));
    }

    public function testPreviewSaveRequiresCsrfAndValidBoundedValues(): void
    {
        $this->auth();
        $payload = ['type' => 'valuation', 'title' => 'Eigene Demo-Vorschau', 'cta' => 'Demo ansehen', 'intro' => 'Lokal gespeicherter Beispieltext.'];
        $this->browser->request('POST', '/marketing/landing-preview', $payload);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->em()->getRepository(UserPreference::class)->count([]));
        $token = $this->token();
        foreach ([['title' => str_repeat('X', 181)], ['cta' => str_repeat('X', 81)], ['intro' => str_repeat('X', 2001)], ['type' => 'unsupported']] as $change) {
            $this->browser->request('POST', '/marketing/landing-preview', array_replace($payload, ['_token' => $token], $change));
            self::assertResponseStatusCodeSame(422);
            self::assertSame(0, $this->em()->getRepository(UserPreference::class)->count([]));
        }
    }

    public function testPrivatePreviewPersistsReloadsEscapesAndUpdatesWithoutCreatingLeads(): void
    {
        $this->auth();
        $token = $this->token();
        $title = '<img src=x onerror=alert(1)> Demo-Titel';
        $payload = ['_token' => $token, 'type' => 'callback', 'title' => $title, 'cta' => 'Demo-Rückruf', 'intro' => 'Synthetische Beschreibung.', 'ownerId' => 'preview-peer', 'tenantId' => 'other-test'];
        $this->browser->request('POST', '/marketing/landing-preview', $payload);
        self::assertResponseStatusCodeSame(302);
        $url = $this->browser->getResponse()->headers->get('Location');
        $draft = $this->em()->getRepository(UserPreference::class)->findOneBy(['ownerId' => 'preview-agent']);
        self::assertNotNull($draft);
        $id = $draft->id;
        self::assertSame('preview-test', $draft->tenantId);
        self::assertFalse($draft->data['isLive']);
        self::assertFalse($draft->data['acceptsLeads']);
        $this->browser->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertInputValueSame('title', $title);
        self::assertSelectorTextSame('#live-cta', 'Demo-Rückruf');
        self::assertSelectorCount(0, '#live-title img');
        self::assertSelectorTextContains('#live-title', $title);
        $payload['title'] = 'Überarbeitete Demo'; $payload['draft'] = $id;
        $this->browser->request('POST', '/marketing/landing-preview', $payload);
        self::assertResponseRedirects('/marketing/landing-preview?draft='.$id);
        self::assertSame(1, $this->em()->getRepository(UserPreference::class)->count([]));
        $this->browser->request('GET', '/marketing/landing-preview?type=callback');
        self::assertInputValueSame('title', 'Überarbeitete Demo');
        self::assertSame(0, $this->em()->getRepository(Lead::class)->count([]));
    }

    public function testDraftIdCannotReadOrOverwriteAnotherOwnersOrTenantsPreview(): void
    {
        $own = new UserPreference(['kind' => 'landing_preview', 'type' => 'valuation', 'title' => 'Geschützter Demo-Entwurf', 'cta' => 'Demo', 'intro' => 'Privat.'], 'preview-agent', 'preview-test');
        $cross = new UserPreference(['kind' => 'landing_preview', 'type' => 'buyer', 'title' => 'Anderer Mandant', 'cta' => 'Demo', 'intro' => 'Privat.'], 'preview-peer', 'other-test');
        $this->em()->persist($own); $this->em()->persist($cross); $this->em()->flush();
        $this->auth('preview-peer');
        $token = $this->token();
        foreach ([$own->id, $cross->id] as $id) {
            $this->browser->request('GET', '/marketing/landing-preview?draft='.$id);
            self::assertResponseStatusCodeSame(404);
            $this->browser->request('POST', '/marketing/landing-preview', ['_token' => $token, 'draft' => $id, 'type' => 'valuation', 'title' => 'Verbotene Änderung', 'cta' => 'Demo', 'intro' => '']);
            self::assertResponseStatusCodeSame(404);
        }
        self::assertSame('Geschützter Demo-Entwurf', $this->em()->find(UserPreference::class, $own->id)->title);
        self::assertSame('Anderer Mandant', $this->em()->find(UserPreference::class, $cross->id)->title);
    }
}
