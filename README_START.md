# CHASE Partnerportal 2.0 – Codex Starterpaket

Dieses Paket ist ein erweiterter Entwicklungsauftrag samt Referenzdesign und ausschließlich synthetischen Testdaten. Es ist **noch nicht der fertige Anwendungscode**. Codex soll daraus die Anwendung erstellen.

## Kurzanleitung
1. ZIP entpacken und Inhalt in das Codex-Projekt / Git-Repository legen (nicht die ZIP-Datei allein).
2. Neues Codex-Projekt mit Arbeitsverzeichnis dieses Repositories öffnen.
3. `AGENTS.md`, `CODEX_MASTERPROMPT.md` und `design/dashboard_referenz.png` liegen bereits im Root/Designordner.
4. Folgende Startanweisung an Codex senden:

> Lies `AGENTS.md` und `CODEX_MASTERPROMPT.md`, einschließlich aller Dateien in `docs/` und `data/`. Implementiere das CHASE Partnerportal 2.0 als lokal lauffähige Symfony-Anwendung. Beginne mit kurzem Plan und arbeite die P0-Meilensteine eigenständig ab. Priorität: echtes Demo-Login, visuell hochwertiges Dashboard, Objektcockpit mit Demo-onOffice-Import, KI-Textgenerator und durchgehende Workflows. Teste alles, dokumentiere den Status ehrlich und nenne am Ende Startbefehl sowie Demozugang. Keine externen oder kostenpflichtigen APIs verwenden.

## Inhalt
- `CODEX_MASTERPROMPT.md` – vollständiger Auftrag inklusive Features, Reihenfolge, Tests und Sicherheit
- `AGENTS.md` – Entwicklungsregeln für Codex
- `design/dashboard_referenz.png` – visuell abgestimmtes GUI
- `data/*.json` – ausschließlich synthetische Datensätze und CHASE-Designregeln
- `docs/` – Produktkonzept, Integrationsplanung, Datenschutzgrenzen, Roadmap und Referenzen

## Wichtig
Die spätere Anmeldung mit bestehenden CHASE-Website-Zugangsdaten sowie die echte onOffice-Anbindung sind **nicht** im ersten PoC produktiv implementierbar, solange bestehender Symfony-Code, Identitätsprovider und API-Berechtigungen unbekannt sind. Der PoC erhält sichere Demo-Logins, eine realistische Mock-onOffice-Anbindung und einen technischen Integrationspfad. Keine echte Immobilienbewertung, keine rechtliche Dokumentenprüfung und keine fingierten KI-Ergebnisse.
