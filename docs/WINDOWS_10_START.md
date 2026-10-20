# Start unter Windows 10

Diese Anleitung beschreibt den vorgesehenen Weg über WSL und Ubuntu 24.04. Die Anwendung wurde unter Linux getestet, nicht auf einem echten Windows-10-Rechner. Bei einer Fehlermeldung bitte den genauen Wortlaut weitergeben, ohne Passwörter mitzuschicken.

## 1. Ubuntu einrichten

PowerShell als Administrator öffnen und ausführen:

```powershell
wsl --install -d Ubuntu-24.04
```

Falls Windows einen Neustart verlangt, neu starten. Anschließend im Startmenü **Ubuntu 24.04** öffnen. Beim ersten Start einen selbst gewählten Linux-Benutzernamen und ein Passwort vergeben. Bei der Passworteingabe werden keine Zeichen angezeigt; das ist normal. Dieses Passwort ist für Ubuntu, nicht für CHASE.

Voraussetzung ist eine ausreichend aktuelle Windows-10-Version mit WSL-Unterstützung. Falls der Befehl nicht unterstützt wird oder Virtualisierung fehlt, zuerst die konkrete Fehlermeldung klären.

## 2. Benötigte Programme installieren

Im Ubuntu-Fenster:

```bash
sudo apt update
sudo apt install -y php-cli php-sqlite3 php-mbstring php-xml php-curl php-intl php-zip composer git unzip
php -v
```

PHP muss mindestens Version 8.3 haben. `sudo` kann nach dem zuvor selbst gewählten Ubuntu-Passwort fragen.

## 3. Projekt herunterladen und nach Ubuntu kopieren

Auf GitHub das Repository `LeonChase/chase` öffnen und **Code → Download ZIP** wählen. Das ZIP unter Windows vollständig entpacken.

Das Projekt soll in Ubuntus eigenem Dateisystem liegen. Im Windows-Explorer `\\wsl.localhost\Ubuntu-24.04\home` öffnen, dann den Ordner mit dem eigenen Linux-Benutzernamen. Den entpackten Projektordner dorthin kopieren und in **chase** umbenennen. Im Ordner `chase` müssen direkt `composer.json`, `README.md`, `scripts` und `src` liegen; keinen zusätzlichen äußeren ZIP-Ordner stehen lassen.

Falls der Explorer-Pfad auf deinem Rechner anders lautet, im Ubuntu-Fenster zuerst `cd ~` und dann `explorer.exe .` ausführen. Das öffnet den richtigen Ordner.

## 4. CHASE einrichten

Im Ubuntu-Fenster:

```bash
cd ~/chase
chmod +x scripts/setup scripts/start scripts/php scripts/demo-access scripts/bootstrap-runtime scripts/cache-composer-dist bin/console
./scripts/setup
./scripts/demo-access
./scripts/start
```

Die Einrichtung erzeugt die Datenbank und zufällige lokale Demo-Passwörter. Für den ersten Rundgang das Konto **max.mustermann@example.test** verwenden. Das zugehörige Passwort zeigt `demo-access`. Für die Verwaltung ist **admin@example.test** vorgesehen.

## 5. Im Windows-Browser öffnen

Die Adresse `http://localhost:8000` in Edge, Firefox oder Chrome eingeben. Das Ubuntu-Fenster mit dem laufenden Server offen lassen. Die Adresse funktioniert auf deinem Rechner; sie ist keine öffentliche Website.

Zum Beenden im Ubuntu-Fenster **Strg+C** drücken. Für spätere Starts genügt:

```bash
cd ~/chase
./scripts/start
```

Falls `localhost` in einer älteren WSL-Konfiguration nicht erreichbar ist, den Fehler zuerst gemeinsam prüfen; keine Firewall oder Sicherheitsprüfung pauschal abschalten.

## Wichtig

- Daten liegen lokal unter `var/`. Diesen Ordner nicht löschen, wenn eigene Eingaben erhalten bleiben sollen.
- Keine echten CHASE-Website-Zugangsdaten verwenden.
- Demopasswörter nicht öffentlich weitergeben und nicht zu GitHub hochladen.
- Echte KI-, CRM- und Veröffentlichungsdienste sind deaktiviert.
