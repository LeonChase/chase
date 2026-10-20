# Architektur und Integrationsgrenzen

## Laufender Prototyp

Symfony 7.4 LTS, Twig, Symfony Security/CSRF/RateLimiter/PasswordHasher, Doctrine ORM 3 und Migrationen mit SQLite. PHP-8.3-Kompatibilität ist im Composer-Resolver festgelegt; ausgeführt wurde PHP 8.4.26. Kein Frontend-Build notwendig, CSS und JavaScript lokal. CSP verhindert normale ausgehende Browseranfragen an Drittanbieter; Bilder, Schriften und Skripte stammen aus der App oder der lokalen Dateivorschau.

## Datenmodell und Zuständigkeiten

Eigene Doctrine-Entities und Tabellen: User, Organization, Region, TeamMembership, Property, Lead, Task, Document, AcademyArticle, AdvertisingProduct, ContentPost, NewsPost, PartnerProfile, MediaAsset, UsageEvent, CreditLedger, AuditEvent, IntegrationConnection, UserPreference und Valuation.

Identität, Tenant/Owner, Titel und Zeitstempel sind typisierte Datenbankfelder mit Scope-Indizes. Moduldetails liegen in strukturierten JSON-Feldern. Das ist eine bewusste Prototyp-Vereinfachung: Verknüpfungen werden durch Services und Controller validiert; es bestehen noch keine vollständigen relationalen Fremdschlüssel für jedes optionale Detail. Vor größerem Produktivbetrieb sind häufig abgefragte JSON-Daten, Beziehungen, Fachvalidierung und Aufbewahrung/Löschung weiter zu normalisieren und zu migrieren.

PortalStore begrenzt Listen und einzelne Datensätze serverseitig. RecordVoter unterstützt VIEW/EDIT/DELETE/DOWNLOAD. Eigentum und Tenant sind unabhängig von Region und Adminrolle. TeamMembership enthält managerId/memberId/active und muss vom Admin explizit vergeben werden. Shared-Inhalte haben separate Freigaben; persönliche Einstellungen und Credits bleiben auch gegenüber Regionalleitungen privat.

Symfony Security schützt alle Anwendungsrouten außer Login; statische lokale Assets laufen über den Serverrouter. Alle mutierenden Formulare verlangen CSRF. Deaktivierung, Rollen-/Passwortänderung invalidieren bestehende Sitzungen über EquatableInterface. Dokumente und Originaluploads sind nicht öffentlich gespeichert.

## Lokale Text- und Kostenpipeline

DemoTextProvider implementiert TextGenerationProvider mit deterministischen Vorlagen. Er ergänzt keine unbelegten Objekt-, Infrastruktur- oder Energiefakten. ContentPost speichert Quellen-Snapshot, Entwurf, Status, Versionen und Freigabe. Jede Generierung verbucht UsageEvent und CreditLedger gemeinsam mit dem Inhalt in einer SQLite-Transaktion. Ein Schreiblock und erneutes Lesen von Konto/Guthaben schützen den serverseitigen Budgetcheck. Ein Fehler rollt Inhalt und Verbrauch zurück.

Demo-Credits sind fiktive Einheiten; tatsächliche Providerkosten sind 0 €. Basisbudget pro Nutzer wird administriert. Teamkosten, echte Anbieterpreise, Reservierungen, Rechnungen und Zahlungen sind spätere Aufgaben; niemals Demo-Credits als verbindlichen API-Tarif ausgeben.

## CRM-Import

MockOnOfficeAdapter implementiert CrmProvider und liest ausschließlich `data/demo_onoffice_objects.json`. Nur eigene oder explizit freigegebene Team-Owner desselben Mandanten werden übernommen. Tenant und externe ID bilden eine eindeutige Datenbankidentität. Import ist ein Upsert innerhalb einer Schreibtransaktion; vorhandene Owner werden nicht heimlich geändert.

onOffice ist künftig führend für importierte Fakten. Nur die im Adapter explizit aufgeführten CRM-Felder werden aktualisiert; Portalnotizen, Checklisten, lokale Medien und generierte Texte bleiben erhalten. Die Ansicht zeigt Demo-Verbindungsstatus und Importergebnis, das Auditlog dokumentiert Aufrufe. Pagination/Retry/LastSync im echten Anbieteradapter, Tombstones, Konfliktbearbeitung sowie Schreib-/Kontaktsynchronisation bleiben für die Live-Integration zu implementieren. Keine echten SDK-Aufrufe oder Zugangsdaten im PoC.

## Weitere Anbieter

Schnittstellen: TextGenerationProvider, ImageProcessingProvider, DocumentExtractionProvider, CrmProvider, NotificationProvider, SocialPublishingProvider, PropertyValuationProvider, AuthBridge. OpenAiTextProvider und DisabledExternalProvider führen keine Netzwerkaufrufe aus. Vor Aktivierung benötigt jeder echte Adapter serverseitige Secrets, spezifische Netzwerkfreigaben, Timeouts, Datenfreigaben und getestete Kosten-/Fehlergrenzen.

Der Browser kann lokale Bildregler, Dateivorschauen und ein Storyboard ausführen. Das sind keine KI-Bilder, keine sichere Schwärzung und kein Videoexport. Originaldateien werden bewahrt. Der Bewertungsadapter multipliziert nur Nutzereingaben; er bezieht keine Marktdaten.

## AuthBridge / späteres SSO

LocalAuthBridge meldet ausdrücklich „nicht verbunden“. Entweder wird das Portal nach Bestandsaufnahme in dieselbe Symfony-Anwendung mit gemeinsamer geprüfter Userbasis integriert, oder eine getrennte Anwendung vertraut einem zentralen OIDC-Identity-Provider. Weder das Teilen von Cookies über Subdomains noch die Übernahme vorhandener Websitepasswörter ist implementiert. Keine Verbindung zur produktiven Website erfolgt.

## Reproduktion und spätere Härtung

`scripts/setup` installiert aus Lockdatei, initialisiert eine geheime lokale APP_SECRET, migriert und legt idempotente dev-Fixtures an. PHP-Helfer verwendet offizielle signierte Debian-Pakete ohne Root; Archive und Herkunftsmanifest bleiben lokal. `scripts/cache-composer-dist` lädt bei Proxybeschränkungen dieselben gesperrten Commits über GitHub codeload mit TLS/Prüfsummenprüfung.

Prozesse überleben keine neue Cloud-Aufgabe: `scripts/start` erneut ausführen. Keine zusätzlichen Git-Worktrees nötig; jede Cloud-Aufgabe ist bereits isoliert. Für Produktion: eigener Webserver mit HTTPS, sichere Secrets/Debug-Konfiguration, relationalere Modelle, ausführliche Rollenprüfung, Datenlösch-/Backupkonzept, Malware-/PDF-Uploadprüfung, Monitoring und Betriebsverantwortung. Der mitgelieferte PHP-Entwicklungsserver ist ausschließlich lokal vorgesehen.
