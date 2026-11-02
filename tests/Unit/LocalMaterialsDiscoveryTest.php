<?php
namespace App\Tests\Unit;

use App\Service\LocalMaterialsDiscovery;
use PHPUnit\Framework\TestCase;

final class LocalMaterialsDiscoveryTest extends TestCase
{
    private string $base;
    private string|false $override;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir().'/chase-discovery-'.bin2hex(random_bytes(8));
        mkdir($this->base.'/project/var/materials/source', 0700, true); mkdir($this->base.'/current/Downloads', 0700, true); mkdir($this->base.'/other/Downloads', 0700, true);
        $this->override = getenv('CHASE_MATERIALS_ZIP'); putenv('CHASE_MATERIALS_ZIP');
    }

    protected function tearDown(): void
    {
        $this->override === false ? putenv('CHASE_MATERIALS_ZIP') : putenv('CHASE_MATERIALS_ZIP='.$this->override);
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $path) { if ($path->isDir() && !$path->isLink()) rmdir($path->getPathname()); else unlink($path->getPathname()); } rmdir($this->base);
    }

    public function testOnlyCurrentDownloadsAreSearchedAndNewestDuplicateIsSelected(): void
    {
        $other = $this->base.'/other/Downloads/Unterlagen_Co.zip'; file_put_contents($other, 'not inspected');
        $discovery = new LocalMaterialsDiscovery($this->base.'/project');
        self::assertNull($discovery->find([$this->base.'/current/Downloads']));
        $first = $this->base.'/current/Downloads/Unterlagen_Co.zip'; $second = $this->base.'/current/Downloads/Unterlagen_Co (1).zip';
        file_put_contents($first, 'older'); touch($first, 1000000000); file_put_contents($second, 'newer'); touch($second, 1000000010);
        self::assertSame($second, $discovery->find([$this->base.'/current/Downloads']));
        $private = $this->base.'/project/var/materials/Unterlagen_Co.zip'; file_put_contents($private, 'private');
        self::assertSame($private, $discovery->find([$this->base.'/current/Downloads']));
    }

    public function testNewArchiveNameAndStandaloneCatalogAreDetectedInCurrentDownloads(): void
    {
        $old = $this->base.'/current/Downloads/Unterlagen_Co.zip'; $new = $this->base.'/current/Downloads/Unterlagen.zip';
        file_put_contents($old, 'older'); touch($old, 1000000000); file_put_contents($new, 'newer'); touch($new, 1000000020);
        $catalog = $this->base.'/current/Downloads/Werbekatalog.pdf'; file_put_contents($catalog, 'synthetic');
        $discovery = new LocalMaterialsDiscovery($this->base.'/project');
        self::assertSame($new, $discovery->find([$this->base.'/current/Downloads']));
        self::assertSame($catalog, $discovery->findCatalog([$this->base.'/current/Downloads']));
        self::assertNull($discovery->findCatalog([$this->base.'/other/Downloads']));
    }

    public function testUnrelatedArchiveAndSymlinkAreIgnored(): void
    {
        file_put_contents($this->base.'/current/Downloads/Other.zip', 'other');
        $target = $this->base.'/other/Downloads/Unterlagen_Co.zip'; file_put_contents($target, 'other');
        if (function_exists('symlink')) symlink($target, $this->base.'/current/Downloads/Unterlagen_Co.zip');
        self::assertNull((new LocalMaterialsDiscovery($this->base.'/project'))->find([$this->base.'/current/Downloads']));
    }

    public function testPrivateSourcePackagingIsReproducibleAndKeepsOriginalBytes(): void
    {
        if (!class_exists(\ZipArchive::class)) self::markTestSkipped('PHP zip required for local material archives.');
        $directory = $this->base.'/project/var/materials/source/4 - Verkauf'; mkdir($directory);
        file_put_contents($directory.'/Synthetischer Test.txt', 'Private Testvorlage.');
        $discovery = new LocalMaterialsDiscovery($this->base.'/project');
        $archive = $discovery->archiveFromPrivateSource(); self::assertNotNull($archive); $hash = hash_file('sha256', $archive);
        $zip = new \ZipArchive(); $zip->open($archive); self::assertSame('Private Testvorlage.', $zip->getFromName('4 - Verkauf/Synthetischer Test.txt')); $zip->close();
        self::assertSame($archive, $discovery->archiveFromPrivateSource()); self::assertSame($hash, hash_file('sha256', $archive));
    }

    public function testMissingAndUnrelatedPrivateSourceDoesNotInventAnArchive(): void
    {
        $directory = $this->base.'/project/var/materials/source/Unrelated'; mkdir($directory); file_put_contents($directory.'/other.txt', 'Ignore.');
        self::assertNull((new LocalMaterialsDiscovery($this->base.'/project'))->archiveFromPrivateSource());
        self::assertFileDoesNotExist($this->base.'/project/var/materials/auto-source.zip');
    }

    public function testUnsafePrivateSourceFailsBeforeArchiveCreation(): void
    {
        if (!function_exists('symlink')) self::markTestSkipped('Symlink support unavailable.');
        $directory = $this->base.'/project/var/materials/source/4 - Verkauf'; mkdir($directory);
        $other = $this->base.'/other/Downloads/private.txt'; file_put_contents($other, 'Not part of source.'); symlink($other, $directory.'/link.txt');
        try { (new LocalMaterialsDiscovery($this->base.'/project'))->archiveFromPrivateSource(); self::fail('Symlink source must fail.'); }
        catch (\RuntimeException) { self::assertFileDoesNotExist($this->base.'/project/var/materials/auto-source.zip'); }
    }

    public function testWindowsProfileParsingIgnoresUncWarningsWithoutEnumeratingOtherProfiles(): void
    {
        $output = "'\\\\wsl.localhost\\Ubuntu\\home\\leon'\r\nUNC paths are not supported. Defaulting to Windows directory.\r\nC:\\Users\\Leön Beispiel\r\n";
        self::assertSame('C:\\Users\\Leön Beispiel', LocalMaterialsDiscovery::windowsProfileFromOutput($output));
        self::assertNull(LocalMaterialsDiscovery::windowsProfileFromOutput('%USERPROFILE%'));
        self::assertNull(LocalMaterialsDiscovery::windowsProfileFromOutput('UNC paths are not supported.'));
        self::assertNull(LocalMaterialsDiscovery::windowsProfileFromOutput('\\\\server\\profile'));
    }
}
