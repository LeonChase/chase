<?php
namespace App\Service;

use App\Integration\TextGenerationProvider;

/** Deterministic, entirely local composition from explicit facts. No AI or invented data. */
final class DemoTextProvider implements TextGenerationProvider
{
    public const TOOLS = ['expose' => 'Exposé-Text', 'lage' => 'Lagebeschreibung', 'highlights' => 'Objekt-Highlights', 'social' => 'Social Post', 'email' => 'E-Mail-Entwurf', 'blog' => 'Ratgeber / Blog'];
    public const TONES = ['sachlich' => 'Sachlich', 'hochwertig' => 'Hochwertig', 'emotional' => 'Einladend', 'kurz' => 'Kurz & klar'];

    public function generate(string $tool, array $facts, string $tone = 'sachlich', array $options = []): array
    {
        if (!isset(self::TOOLS[$tool], self::TONES[$tone])) { throw new \InvalidArgumentException('Unbekanntes Textwerkzeug oder unbekannter Stil.'); }
        $clean = static fn ($value): string => trim(strip_tags(is_scalar($value) ? (string) $value : ''));
        $known = static fn ($value): bool => $value !== null && $value !== '';
        $format = static fn ($value): string => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
        $title = $clean($facts['title'] ?? '');
        $type = $clean($facts['propertyType'] ?? '');
        $city = $clean($facts['city'] ?? '');
        $district = $clean($facts['district'] ?? '');
        $headline = $title ?: ($type ?: 'Immobilie').($city ? ' in '.$city : ' – Ort: Angabe fehlt');
        $area = $facts['areaSqm'] ?? null;
        $rooms = $facts['rooms'] ?? null;
        $features = $facts['features'] ?? [];
        if (is_string($features)) { $features = preg_split('/[,;\n]+/', $features); }
        $features = array_values(array_filter(array_map($clean, is_array($features) ? array_slice($features, 0, 20) : [])));
        $highlights = [];
        if ($known($area) && is_numeric($area)) { $highlights[] = $format($area).' m² Wohnfläche'; }
        if ($known($rooms) && is_numeric($rooms)) { $highlights[] = $format($rooms).' Zimmer'; }
        $highlights = array_merge($highlights, $features);
        $location = 'Ort: '.($city ?: 'Angabe fehlt').($district ? ', '.$district : '').'.';
        $factsText = 'Objektart: '.($type ?: 'Angabe fehlt').'. '.$location."\nWohnfläche: ".($known($area) ? $format($area).' m²' : 'Angabe fehlt').'. Zimmer: '.($known($rooms) ? $format($rooms) : 'Angabe fehlt').'.';
        if ($known($facts['yearBuilt'] ?? null)) { $factsText .= ' Baujahr: '.$clean($facts['yearBuilt']).'.'; }
        $price = $facts['priceEur'] ?? null;
        $factsText .= "\nPreis: ".($known($price) ? $format($price).' €' : 'Angabe fehlt').'.';
        $factsText .= "\nAusstattung: ".($features ? implode(', ', $features) : 'Angabe fehlt').'.';
        $verifiedLocation = $clean($facts['verifiedLocation'] ?? '');
        $cta = $clean($options['cta'] ?? '') ?: 'Weitere Informationen und einen Besichtigungstermin auf Anfrage.';
        $intro = match ($tone) { 'hochwertig' => 'Ein strukturierter Überblick für Ihre Immobilienentscheidung.', 'emotional' => 'Lernen Sie diese Immobilie kennen.', 'kurz' => 'Die Fakten im Überblick.', default => 'Informationen zur Immobilie.' };
        $text = $intro."\n\n".$factsText."\n\n".$cta;
        if ($tool === 'lage') {
            $headline = 'Lage – '.($city ?: 'Ort: Angabe fehlt');
            $text = $location."\n\n".($verifiedLocation ? 'Vom Nutzer bestätigte Lagefakten: '.$verifiedLocation : 'Weitere verifizierte Lagefakten: Angabe fehlt.')."\n\nInfrastruktur, Entfernungen und Verkehrsanbindung: Angabe fehlt, sofern oben nicht ausdrücklich bestätigt.";
        } elseif ($tool === 'highlights') {
            $text = $highlights ? 'Bestätigte Objektmerkmale:' . "\n• ".implode("\n• ", $highlights) : 'Bestätigte Objektmerkmale: Angabe fehlt.';
        } elseif ($tool === 'social') {
            $text = $headline."\n\n".($tone === 'kurz' ? implode(' · ', $highlights) : $factsText)."\n\n".$cta."\n\n#CHASEImmobilien #Immobilien #Demo";
        } elseif ($tool === 'email') {
            $text = 'Betreff: Informationen zu '.$headline."\n\nGuten Tag,\n\ngern stellen wir Ihnen die vorliegenden Objektdaten vor.\n\n".$factsText."\n\n".$cta."\n\nFreundliche Grüße\nIhr CHASE Partnerteam\n\nEntwurf – Empfänger, Pflichtangaben und Signatur vor Verwendung ergänzen. Kein Versand.";
        } elseif ($tool === 'blog') {
            $topic = $clean($options['topic'] ?? '') ?: 'Immobilienverkauf vorbereiten';
            $headline = $topic;
            $text = $topic."\n\nRedaktioneller Demo-Entwurf zur weiteren fachlichen Bearbeitung.\n\n1. Objektunterlagen sammeln\nStellen Sie vorhandene Unterlagen zusammen und dokumentieren Sie deren Quelle und Aktualität.\n\n2. Angaben prüfen\nGleichen Sie Flächen, Ausstattung und Energieangaben mit den vorliegenden Nachweisen ab. Fehlende Informationen als offen kennzeichnen.\n\n3. Vermarktung abstimmen\nKlären Sie Zielgruppe, Freigaben und nächste Schritte mit den Beteiligten.\n\nKonkrete Marktpreise, rechtliche Einschätzungen und regionale Marktdaten: Angabe fehlt.\n\n".$cta;
        }
        $checklist = ['Alle Angaben mit der Quelle und dem Eigentümer abgleichen.', 'Energieausweisart, Kennwerte, Energieträger, Baujahr und Pflichtangaben fachlich prüfen.', 'Preis, Flächen, Bildrechte und Freigaben vor externer Verwendung prüfen.', 'Keine automatische Veröffentlichung oder E-Mail. Menschliche Freigabe erforderlich.'];
        $energy = $facts['energyData'] ?? null;
        if (!$energy) { $checklist[] = 'Energiekennwerte: Angabe fehlt.'; }
        return ['headline' => $headline, 'text' => $text, 'highlights' => $highlights, 'checklist' => $checklist, 'mode' => 'Regelbasierte Offline-Demo'];
    }
}
