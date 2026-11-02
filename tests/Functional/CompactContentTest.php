<?php
namespace App\Tests\Functional;

use App\Entity\{AcademyArticle, User};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CompactContentTest extends WebTestCase
{
    private function client(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient(); $em = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $em->getMetadataFactory()->getAllMetadata(); $schema = new SchemaTool($em); $schema->dropSchema($metadata); $schema->createSchema($metadata);
        $user = new User(); $user->id = 'compact-content-agent'; $user->name = 'Synthetischer Testname'; $user->email = 'compact-content@example.test';
        $user->password = password_hash(bin2hex(random_bytes(12)), PASSWORD_BCRYPT, ['cost'=>4]); $em->persist($user); $em->flush(); $client->loginUser($user); return $client;
    }

    public function testOneAcademySearchReturnsArticlesVideosAndLexiconTerms(): void
    {
        $client = $this->client(); $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['article'=>'Ratgeber Erbbaurecht', 'video'=>'Video Erbbaurecht'] as $kind=>$title) { $em->persist(new AcademyArticle(['title'=>$title,'kind'=>$kind,'teaser'=>'Synthetischer Suchtest.'], null)); } $em->flush();
        $client->request('GET','/academy?q=Erbbaurecht'); self::assertResponseIsSuccessful();
        self::assertSelectorCount(1,'.academy-workspace input[type="search"]'); self::assertSelectorExists('.academy-onboarding');
        self::assertSelectorExists('a[aria-label="Immobilienlexikon öffnen"]'); self::assertStringContainsString('Ratgeber Erbbaurecht',$client->getResponse()->getContent());
        self::assertStringContainsString('Video Erbbaurecht',$client->getResponse()->getContent()); self::assertSelectorExists('.academy-results a[href^="/academy/lexicon/"]');
    }

    public function testOptionalFirstNameIsTrimmedAndValidatedWithoutOverwritingDisplayName(): void
    {
        $client = $this->client(); $crawler = $client->request('GET','/profile'); $token = $crawler->filter('meta[name="portal-csrf"]')->attr('content');
        self::assertSelectorExists('input[name="first_name"][maxlength="60"]');
        $client->request('POST','/profile',['_token'=>$token,'name'=>'Synthetischer Testname','first_name'=>'  Leon  ']); self::assertResponseRedirects('/profile');
        $em = self::getContainer()->get(EntityManagerInterface::class); $user = $em->find(User::class,'compact-content-agent'); self::assertSame('Leon',$user->preferences['firstName']); self::assertSame('Synthetischer Testname',$user->name);
        $client->request('POST','/profile',['_token'=>$token,'name'=>'Synthetischer Testname','first_name'=>str_repeat('A',61)]); self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]','Vorname darf höchstens 60 Zeichen');
        self::assertSame('Leon',self::getContainer()->get(EntityManagerInterface::class)->find(User::class,'compact-content-agent')->preferences['firstName']);
    }

    public function testAutomaticPrivateImportIsIdempotentAndInvalidArchiveDoesNotBlockStartup(): void
    {
        $this->client(); $em = self::getContainer()->get(EntityManagerInterface::class);
        $base = sys_get_temp_dir().'/chase-auto-command-'.bin2hex(random_bytes(8)); mkdir($base.'/var/materials',0700,true);
        $archive = $base.'/var/materials/Unterlagen_Co.zip';
        $override = getenv('CHASE_MATERIALS_ZIP'); putenv('CHASE_MATERIALS_ZIP='.$archive);
        $importer = new \App\Service\MaterialsImporter($em,$base);
        $command = new \App\Command\AutoMaterialsCommand(new \App\Service\LocalMaterialsDiscovery($base),$importer,new \App\Command\CatalogPreviewCommand($em,$importer,$base,new \App\Service\CatalogAssets($base)),$em,$base);
        try {
            file_put_contents($archive,'Invalid synthetic ZIP.');
            $output = new \Symfony\Component\Console\Output\BufferedOutput();
            self::assertSame(0,$command->run(new \Symfony\Component\Console\Input\ArrayInput([]),$output));
            self::assertStringContainsString('nicht abgeschlossen',$output->fetch());
            self::assertSame(0,$em->getRepository(\App\Entity\Document::class)->count([]));
            $zip = new \ZipArchive(); $zip->open($archive,\ZipArchive::CREATE|\ZipArchive::OVERWRITE); $zip->addFromString('4 - Verkauf/Automatisch.txt','Private synthetische Testvorlage.'); $zip->close();
            self::assertSame(0,$command->run(new \Symfony\Component\Console\Input\ArrayInput([]),$output));
            self::assertStringContainsString('1 neu',$output->fetch());
            self::assertSame(1,$em->getRepository(\App\Entity\Document::class)->count([]));
            self::assertSame(0,$command->run(new \Symfony\Component\Console\Input\ArrayInput([]),$output));
            self::assertSame('',$output->fetch());
            self::assertSame(1,$em->getRepository(\App\Entity\Document::class)->count([]));
        } finally {
            $override === false ? putenv('CHASE_MATERIALS_ZIP') : putenv('CHASE_MATERIALS_ZIP='.$override);
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
            foreach($it as $file){if($file->isDir()&&!$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($base);
        }
    }
}
