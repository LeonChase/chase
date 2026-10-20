# Implementierungsstand · 20. Oktober 2026

Eigenständige Symfony-7.4-LTS-Anwendung. Sämtliche P0-Kernabläufe sind lokal implementiert und getestet. Zusätzlich sind ausgewählte P1-Erweiterungen umgesetzt. Keine Produktivanbindung oder öffentliche Bereitstellung durchgeführt.

## Meilensteine

1. **Abgeschlossen:** Symfony, SQLite, Migrationen, idempotente dev-Fixtures, echte Passwort-/Session-Anmeldung.
2. **Abgeschlossen:** CHASE-Dashboard, sechs Originalkacheln, responsive Sidebar, Dark Mode und Suche.
3. **Abgeschlossen:** Immobilien-CRUD, Objektakte, Statuspipeline, private Dateien und onOffice-Mockimport.
4. **Abgeschlossen:** Regelbasierter Textgenerator, Objektübernahme, Versionierung, Freigabe, Marketingentwürfe und Kalender.
5. **Abgeschlossen:** Leadpipeline, Aufgaben-CRUD, Verknüpfungen, Objektchecklisten.
6. **Abgeschlossen:** Dokumente, Academy, Werbekatalog, News und Partnerverzeichnis.
7. **Abgeschlossen:** Profile, Admin-Center, explizite Teamfreigaben, persönliches Demo-Guthaben und Auditlog.
8. **Abgeschlossen:** P0-Funktionstests, Browser-/Mobilprüfung und Dokumentation. P0-Gate: 70 Tests, 754 Assertions.
9. **Abgeschlossen:** lokale P1-Inhaltsverwaltung, E-Mail-Textentwürfe, Bildregler/Storyboard, tatsächlicher PDF-Musterexport und gespeicherte Landingpage-Vorschauen. Abschluss: 77 Tests, 843 Assertions.

## Funktionsmatrix

| Bereich | Fertig/Lokal/Mock/Geplant | konkrete Route | Test |
|---|---|---|---|
| Login/Logout | Lokal | `/login`, POST `/logout` | echte Formanmeldung, CSRF, falsches Passwort, Logout, RateLimiter konfiguriert |
| Zugriffstrennung | Lokal | alle privaten Routen | Tenant/Owner, direkte Fremd-URLs, Admin ohne Kundendaten, explizites Teamgrant/Revoke |
| Benutzerdeaktivierung/Rollenentzug | Lokal | `/admin/users/{id}` | bestehende Sitzung beim nächsten Zugriff verworfen |
| Dashboard | Lokal | `/` | Kennzahlen aus DB, exakt sechs Kacheln, 3/2/1 Spalten, Desktop-/Mobilscreenshots |
| Globale Suche | Lokal | `/search` | eigene Objekte und freigegebene Dokumente/Academy/News, keine fremden Daten |
| Immobilienverwaltung | Lokal | `/properties`, `/properties/new`, `/properties/{id}` | erstellen, bearbeiten, archivieren, Filter und Statuspipeline |
| Objektakte | Lokal | `/properties/{id}?tab=…` | sieben Tabs; Texte, Dokumente, Marketing, Medien, Checkliste, Aktivitäten |
| Privatdateien | Lokal | POST `/properties/{id}/media`, `/media/{id}` | MIME/Endung, 8-MB-Grenze, geschützter Download, fremdes Objekt 404 |
| onOffice | Mock | `/crm`, POST `/crm/import` | echter lokaler Import/Upsert, zweiter Import ohne Dubletten, Owner-Schutz |
| Textstudio | Demo-Regeln | `/studio`, POST `/studio/generate` | alle sechs Tools und vier Stile, Faktenquelle, fehlende Daten, keine erfundenen Fakten |
| Textversionen/Freigabe | Lokal | `/studio/content/{id}` | speichern, bearbeiten, Version2, Quellen-Snapshot, Freigabestatus |
| Markdown/PDF | Lokal | `/studio/content/{id}/export`, `/studio/content/{id}/pdf` | echter Download, private ACL, PDF-Signatur, sichtbares DEMO/MUSTER, Umlaute |
| Social Creator | Demo-Regeln | `/marketing/new`, `/marketing/{id}/edit` | Kanäle/Formate, bearbeitbarer Entwurf, Vorschau, interne Planung |
| Marketingkalender | Lokal | `/marketing` | Monat/Woche, Datum speichern, Termin sichtbar; keine Veröffentlichung |
| Landingpage-/Widgetvorschau | Lokal/Vorschau | `/marketing/landing-preview` | vier Typen, Titel/CTA ändern, speichern, neu laden; Kontaktfelder deaktiviert |
| Leads | Lokal | `/leads`, `/leads/new`, `/leads/{id}` | anlegen, bearbeiten, Status, Notiz, Objekt-/Aufgabenbezug, löschen |
| Aufgaben | Lokal | `/tasks`, `/tasks/new`, `/tasks/{id}/edit` | CRUD, Erledigen, Fälligkeit, Heute/Überfällig/Woche in Europe/Berlin |
| Objektcheckliste | Lokal | `/properties/{id}?tab=checklist` | Vorlage abhaken, persistieren; keine Zusicherung rechtlicher Vollständigkeit |
| Dokumentencenter | Lokal/Muster | `/documents`, `/documents/{id}/download` | Kategorien, Suche, Favoriten, Musterdownload, rollenabhängige Freigabe |
| Academy | Lokal/Demo | `/academy`, `/academy/{id}` | Lesen, Suche, persönlicher Lernfortschritt und Favoriten |
| Werbekatalog | Lokal/Entwurf | `/advertising` | Merkliste, Mengen, lokale Anfrage speichern, TXT-Export, löschen; keine Bestellung |
| News/Benachrichtigungen | Lokal/Demo | `/news`, `/news/{id}` | Admin veröffentlicht/archiviert, persönlicher Lesestand und ungelesene Anzahl |
| Partnerverzeichnis | Lokal/Demo | `/network` | nur explizit sichtbare synthetische Profile; Profilsichtbarkeit änderbar |
| Bewertungsrechner | Demo-Regeln | `/valuation` | Monatsnettokaltmiete × 12 × eigener Faktor, Eingaben/Rechenweg speichern |
| Profil/Dark Mode | Lokal | `/profile`, POST `/profile/theme` | Name, Passwort nur mit aktuellem Passwort, serverseitiges Theme nach Reload |
| Demo-Credits | Lokal/Demo | `/usage` | eigener Ledger, atomare Buchung/Rollback, serverseitige Budgetsperre |
| Admin-Center | Lokal | `/admin` | Benutzer, Rollen, Regionen, Budget, News, Teamfreigaben, Inhalte, Auditlog |
| Bild-/Grundrissstudio | Lokal/Vorschau, API fehlt | `/studio/visuals`, `/studio/floorplan` | echte lokale Datei-/Bildvorschau, CSS-Regler; keine generierten Bilder oder Maße |
| Video-Storyboard | Lokal/Vorschau, API fehlt | `/studio/video` | Sequenzvorschau aus lokalen Bildern; kein Videoexport |
| Dokumentenprüfung/Schwärzung | Checkliste/Vorschau, API fehlt | `/studio/doccheck`, `/studio/redaction` | lokale Vorschau und Prüffragen; keine Rechtsprüfung oder sichere Schwärzung |

## Externe Integrationen und offener Umfang

- **P2 / nicht verbunden:** echte onOffice-Synchronisation, OpenAI, Bild-/Video-/Grundrissanbieter, sichere PDF-Schwärzung, professionelle Marktdatenbewertung, Meta/LinkedIn-Publishing, Outlook/To Do, Newsletter/Funnels, echte Website-Leadformulare, SSO, Zahlungen und Rechnungen.
- Interfaces und deaktivierte Adapter sind vorhanden. Sie sind keine vollständigen produktiven API-Clients. Die Anwendung führt keine entsprechenden Netzwerkaufrufe aus.
- **Weitere P1-Arbeit:** umfangreichere Checklistenvorlagen, Medienbearbeitung mit gespeicherten Vorher/Nachher-Dateien, zusätzliche MIME-Typen und größere CMS-/Suchfunktionen. Die vorhandenen P0-Workflows hängen davon nicht ab.
- SQLite/JSON-Fachfelder sind für den lokalen Prototyp gedacht. Vor größerem Mehrbenutzerbetrieb Datenmodell, Fremdschlüssel, Betrieb, Uploadprüfung und Datenschutz vertiefen; siehe Architektur.
- Eigenständiger Composer-Sicherheitsaudit war über den Netzwerkproxy blockiert. Paketauflösung und Plattformprüfung funktionieren; das ersetzt keine vollständige Sicherheitsprüfung.

## Gespeicherter Betrieb

Projekt und Laufzeit sind im aktuellen Arbeitsbereich eingerichtet. `scripts/setup` ist wiederholbar; `scripts/start` startet die Anwendung neu. Prozesse werden nicht als dauerhafte Cloud-Konfiguration betrachtet. Die vollständigen Felder `install_script` und `start_skill` wurden erfolgreich im Cloud-Konfigurationsentwurf gespeichert. Für die dauerhafte Übernahme Änderungen in den Umgebungseinstellungen prüfen und speichern, danach die Umgebung veröffentlichen. Dieser Entwurfsspeichervorgang führt keine Skripte aus und veröffentlicht nichts. Eine Veröffentlichung und Wiederherstellung in einer neuen Cloud-Aufgabe ist nicht durchgeführt oder behauptet.
