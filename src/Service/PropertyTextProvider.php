<?php
namespace App\Service;

use App\Entity\{Property, MediaAsset};

/** Fixed local patterns. Images are not analysed: only manually confirmed captions are facts. */
final class PropertyTextProvider
{
    public const SECTIONS = ['description' => 'Objektbeschreibung', 'equipment' => 'Ausstattung', 'location' => 'Lagebeschreibung'];
    private const FIELDS = ['title','propertyType','marketingType','city','district','region','areaSqm','usableAreaSqm','plotSqm','rooms','bedrooms','bathrooms','yearBuilt','floor','buildingFloors','condition','availability','features','constructionType','heatingType','energyCarrier','energyType','energyValue','energyClass','kitchen','sanitary','floorCoverings','windows','glazing','basement','parkingType','parkingSpaces','internet','verifiedLocation','locationFacts','priceEur','rentEur'];

    public function source(Property $property, array $images): array
    {
        $facts = array_intersect_key($property->data, array_flip(self::FIELDS));
        $facts['title'] = $property->title;
        $captions = [];
        foreach ($images as $image) {
            if (!$image instanceof MediaAsset || $image->tenantId !== $property->tenantId || ($image->data['propertyId'] ?? '') !== $property->id || ($image->data['factsConfirmed'] ?? false) !== true || ($image->data['kind'] ?? 'image') === 'document' || !str_starts_with($image->data['mime'] ?? '', 'image/')) continue;
            $caption = $this->clean($image->data['caption'] ?? '');
            if ($caption !== '') $captions[] = ['id' => $image->id, 'caption' => $caption, 'confirmation' => 'manuell bestätigt'];
        }
        return ['type' => 'property', 'id' => $property->id, 'title' => $property->title, 'facts' => $facts, 'confirmedImageCaptions' => $captions, 'imageAnalysis' => false];
    }

    public function generate(Property $property, array $images = []): array
    {
        $source = $this->source($property, $images); $facts = $source['facts'];
        $value = fn(string $key): string => $this->clean($facts[$key] ?? '');
        $number = function(string $key, string $unit = '') use ($facts): string {
            $v = $facts[$key] ?? null;
            return is_numeric($v) ? rtrim(rtrim(number_format((float)$v, 2, ',', '.'), '0'), ',').$unit : 'Angabe fehlt';
        };
        $description = [
            ($value('title') ?: 'Immobilie').' – die vorliegenden Eckdaten im Überblick.',
            'Objektart: '.($value('propertyType') ?: 'Angabe fehlt').'. Vermarktung: '.($value('marketingType') ?: 'Angabe fehlt').'.',
            'Wohnfläche: '.$number('areaSqm',' m²').'. Zimmer: '.$number('rooms').'.',
        ];
        foreach (['usableAreaSqm' => ['Nutzfläche',' m²'], 'plotSqm' => ['Grundstück',' m²'], 'yearBuilt' => ['Baujahr',''], 'floor' => ['Etage',''], 'buildingFloors' => ['Geschosse','']] as $key => [$label,$unit]) if (isset($facts[$key]) && $facts[$key] !== '') $description[] = $label.': '.$number($key,$unit).'.';
        if ($value('condition')) $description[] = 'Erfasster Zustand: '.$value('condition').'.';
        if ($value('availability')) $description[] = 'Verfügbarkeit laut Erfassung: '.$value('availability').'.';
        $features = $facts['features'] ?? [];
        if (is_string($features)) $features = preg_split('/[\r\n,]+/u', $features) ?: [];
        $equipment = ['Bestätigte Ausstattung aus der Objektaufnahme:'];
        foreach (is_array($features) ? $features : [] as $feature) if ($this->clean($feature) !== '') $equipment[] = '• '.$this->clean($feature);
        if (count($equipment) === 1) $equipment[] = 'Ausstattungsmerkmale: Angabe fehlt.';
        foreach (['heatingType'=>'Heizungsart','energyCarrier'=>'Energieträger','energyType'=>'Energieausweisart','energyClass'=>'Energieeffizienzklasse','constructionType'=>'Bauweise','kitchen'=>'Küche','sanitary'=>'Bad / Sanitär','floorCoverings'=>'Bodenbeläge','windows'=>'Fenster','glazing'=>'Verglasung','basement'=>'Keller','parkingType'=>'Stellplatzart','internet'=>'Bestätigte Internetangaben'] as $key=>$label) if ($value($key)) $equipment[] = $label.': '.$value($key).'.';
        if (is_numeric($facts['energyValue'] ?? null)) $equipment[] = 'Endenergiekennwert: '.$number('energyValue',' kWh/(m²·a)').'.';
        else $equipment[] = 'Energiekennwert: Angabe fehlt; vor Vermarktung prüfen.';
        foreach ($source['confirmedImageCaptions'] as $image) $equipment[] = 'Manuell bestätigte Bildbeschriftung: '.$image['caption'];
        $location = ['Ort: '.($value('city') ?: 'Angabe fehlt').'.'];
        if ($value('district')) $location[] = 'Ortsteil: '.$value('district').'.';
        if ($value('region')) $location[] = 'Region: '.$value('region').'.';
        $locationFacts = $value('verifiedLocation') ?: $value('locationFacts');
        $location[] = $locationFacts ? 'Vom Makler erfasste Lagefakten: '.$locationFacts : 'Weitere verifizierte Lagefakten: Angabe fehlt.';
        $location[] = 'Infrastruktur, Entfernungen und Verkehrsanbindung werden ohne belegte Angaben nicht ergänzt.';
        return ['sections' => ['description'=>$this->fit($description), 'equipment'=>$this->fit($equipment), 'location'=>$this->fit($location)], 'source'=>$source, 'mode'=>'Lokale Textvorlage – kein externes KI-Modell'];
    }

    public function social(Property $property, array $images = []): array
    {
        $generated = $this->generate($property, $images); $f = $generated['source']['facts'];
        $details = [];
        foreach (['areaSqm'=>' m²','rooms'=>' Zimmer'] as $key=>$unit) if (is_numeric($f[$key] ?? null)) $details[] = rtrim(rtrim(number_format((float)$f[$key], 2, ',', '.'),'0'),',').$unit;
        $parts = ['CHASE Immobilien · '.$this->clean($property->title), 'Ort: '.($this->clean($f['city'] ?? '') ?: 'Angabe fehlt').'.'];
        if ($details) $parts[] = implode(' · ', $details);
        $captions = $generated['source']['confirmedImageCaptions'];
        if ($captions && mb_strlen($captions[0]['caption']) < 180) $parts[] = $captions[0]['caption'];
        $parts[] = 'Mehr erfahren? Exposé und Besichtigung auf Anfrage.';
        $parts[] = '#CHASEImmobilien #Immobilien';
        return ['caption'=>$this->fit($parts, 650), 'source'=>$generated['source']];
    }

    public function combine(array $sections): string
    {
        $parts=[];
        foreach (self::SECTIONS as $key=>$label) $parts[]=$label."\n".($sections[$key] ?? 'Angabe fehlt.');
        return implode("\n\n",$parts);
    }

    private function clean(mixed $value): string
    {
        return is_scalar($value) ? trim(strip_tags((string)$value)) : '';
    }

    private function fit(array $lines, int $maximum = 2000): string
    {
        $text=''; $notice="\nWeitere erfasste Details siehe Quellen; Angaben vor Verwendung prüfen.";
        foreach ($lines as $line) {
            $candidate=$text.($text!=='' ? "\n" : '').$line;
            if (mb_strlen($candidate) > $maximum-mb_strlen($notice)) return $text.$notice;
            $text=$candidate;
        }
        return $text;
    }
}
