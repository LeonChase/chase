<?php
namespace App\Service;

/** Local form catalogue, informed by the supplied CHASE property intake forms. */
final class PropertyFormSchema
{
    public const STATUSES = ['Entwurf', 'Vorbereitung', 'Aktiv', 'Reserviert', 'Verkauft', 'Archiviert'];
    public const TYPES = ['Eigentumswohnung', 'Etagenwohnung', 'Maisonettewohnung', 'Penthouse', 'Einfamilienhaus', 'Zweifamilienhaus', 'Doppelhaushälfte', 'Reihenmittelhaus', 'Reihenendhaus', 'Bungalow', 'Stadthaus', 'Mehrfamilienhaus', 'Wohn- und Geschäftshaus', 'Grundstück', 'Gewerbe', 'Sonstiges'];
    public const MODERNISATION_COMPONENTS = ['Fenster', 'Dach', 'Fassade', 'Heizung', 'Leitungen', 'Bad', 'Innenausbau', 'Sonstiges'];

    public static function sections(): array
    {
        $year = (int) date('Y') + 5;
        return [
            'start' => ['title'=>'Start', 'subtitle'=>'Welches Objekt möchtest du aufnehmen? Wer ist beteiligt?', 'icon'=>'house', 'fields'=>[
                'title'=>self::field('Objektbezeichnung', 'text', 'basic', required:true, help:'Eine kurze, sachliche Bezeichnung. Der Titel kann später angepasst werden.'),
                'propertyType'=>self::field('Objektart', 'select', 'basic', options:self::TYPES, required:true),
                'marketingType'=>self::field('Vermarktungsart', 'select', 'basic', options:['Kauf','Miete'], required:true),
                'status'=>self::field('Interner Status', 'select', 'basic', options:self::STATUSES, required:true, help:'Der Status veröffentlicht das Objekt nicht automatisch.'),
                'city'=>self::field('Ort', 'text', 'basic', required:true),
                'region'=>self::field('Region', 'text', 'basic', required:true),
                'street'=>self::field('Straße', 'text', 'address'),
                'houseNumber'=>self::field('Hausnummer', 'text', 'address', maxlength:30),
                'postalCode'=>self::field('Postleitzahl', 'text', 'address', maxlength:10),
                'district'=>self::field('Ortsteil / Stadtteil', 'text', 'address'),
                'country'=>self::field('Land', 'text', 'address'),
                'address'=>self::field('Ergänzende Anschrift / bisherige Adressangabe', 'text', 'address', help:'Bestehende Adressangaben bleiben erhalten. Nicht zusätzlich ausfüllen, wenn Straße und Hausnummer genügen.'),
                'contactName'=>self::field('Ansprechpartner zum Objekt', 'text', 'contact'),
                'contactEmail'=>self::field('E-Mail Ansprechpartner', 'email', 'contact'),
                'contactPhone'=>self::field('Telefon Ansprechpartner', 'tel', 'contact', maxlength:100),
                'ownerName'=>self::field('Eigentümer: Name / Firma', 'text', 'owners'),
                'ownerEmail'=>self::field('Eigentümer: E-Mail', 'email', 'owners'),
                'ownerPhone'=>self::field('Eigentümer: Telefon', 'tel', 'owners', maxlength:100),
                'ownerAddress'=>self::field('Eigentümer: Anschrift', 'textarea', 'owners', maxlength:1000),
                'secondOwnerName'=>self::field('Weitere Eigentümer: Name / Firma', 'text', 'owners'),
                'secondOwnerEmail'=>self::field('Weitere Eigentümer: E-Mail', 'email', 'owners'),
                'secondOwnerPhone'=>self::field('Weitere Eigentümer: Telefon', 'tel', 'owners', maxlength:100),
                'managementName'=>self::field('Hausverwaltung: Firma / Kontakt', 'text', 'owners'),
                'managementEmail'=>self::field('Hausverwaltung: E-Mail', 'email', 'owners'),
                'managementPhone'=>self::field('Hausverwaltung: Telefon', 'tel', 'owners', maxlength:100),
                'sellingReason'=>self::field('Verkaufsgrund (intern)', 'text', 'owners'),
                'privacyNotes'=>self::field('Kontakt- und Datenschutzhinweise (intern)', 'textarea', 'owners', maxlength:5000, help:'Nur notwendige Daten erfassen. Eigentümerkontakte werden nicht in generierte Exposé-Texte übernommen.'),
            ]],
            'base' => ['title'=>'Grunddaten', 'subtitle'=>'Nur bekannte Fakten erfassen. Nicht benötigte Angaben bleiben leer.', 'icon'=>'clipboard', 'fields'=>[
                'internalId'=>self::field('Objektnummer', 'text', 'core'),
                'usageType'=>self::field('Nutzungsart', 'select', 'core', options:['Wohnen','Gewerbe','Wohnen und Gewerbe','Anlageobjekt','Grundstück','Sonstiges']),
                'yearBuilt'=>self::number('Baujahr', 'core', 1000, $year, '1'),
                'areaSqm'=>self::number('Wohnfläche (m²)', 'core', 0.01, 1000000000),
                'usableAreaSqm'=>self::number('Nutzfläche (m²)', 'core', 0.01, 1000000000),
                'plotSqm'=>self::number('Grundstücksfläche (m²)', 'core', 0.01, 1000000000),
                'rooms'=>self::number('Zimmer', 'core', 0.5, 10000, '0.5'),
                'bgfSqm'=>self::number('Bruttogrundfläche / BGF (m²)', 'extended', 0.01, 1000000000, help:'Für die Sachwertvorbereitung. BGF und Wohnfläche sind unterschiedliche Größen.'),
                'propertySubtype'=>self::field('Objekttyp / nähere Einordnung', 'text', 'extended'),
                'bedrooms'=>self::number('Schlafzimmer', 'extended', 0, 10000, '1'),
                'bathrooms'=>self::number('Badezimmer', 'extended', 0, 10000, '1'),
                'floor'=>self::number('Etage (0 = Erdgeschoss)', 'extended', -10, 200, '1'),
                'buildingFloors'=>self::number('Geschosse im Gebäude', 'extended', 1, 200, '1'),
                'units'=>self::number('Einheiten im Objekt', 'extended', 1, 100000, '1'),
                'totalUnits'=>self::number('Einheiten der Gesamtanlage', 'extended', 1, 100000, '1'),
                'parkingSpaces'=>self::number('Anzahl Stellplätze', 'extended', 0, 10000, '1'),
                'parkingType'=>self::field('Stellplatzart', 'select', 'extended', options:['Garage','Carport','Freiplatz','Tiefgarage','Mehrere Stellplatzarten','Kein Stellplatz']),
                'availability'=>self::field('Verfügbarkeit / Übergabe', 'text', 'extended', help:'Zum Beispiel „nach Vereinbarung“. Keine Zusage ohne Bestätigung.'),
                'availableFrom'=>self::field('Verfügbar ab', 'date', 'extended'),
                'occupancy'=>self::field('Nutzungsstand', 'select', 'extended', options:['Leerstehend','Eigengenutzt','Vermietet','Teilweise vermietet','Unbekannt']),
                'listedMonument'=>self::field('Denkmalschutz bestätigt', 'checkbox', 'extended'),
                'leasehold'=>self::field('Erbbaurecht', 'checkbox', 'extended'),
                'leaseholdUntil'=>self::field('Erbbaurecht bis', 'date', 'extended'),
                'annualGroundRentEur'=>self::number('Jährlicher Erbbauzins (EUR)', 'extended', 0, 1000000000),
            ]],
            'equipment' => ['title'=>'Ausstattung & Energie', 'subtitle'=>'Bestätigte Merkmale und Energieangaben statt Vermutungen.', 'icon'=>'zap', 'fields'=>[
                'features'=>self::field('Ausstattung', 'textarea', 'features', maxlength:8000, help:'Ein Merkmal pro Zeile, z. B. Balkon, Terrasse, Garten, Gäste-WC, Einbauküche oder Aufzug.'),
                'heatingType'=>self::field('Heizungsart', 'select', 'energy', options:['Zentralheizung','Etagenheizung','Fußbodenheizung','Blockheizkraftwerk','Einzelöfen','Mehrere Heizungsarten','Sonstiges']),
                'energyCarrier'=>self::field('Wesentlicher Energieträger', 'select', 'energy', options:['Gas','Öl','Fernwärme','Strom','Wärmepumpe','Holz','Pellets','Solarenergie','Mehrere Energieträger','Sonstiges']),
                'heatingYear'=>self::number('Baujahr Heizung', 'energy', 1900, $year, '1'),
                'warmWater'=>self::field('Warmwasserbereitung', 'select', 'energy', options:['Zentral','Boiler','Durchlauferhitzer','Wärmepumpe','Solarthermie','Sonstiges']),
                'energyType'=>self::field('Energieausweisart', 'select', 'energy', options:['Bedarfsausweis','Verbrauchsausweis','Liegt noch nicht vor','Ausnahme / keine Pflicht fachlich prüfen']),
                'energyCreatedAt'=>self::field('Energieausweis ausgestellt am', 'date', 'energy'),
                'energyValidUntil'=>self::field('Energieausweis gültig bis', 'date', 'energy'),
                'energyValue'=>self::number('Endenergiekennwert (kWh/(m²·a))', 'energy', 0, 10000),
                'energyClass'=>self::field('Energieeffizienzklasse', 'select', 'energy', options:['A+','A','B','C','D','E','F','G','H']),
                'energyYearBuilt'=>self::number('Gebäudebaujahr laut Energieausweis', 'energy', 1000, $year, '1'),
                'energyData'=>self::field('Ergänzende Energieangaben', 'textarea', 'energy', maxlength:5000, help:'Werte nur aus einer verifizierten Quelle übernehmen. Fehlende Angaben vor Veröffentlichung prüfen.'),
                'constructionType'=>self::field('Bauweise', 'select', 'construction', options:['Massiv','Fachwerk','Fertighaus','Stahlbau','Hybrid','Holzbau','Sonstiges']),
                'roofType'=>self::field('Dachform', 'select', 'construction', options:['Satteldach','Walmdach','Flachdach','Pultdach','Mansarddach','Sonstiges']),
                'roofCovering'=>self::field('Dacheindeckung', 'select', 'construction', options:['Ziegel','Beton','Schiefer','Reet','Bitumen','Metall','Sonstiges']),
                'facade'=>self::field('Fassade', 'select', 'construction', options:['Klinker','Putz','Platten','Holz','Mehrere Materialien','Sonstiges']),
                'insulation'=>self::field('Dämmung / bekannte Maßnahmen', 'text', 'construction'),
                'basement'=>self::field('Keller', 'select', 'construction', options:['Voll unterkellert','Teilunterkellert','Ausgebaut','Beheizt','Kein Keller','Unbekannt']),
                'windows'=>self::field('Fenstermaterial', 'select', 'construction', options:['Kunststoff','Holz','Aluminium','Holz-Aluminium','Mehrere Materialien','Sonstiges']),
                'glazing'=>self::field('Verglasung', 'select', 'construction', options:['Einfachverglasung','Doppel- / Isolierverglasung','Dreifachverglasung','Unbekannt']),
                'shutters'=>self::field('Rollläden / Jalousien', 'select', 'construction', options:['Außen','Innen','Teilweise','Keine','Unbekannt']),
                'floorCoverings'=>self::field('Bodenbeläge', 'text', 'construction', help:'Zum Beispiel Fliesen, Parkett, Laminat oder Teppich.'),
                'kitchen'=>self::field('Küche', 'select', 'construction', options:['Einbauküche','Pantryküche','Offene Küche','Keine Küche','Sonstiges']),
                'sanitary'=>self::field('Bad / Sanitär', 'text', 'construction', help:'Zum Beispiel Dusche, Badewanne, Bidet und Gäste-WC.'),
                'internet'=>self::field('Internet / TV (bestätigt)', 'text', 'construction', help:'Zum Beispiel Glasfaser, Kabel, DSL oder Satellit; keine Verfügbarkeit vermuten.'),
                'photovoltaics'=>self::field('Photovoltaik vorhanden (bestätigt)', 'checkbox', 'construction'),
            ]],
            'condition' => ['title'=>'Zustand & Modernisierungen', 'subtitle'=>'Bauzustand nachvollziehbar dokumentieren.', 'icon'=>'tool', 'fields'=>[
                'condition'=>self::field('Gesamtzustand', 'select', 'core', options:['gepflegt','neuwertig','erstbezug','renovierungsbedürftig','sanierungsbedürftig','modernisiert','teilweise modernisiert','saniert','Erstbezug nach Sanierung','Rohbau','baufällig','unbekannt']),
                'lastModernisationYear'=>self::number('Letzte größere Modernisierung (Jahr)', 'core', 1900, $year, '1'),
                'defects'=>self::field('Baumängel / Auffälligkeiten', 'textarea', 'core', maxlength:5000, help:'Bekannte Mängel sachlich beschreiben. Ein leeres Feld ist keine Bestätigung der Mangelfreiheit.'),
                'notes'=>self::field('Interne Objektnotizen', 'textarea', 'core', maxlength:5000),
            ]],
            'pricing' => ['title'=>'Preis & Provision', 'subtitle'=>'Preisgrundlagen und vereinbarte Provision getrennt festhalten.', 'icon'=>'euro', 'fields'=>[
                'priceEur'=>self::number('Kaufpreis (EUR)', 'price', 0.01, 1000000000),
                'rentEur'=>self::number('Monatliche Nettokaltmiete (EUR)', 'price', 0.01, 1000000000),
                'serviceChargeEur'=>self::number('Monatliche Nebenkosten (EUR)', 'price', 0, 1000000000),
                'annualNetRentEur'=>self::number('Jahresnettokaltmiete (EUR)', 'price', 0, 1000000000),
                'hoaFeeEur'=>self::number('Monatliches Hausgeld (EUR)', 'price', 0, 1000000000),
                'maintenanceReserveEur'=>self::number('Instandhaltungsrücklage (EUR)', 'price', 0, 1000000000),
                'negotiable'=>self::field('Preis als Verhandlungsbasis', 'checkbox', 'price'),
                'insideCommissionPercent'=>self::number('Innenprovision (%)', 'commission', 0, 100),
                'outsideCommissionPercent'=>self::number('Außenprovision (%)', 'commission', 0, 100),
                'commissionVatIncluded'=>self::field('Provisionsangaben inklusive Umsatzsteuer', 'checkbox', 'commission'),
                'insideCommissionNote'=>self::field('Innenprovision: Vereinbarung / Hinweis', 'textarea', 'commission', maxlength:2000),
                'outsideCommissionNote'=>self::field('Außenprovision: Vereinbarung / Hinweis', 'textarea', 'commission', maxlength:2000, help:'Rechtliche Anforderungen und konkrete Provisionsvereinbarung manuell prüfen.'),
                'dataSharing'=>self::field('Freigabevermerk / Datenfreigabe', 'text', 'release', help:'Ein Vermerk ersetzt keine technische oder rechtliche Einwilligung.'),
                'source'=>self::field('Datenquelle / Erfassungsquelle', 'text', 'release'),
            ]],
        ];
    }

    public static function fields(): array
    {
        $fields = [];
        foreach (self::sections() as $section) $fields += $section['fields'];
        return $fields;
    }

    private static function field(string $label, string $type, string $group, array $options = [], bool $required = false, string $help = '', int $maxlength = 250): array
    {
        return compact('label','type','group','options','required','help','maxlength');
    }

    private static function number(string $label, string $group, int|float $min, int|float $max, string $step = '0.01', string $help = ''): array
    {
        return self::field($label, 'number', $group, help:$help) + compact('min','max','step');
    }
}
