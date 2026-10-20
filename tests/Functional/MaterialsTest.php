<?php

namespace App\Tests\Functional;

use App\Entity\{Document, User};
use App\Service\{Lexicon, MaterialsImporter};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MaterialsTest extends WebTestCase
{
    private KernelBrowser $browser;
    private string $tenant;
    private string $temporary;
    private string $projectDir;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $em = $this->em(); $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($em); $schema->dropSchema($metadata); $schema->createSchema($metadata);
        $this->tenant = 'materials-test-'.bin2hex(random_bytes(5));
        $this->temporary = sys_get_temp_dir().'/chase-materials-'.bin2hex(random_bytes(6)); mkdir($this->temporary, 0700);
        $this->projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $user = new User(); $user->id = 'materials-agent'; $user->tenantId = $this->tenant;
        $user->name = 'Synthetischer Unterlagen-Test'; $user->email = 'materials-test@example.test';
        $user->password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 4]);
        $em->persist($user); $em->flush(); $this->browser->loginUser($user);
    }

    protected function tearDown(): void
    {
        foreach ([$this->temporary ?? '', isset($this->projectDir) ? $this->projectDir.'/var/materials/library/'.substr(hash('sha256', $this->tenant), 0, 16) : ''] as $directory) {
            if (!$directory || !is_dir($directory)) continue;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $path) { if ($path->isDir() && !$path->isLink()) rmdir($path->getPathname()); else unlink($path->getPathname()); }
            rmdir($directory);
        }
        parent::tearDown();
    }

    private function em(): EntityManagerInterface { return self::getContainer()->get(EntityManagerInterface::class); }
    private function importer(): MaterialsImporter { return new MaterialsImporter($this->em(), $this->projectDir); }

    private function zip(array $files): string
    {
        $path = $this->temporary.'/test-'.bin2hex(random_bytes(4)).'.zip';
        $zip = new \ZipArchive(); $zip->open($path, \ZipArchive::CREATE | \ZipArchive::EXCL);
        foreach ($files as $name => $bytes) $zip->addFromString($name, $bytes);
        $zip->close(); return $path;
    }

    public function testOriginalImportIsIdempotentAndPreservesFolderDatesAndBytes(): void
    {
        $source = '4 - Verkauf/Unterordner/Bereitgestellte Vorlage.txt'; $bytes = "Synthetische Testvorlage – kein Rechtsdokument.\n";
        $archive = $this->zip([$source => $bytes, '8 - Links & Tools/Prüflink.url' => "[InternetShortcut]\nURL=https://example.test/fachquelle\n"]);
        $first = $this->importer()->import($archive, $this->tenant);
        self::assertSame(2, $first['created']);
        $id = MaterialsImporter::documentId($this->tenant, $source);
        $document = $this->em()->find(Document::class, $id);
        self::assertSame('Verkauf', $document->data['category']);
        self::assertSame('Unterordner', $document->data['subfolder']);
        self::assertSame($source, $document->data['sourcePath']);
        self::assertFalse($document->data['isDemo']); self::assertTrue($document->data['provided']);
        self::assertSame(hash('sha256', $bytes), $document->data['sourceHash']);
        self::assertSame($bytes, file_get_contents($this->importer()->filePath($document)));
        $uploaded = $document->data['uploadedAt']; $updatedAt = $document->updatedAt;
        $second = $this->importer()->import($archive, $this->tenant);
        self::assertSame(0, $second['created']); self::assertSame(0, $second['updated']); self::assertSame(2, $second['unchanged']);
        self::assertSame($uploaded, $document->data['uploadedAt']); self::assertEquals($updatedAt, $document->updatedAt);
        self::assertSame(2, $this->em()->getRepository(Document::class)->count([]));
        $this->browser->request('GET', '/documents?category=Verkauf&format=TXT&sort=title_asc');
        self::assertResponseIsSuccessful(); self::assertSelectorTextContains('article', 'Bereitgestellte Vorlage');
        self::assertStringContainsString('Hochgeladen', $this->browser->getResponse()->getContent());
        foreach (MaterialsImporter::CATEGORIES as $category) self::assertStringContainsString(htmlspecialchars($category, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $this->browser->getResponse()->getContent());
    }

    public function testOriginalHtmlIsAnAttachmentAndForeignTenantAndCorruptedFilesAreDenied(): void
    {
        $source = '1 - Start/Signatur.html'; $bytes = '<!doctype html><script>window.untrustedOriginal=true</script><p>Synthetischer HTML-Test.</p>';
        $this->importer()->import($this->zip([$source => $bytes]), $this->tenant);
        $id = MaterialsImporter::documentId($this->tenant, $source);
        $this->browser->request('GET', '/documents/'.$id.'/download');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/octet-stream');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertStringStartsWith('attachment;', (string) $this->browser->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('sandbox', (string) $this->browser->getResponse()->headers->get('Content-Security-Policy'));
        $this->browser->request('GET', '/documents/'.$id);
        self::assertResponseIsSuccessful(); self::assertStringNotContainsString('<script>window.untrustedOriginal=true</script>', $this->browser->getResponse()->getContent());
        $user = $this->em()->find(User::class, 'materials-agent'); $user->tenantId = 'another-materials-tenant'; $this->em()->flush();
        $this->browser->loginUser($user); $this->browser->request('GET', '/documents/'.$id.'/download');
        self::assertResponseStatusCodeSame(404);
        $user = $this->em()->find(User::class, 'materials-agent'); $user->tenantId = $this->tenant; $this->em()->flush(); $this->browser->loginUser($user);
        $document = $this->em()->find(Document::class, $id); $path = $this->importer()->filePath($document);
        file_put_contents($path, 'Veränderte Bytes');
        self::assertNull($this->importer()->filePath($document));
        $this->browser->request('GET', '/documents/'.$id.'/download'); self::assertResponseStatusCodeSame(404);
    }

    public static function unsafeNames(): iterable
    {
        yield 'parent traversal' => ['../outside.txt']; yield 'absolute' => ['/tmp/outside.txt'];
        yield 'windows traversal' => ['Start\\..\\outside.txt']; yield 'drive' => ['C:/outside.txt'];
    }

    #[DataProvider('unsafeNames')]
    public function testUnsafeArchivePathsAreRejectedBeforeImport(string $name): void
    {
        $archive = $this->zip(['1 - Start/allowed.txt' => 'Erlaubter synthetischer Text.', $name => 'Nicht extrahieren.']);
        try { $this->importer()->import($archive, $this->tenant); self::fail('Unsafe path must be rejected.'); }
        catch (\InvalidArgumentException) { self::assertSame(0, $this->em()->getRepository(Document::class)->count([])); }
        self::assertDirectoryDoesNotExist($this->projectDir.'/var/materials/library/'.substr(hash('sha256', $this->tenant), 0, 16));
    }

    public function testSymlinksAndCompressionBombsAreRejected(): void
    {
        $path = $this->zip(['1 - Start/symlink.txt' => '/etc/passwd']);
        $zip = new \ZipArchive(); $zip->open($path); $zip->setExternalAttributesName('1 - Start/symlink.txt', \ZipArchive::OPSYS_UNIX, 0120777 << 16); $zip->close();
        foreach ([$path, $this->zip(['1 - Start/bomb.txt' => str_repeat('A', 2 * 1024 * 1024)])] as $archive) {
            try { $this->importer()->import($archive, $this->tenant); self::fail('Unsafe archive must fail.'); }
            catch (\InvalidArgumentException) { self::assertSame(0, $this->em()->getRepository(Document::class)->count([])); }
        }
    }

    public function testAllLexiconTermsRetainSourcePagesAndAreAvailableInGlobalSearch(): void
    {
        $lexicon = self::getContainer()->get(Lexicon::class);
        self::assertCount(441, $lexicon->all()); self::assertSame(21, $lexicon->source()['pageCount']);
        self::assertSame('ddfdaaff491973edc16aa69ad2d49ca2789724dbbe1bc316744aa43860608bf8', $lexicon->source()['sha256']);
        foreach ($lexicon->all() as $entry) {
            self::assertSame($entry, $lexicon->find($entry['id'])); self::assertNotEmpty($entry['definition']);
            self::assertGreaterThanOrEqual(2, $entry['page']); self::assertLessThanOrEqual(21, $entry['page']);
        }
        $matches = $lexicon->search('Erbbaurecht'); self::assertNotEmpty($matches);
        $entry = $matches[0];
        $this->browser->request('GET', '/academy/lexicon?q=Erbbaurecht'); self::assertResponseIsSuccessful();
        self::assertStringContainsString($entry['term'], $this->browser->getResponse()->getContent());
        $this->browser->request('GET', '/academy/lexicon/'.$entry['id']); self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $entry['term']);
        $this->browser->request('GET', '/search?q=Erbbaurecht'); self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/academy/lexicon/'.$entry['id'].'"]');
    }
}
