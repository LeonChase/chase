# Usability-Update 2.3 · 2. November 2026

## Bedienung

| Bereich | Neuer Stand |
| --- | --- |
| Dashboard | Sechs gleichwertige Kacheln: Immobilien, Bewertung, KI-Tools; darunter Dokumente, Academy, Werbekatalog. Keine hervorgehobene Bewertung. |
| Navigation | Standardmäßig schmale Symbolleiste. Öffnet bei Maus- und Tastaturfokus; Fixierung bleibt nach Neuladen erhalten. Mobil weiterhin Menüknopf und Drawer. Immobilien und Aufgaben kürzer benannt, Partnernetzwerk und Links direkt erreichbar; Verwaltung nur für Admins. |
| Immobilien | Acht Kapitel mit kurzen Maskenseiten statt aufklappenden Tabellen. Ansprechpartner, Eigentümer und Hausverwaltung getrennt; Modernisierungen einzeln durchblättern. Alle 95 Felder erhalten. Nur notwendige Angaben sind Pflicht; weitere Seiten können direkt angewählt und später ergänzt werden. Speichern ist jederzeit möglich. Fehler führen zum richtigen Feld. Bilder und KI-Werkzeuge öffnen Dialoge. |
| Bewertung | Sachwert, Ertrag und Vergleich als Verfahren-Tabs, passende Eingaben links und Ergebnisse rechts. Annahmen, Quellen und Rechenwege in Dialogen. Live-Berechnung, Objektübernahme und PDF bleiben erhalten. |
| Leads | Fünf Phasen: Neu, Kontaktiert, Termin, Auftrag, Verloren. Alte „Qualifiziert“-Datensätze erscheinen unter Kontaktiert, bleiben bei bloßem Lesen unverändert und verlieren ihre Informationen nicht. |
| Aufgaben | Datum rechts neben Titel, vereinfachte Bearbeitung mit Wiederholung und Notizen. Zuordnungen optional. Bereits gespeicherte Status, Prioritäten und Checklisten bleiben erhalten, auch wenn diese Felder im vereinfachten Formular fehlen. |
| KI-Tools | Eigenständiger Werkzeugkasten unter `/tools`; weiterhin auch aus der Objektakte nutzbar. Texte als regelbasierte Demo mit Versionen, private Dokumente mit manueller Prüfliste; Bildbearbeitung funktioniert ohne Objekt direkt im Browser. |
| Bildbearbeitung | Tatsächliche lokale Helligkeit, Kontrast, Sättigung, Drehung, Reset und PNG-Download. Dabei wird das Bild weder hochgeladen noch an einen KI-Anbieter gesendet. Kein behauptetes Staging-/KI-Ergebnis. |
| Werbekatalog | 42 Originalmotive mitgeliefert, Bilder ohne Poppler oder PDF-Import sichtbar. Keine wiederholten Preis-Platzhalter; freie Mengen mit eigenen Plus/Minus-Knöpfen. Startmenge 1, keine Mindest- oder Paketmengen; technisch bis 999.999.999. Sortierung, Kategorien, Merkliste und persönliche Auswahl bleiben erhalten. Produktverwaltung nur für Admins. |
| Dokumente | Neue ZIP geprüft und übernommen: 99 Dateien unverändert, zwei PSD-Schablonen aktualisiert. Der neue separate Werbekatalog ersetzt die kanonische Katalog-PDF ohne Produktdublettierung. Originalunterlagen werden bevorzugt angezeigt, ältere Demo-Muster sind zuschaltbar; eigene Dokumente bleiben sichtbar. |
| Netzwerk | Direkter Menüpunkt mit eigener interaktiver schematischer Karte, Partnerauswahl, Zoom und Listenansicht. Ausschließlich freigegebene aktive Portalprofile; Demo-Regionspunkte sind ausdrücklich keine Bürostandorte. |
| Ansprechpartner | Kompakte Karte mit dem gewünschten Namen Linda Feinberg und dem belegten allgemeinen Partnermanagementkontakt. Ein Namensavatar ersetzt das noch nicht belegte persönliche Foto. |
| Links & Tools | 14 kuratierte externe Websites, Kategorien, Suche und persönliche Favoriten. Keine automatische Anmeldung, Datenübertragung oder Buchung. |

Academy, Chat und die bestehenden Admin-Funktionen wurden inhaltlich nicht erweitert. Externe Bild-KI, Staging, Grundriss-Neuzeichnung, sichere automatische Schwärzung und Videoerzeugung bleiben ohne Providerzugang als Vorbereitung gekennzeichnet.

## Auf Windows / Ubuntu aktualisieren

Den laufenden Server in Ubuntu mit **Strg+C** beenden. Dann:

```bash
cd ~/chase
git pull --ff-only
./scripts/setup
./scripts/start
```

Im Browser **http://localhost:8000** öffnen und **Strg+F5** drücken. Version **2.3** steht in der aufgeklappten Seitenleiste. Der Starthelfer erneuert jetzt die Service-Konfiguration und führt ausstehende Migrationen aus, bevor automatische Unterlagenbefehle aufgerufen werden. Das verhindert den zuvor gemeldeten Fehler mit einem nach Updates noch unbekannten Befehl. Die Routerkorrektur aus 2.2 bleibt enthalten.

Die Katalogbilder sind bereits im Update enthalten. Für die privaten Originalunterlagen sollten **Unterlagen.zip** und optional **Werbekatalog.pdf** im Downloads-Ordner des aktuellen Windows-Nutzers liegen. Setup/Start erkennt diese sowie `Unterlagen_Co.zip` und private Projektablagen automatisch. Die Originale werden nicht auf GitHub veröffentlicht. Ein fehlendes Originalarchiv lässt sich außerdem weiterhin über den geschützten Admin-Import übernehmen.

## Quellen und Grenzen

Die öffentliche CHASE-Website sowie Propstack/onOffice waren aus der Arbeitsumgebung nicht abrufbar (HTTPS-Proxy: CONNECT 403). Ein aktueller visueller Websiteabgleich, Lindas persönliches Foto und eine aktuelle Liste echter CHASE-Standorte konnten deshalb nicht verifiziert werden. Keine dieser Angaben wurde erfunden.

Der allgemeine Kontakt `partner@chase-immobilien.de` / `05151 91 91 228 02` stammt aus „Onboarding abgeschlossen.pdf“, Seite 1. Er wird ausdrücklich als Partnermanagementkontakt verwendet, nicht als belegte persönliche Adresse von Linda. Nachweise: [Öffentliche Quellenprüfung](public-source-check.md).

Die schematische Karte zeigt bei den vier Demo-Profilen ungefähre Regionsmittelpunkte. Reale Profile erscheinen auf der Karte nur mit hinterlegten Koordinaten; ohne diese bleiben sie in der Liste. Es werden keine Kartendienste, Tracker oder Geocoder geladen. Die Kartenumrisse sind eine eigene schematische Zeichnung.

Die mitgelieferten Katalogbilder sind Ausschnitte aus dem ausdrücklich zur Integration übersandten Werbekatalog. Sie liegen außerhalb von `public/` und verlangen für die Anzeige eine Anmeldung und passende Mandantenrechte. Original-PDFs und die übrigen Unterlagen bleiben privat. Die Vorschaubilder sind keine fertig personalisierten Druckdateien und die Einkaufstasche löst keine Bestellung aus.

## Prüfung

**199 Tests / 5.043 Assertions erfolgreich**, 81 Twig-Vorlagen und Service-Konfiguration gültig, Datenbankschema passend. Chromium prüfte die Immobilienmasken, alle drei Bewertungsverfahren, Speichern/Neuladen, PDF, Bildbearbeitung mit echten Pixelveränderungen/Rotation/PNG-Download, Katalogbilder und Mengen, Aufgaben und neue Navigation.

24 Modulrouten wurden bei 390 Pixeln ohne horizontalen Seitenüberlauf geprüft. Immobilien- und Bewertungsmasken wurden zusätzlich bei 1440 × 900 und 1280 × 720 ohne Seiten-/Eingabepanel-Scrollen geprüft. Umfangreiche Listen bleiben intern scrollbar; Mobilgeräte nutzen natürlichen Dokumentfluss. Keine JavaScript-Fehler oder externen Browseranfragen in den geprüften Abläufen.

Upgradeprobe: `scripts/setup` erzeugte 0 neue Demo-Datensätze; bestehende Datensatzanzahlen, Passwort-Hashes und die lokale Zugangsdaten-Datei blieben unverändert. Der tatsächliche Start über `scripts/start` lieferte Anmeldung und CSS mit HTTP 200.
