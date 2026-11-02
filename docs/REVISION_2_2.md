# Update 2.2 · 2. November 2026

Die Oberfläche konzentriert sich auf die Arbeit im jeweiligen Bereich. Am Desktop bleibt das Browserfenster bei den geprüften Größen 1440 × 900 und 1280 × 720 stehen; längere Listen, einzelne große Formulare und der Katalog haben eigene Scrollbereiche. Auf kleinen Mobilbildschirmen bleiben Inhalte durch normales Scrollen erreichbar. Große Datenmengen werden nicht ausgeblendet, um eine starre Bildschirmhöhe zu erzwingen.

| Bereich | Geänderte Bedienung |
| --- | --- |
| Dashboard | Persönliche Du-Begrüßung, Aufgaben und Woche kompakt; sechs ursprüngliche Funktionskacheln; Datum in der Wochenansicht. Optionaler Vorname im Profil. |
| Immobilien | Schritte links; jeweils ein sichtbarer Abschnitt mit Vor/Zurück statt langer Formularseite. Speichern, Wiederöffnen, Dateien und objektbezogene Text-/Medienwerkzeuge bleiben erhalten. |
| Bewertung | Kompakte Eingaben und Ergebniskarten, automatische lokale Neuberechnung, Infoknöpfe und PDF aus den aktuellen Eingaben. Faktoren bleiben manuell bzw. mit einer selbst hinterlegten Quelle belegt. |
| Akquise | Kompakte Pipeline; schnelle Erfassung über Blitzdialog. Integrationshinweise stehen im kleinen Infomenü. |
| Dokumente | Kategorien, Suche und Dateiliste; kleiner Standhinweis. Originale bleiben geschützt außerhalb des öffentlichen Webverzeichnisses. |
| Academy | Onboarding horizontal; gemeinsame Suche für Artikel, Videos und Lexikonbegriffe; separates Lexikon über das Buchsymbol. |
| Werbekatalog | 42 zugeordnete Originaldesigns als Produktkarten mit lokalen Bildausschnitten aus dem gelieferten PDF; Kategorien, Favoriten und Auswahl. Admins können Kategorien ergänzen/umbenennen und eigene Produkte mit privatem Bild hinzufügen. |
| Aufgaben | Drei gespeicherte Ansichten: Liste, geteilt, Kalender. Direktes Hinzufügen und Bearbeiten; Filter und zusätzliche Werkzeuge hinter Icons. Checklisten, Wiederholungen und Übernahmeangebote bleiben erreichbar. |
| Chat | Gesprächsliste und Unterhaltung auf einer Seite. Senden ohne Betreff oder Bestätigungsseite, Wiedereinstieg in bestehende Gespräche, lokale Aktualisierung etwa alle 15 Sekunden. |
| Glocke | Benachrichtigungsleiste mit tatsächlich sichtbaren Aufgaben, heutigen Fälligkeiten, neuen Leads, internen Nachrichten, Aufgabenangeboten und veröffentlichten News; persönliche Gelesen-Markierungen. News öffnen innerhalb der Leiste. |

## Startkorrektur für Ubuntu/WSL

Die ergänzende Korrektur vom 2. November behebt den Startfehler mit `callable object expected` im lokalen PHP-Router. Falls Update 2.2 schon eingerichtet ist, genügen nach **Strg+C** die Befehle `git pull --ff-only` und `./scripts/start` im Projektordner.

## Auf dem eigenen Rechner aktualisieren

Ubuntu öffnen. Einen laufenden Server mit **Strg+C** anhalten und eingeben:

```bash
cd ~/chase
git pull --ff-only
./scripts/setup
./scripts/start
```

Dann **http://localhost:8000** öffnen und bei Bedarf mit **Strg+F5** neu laden. Oben links in der ausgeklappten Seitenleiste steht **2.2**. Ubuntu bleibt während der Nutzung geöffnet. Vorhandene Zugangsdaten und Datensätze bleiben erhalten.

## Originalunterlagen automatisch übernehmen

Die Cloud-Arbeitskopie enthält bereits die 101 gelieferten Originaldateien. Private Originale werden nicht zusammen mit dem Quellcode auf GitHub veröffentlicht.

Auf dem eigenen Rechner sucht Setup/Start nach der vorhandenen **Unterlagen_Co.zip** im aktuellen Downloads-Ordner, in WSL auch im Downloads-Ordner des aktuell angemeldeten Windows-Nutzers. Ebenfalls unterstützt werden private Projektordner `var/materials`, `var/materials/incoming` und `var/materials/source`. Ein bereits vorhandener privater Quellordner kann automatisch verarbeitet werden. Es werden keine anderen Windows-Profile durchsucht. Ein erneuter Import erzeugt keine Dubletten. Fehlt das Archiv lokal, kann der Code es nicht aus dem Chat herunterladen: ZIP im Windows-Downloads-Ordner ablegen oder weiterhin einmal geschützt im Admin-Center auswählen.

Für die Originalbilder im Katalog muss das lokale PDF-Werkzeug vorhanden sein. Unter Ubuntu einmalig bei Bedarf:

```bash
sudo apt install -y poppler-utils
```

Danach `./scripts/start` erneut starten. Ohne dieses Werkzeug bleibt der Katalog bedienbar; die privaten PDF-Bilder können dann noch nicht erzeugt werden. Die Archiv-Erkennung lässt sich für abweichende lokale Ablagen außerdem mit `CHASE_MATERIALS_ZIP` konfigurieren.

## Preise und externe Dienste

Die offizielle Flyeralarm-Seite war bei beiden Prüfungen aus der Arbeitsumgebung nicht abrufbar (Netzwerkproxy: HTTPS CONNECT 403). Es wurden deshalb keine aktuellen Musterpreise erfunden. Stattdessen stehen sinnvolle Ausgangsmengen bereit, etwa 100 Visitenkarten und eine Bauzaunplane. Admins können einen manuellen Musterpreis mit konkreter Menge, Anbieter, URL, Datum und Netto-/Bruttoangabe hinterlegen. Siehe [Preisrecherche](catalog-price-research.md).

Die Auswahl im Katalog ist eine lokale Anfragevorbereitung, keine Bestellung. Es wurden keine Verbindungen zur produktiven CHASE-Website, zu onOffice-Kundendaten oder zu kostenpflichtigen KI-Diensten aufgebaut.

## Prüfung

180 automatisierte Tests mit 4.697 Assertions erfolgreich. Zusätzlich wurden die neuen Chat-, Glocken- und Aufgabenansichten im echten Chromium bedient, 17 Modulrouten mobil geprüft und zehn Hauptbereiche in drei Bildschirmgrößen aufgerufen. Syntaxprüfung aller 70 Twig-Vorlagen, Service-Konfiguration, Datenbankschema und Composer-Plattformanforderungen erfolgreich. Ausführliche Nachweise: [Testprotokoll](TESTPROTOKOLL.md).
