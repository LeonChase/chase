<?php
namespace App\Command;

use App\Entity\Document;
use App\Service\MaterialsImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;

/** Rasterizes only the privately imported, checksum-verified catalogue. No external service. */
#[AsCommand(name:'app:catalog:preview',description:'Private Blättervorschau aus dem bereitgestellten Werbekatalog erzeugen (optional: Poppler).')]
final class CatalogPreviewCommand extends Command
{
    public function __construct(private EntityManagerInterface $em,private MaterialsImporter $materials,private string $projectDir){parent::__construct();}
    protected function configure():void{$this->addOption('tenant',null,InputOption::VALUE_REQUIRED,'Mandantenkennung','chase-demo');}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        $tenant=(string)$input->getOption('tenant');$document=null;
        foreach($this->em->getRepository(Document::class)->findBy(['tenantId'=>$tenant]) as $record){if(($record->data['catalogOriginal']??false)&&($record->data['provided']??false)){$document=$record;break;}}
        if(!$document||!($pdf=$this->materials->filePath($document))){$output->writeln('<comment>Kein intakter Originalkatalog importiert. Zuerst die bereitgestellten Materialien importieren. Die PDF-Ansicht bleibt verfügbar, sobald die Quelle vorliegt.</comment>');return Command::FAILURE;}
        $base=$this->projectDir.'/var/materials/catalog/'.substr(hash('sha256',$tenant),0,16).'/'.$document->data['sourceHash'];
        $manifest=$base.'/manifest.json';
        if(is_file($manifest)){$existing=json_decode((string)file_get_contents($manifest),true);$ready=($existing['sourceHash']??'')===$document->data['sourceHash']&&($existing['pages']??0)>0;for($p=1;$p<=(int)($existing['pages']??0);$p++)$ready=$ready&&is_file($base.'/page-'.sprintf('%03d',$p).'.jpg');if($ready){$output->writeln('Die private Katalogvorschau ist aktuell ('.$existing['pages'].' Seiten).');return Command::SUCCESS;}}
        [$status,$info]=$this->runProcess(['pdfinfo',$pdf]);
        if($status!==0||!preg_match('/^Pages:\s+(\d+)/m',$info,$match)){$output->writeln('<comment>Die optionale Blättervorschau benötigt Poppler (pdfinfo und pdftoppm). Der geschützte Original-PDF-Download und die Produktauswahl funktionieren ohne diese Zusatzprogramme.</comment>');return Command::FAILURE;}
        $pages=(int)$match[1];if($pages<1||$pages>100){$output->writeln('<error>Die Seitenzahl liegt außerhalb des unterstützten Bereichs (1–100).</error>');return Command::FAILURE;}
        if(!is_dir($base)&&!mkdir($base,0700,true)&&!is_dir($base))throw new \RuntimeException('Privater Vorschauordner nicht verfügbar.');
        $temporary=$base.'/.render-'.bin2hex(random_bytes(8));mkdir($temporary,0700);
        try {
            [$status]=$this->runProcess(['pdftoppm','-jpeg','-jpegopt','quality=86','-r','96','-scale-to-x','1440','-scale-to-y','-1','-f','1','-l',(string)$pages,$pdf,$temporary.'/page']);
            $rendered=glob($temporary.'/page-*.jpg')?:[];
            if($status!==0||count($rendered)!==$pages){$output->writeln('<comment>Die Vorschau konnte nicht vollständig erzeugt werden. Der Original-PDF-Download bleibt verfügbar.</comment>');return Command::FAILURE;}
            foreach($rendered as $file){if(preg_match('/page-(\d+)\.jpg$/',$file,$m)){chmod($file,0600);rename($file,$base.'/page-'.sprintf('%03d',(int)$m[1]).'.jpg');}}
            $data=['documentId'=>$document->id,'sourceHash'=>$document->data['sourceHash'],'pages'=>$pages,'renderedAt'=>(new \DateTimeImmutable())->format(DATE_ATOM),'width'=>1440];
            file_put_contents($manifest.'.tmp',json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));chmod($manifest.'.tmp',0600);rename($manifest.'.tmp',$manifest);
            $output->writeln($pages.' Katalogseiten lokal gerendert und privat gespeichert. Keine Übertragung an Dritte.');return Command::SUCCESS;
        } finally {foreach(glob($temporary.'/*')?:[] as $file)if(is_file($file))unlink($file);if(is_dir($temporary))rmdir($temporary);}
    }
    /** @return array{int,string} */
    private function runProcess(array $arguments):array
    {
        if(!function_exists('proc_open'))return [127,''];
        $pipes=[];$process=@proc_open($arguments,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$this->projectDir);
        if(!is_resource($process))return [127,''];fclose($pipes[0]);$text=(string)stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);return [proc_close($process),$text];
    }
}
