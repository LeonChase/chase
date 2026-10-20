# Überarbeitung des Partnerportals · Oktober 2026

Grundlage: Änderungsauftrag aus „Eingefügter Text.txt“ und bereitgestelltes Paket „Unterlagen_Co.zip“. Bestehende Datensätze und lokale Passwörter werden erhalten. Der neuere Änderungsauftrag ersetzt die vorherige Navigation und Modulpositionierung; die alte Studio-/Marketinglogik bleibt aus Kompatibilitätsgründen im Backend erreichbar.

| Wunsch | Umsetzung | Grenzen |
|---|---|---|
| Ruhigeres, planerisches Dashboard | Tagesfokus, fällige Aufgaben, Wochenübersicht; keine Hero-Buttons „Objekt anlegen“/„Exposé erstellen“, keine Beitragskennzahl | Kennzahlen stammen weiterhin aus berechtigten lokalen Daten |
| Weniger separate Arbeitsbereiche | KI-Studio, Marketing & Social sowie News & Netzwerk aus der Hauptnavigation entfernt; Bewertung unter Immobilien | Objektbezogene Werkzeuge ersetzen den Einstieg über separate Module |
| Immobilienaufnahme auf einer Seite | Gegliederte Abschnitte für Start, Grunddaten, Ausstattung/Energie, Zustand/Modernisierung, Bilder, Unterlagen, Preis/Provision, Texte und Vermarktung | Kein Anspruch, alle kundenspezifischen onOffice-Felder zu kennen; öffentliche API-Metadaten konnten im Netzwerk nicht abgerufen werden |
| Medien im Objekt | Mehrfachupload, Sortierung, Kategorien und Beschriftungen; Originale privat erhalten | Keine Bilder oder Unterlagen werden an Drittanbieter übertragen |
| Exposé aus Objektfakten | Drei feste Abschnitte Objektbeschreibung/Ausstattung/Lage, jeweils maximal 2.000 Zeichen, Quellen-Snapshots und Versionsfreigabe | Regelbasierter lokaler Generator; keine echte Bildanalyse. Nur ausdrücklich bestätigte Bildbeschriftungen werden als manuelle Fakten verwendet |
| Bildoptimierung/Home Staging | Auswahl tatsächlicher Objektbilder, lokale Helligkeits-/Kontrastvorschau und gespeicherte Bearbeitungswünsche/Stile | Personenentfernung, Perspektivkorrektur, Himmeltausch und Home Staging erzeugen ohne echten Anbieter kein Ergebnis |
| Grundriss und Dokumentenprüfung | Objektgebundene Auswahl vorhandener Dateien, Prüffragen und vorbereitete Workflows | Keine rechtliche Prüfung, automatische sichere Schwärzung, neue Maße oder fertige Grundriss-KI behauptet |
| Vermarktung | Kurzer objektbezogener Social-Entwurf, Quellenbilder und lokales Storyboard; interne Abstimmung über Nachrichten | Kein Videoexport, Portalversand, Interessenten-Massenversand oder Social Publishing ohne geprüfte Verbindung |
| Akquise/Leads | Lokale Pipeline, schnelle Erfassung, Aktivitäten und Folgeaufgaben; vorbereitete Kontaktentwürfe | Hausgold, Aroundhome und Privatanzeigen-Marktabgleich sind nicht verbunden; keine unerlaubte automatische Datensammlung |
| Immobilienbewertung | Sachwert, Ertragswert und Vergleichswert aus dem gelieferten HTML-Rechner übertragen; Objektübernahme und Rechenwege | Quellenregister und lokale CSV-Übernahme statt erfundener automatischer Quartalswerte; Angaben müssen fachlich geprüft werden |
| Unterlagenstruktur | Neun Originalkategorien mit Unterordnern, Dateityp, Suche, Sortierung, Uploaddatum, Favoriten und sicheren Originaldownloads | Originale bleiben unter var/materials privat und werden nicht im Git-Repository veröffentlicht |
| Academy/Lexikon | 441 Originalbegriffe mit Definition und Quellen-Seite; globale Suche; private Video-Uploads | Keine erfundene Zertifizierung; hochgeladene Videos müssen eigene passende Rechte haben |
| Virtueller Werbekatalog | 14 Originalseiten, Seitenwechsel/Suche, 42 aus dem Katalog übernommene Produkte, Mengen, persönliche Auswahl und lokale Angebotsanfragen | Kein Preisfeed und keine Bestellung bei Flyeralarm. Im gelieferten Katalog stehen keine verbindlichen Preise |
| Kommunikation/News | Chatblase mit echten lokalen internen Nachrichten; Unternehmensnews im Kopfbereich | Nachrichten sind keine externen E-Mails. Morning-Briefing-/Marktfeeds bleiben ohne passende lizenzierte Quelle unverbunden |
| Aufgabenplanung | Aufgaben und Kalender, Fälligkeitsfilter, Checklisten, wiederkehrende Aufgaben, interne Übernahmeangebote und lokale Kalenderdateien | Keine Behauptung vollständiger Microsoft-To-Do-Funktionsgleichheit; Google/Outlook nicht live synchronisiert |
| Profil/Marke/Hilfe | Originales CHASE-Logo, privates Profilbild auch in eigener Landingpage-Vorschau; Guthabenlink aus Profil entfernt; lokaler Hilfewegweiser | Guthabenprüfung bleibt serverseitig; Hilfe ist ein lokaler Wegweiser mit Lexikon, kein externer KI-Chat |

## Originalmaterialien sicher übernehmen

Im Admin-Center **Unterlagen importieren** öffnen und das ursprüngliche ZIP wählen. Der Server prüft das gesamte Archiv vor dem Import auf unsichere Pfade, Verknüpfungen, Anzahl, Größen und auffällige Kompression. Dateien werden in einem mandantengebundenen privaten Verzeichnis über ihren SHA-256-Inhalt gespeichert. Ein erneuter Import erzeugt keine Dubletten.

Im vorbereiteten Arbeitsbereich wurden **101 Originaldateien** importiert. Der zweite Import ergab **0 neu, 0 geändert, 101 unverändert**. Zusätzlich wurden 42 Katalogeinträge zugeordnet. Das Originallexikon umfasst 441 extrahierte Begriffe auf den Seiten 2–21. Die Dateien enthalten bereitgestellte CHASE-Vorlagen und werden entsprechend von synthetischen Fixtures unterschieden; ihre rechtliche Aktualität wird nicht zugesichert.

Für eine blätterbare PDF-Vorschau werden lokal `pdfinfo` und `pdftoppm` aus **poppler-utils** verwendet. Ohne diese Programme bleiben Produktauswahl und geschützter Original-PDF-Download verfügbar. Unter Ubuntu:

```bash
sudo apt install -y poppler-utils
```

Danach kann ein Admin im Werbekatalog **Lokale Blättervorschau vorbereiten** auswählen. Für lokale Administratoren ist alternativ verfügbar:

```bash
./scripts/php bin/console app:materials:import '/Pfad/Unterlagen_Co.zip'
./scripts/php bin/console app:catalog:preview
```

Das Lexikon benötigt beim normalen Betrieb weder Python noch Poppler. `scripts/build-lexicon` ist nur ein optionales Werkzeug für die erneute Aufbereitung einer späteren Lexikon-PDF.

## Aktualisierung auf dem vorhandenen Windows-/Ubuntu-Rechner

Den laufenden Server im Ubuntu-Fenster mit **Strg+C** anhalten. Dann im gleichen Fenster:

```bash
cd ~/chase
git pull --ff-only
./scripts/setup
./scripts/start
```

Anschließend die bereits bekannte lokale Browseradresse öffnen und die Seite neu laden. Die vorhandenen Zugangsdaten bleiben erhalten. Für die Originaldateien einmal mit dem Administrationskonto anmelden und unter **Admin-Center → Unterlagen importieren** die ZIP-Datei direkt von Windows auswählen. Es werden keine vorhandenen Objekte, Leads oder Passwörter zurückgesetzt.

Der Startbefehl erlaubt nun ZIP-Uploads bis 32 MB; die strengeren Grenzen für einzelne Objektdateien (8 MB), Profilbilder (4 MB) und Lernvideos (12 MiB) werden separat geprüft.

## Bewertungsquellen und onOffice

Siehe [Bewertungsübertragung](valuation-notes.md) für Originaldatei-Hash, Rechenregeln und direkte Vergleichswerte des Original-JavaScripts mit dem PHP-Service. Faktorwerte sind explizite Annahmen oder vom Nutzer mit Quelle/Quartal erfasste Werte. Das Portal täuscht keine automatische Aktualisierung von Bodenrichtwerten, Liegenschaftszinsen oder Sachwertfaktoren vor.

Die Anforderung an öffentliche onOffice-Feldmetadaten wurde geprüft; der direkte Dokumentationsabruf wurde vom Netzwerkproxy mit 403 blockiert. Die Objektmaske basiert deshalb auf dem Änderungsauftrag und den gelieferten Aufnahmebögen. Der spätere echte Adapter muss kundenspezifische Felddefinitionen und Rechte aus dem gebuchten onOffice-Zugang berücksichtigen. Es wurde keine Verbindung zum produktiven CHASE-System oder einem kostenpflichtigen API-Dienst aufgebaut.

Objektbezogene interne Nachrichten setzen eine ausdrückliche Objektberechtigung des Empfängers voraus. Ein Versand oder ein Link allein erteilt keine zusätzlichen Kundendatenrechte.
