# Integrationsplan mit CHASE Website und onOffice

## Bestand erst erheben
1. Welche PHP-/Symfony-Version, welche Twig-/Frontend-Architektur?
2. Wie sind Benutzer und Accounts aktuell gespeichert? Login/Passwort, MFA, bestehender Identity Provider? Rolle eines vorhandenen geschützten Partnerbereichs?
3. Server-/Hostingart, Datenbank, Dateispeicher, HTTPS, Cron/Queue, Backups, Dev/Testumgebung?
4. Git-Repository, Deployment-Pipeline, verfügbare Admin-Berechtigungen? Keine Passwörter per Chat übertragen.
5. Wer betreut Sicherheit, Monitoring und Updates nach dem Go-live?

## Zielarchitektur
- Option A: Geschütztes Portal direkt in bestehender Symfony-App; gemeinsames Usermodell, überprüfte Migrationen, klar abgekapselte Module. Gut, wenn Bestandsstand kompatibel.
- Option B: Subdomain `partner.chase-immobilien.de` als getrennte Symfony-App und OIDC/Identity-Provider. Gut bei größeren Versions-/Releaseunterschieden, jedoch mehr Hosting-/Auth-Aufwand. Entscheidung nach Bestandsaufnahme.
- Keine unauthentifizierte Client-Weitergabe von User-/Sessiontokens; keine direkte Browser-Kommunikation zu externen KI-APIs.

## onOffice
- Herstellerdokumentation: https://apidoc.onoffice.de/
- Offizielles PHP-SDK: https://github.com/onOfficeGmbH/sdk
- API-Verfügbarkeit/-Kosten: Hersteller weist ein kostenpflichtiges Enterprise-Modul aus; Umfang/Zugänge vor Live-Anbindung klären.
- V1: read-only Immobilienimport und nur ausdrücklich freigegebene Datensätze. Verknüpfung `externalId` + Tenant/Owner + source-of-truth; Feldmapping, Pagination, Tombstones/Archivierung, Fehlerbehandlung, Rate-Limits, Importlog, Dublettenschutz.
- Nachgewiesene Objektberechtigung wird **vor** Eintrag in Portal geprüft. Keine universellen API-Schlüssel direkt im Browser. API-Secrets verschlüsselt/Secret Store, Rotations- und Widerrufsprozess.
- Kontakt-/Adress-Synchronisation und Rückschreiben später separat. Keine Duplikation aller CRM-Funktionen.

## Weitere Anbieter später
- OpenAI API: nur Backend-API-Key, Freigaben, Kosten- und Tokenlimits, Retry/Timeout, DLP und protokollierte menschliche Qualitätskontrolle.
- Bild-/Video-Provider: separate Nutzungsbedingungen und geeignete Auftragsverarbeitung prüfen.
- Meta/Instagram/LinkedIn: offizielle OAuth- und Publishing-APIs, Benutzerbestätigung, Plattformrichtlinien, automatisches Publishing erst bei zulässiger Berechtigung.
- Microsoft Graph: Kalender/To Do/Outlook nach getrennten Berechtigungen und ausdrücklicher Zustimmung.

## Webentwickler-Lieferobjekte
Bereinigtes Repository oder technische Dokumentation, Versionen, Testserver, Rechte-/Login-Skizze, vorhandene Datenbanktabellen und Rollenlogik, Build-/Deployprozess. Keine Zugangsdaten oder Kundendatensätze im Codex-Starterpaket.
