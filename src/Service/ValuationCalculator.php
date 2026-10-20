<?php
namespace App\Service;

/** Server-side port of the supplied CHASE HTML calculator, not a certified appraisal. */
final class ValuationCalculator
{
    public const VERSION = 'chase-html-port-1';
    public const FIELD_GROUPS = [
        'Sachwert und Grundstück' => ['bgf'=>['Brutto-Grundfläche','m²'],'nhk'=>['NHK-2010-Kostenkennwert','€/m² BGF'],'modYear'=>['Modifiziertes Baujahr','Jahr'],'landArea'=>['Grundstücksfläche / zugeordneter Anteil','m²'],'brw'=>['Bodenrichtwert','€/m²'],'marketFactor'=>['Sachwertfaktor','Faktor']],
        'Ertrag und Vergleich' => ['livingArea'=>['Wohnfläche','m²'],'grossRentAnnual'=>['Jahresrohertrag (kalt)','€/Jahr'],'rentQm'=>['Alternativ: monatliche Kaltmiete','€/m²'],'lz'=>['Liegenschaftszins','%'],'compQmPrice'=>['Vergleichspreis','€/m²'],'adjustments'=>['Zu- oder Abschläge','€']],
        'Preis und Provision' => ['priceAssumption'=>['Eigener Angebotspreis (optional)','€'],'commissionRate'=>['Courtage für die Rechenanzeige','%']],
        'Annahmen aus dem bestehenden Rechner' => ['cfg_bpi'=>['Baupreisindex (Basis 2010 = 100)','Index / Faktor'],'cfg_outdoor'=>['Außenanlagen','%'],'cfg_mgmt'=>['Bewirtschaftungskosten','%'],'cfg_totalLife'=>['Gesamtnutzungsdauer','Jahre'],'cfg_ageRate'=>['Jährliche Alterswertminderung','%']],
    ];
    public const FACTORS = ['brw'=>'Bodenrichtwert','marketFactor'=>'Sachwertfaktor','lz'=>'Liegenschaftszins','cfg_bpi'=>'Baupreisindex','compQmPrice'=>'Vergleichspreis'];
    public const DEFAULTS = ['bgf'=>'','nhk'=>'','modYear'=>'','landArea'=>'','brw'=>'','marketFactor'=>'','livingArea'=>'','grossRentAnnual'=>'','rentQm'=>'','lz'=>'','compQmPrice'=>'','adjustments'=>'','priceAssumption'=>'','commissionRate'=>'','cfg_bpi'=>'198.2','cfg_outdoor'=>'5','cfg_mgmt'=>'20','cfg_totalLife'=>'80','cfg_ageRate'=>'1.25'];

    public function parseNumber(mixed $raw): float
    {
        if (!is_scalar($raw) && $raw !== null) throw new \InvalidArgumentException('Bitte nur einzelne Zahlen eingeben.');
        $value=preg_replace('/\s+/u','',trim((string)$raw));
        if ($value==='') return 0.0;
        if (str_contains($value,',')) $value=str_replace(',','.',str_replace('.','',$value));
        if (!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/D',$value) || !is_finite($number=(float)$value) || abs($number)>1e12) throw new \InvalidArgumentException('Bitte gültige Zahlen eingeben; zum Beispiel 1234,56 oder 1234.56.');
        return $number;
    }

    public function calculate(array $raw, ?int $currentYear=null): array
    {
        $currentYear ??= (int)(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Berlin')))->format('Y');
        $input=[];
        foreach(self::DEFAULTS as $key=>$default) {
            $input[$key]=$this->parseNumber($raw[$key]??$default);
            if($key!=='adjustments' && $input[$key]<0) throw new \InvalidArgumentException('Negative Werte sind nur bei Zu- und Abschlägen erlaubt.');
        }
        // The original UI falls back to these values when these two fields are cleared.
        if(!$input['cfg_bpi']) $input['cfg_bpi']=198.2;
        if(!$input['cfg_totalLife']) $input['cfg_totalLife']=80;
        foreach(['cfg_outdoor','cfg_mgmt','cfg_ageRate','commissionRate','lz'] as $key) if($input[$key]>100) throw new \InvalidArgumentException('Prozentwerte dürfen höchstens 100 betragen.');
        if($input['cfg_totalLife']>200 || ($input['modYear'] && (floor($input['modYear'])!==$input['modYear'] || $input['modYear']<1000 || $input['modYear']>$currentYear+5))) throw new \InvalidArgumentException('Bitte Nutzungsdauer und modifiziertes Baujahr prüfen.');
        $ageYears=$input['modYear']>0 ? $currentYear-$input['modYear'] : 0;
        $ageDep=max(0,min(1,$ageYears*$input['cfg_ageRate']/100));
        $landValue=$input['landArea']*$input['brw'];
        $common=['year'=>$currentYear,'ageYears'=>$ageYears,'ageDep'=>$ageDep,'landValue'=>$landValue];
        $missing=function(array $fields)use($input):array { $result=[];foreach($fields as $field=>$label)if($input[$field]<=0)$result[]=$label;return $result; };
        $swMissing=$missing(['bgf'=>'Brutto-Grundfläche','nhk'=>'NHK-2010-Kostenkennwert','modYear'=>'Modifiziertes Baujahr','landArea'=>'Grundstücksfläche','brw'=>'Bodenrichtwert','marketFactor'=>'Sachwertfaktor']);
        $bpiFactor=$input['cfg_bpi']>10 ? $input['cfg_bpi']/100 : $input['cfg_bpi'];
        $normalHK=$input['bgf']*$input['nhk']*$bpiFactor;
        $ageDepAmt=$normalHK*$ageDep;
        $buildingValue=$normalHK-$ageDepAmt;
        $outdoor=$buildingValue*$input['cfg_outdoor']/100;
        $preliminary=$buildingValue+$outdoor+$landValue;
        $afterFactor=$input['marketFactor']>0 ? $preliminary*$input['marketFactor'] : 0;
        $sw=['ok'=>!$swMissing,'missing'=>$swMissing,'bpiFactor'=>$bpiFactor,'normalHK'=>$normalHK,'ageDepAmt'=>$ageDepAmt,'buildingValue'=>$buildingValue,'outdoor'=>$outdoor,'preliminary'=>$preliminary,'afterFactor'=>$afterFactor,'value'=>$afterFactor+$input['adjustments']];
        $grossAnnual=$input['grossRentAnnual']>0 ? $input['grossRentAnnual'] : ($input['rentQm']>0 && $input['livingArea']>0 ? $input['rentQm']*$input['livingArea']*12 : 0);
        $rentSource=$input['grossRentAnnual']>0 ? 'annual' : ($grossAnnual>0 ? 'sqm' : 'none');
        $lz=$input['lz']/100;
        $mgmtCost=$grossAnnual*$input['cfg_mgmt']/100;
        $netAnnual=$grossAnnual-$mgmtCost;
        $landInterest=$landValue*$lz;
        $buildingNet=$netAnnual-$landInterest;
        $rnd=max(0,min(120,$input['cfg_totalLife']-$ageYears));
        $power=(1+$lz)**$rnd;
        $multiplier=$lz>0 ? ($power-1)/($power*$lz) : $rnd;
        $buildingIncome=$buildingNet*$multiplier;
        $ewPreliminary=$grossAnnual>0 && $landValue>0 ? $buildingIncome+$landValue : 0;
        $ewMissing=$missing(['lz'=>'Liegenschaftszins','modYear'=>'Modifiziertes Baujahr','landArea'=>'Grundstücksfläche','brw'=>'Bodenrichtwert']);
        if($grossAnnual<=0)$ewMissing[]='Jahresrohertrag oder Miete je m² und Wohnfläche';
        $ew=['ok'=>!$ewMissing,'missing'=>$ewMissing,'grossAnnual'=>$grossAnnual,'rentSource'=>$rentSource,'mgmtCost'=>$mgmtCost,'netAnnual'=>$netAnnual,'lz'=>$lz,'landInterest'=>$landInterest,'buildingNet'=>$buildingNet,'rnd'=>$rnd,'multiplier'=>$multiplier,'buildingIncome'=>$buildingIncome,'preliminary'=>$ewPreliminary,'value'=>$ewPreliminary+$input['adjustments']];
        $vwMissing=$missing(['livingArea'=>'Wohnfläche','compQmPrice'=>'Vergleichspreis']);
        $base=$input['livingArea']>0 && $input['compQmPrice']>0 ? $input['livingArea']*$input['compQmPrice'] : 0;
        $vw=['ok'=>!$vwMissing,'missing'=>$vwMissing,'base'=>$base,'value'=>$base+$input['adjustments']];
        // Original max-of-positive-methods rule, restricted to complete calculations.
        $candidates=[];foreach(['sw'=>$sw,'ew'=>$ew,'vw'=>$vw] as $name=>$method)if($method['ok']&&$method['value']>0)$candidates[$name]=$method['value'];
        $autoMarket=$candidates ? max($candidates) : 0;
        $market=$input['priceAssumption']>0 ? $input['priceAssumption'] : $autoMarket;
        $offer=['ok'=>$market>0,'value'=>$market,'provided'=>$input['priceAssumption']>0,'autoMarket'=>$autoMarket,'factor'=>$market>0&&$grossAnnual>0?$market/$grossAnnual:0,'yieldRate'=>$market>0&&$grossAnnual>0?$grossAnnual/$market:0,'provision'=>$market>0&&$input['commissionRate']>0?$market*$input['commissionRate']/100:0];
        $warnings=['Schnellkalkulation nach dem bereitgestellten CHASE-Rechner; keine zertifizierte Verkehrswertermittlung.','Vorbelegte Annahmen stammen aus der HTML-Vorlage und sind keine amtlichen aktuellen Quartalswerte.','Faktoren müssen zu Region, Stichtag, Objektart und Bewertungsmodell passen. Keine automatische Aktualisierung oder KI-Bewertung.'];
        if($input['grossRentAnnual']>0 && $input['rentQm']>0)$warnings[]='Der ausdrücklich eingetragene Jahresrohertrag hat Vorrang vor der Miete je m².';
        if(!$offer['provided']&&$offer['ok'])$warnings[]='Die Preisorientierung entspricht dem höchsten positiven, vollständig berechenbaren Verfahrenswert. Das ist die Regel Ihrer Vorlage und keine eigenständige Marktpreisempfehlung.';
        if($buildingNet<0)$warnings[]='Der Gebäudereinertrag ist negativ. Eingaben, Bodenwert und Zins fachlich prüfen.';
        return ['version'=>self::VERSION,'input'=>$input,'common'=>$common,'sw'=>$sw,'ew'=>$ew,'vw'=>$vw,'offer'=>$offer,'warnings'=>$warnings];
    }

    public function prefill(array $property): array
    {
        $map=['bgf'=>['bgfSqm','grossFloorAreaSqm','bgf'],'livingArea'=>['areaSqm'],'landArea'=>['plotSqm'],'modYear'=>['modifiedYear','yearBuilt'],'grossRentAnnual'=>['annualNetRentEur','annualRentEur'],'priceAssumption'=>['priceEur'],'commissionRate'=>['outsideCommissionPercent','externalCommissionPct','buyerCommissionPct']];
        $values=self::DEFAULTS;
        foreach($map as $target=>$fields)foreach($fields as $field)if(isset($property[$field])&&$property[$field]!==''){ $values[$target]=(string)$property[$field];break; }
        if($values['grossRentAnnual']==='' && !empty($property['rentEur']))$values['grossRentAnnual']=(string)((float)$property['rentEur']*12);
        return $values;
    }
}
