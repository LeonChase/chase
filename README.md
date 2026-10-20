# CHASE Immobilien Partnerportal 2.0

Ein eigenständiger, lokal lauffähiger Symfony-Prototyp für das CHASE-Partnernetzwerk. Die Anwendung speichert Änderungen in einer lokalen SQLite-Datenbank. Alle mitgelieferten Personen, Immobilien, Leads und Nachrichten sind synthetische Demodaten. Es besteht keine Verbindung zur produktiven CHASE-Website.

**Windows 10:** [Schritt-für-Schritt-Anleitung mit Ubuntu/WSL](docs/WINDOWS_10_START.md).

**Überarbeitete Version:** [Neue Abläufe, Originalunterlagen und Update-Anleitung](docs/REVISION_2_1.md).

## Einfach starten

Diese Befehle im entpackten Projektordner in einem Terminal ausführen:

```bash
./scripts/setup
./scripts/demo-access
./scripts/start
```

1. `setup` installiert die benötigten Programmpakete, legt die Datenbank an und erzeugt lokale Demokonten. Unter Debian 13 richtet das Skript bei Bedarf PHP und Composer ohne Administratorrechte im Projektordner ein.
2. `demo-access` zeigt die E-Mail-Adressen und zufällig erzeugten Passwörter. Für den ersten Rundgang **Max Mustermann** wählen. Für die Verwaltung gibt es **admin@example.test**. Keine dieser Angaben gehört zur echten CHASE-Website.
3. `start` startet die Website. Im Browser die Adresse `http://127.0.0.1:8000` eingeben. Das Terminal dabei geöffnet lassen; mit **Strg+C** beenden.

Nach einem Neustart genügt `./scripts/start`. Änderungen bleiben erhalten. Erneutes `setup` erhält vorhandene Objekte, Nutzer und Passwörter. Neue Projektdateien nach einem Update erfordern `./scripts/setup`, um Programmpakete, Datenbankänderungen und Cache zu aktualisieren.

**In der vorbereiteten Cloud-Umgebung** sind Installation und Einrichtung bereits ausgeführt. Die Adresse oben bezeichnet den Rechner, auf dem das Startskript läuft; sie ist kein öffentlicher Link zur Cloud-Umgebung. Die Anwendung wurde nicht ins Internet veröffentlicht.

## Voraussetzungen auf einem eigenen Rechner

- PHP **8.3 oder 8.4**, Composer 2. Die geprüfte Cloud-Laufzeit ist PHP 8.4.26.
- PHP-Erweiterungen: PDO/SQLite, mbstring, XML/DOM, tokenizer, fileinfo und curl; intl und zip empfohlen.
- Mindestens `upload_max_filesize=8M`, `post_max_size=10M`; der lokale Helfer setzt 15M/18M. Eigene Dateiuploads sind auf 8 MB begrenzt.
- Keine Container, keine Node-Installation und keine externe Datenbank für den Betrieb erforderlich.

Unter Windows ist für die Befehle eine Linux-Umgebung (WSL) oder Git Bash mit vorhandenem PHP/Composer nötig. Der optionale automatische PHP-Helfer ist für Debian 13 ausgelegt. Auf anderen Systemen PHP/Composer mit dem jeweiligen Paketmanager installieren. Keine externen API-Schlüssel erforderlich.

Wenn ein Firmenproxy die GitHub-API blockiert, ist ein geprüfter alternativer Paketdownload vorhanden:

```bash
CHASE_CODELOAD_CACHE=1 ./scripts/setup
```

Dieser lädt ausschließlich die exakten in `composer.lock` festgelegten GitHub-Commits vom offiziellen codeload-Dienst. TLS, vorhandene Prüfsummen und APT-Signaturen bleiben aktiv.

## Ein erster Rundgang

- **Dashboard `/`**: Tagesfokus, Wochenplanung, eigene Kennzahlen und die sechs bekannten Werkzeugkacheln.
- **Meine Immobilien `/properties`**: Objekt anlegen und in einer gegliederten Maske auf einer Seite bearbeiten. Bilder, Unterlagen, Preis/Provision, Exposé-Texte und Vermarktung gehören direkt zur Objektakte.
- **Exposé und Medien im Objekt**: drei Textabschnitte mit jeweils maximal 2.000 Zeichen, Bestätigung der Faktenquelle, Versionen, Freigabe sowie Markdown-/PDF-Musterexport. Bilder lassen sich sortieren und beschriften; Bild-KI bleibt ohne Anbieter unverbunden.
- **Bewertung `/valuation`** (auch unter Meine Immobilien): Sachwert, Ertragswert und Vergleichswert gemäß dem gelieferten Rechner, Objektübernahme und dokumentierte Faktorquellen. Kein behaupteter automatischer Marktdatenabruf.
- **Leads `/leads`**, **Aufgaben `/tasks`**: lokale Pipeline sowie Aufgabenliste neben einem Kalender, Checklisten und interne Aufgabenangebote. Kalenderdateien sind lokal importier-/exportierbar; kein Live-Abgleich mit Google/Outlook.
- **Dokumente `/documents`**: neun Arbeitsbereiche mit Unterordnern, Suche, Sortierung, Uploaddatum und privaten Originaldownloads.
- **Academy `/academy`** und **Lexikon `/academy/lexicon`**: Lernpfad, private Lernvideos und 441 Begriffe aus dem bereitgestellten Immobilienlexikon.
- **Werbekatalog `/advertising`**: durch Originalseiten blättern, Designs suchen, Mengen in die persönliche Auswahl legen und einen lokalen Anfrageentwurf für ein Angebot zusammenstellen.
- **Nachrichten `/messages`**, **News `/news`**, **Hilfe `/help/assistant`**: Kontakt und Unternehmensmeldungen über den Kopfbereich; lokaler Hilfewegweiser und Lexikon. Interne Nachrichten sind kein E-Mail-Versand.
- **Profil `/profile`**: eigenes Bild, Name, Passwort und Darstellung. Das Profilbild erscheint auch in der privaten Landingpage-Vorschau.
- **Admin-Center `/admin`**: Benutzer, Rollen, Regionen, explizite Teamfreigaben, Inhalte, Budgets und Auditprotokoll. Unter **Unterlagen importieren** das Original-ZIP einmal lokal übernehmen.
- **onOffice-Demo `/crm`**: wiederholbarer Fixtureimport ohne Dubletten. Kein Live-CRM. Frühere Studio-/Marketingrouten bleiben für vorhandene Entwürfe erreichbar; sie stehen nicht mehr in der Hauptnavigation.

## Anmeldung und Datenschutz im Demo-Betrieb

Passwörter stehen nur lokal in `var/demo-credentials.txt` mit eingeschränkten Dateirechten. In der Datenbank werden ausschließlich Passwort-Hashes gespeichert. Der Seed-Befehl erzeugt Demokonten ausschließlich bei `APP_ENV=dev`. Es gibt keine öffentliche Registrierung und keinen eingebauten Rollenwechsel.

Ein Makler sieht eigene Daten. Eine Regionalleitung sieht Teamdaten erst nach einer ausdrücklichen Freigabe im Admin-Center; dieselbe Region allein genügt nicht. Adminrechte erlauben Inhalts- und Nutzerverwaltung, gewähren aber keinen pauschalen Zugriff auf Kundendaten. Lesestände, Merkliste und Creditkonto bleiben persönlich. Deaktivierung und Rollenentzug beenden bestehende Sitzungen beim nächsten Zugriff.

Dateien werden privat unter `var/uploads` abgelegt. Downloads prüfen Anmeldung, Mandant und Datensatzrechte. Erlaubt: JPG, PNG, WebP und PDF bis 8 MB mit passender Endung und MIME-Typ. Dokumentmuster sind unverbindliche Textbeispiele. Echte Kundendaten gehören vor einer Produktionsprüfung nicht in diese Demoumgebung.

## Technische Befehle

```bash
# Falls PHP nur im Projekt installiert ist:
export PATH="$PWD/.tools/bin:$PATH"

composer install --no-interaction --prefer-dist
./scripts/php scripts/initialize.php
./scripts/php bin/console doctrine:migrations:migrate --no-interaction
./scripts/php bin/console app:demo:seed
./scripts/php bin/console cache:clear
./scripts/php bin/console doctrine:schema:validate
./scripts/php bin/console lint:container
./scripts/php bin/console lint:twig templates
./scripts/php vendor/bin/phpunit
```

Die Tests verwenden eine getrennte `var/phpunit.db` und verändern die Demo-Datenbank nicht. Sie sollten nicht parallel gegen dieselbe Testdatenbank laufen.

Die Datenbank befindet sich in `var/chase.db`; lokale Konfiguration in `.env.local`. Datenbank, Passwörter, Uploads, installierte Pakete und Laufzeitdateien sind gitignoriert. Vor dem Löschen von `var/` eine Sicherung anlegen: Dort liegen eigene Eingaben und Dateien.

## Stand und Grenzen

Siehe [Implementierungsstatus](docs/IMPLEMENTIERUNGSSTATUS.md), [Testprotokoll](docs/TESTPROTOKOLL.md) und [Architektur und Integration](docs/ARCHITEKTUR.md). Bildschirmaufnahmen befinden sich unter `docs/screenshots/`.

Echte onOffice-/OpenAI-Anbindung, professionelle Bild-/Videoverarbeitung, sichere PDF-Schwärzung, Marktdatenbewertung, Social Publishing, Outlook, Zahlungen und Website-SSO sind ausdrücklich **nicht verbunden**. Lokale Vorschauen und regelbasierte Texte werden als solche gekennzeichnet. Es werden keine Daten an diese Dienste geschickt. Ein Produktivbetrieb benötigt unter anderem Hosting-/Sicherheitsprüfung, Backup- und Datenschutzkonzept, Anbieterfreigaben sowie passende Zugangsdaten; diese Anwendung behauptet keine automatische Rechts- oder DSGVO-Konformität.
