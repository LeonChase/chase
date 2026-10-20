# Testprotokoll · 20. Oktober 2026

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
