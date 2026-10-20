# Testprotokoll · 20. Oktober 2026

## Aktuelle Revision 2.1

Abschließender Gesamtlauf nach Zusammenführung:

```bash
./scripts/php bin/console cache:clear --env=test --no-warmup
./scripts/php vendor/bin/phpunit --colors=never
```

**161 Tests, 4.546 Assertions, alle erfolgreich, Exitcode 0.** Laufzeit ca. 17 Sekunden unter PHP 8.4.26. Keine übersprungenen oder deaktivierten Tests. Isolierte Testdatenbank; Originalunterlagen und eigene Eingaben bleiben unberührt.

Neue Prüffälle decken die gegliederte Objektmaske, Feldvalidierung und Modernisierungen, privaten Mehrfachupload mit Metadaten/Sortierung/Originalerhalt, drei faktenbasierte Textabschnitte (je maximal 2.000 Zeichen), bestätigte Bildbeschriftungen/Quellen, vorbereitete Medien-Workflows ohne Fake-Ergebnisse, sichere ZIP-Übernahme und echte Originaldownloads, alle 441 Lexikoneinträge und Seitenangaben, Katalogauswahl/Preise-auf-Anfrage, private Nachrichten, Profilbilder, Hilfewegweiser, Aufgabenübernahme/Idempotenz/Freigaben, Checklisten/Wiederholungen, lokalen ICS-Austausch und Lead-Kontaktentwürfe ab.

Die Rechenergebnisse der drei Bewertungsverfahren wurden mit dem gelieferten Original-JavaScript für denselben Prüffall verglichen. Alle sechs verglichenen Werte stimmen bis auf numerische Rundung überein; Dokumentation in `valuation-notes.md`. Faktorquellen, Quartale, gespeicherte Eingaben, Quellensnapshots und CSV-Batchvalidierung sind zusätzlich getestet. Es werden keine Live-Marktdaten vorausgesetzt.

**Upgradeprobe:** Die neue Version wurde mit `scripts/setup` auf eine Kopie einer bestehenden Installation angewendet. Eine neue Migration wurde ausgeführt, 0 neue Demodatensätze erzeugt. Bestehende 5 Benutzer, 4 Objekte und der SHA-256 der lokalen Zugangsdaten-Datei blieben unverändert. Die neuen Nachrichten-/Aufgabenanfragetabellen sind vorhanden.

**Materialimport:** 101 Originale einmal importiert; zweiter Import 0 neu / 0 geändert / 101 unverändert. 42 Katalogprodukte zugeordnet und 14 Originalseiten lokal gerendert. Originale und Vorschauseiten bleiben außerhalb von Git in privatem Speicher.

**Browser:** 1440/390 Pixel, Hell/Dunkel, aktualisiertes Logo/Dashboard/Katalog, eigener Warenkorb, private Originalseiten, Objektaufnahme/Medien/Textworkflow, Aufgaben und Leads visuell geprüft. Zwei gefundene mobile Überläufe (Modernisierungstabelle und Pipeline-Label) behoben und nachgeprüft. Screenshots im selben Verzeichnis. Temporäre Browser-Testdaten samt Datei-, Text- und Creditbuchungen wurden anhand ihrer IDs entfernt.

Container, 60 Twig-Templates, Datenbankschema, Composer-Datei/Plattform und JavaScript-Syntax geprüft. Der optionale aktuelle Browser-Smoke prüft 17 sichtbare Modulrouten sowie Anmeldung, Navigation, Dark Mode, Fremdzugriff und Logout. Kein automatischer Aufruf einer produktiven CHASE-Seite und keine externen Browseranfragen.

## Historie der Erstversion

Die nachfolgenden 77 Tests dokumentieren den früheren Entwicklungsstand und sind Bestandteil der erweiterten aktuellen Prüfung.


## Automatisierte Abschlussprüfung

Ausgeführt im Projektverzeichnis `/workspace/chase`:

```bash
./scripts/php vendor/bin/phpunit --colors=never
```

**Ergebnis: 77 Tests, 843 Assertions, alle bestanden, Exitcode 0.** Laufzeit ca. 10 Sekunden. PHPUnit 11.5.57 unter PHP 8.4.26. Es wurden keine Tests übersprungen und keine Fehler als erwartetes Bestehen umdeklariert. Die protokollierten 403/404-Ausnahmen stammen aus den absichtlichen Zugriffstests und werden anhand ihrer Antwort geprüft.

Zusammensetzung: 23 Portal-Funktionstests + 2 Sitzungsentzugstests, 6 Content-Funktionstests, 32 Service-/datengetriebene Tests, 7 Textprovider-Unit-Tests, 7 PDF-/Preview-Funktionstests.

Die Suite verwendet `var/phpunit.db`, getrennt von `var/chase.db`; kein paralleler Lauf gegen dieselbe Testdatenbank.

Abgedeckt: echte Passwortanmeldung mit CSRF, ungültige Anmeldung, Logout, Zugriff ohne Login; Mandanten- und Eigentumsgrenzen, Region allein ohne Freigabe, explizite Teamfreigabe, Admin ohne Kundendatenzugriff; Deaktivierung/Rollenentzug bestehender Sitzungen; Objekt-/Lead-/Aufgaben-CRUD und Relationen; private Uploads, MIME-Ablehnung und sichere Downloads; Import-Upsert ohne Dubletten und ohne Ownerübernahme; regelbasierte Texte in allen Tool-/Stilkombinationen, Quellen, Versionen und Freigaben; Kalendertermine, Lesestände, Merkliste/Lernfortschritt, Such- und Downloadrechte; atomare Guthabenbuchung mit Rollback und Budgetlimit; PDF-Signatur/UTF-8/sichtbare Musterkennzeichnung; private Landingpage-Vorschau samt Validierung/CSRF/Fremdzugriff.

## Weitere ausgeführte Prüfungen

```bash
./scripts/php bin/console doctrine:schema:validate
./scripts/php bin/console lint:container
./scripts/php bin/console lint:twig templates
PATH="$PWD/.tools/bin:$PATH" composer validate --no-check-publish
PATH="$PWD/.tools/bin:$PATH" composer check-platform-reqs
node --check public/assets/app.js
node scripts/browser-smoke.cjs
```

Alle erfolgreich. Datenbankschema und Entity-Mapping stimmen überein. 44 Twig-Templates syntaktisch gültig. Browser-Smoke: 8 Prüfgruppen bestanden, 16 Modulrouten HTTP 200 bei 390 px ohne Seitenüberlauf; echte Anmeldung/Abmeldung, Dark-Mode-Persistenz, mobile Navigation mit Escape, sechs Originalkacheln in drei Spalten und Fremdzugriffsschutz. Keine JavaScript-Fehler und keine externen Browseranfragen.

Browser: vorhandenes Chromium `/usr/bin/chromium` mit Playwright. Desktop 1440 px, Tablet 850 px, Mobil 390 px: Kachelraster 3/2/1 Spalten. Screenshots unter `docs/screenshots/` wurden visuell angesehen.

Weitere tatsächlich ausgeführte Browserabläufe: Objekt anlegen/bearbeiten/archivieren, Lead und Aufgabe anlegen/ändern/löschen, private PNG-Datei hochladen, zweimaliger Mockimport, Exposé erstellen/bearbeiten/freigeben/Markdown exportieren, Social-Entwurf im Oktoberkalender planen, Bewertung berechnen, Academy abschließen, Musterfavorit, Werbemittelanfrage speichern/exportieren/löschen, Admin-News publizieren/archivieren, Benutzer/Region/Budget/Teamgrant verändern. Eigene temporäre QA-Datensätze wurden anschließend gezielt entfernt; die ursprünglichen Fixtures und ein importiertes Mockobjekt bleiben erhalten.

PDF-Export zusätzlich mit `pdftotext` kontrolliert: sichtbares „DEMO / MUSTER“, „KEIN PRODUKTIVES EXPOSÉ“, deutsche Sonderzeichen und belegte Flächenangabe. Vier Landingpage-Typen im Browser geprüft: editierbarer Vorschautext, deaktivierte Kontaktfelder, keine Veröffentlichung.

## Laufzeit und Wiederholbarkeit

Native PHP-Laufzeit aus signierten offiziellen Debian-Paketen ohne Systeminstallation eingerichtet. Vollständiger separater Neuaufbau des Laufzeithelfers sowie Wiederholung bestanden. Composer-Lockdatei verwendet PHP-8.3-kompatible Pakete mit Symfony 7.4 LTS. Abhängigkeiten aus exakten gesperrten Commits installiert; zweite Composer-Installation ohne Änderungen bestanden. TLS-, APT-Signatur- und vorhandene Lock-Prüfsummenprüfung blieben aktiv.

Die Einrichtung über `scripts/setup` wurde zusätzlich aus einer frischen Dateikopie ohne Datenbank, lokale Konfiguration oder Zugangsdaten ausgeführt und wiederholt. Ergebnis: erster Lauf 2 Migrationen / 57 Datensätze; zweiter Lauf keine offenen Migrationen / 0 neue Datensätze. Anschließend startete die frische Kopie auf einem separaten lokalen Port; Anmeldung mit neu generiertem Passwort und Dashboard mit sechs Kacheln erfolgreich. Dateirechte der Zugangsdaten: 0600. Der zusätzliche Testserver wurde danach beendet.

## Nicht als geprüft ausgegeben

Kein Live-onOffice, kein echtes KI-Modell, kein Social Publishing, kein SSO, keine Drittanbieterdateiverarbeitung, keine produktive CHASE-Verbindung, keine Produktionsbereitstellung. Keine Tests unter Windows/macOS oder einer tatsächlich mit PHP 8.3 laufenden Maschine; Kompatibilitätsauflösung ist auf PHP 8.3 festgelegt, ausgeführt wurde PHP 8.4.26. Ein separater `composer audit` war wegen Proxy-403 auf dem Packagist-Sicherheitsendpunkt nicht möglich; keine vollständige Sicherheitszertifizierung behauptet.
