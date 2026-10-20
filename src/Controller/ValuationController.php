<?php
namespace App\Controller;

use App\Entity\{Property, Valuation};
use App\Service\{PortalStore, ValuationCalculator};
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;

final class ValuationController extends PortalController
{
    public function __construct(PortalStore $store, private ValuationCalculator $calculator) { parent::__construct($store); }

    #[Route('/valuation',name:'valuation',methods:['GET','POST'])]
    #[Route('/properties/{id}/valuation',name:'property_valuation',methods:['GET','POST'])]
    public function index(Request $request, ?string $id=null): Response
    {
        $propertyId=$id ?? ($request->isMethod('POST')?$request->request->getString('propertyId'):$request->query->getString('property'));
        $property=$propertyId?$this->owned(Property::class,$propertyId):null;
        $data=$property?$this->calculator->prefill($property->data):ValuationCalculator::DEFAULTS;
        $valuationDate=(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Berlin')))->format('Y-m-d');
        $result=null; $error=null; $saved=null; $sourceBindings=[];
        if($resultId=$request->query->getString('result')) {
            $saved=$this->owned(Valuation::class,$resultId);
            if(($saved->data['kind']??'')==='factor_source')throw $this->createNotFoundException();
            if($saved->data['propertyId']??null)$property=$this->owned(Property::class,(string)$saved->data['propertyId']);
            $result=$saved->data['calculation']??null;
            $data=$saved->data['inputs']??$data;
            $valuationDate=$saved->data['valuationDate']??$valuationDate;
            $sourceBindings=$saved->data['sourceBindings']??[];
        }
        if($request->isMethod('POST')) {
            $this->token($request);
            $sourceBindings=[]; $saved=null;
            foreach(ValuationCalculator::DEFAULTS as $key=>$default)$data[$key]=$request->request->getString($key,'');
            $valuationDate=$request->request->getString('valuationDate',$valuationDate);
            try {
                $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$valuationDate,new \DateTimeZone('Europe/Berlin'));
                if(!$date||$date->format('Y-m-d')!==$valuationDate||$date->format('Y')<1900||$date->format('Y')>2200)throw new \InvalidArgumentException('Bitte einen gültigen Bewertungsstichtag zwischen 1900 und 2200 angeben.');
                $result=$this->calculator->calculate($data,(int)$date->format('Y'));
                $sourceSnapshots=[];
                foreach($request->request->all('sources') as $field=>$sourceId) {
                    if(!isset(ValuationCalculator::FACTORS[$field])||!is_string($sourceId)||$sourceId==='')continue;
                    $source=$this->owned(Valuation::class,$sourceId);
                    if(($source->data['kind']??'')!=='factor_source'||($source->data['factor']??'')!==$field)throw new \InvalidArgumentException('Die gewählte Faktorquelle passt nicht zum Feld.');
                    if(abs((float)$source->data['value']-$result['input'][$field])>0.00000001)throw new \InvalidArgumentException('Ein übernommener Faktor wurde geändert. Bitte dessen Quellenauswahl auf „Manuelle Eingabe“ setzen oder den Quellenwert erneut übernehmen.');
                    $sourceBindings[$field]=$source->id;
                    $sourceSnapshots[$field]=$source->data+['recordId'=>$source->id,'recordedAt'=>$source->createdAt->format(DATE_ATOM)];
                }
                if($request->request->getString('action','save')==='save') {
                    if(!$result['sw']['ok']&&!$result['ew']['ok']&&!$result['vw']['ok']&&!$result['offer']['provided'])throw new \InvalidArgumentException('Bitte mindestens ein Verfahren vollständig ausfüllen oder einen eigenen Angebotspreis angeben.');
                    $valuation=new Valuation(['kind'=>'calculation','propertyId'=>$property?->id,'inputs'=>$data,'calculation'=>$result,'sourceBindings'=>$sourceBindings,'sourceSnapshots'=>$sourceSnapshots,'valuationDate'=>$valuationDate,'status'=>'Unverbindliche Schnellkalkulation','isDemo'=>true],$this->user()->id,$this->user()->tenantId);
                    $valuation->title=$property?->title??'Freie Immobilien-Schnellkalkulation';
                    $this->save($valuation);$this->audit('valuation.calculated',$valuation->id);
                    $this->addFlash('success','Berechnung mit Eingaben, Quellen und Rechenschritten lokal gespeichert.');
                    return $this->redirectToRoute($property?'property_valuation':'valuation',($property?['id'=>$property->id]:[])+['result'=>$valuation->id]);
                }
            }catch(\InvalidArgumentException $exception){$error=$exception->getMessage();}
        }
        $valuations=array_values(array_filter($this->store->visible(Valuation::class,$this->user()),fn($v)=>($v->data['kind']??'')!=='factor_source'&&(!$property||($v->data['propertyId']??'')===$property->id)));
        return $this->page('valuation/index.html.twig',['property'=>$property,'properties'=>$this->store->visible(Property::class,$this->user()),'data'=>$data,'groups'=>ValuationCalculator::FIELD_GROUPS,'factors'=>ValuationCalculator::FACTORS,'result'=>$result,'saved'=>$saved,'error'=>$error,'valuations'=>$valuations,'valuationDate'=>$valuationDate,'sources'=>$this->sources(),'sourceBindings'=>$sourceBindings,'quarter'=>$this->quarter()]);
    }

    #[Route('/valuation/sources',name:'valuation_sources',methods:['GET','POST'])]
    public function sourceRegistry(Request $request): Response
    {
        $error=null;
        if($request->isMethod('POST')) {
            $this->token($request);
            try {
                $data=[];foreach(['factor','value','region','quarter','source_title','source_url','source_date','note'] as $field)$data[$field]=$request->request->getString($field);
                $record=$this->sourceRecord($data);
                $this->save($record);$this->audit('valuation.factor_source.recorded',$record->id);
                $this->addFlash('success','Faktor samt Quelle und Quartalsprüfung gespeichert. Es wurde keine Quelle automatisch abgefragt.');
                return $this->redirectToRoute('valuation_sources');
            }catch(\InvalidArgumentException $exception){$error=$exception->getMessage();}
        }
        return $this->page('valuation/sources.html.twig',['sources'=>$this->sources(),'factors'=>ValuationCalculator::FACTORS,'quarter'=>$this->quarter(),'error'=>$error]);
    }

    #[Route('/valuation/sources/import',name:'valuation_sources_import',methods:['POST'])]
    public function import(Request $request): Response
    {
        $this->token($request);
        try {
            $file=$request->files->get('csv');
            if(!$file instanceof UploadedFile||!$file->isValid()||$file->getSize()>512000||strtolower($file->getClientOriginalExtension())!=='csv')throw new \InvalidArgumentException('Bitte eine CSV-Datei mit höchstens 500 KB hochladen.');
            $handle=fopen($file->getPathname(),'rb');
            if(!$handle)throw new \InvalidArgumentException('Die CSV-Datei konnte nicht gelesen werden.');
            try {
                $header=fgetcsv($handle,0,';','"','');
                if(isset($header[0]))$header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
                $expected=['factor','value','region','quarter','source_title','source_url','source_date','note'];
                if($header!==$expected)throw new \InvalidArgumentException('Die CSV-Kopfzeile muss der angegebenen Vorlage entsprechen; Trennzeichen ist das Semikolon.');
                $records=[];$rowCount=0;
                while(($row=fgetcsv($handle,0,';','"',''))!==false){
                    if($row===[null])continue;
                    if(count($row)!==count($expected))throw new \InvalidArgumentException('Eine CSV-Zeile enthält nicht alle acht Spalten.');
                    if(++$rowCount>100)throw new \InvalidArgumentException('Pro Import sind höchstens 100 Faktoren erlaubt.');
                    $record=$this->sourceRecord(array_combine($expected,$row));
                    $records[$record->data['fingerprint']]=$record;
                }
                if(!$records)throw new \InvalidArgumentException('Die CSV-Datei enthält keine Faktoren.');
                $this->store->em()->wrapInTransaction(function()use($records):void{foreach($records as $record)$this->store->em()->persist($record);});
                $this->audit('valuation.factor_sources.imported');
                $this->addFlash('success',count($records).' Faktorquellen lokal importiert. Werte und Anwendbarkeit bitte fachlich prüfen.');
            }finally{fclose($handle);}
        }catch(\InvalidArgumentException $exception){$this->addFlash('error',$exception->getMessage());}
        return $this->redirectToRoute('valuation_sources');
    }

    private function sourceRecord(array $raw): Valuation
    {
        foreach($raw as $value)if(!is_scalar($value)||!mb_check_encoding((string)$value,'UTF-8'))throw new \InvalidArgumentException('Bitte UTF-8-Text für Quellen und CSV-Daten verwenden.');
        $factor=(string)($raw['factor']??'');
        if(!isset(ValuationCalculator::FACTORS[$factor]))throw new \InvalidArgumentException('Bitte einen unterstützten Faktor auswählen.');
        $value=$this->calculator->parseNumber($raw['value']??'');
        if($value<=0||($factor==='lz'&&$value>100))throw new \InvalidArgumentException('Der Faktorwert muss positiv und im gültigen Wertebereich sein.');
        $quarter=trim((string)($raw['quarter']??''));
        if(!preg_match('/^20\d{2}-Q[1-4]$/D',$quarter))throw new \InvalidArgumentException('Das Prüfquartal bitte als JJJJ-Q1 bis JJJJ-Q4 angeben.');
        $sourceUrl=trim((string)($raw['source_url']??''));
        if(!filter_var($sourceUrl,FILTER_VALIDATE_URL)||parse_url($sourceUrl,PHP_URL_SCHEME)!=='https'||parse_url($sourceUrl,PHP_URL_USER)||parse_url($sourceUrl,PHP_URL_PASS)||mb_strlen($sourceUrl)>1000)throw new \InvalidArgumentException('Bitte einen öffentlichen HTTPS-Quellenlink ohne Zugangsdaten angeben. Der Link wird nur gespeichert, nicht abgerufen.');
        $sourceDate=trim((string)($raw['source_date']??''));
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$sourceDate);
        if(!$date||$date->format('Y-m-d')!==$sourceDate)throw new \InvalidArgumentException('Bitte den tatsächlichen Datenstichtag der Quelle angeben.');
        $region=trim((string)($raw['region']??''));$title=trim((string)($raw['source_title']??''));$note=trim((string)($raw['note']??''));
        if(mb_strlen($region)<2||mb_strlen($region)>180||mb_strlen($title)<3||mb_strlen($title)>250||mb_strlen($note)>3000)throw new \InvalidArgumentException('Bitte Region, Quellentitel und eine kurze Modell-/Zonenangabe eintragen.');
        $data=['title'=>ValuationCalculator::FACTORS[$factor].' · '.$region,'kind'=>'factor_source','factor'=>$factor,'value'=>$value,'region'=>$region,'quarter'=>$quarter,'sourceTitle'=>$title,'sourceUrl'=>$sourceUrl,'sourceDate'=>$sourceDate,'note'=>$note,'status'=>'Manuell dokumentiert; nicht automatisch verifiziert','connection'=>'Nicht verbunden','recordedBy'=>$this->user()->id];
        $data['fingerprint']=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
        foreach($this->sources() as $existing)if($existing->ownerId===$this->user()->id&&($existing->data['fingerprint']??'')===$data['fingerprint'])return $existing;
        return new Valuation($data,$this->user()->id,$this->user()->tenantId);
    }
    private function sources(): array {return array_values(array_filter($this->store->visible(Valuation::class,$this->user()),fn($v)=>($v->data['kind']??'')==='factor_source'));}
    private function quarter(): string {$date=new \DateTimeImmutable('now',new \DateTimeZone('Europe/Berlin'));return $date->format('Y').'-Q'.(int)ceil((int)$date->format('n')/3);}
}
