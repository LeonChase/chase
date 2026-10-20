<?php

namespace App\Tests\Functional;

use App\Entity\{AcademyArticle, AdvertisingProduct, Document, NewsPost, User, UserPreference};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Real session/CSRF requests against an isolated synthetic database. */
final class ContentTest extends WebTestCase
{
    private KernelBrowser $browser;
    private string $userId = 'content-agent';
    private const PASSWORD = 'Only-for-isolated-content-tests!';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $em = $this->em();
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        foreach (['content-agent', 'content-peer'] as $id) {
            $user = new User();
            $user->id = $id;
            $user->tenantId = 'content-test';
            $user->name = 'Synthetischer Testmakler '.$id;
            $user->email = $id.'@example.test';
            $user->region = 'Demo-Testregion';
            $user->password = password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);
            $em->persist($user);
        }
        $em->flush();
        $this->login($this->userId);
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function login(string $id): void
    {
        $this->browser->loginUser($this->em()->find(User::class, $id));
    }

    private function persist(object ...$records): void
    {
        foreach ($records as $record) $this->em()->persist($record);
        $this->em()->flush();
    }

    private function token(string $page = '/profile'): string
    {
        return (string) $this->browser->request('GET', $page)->filter('meta[name="portal-csrf"]')->attr('content');
    }

    public function testNewsReadStateChangesTheBadgeAndRemainsPersonal(): void
    {
        $first = new NewsPost(['title' => 'Synthetische Nachricht A', 'status' => 'Veröffentlicht', 'body' => 'Lokaler Test.'], null, 'content-test');
        $second = new NewsPost(['title' => 'Synthetische Nachricht B', 'status' => 'Veröffentlicht', 'body' => 'Lokaler Test.'], null, 'content-test');
        $unpublished = new NewsPost(['title' => 'Nicht veröffentlichter Testentwurf', 'status' => 'Entwurf'], null, 'content-test');
        $this->persist($first, $second, $unpublished);
        $token = $this->token('/news');
        self::assertSelectorTextContains('.nav-count', '2');
        self::assertSelectorNotExists('a[href="/news/'.$unpublished->id.'"]');
        $this->browser->request('POST', '/preferences/read/news/'.$first->id, ['_token' => $token]);
        self::assertResponseRedirects('/news/'.$first->id);
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.nav-count', '1');
        self::assertSelectorTextContains('article', 'Gelesen');
        $this->login('content-peer');
        $this->browser->request('GET', '/news');
        self::assertSelectorTextContains('.nav-count', '2');
        $this->browser->request('GET', '/news/'.$unpublished->id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDocumentSearchAndDownloadEnforceAudienceAndTenantRules(): void
    {
        $allowed = new Document(['title' => 'Öffentliches Testmuster', 'content' => 'Nur ein lokales Demo-Muster.'], null, 'content-test');
        $restricted = new Document(['title' => 'SECRET interner Admininhalt', 'audience' => 'Admin', 'content' => 'SECRET VERTRAULICH'], null, 'content-test');
        $foreign = new Document(['title' => 'SECRET anderer Mandant', 'content' => 'SECRET MANDANT'], null, 'other-test-tenant');
        $this->persist($allowed, $restricted, $foreign);
        $this->browser->request('GET', '/documents/'.$allowed->id.'/download');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertStringContainsString('NICHT RECHTSVERBINDLICH', $this->browser->getResponse()->getContent());
        self::assertStringContainsString('attachment;', (string) $this->browser->getResponse()->headers->get('Content-Disposition'));
        foreach ([$restricted, $foreign] as $forbidden) {
            $this->browser->request('GET', '/documents/'.$forbidden->id);
            self::assertResponseStatusCodeSame(404);
            $this->browser->request('GET', '/documents/'.$forbidden->id.'/download');
            self::assertResponseStatusCodeSame(404);
            self::assertStringNotContainsString('SECRET VERTRAULICH', $this->browser->getResponse()->getContent());
        }
        $this->browser->request('GET', '/search?q=SECRET');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('SECRET interner Admininhalt', $this->browser->getResponse()->getContent());
        self::assertStringNotContainsString('SECRET anderer Mandant', $this->browser->getResponse()->getContent());
    }

    public function testFavoritesAndAcademyProgressArePersistedForTheCurrentAccount(): void
    {
        $document = new Document(['title' => 'Gemerkte Demo-Unterlage'], null, 'content-test');
        $article = new AcademyArticle(['title' => 'Demo-Lerneinheit', 'content' => [['Grundlage', 'Synthetischer Lerntext.']]], null, 'content-test');
        $this->persist($document, $article);
        $token = $this->token();
        $this->browser->request('POST', '/preferences/favorite/document/'.$document->id, ['_token' => $token]);
        self::assertResponseStatusCodeSame(302);
        $this->browser->request('GET', '/documents?favorites=1');
        self::assertSelectorTextContains('article', 'Gemerkte Demo-Unterlage');
        $this->browser->request('POST', '/preferences/progress/academy/'.$article->id, ['_token' => $token]);
        self::assertResponseStatusCodeSame(302);
        $this->browser->request('GET', '/academy');
        self::assertStringContainsString('1 von 1 abgeschlossen', $this->browser->getResponse()->getContent());
        $this->login('content-peer');
        $this->browser->request('GET', '/documents?favorites=1');
        self::assertSelectorNotExists('article');
        $this->browser->request('GET', '/academy');
        self::assertStringContainsString('0 von 1 abgeschlossen', $this->browser->getResponse()->getContent());
    }

    public function testCatalogDraftExportsActualQuantitiesAndCannotBeDownloadedByAnotherUser(): void
    {
        $product = new AdvertisingProduct(['title' => 'Synthetische Testkarten', 'unit' => '500 Stück'], null, 'content-test');
        $this->persist($product);
        $token = $this->token('/advertising');
        $this->browser->request('POST', '/advertising/request', ['_token' => $token, 'quantities' => [$product->id => 2], 'note' => 'Nur lokaler Test.']);
        self::assertResponseRedirects('/advertising?tab=requests');
        $draft = $this->em()->getRepository(UserPreference::class)->findOneBy(['ownerId' => $this->userId]);
        self::assertNotNull($draft);
        self::assertSame('advertising_request', $draft->data['kind']);
        self::assertFalse($draft->data['sent']);
        $this->browser->request('GET', '/advertising/request/'.$draft->id.'/download');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('2 × Synthetische Testkarten (500 Stück)', $this->browser->getResponse()->getContent());
        $this->login('content-peer');
        $this->browser->request('GET', '/advertising/request/'.$draft->id.'/download');
        self::assertResponseStatusCodeSame(404);
        $this->browser->request('POST', '/advertising/request/'.$draft->id.'/delete', ['_token' => $this->token()]);
        self::assertResponseStatusCodeSame(404);
        self::assertNotNull($this->em()->find(UserPreference::class, $draft->id));
    }

    public function testThemePersistsInServerHtmlAndRequiresCsrf(): void
    {
        $token = $this->token();
        $this->browser->request('POST', '/profile/theme', ['theme' => 'dark']);
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('POST', '/profile/theme', ['_token' => $token, 'theme' => 'dark']);
        self::assertResponseRedirects('/profile');
        $this->browser->request('GET', '/profile');
        self::assertSelectorExists('html[data-theme="dark"]');
        self::assertSame('dark', $this->em()->find(User::class, $this->userId)->preferences['theme']);
        $this->browser->request('POST', '/profile/theme', ['_token' => $token, 'theme' => 'unexpected']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testPasswordChangeRequiresCurrentPasswordAndStoresOnlyAHash(): void
    {
        $token = $this->token();
        $original = $this->em()->find(User::class, $this->userId)->password;
        $next = 'New-password-only-for-local-tests!';
        $payload = ['_token' => $token, 'name' => 'Geänderter Testname', 'current_password' => 'wrong', 'new_password' => $next, 'password_confirmation' => $next];
        $this->browser->request('POST', '/profile', $payload);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', 'bisherige Passwort ist nicht korrekt');
        self::assertSame($original, $this->em()->find(User::class, $this->userId)->password);
        $payload['current_password'] = self::PASSWORD;
        $this->browser->request('POST', '/profile', $payload);
        self::assertResponseRedirects('/profile');
        $user = $this->em()->find(User::class, $this->userId);
        self::assertNotSame($next, $user->password);
        self::assertNotSame($original, $user->password);
        self::assertTrue(password_verify($next, $user->password));
        self::assertSame('Geänderter Testname', $user->name);
    }
}
