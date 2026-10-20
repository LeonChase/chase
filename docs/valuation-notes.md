# Immobilienbewertung: Portierung des vorhandenen CHASE-Rechners

Stand: 20.10.2026. Die neue Anwendung führt die drei Berechnungen auf dem lokalen Symfony-Server aus. Das hochgeladene HTML wird weder eingebettet noch ausgeführt; es lädt keine Bilder, Skripte oder Quellen von der produktiven CHASE-Website.

## Herkunft und fachliche Grenze

Berechnungsquelle ist die tatsächlich hochgeladene Datei `CHASE ImmoWertV-Rechner.html` aus „3 - Objektaufnahme & Bewertung“; eine zweite Kopie lag im Materialpaket unter „8 - Links & Tools“ vor. SHA-256 der ausgewerteten Datei:

`d6fca40699dfa07f14dc18dc2d79ca3c831c26e1b304d2bc746399ca0a4cc988`

`src/Service/ValuationCalculator.php` übernimmt deren Formeln. Das belegt keine fachliche Zertifizierung oder vollständige Modellkonformität mit ImmoWertV/ImmoWertA. Lage, Bodenrichtwertzone, Objektart, Datenstichtag, Quellenrechte, modellkonforme Faktoren und gegebenenfalls Miteigentumsanteile müssen fachlich geprüft werden. Es ist eine nachvollziehbare Schnellkalkulation, keine belastbare Verkehrswertermittlung und keine KI-Prognose.

## Berechnungen

| Verfahren | Übernommener Rechenweg |
| --- | --- |
| Sachwert | BGF × NHK-2010-Kostenkennwert × Baupreisindexfaktor; davon lineare Alterswertminderung abziehen; Außenanlagen auf den verbleibenden Gebäudewert aufschlagen; Bodenwert addieren; mit Sachwertfaktor multiplizieren; Zu-/Abschläge addieren. |
| Bodenwert | Grundstücksfläche bzw. fachlich zugeordneter Bodenanteil × Bodenrichtwert. |
| Alterswertminderung | (Stichtagsjahr − modifiziertes Baujahr) × jährlicher Prozentsatz; auf 0 bis 100 % begrenzen. |
| Ertragswert | Jahresrohertrag minus pauschale Bewirtschaftungskosten minus Bodenwertverzinsung; Gebäudereinertrag mit Vervielfältiger kapitalisieren; Bodenwert und Zu-/Abschläge addieren. |
| Restnutzungsdauer | Gesamtnutzungsdauer − Gebäudealter; wie im Original auf 0 bis 120 Jahre begrenzen. |
| Vervielfältiger | Bei positivem Zins `((1 + i)^n − 1) / ((1 + i)^n × i)`; bei Nullzins rechnerisch `n`, wobei die ursprüngliche Vollständigkeitsprüfung einen positiven Liegenschaftszins voraussetzt. |
| Vergleichswert | Wohnfläche × eingegebener Vergleichspreis je m² + Zu-/Abschläge. |
| Jahresrohertrag | Expliziter Jahreswert hat Vorrang, andernfalls monatliche Miete je m² × Wohnfläche × 12. |
| Preisorientierung | Eigener Angebotspreis hat Vorrang. Ohne eigenen Preis gilt die aus der HTML-Vorlage übernommene Regel „höchster positiver Verfahrenswert“. Dies ist ausdrücklich keine eigenständige Marktpreisempfehlung. |
| Faktor / Rendite / Courtage | Preis ÷ Jahresrohertrag; Jahresrohertrag ÷ Preis; Preis × eingegebener Courtagesatz. |

Die effektive HTML-Vorbelegung des Baupreisindex ist **198,2**: Das Eingabefeld enthält diesen Wert, und die originale Funktion `syncConfigFromUI()` überschreibt damit den anfänglichen JavaScript-Konfigurationswert 189,6. Weitere unverändert übernommene, editierbare Vorbelegungen sind Außenanlagen 5 %, Bewirtschaftungskosten 20 %, Gesamtnutzungsdauer 80 Jahre und jährliche Alterswertminderung 1,25 %. Diese Zahlen werden als **ungeprüfte Annahmen aus der Vorlage** angezeigt. Sie sind keine Behauptung aktueller amtlicher Werte.

Wie im Original werden Baupreisindex-Eingaben über 10 durch 100 geteilt; kleinere Eingaben werden als unmittelbarer Multiplikator behandelt. Andere Indexbasisjahre dürfen nicht ohne geeignete Umrechnung übernommen werden. Eine tatsächliche Modellprüfung bleibt notwendig.

## Bewusst dokumentierte Korrekturen gegenüber der HTML-Anzeige

- Die automatische Preisorientierung verwendet ausschließlich **vollständig berechenbare** Verfahren. Das Original bezog auch positive Zwischenergebnisse unvollständiger Verfahren ein; dadurch konnte zum Beispiel ein einzelner positiver Zuschlag bereits einen vermeintlichen Angebotspreis erzeugen. Die Formeln vollständiger Verfahren bleiben unverändert.
- Zahlen werden serverseitig validiert. Fehlerhafte Texte, nicht endliche Zahlen und negative Flächen werden nicht stillschweigend in Rechenergebnisse übernommen.
- Ein expliziter Bewertungsstichtag ersetzt das nur implizit verwendete aktuelle Kalenderjahr; der gespeicherte Stand bleibt damit später nachvollziehbar.
- Die Jahresmiete und die Miete je m² überschreiben sich im Portal nicht automatisch gegenseitig. Die dokumentierte Priorität des expliziten Jahresrohertrags bleibt erhalten.

Zahlen akzeptieren Komma oder Punkt als Dezimalzeichen. Eingaben ohne Tausenderpunkte werden empfohlen. Wie im ursprünglichen Parser bedeutet ein einzelner Punkt ein Dezimalzeichen; zum Beispiel ist `300.000` rechnerisch 300, nicht 300000. Das Formular nennt deshalb ausdrücklich die sichere Schreibweise `300000`.

## Nutzung im Portal

- `/properties/{id}/valuation`: Berechnung direkt beim zugänglichen Objekt, erreichbar aus „Preis & Provision“.
- `/valuation?property={id}`: gleiche Funktion mit Objektübernahme.
- `/valuation`: freie Kalkulation und Auswahl eigener/freigegebener Objekte.
- `/valuation/sources`: manuell dokumentierte Faktorquellen und deren Prüfquartale.
- `/valuation/sources/import`: geschützter lokaler CSV-Import, ausschließlich POST mit CSRF.

Aus dem Objekt werden BGF, Wohnfläche, Grundstücksfläche, Baujahr, Jahresnettokaltmiete bzw. Monatskaltmiete, Preis und Außenprovisionssatz übernommen. Ein letztes Modernisierungsjahr wird **nicht** ohne fachliche Ableitung als modifiziertes Baujahr ausgegeben. Bodenrichtwert, Sachwertfaktor, Liegenschaftszins und Vergleichspreise werden nicht erfunden.

„Nur berechnen“ speichert nichts. „Berechnen & speichern“ legt einen neuen unveränderlichen Berechnungsstand mit Eingaben, Stichtag, Rechenschritten, Quellen-Snapshots und Objektzuordnung an. Eine spätere Änderung einer Faktorquelle verändert keine bereits gespeicherte Berechnung. Direktzugriffe auf fremde Objekte, Ergebnisse und Faktorquellen werden serverseitig abgewiesen.

## Quartalsprüfung und Quellen

Der Quellenbereich dokumentiert Faktor, Wert, Region/Zone/Teilmarkt, Prüfquartal, tatsächlichen Datenstichtag, Quellentitel, HTTPS-Link, Modellhinweis und Erfassungszeit. Das aktuelle Quartal wird aus der Zeitzone Europe/Berlin bestimmt. Ein älteres Prüfquartal wird als erneut zu prüfen angezeigt. „Für ein Quartal dokumentiert“ bedeutet **manuell erfasst**, nicht automatisch aktuell oder verifiziert. Quellen veröffentlichen beispielsweise Bodenrichtwerte nicht zwangsläufig quartalsweise.

Ein gespeicherter Quellenwert kann ausdrücklich in das entsprechende Rechenfeld übernommen werden. Wird der Zahlenwert verändert, muss die Quellenzuordnung aufgehoben werden; andernfalls verhindert das Backend eine falsche Quellenbehauptung. Die Auswahl allein attestiert weder Eignung noch Richtigkeit.

CSV-Format: UTF-8, Semikolon, maximal 100 Datensätze/500 KB:

```text
factor;value;region;quarter;source_title;source_url;source_date;note
```

Faktorkennungen: `brw`, `marketFactor`, `lz`, `cfg_bpi`, `compQmPrice`. Alle Zeilen werden vor dem Schreiben geprüft; eine fehlerhafte Zeile führt zum Abbruch des gesamten Imports. Quellenlinks werden ausschließlich gespeichert. Es erfolgt dabei kein Netzwerkzugriff.

Offizielle Anlaufstellen für die fachliche Recherche und eine spätere, gesondert zu prüfende Integration:

- [BORIS-D – Bodenrichtwertinformationen der Länder](https://www.bodenrichtwerte-boris.de/): Zugang zu regionalen Richtwertinformationen; Zone und Stichtag beachten.
- [Gutachterausschüsse Niedersachsen – Grundstücksmarktinformationen](https://www.gag.niedersachsen.de/grundstuecksmarktinformationen): regionale Informationen, Berichte und modellabhängige Faktoren.
- [Statistisches Bundesamt – Baupreise und Immobilienpreisindizes](https://www.destatis.de/DE/Themen/Wirtschaft/Preise/Baupreise-Immobilienpreisindex/_inhalt.html): Indexart, Berichtszeitraum und Basisjahr prüfen.
- [ImmoWertV bei Gesetze im Internet](https://www.gesetze-im-internet.de/immowertv_2022/): gesetzlicher Rahmen; die Bezeichnung der hochgeladenen Vorlage ist keine Zertifizierung dieses Schnellrechners.

**Verbindungsstand:** Nicht verbunden. Leseversuche auf BORIS-D, Destatis und Gesetze im Internet wurden in der Cloud mit HTTP 403 blockiert. Deshalb wurden keine aktuellen Werte, Veröffentlichungsintervalle oder API-Verfügbarkeit aus diesen Seiten abgeleitet. Die Quellenlinks sind Anlaufstellen, keine erfolgreich geprüften Live-Datenfeeds.

Ein späterer automatischer Quartalslauf benötigt je Region eine geklärte, zulässige Datenquelle/API bzw. einen lizenzierten Export, einen passenden Modell-/Objekttyp, ein Basisjahr-Mapping und eine fachliche Freigabe. Ohne diese Voraussetzungen startet das Portal weder Abrufe noch kostenpflichtige Dienste. Der lokale CSV-Import ist bereits bedienbar.

## Prüfung

Die originale JavaScript-Berechnung wurde lokal mit denselben Werten wie der neue PHP-Service ausgeführt: BGF 300, NHK 835, modifiziertes Baujahr 1980, Grundstück 700, Bodenrichtwert 100, Sachwertfaktor 1,1, Wohnfläche 140, Jahresrohertrag 15000, Liegenschaftszins 4 %, Vergleichspreis 2000, Abschlag 10000, Stichtagsjahr 2026. Sachwert **310715,019625**, Ertragswert **229383,0194020321**, Vergleichswert **270000**, Preisorientierung **310715,019625**, Vervielfältiger **18,411197761090445** und Indexfaktor **1,982** stimmen innerhalb von 0,00000001 überein.

Die PHPUnit-Tests unter `tests/Unit/ValuationCalculatorTest.php` und `tests/Functional/ValuationTest.php` prüfen Referenzwerte, Grenzfälle, Mietpriorität, Formularspeicherung, Objekt-/Mandantengrenzen, CSRF, Quellen-Snapshots, CSV-Abbruch und fehlende Live-Verbindungen. Ausführen im normalen Gesamttest mit `./scripts/php vendor/bin/phpunit`.

Prüfung ausgeführt: **27 Tests, 136 Assertions, alle erfolgreich** (19 Unit-Fälle, 8 Funktionsfälle). Ein eigener temporärer Kernel/Cache und `var/valuation-tests.db` hielten den parallelen Gesamttest unberührt. Desktop 1440 px und Mobil 390 px wurden mit Chromium visuell geprüft; die Berechnung zeigte die erwarteten drei Werte, kein horizontaler Überlauf und keine JavaScript-Seitenfehler. Interne Prüfabbildungen: `var/valuation-desktop.png`, `var/valuation-mobile.png`, `var/valuation-sources-mobile.png`.
