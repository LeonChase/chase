# MASTER-AUFTRAG FÜR CODEX
## CHASE Partnerportal 2.0 – eigenständige Symfony-Webanwendung als später integrierbarer Prototyp

> **Arbeitsauftrag, keine Konzeptanfrage:** Baue jetzt eine lokal lauffähige, hochwertige, mehrbenutzerfähige Webanwendung. Plane kurz, programmiere, teste und dokumentiere. Die Datei `AGENTS.md` und alle Referenzdaten unter `data/` sind bindender Projektkontext.

### 0. Deine Rolle und das Produktziel

Du agierst als Senior Symfony/PHP-Entwickler, UX/UI-Designer, Softwarearchitekt, QA-Engineer und Product Owner. Kunde ist **CHASE Immobilien**, ein deutsches Immobilienunternehmen mit selbstständigen Regionalpartnern. Es gibt eine bestehende Website auf **https://chase-immobilien.de**, technisch PHP/Symfony. Ihre konkrete Symfony-Version, Zugangslösung, Hosting und Datenbank sind uns noch nicht bekannt; du hast **keinen Zugriff** auf diese produktive Website und darfst keinen behaupten.

Baue einen **hochwertigen Prototypen als eigenes Symfony-Projekt** für ein internes Makler-Partnerportal. Das Portal soll die tägliche Arbeit von Maklern erleichtern: **Objekte zentral bearbeiten, Marketing vorbereiten, Leads verwalten, Wissen und Dokumente finden, KI-Werkzeuge nutzen, mit CHASE zusammenarbeiten und Kosten transparent sehen**. Vorbilder sind BOTTIMMO (Marketing/Leadgenerierung) und PropShift (KI-Objektvermarktung), aber **weder deren Marke noch geschützte Oberflächen, Texte, Assets oder Code kopieren**. Nutze deren Funktionenkategorien nur als Inspiration.

**Ergebnis** ist ein stabiler, responsiver, visuell ausgezeichneter und lokal ausführbarer MVP mit echten bedienbaren Workflows und klar markierten Integrationsgrenzen. KEIN reines Konzeptpapier, KEIN statischer Screenshot, KEINE nur angedeuteten Funktionen. Baue realistische lokale Demoabläufe auch ohne externe API-Zugänge.

### 1. Das Design ist bereits entschieden

Sieh dir **unbedingt** `design/dashboard_referenz.png` an. Es zeigt das mit dem Auftraggeber abgestimmte Dashboard: orange Akzente, weiß/anthrazit, elegante große Kacheln, klare Typografie und ein Ansprechpartner-Feld. Setze den Stil als **echte HTML/Twig/CSS-Oberfläche** um, nicht als Bildhintergrund. Wenn das Referenzbild nicht visuell gelesen werden kann, orientiere dich an folgenden Eigenschaften:

- Marke CHASE Immobilien, typografisches Logo als austauschbarer Platzhalter, Hauptfarbe `#FF6A00`, zusätzlich Anthrazit `#151923`, warme Weiß-/Grautöne, sanfte Schatten, großzügige Abstände, 14–18px Kartenradien, Inter (wenn verfügbar) sonst Systemsans, elegante feine Icons, heller und wirklich funktionierender dunkler Modus.
- Desktop: linke **einklappbare Sidebar** für Arbeitsbereiche, oberer Header mit globaler Suche, Benachrichtigungen, Hell/Dunkel, persönlichem Profil mit Region. Mobil: nutzbares Drawer-Menü und hochwertige Touch-Bedienung. WCAG-orientierte Kontraste, Fokuszustände, Labels, Tastaturnavigation.
- Start-Dashboard: persönliche Begrüßung, „Gemeinsam mehr erreichen.“, Kacheln **genau in der ursprünglichen 3×2-Anordnung**: **Dokumente, Immobilienbewertung, KI-Tools, Wissen / Academy, Werbekatalog, Visualisierung**. Immobilienbewertung darf visuell akzentuiert sein. **Die sechs großen Kacheln beibehalten**, weitere Funktionalität kommt über Sidebar, Schnellzugriffe und Widget-Bereiche.
- Oberhalb oder unterhalb der sechs Kacheln: dynamische Kennzahlen (eigene aktive Objekte, offene Aufgaben, neue Leads, vorbereitete Beiträge) und ein „Heute wichtig“-Bereich, sofern der Aufbau nicht überladen wird. Nach den Kacheln: **News aus der CHASE Zentrale**, „Meine Aufgaben“, „Nächste Marketingaktionen“, **Ansprechpartner CHASE Zentrale**. Keine fiktiven echten Mitarbeitenden oder Kontakte nennen.
- Farben und Texte zentral über Designtokens / Konfiguration. Mobile-first; 3 Spalten Desktop, 2 Tablet, 1 Mobile.
- Microinteractions, Badges, Skeleton-/Loading-/Fehlerzustände, Toasts, hübsche Tabellen und Karten. **Keine funktionslosen primären Buttons**.

### 2. Technologie – bewusst kompatibel zur vorhandenen Website

- PHP 8.3 (falls vorhanden) und passende, unterstützte Symfony-LTS-Version (bevorzugt 7.4 LTS, tatsächliche Kompatibilität prüfen), Twig, Symfony UX/Stimulus nur soweit sinnvoll, Doctrine ORM, SQLite für lokalen PoC, Migrationen und Fixtures, Symfony Security, Validator, Form / HttpClient / Messenger soweit erforderlich.
- Für echten DB-Mehrbenutzerbetrieb: ordentliche Entities, Repositories, Services, DTOs, Controller, Twig Components und zugriffsgesicherte Dateiverwaltung.
- Kein unnötiges React/Next.js, keine No-Code-Abhängigkeit, keine Containerpflicht, keine Live-/Cloud-Datenbankpflicht. Wenn UI-Build-Tools nötig, minimal halten und vollständig dokumentieren. Vite/Node nur falls zwingend technisch begründet.
- Keine Erweiterung an oder Verbindung zur produktiven Website. Benötigte Produktivintegration später über klar getrennte Interfaces / Auth-Migrationskonzept.
- Provideradapter als Interfaces: `TextGenerationProvider`, `ImageProcessingProvider`, `DocumentExtractionProvider`, `CrmProvider`, `NotificationProvider`, `SocialPublishingProvider`, `PropertyValuationProvider` sowie kostenbewusster `UsageMeter`. Mock/Local-Implementierungen für den PoC. Externe Adapter dürfen als **ehrlich als nicht verbunden** gekennzeichnete Schnittstellen vorbereitet werden.
- Falls kein PHP/Composer verfügbar oder Paketdownloads blockiert sind: ein echter, isolierter, lauffähiger HTML/CSS/JS-Prototyp als `prototype/`-Fallback mit funktionierenden Demo-Interaktionen PLUS vorbereiteter Symfony-Quellstruktur. Den Blocker dokumentieren, nicht vortäuschen, dass Symfony lauffähig ist.

### 3. Produktnavigation und Informationsarchitektur

**Hauptsidebar**: Dashboard, Meine Immobilien, KI-Studio, Marketing & Social Media, Akquise & Leads, Immobilienbewertung, Dokumente, Wissen / Academy, Werbekatalog, News & Netzwerk, Aufgaben & Checklisten. 

**Unter dem Profil**: Mein Profil, Mein KI-Guthaben & Verbrauch, Einstellungen, Hilfe / Ansprechpartner. 

**Nur für Admins**: Admin-Center (Benutzer, Rollen, Regionen, Inhalte, News, Featureflags, Credits/Preise, API-Status, Auditlog, Importstatus).

Das KI-Studio fasst Untertools zusammen: Exposé-Texte, Lage, Objekt-Highlights, Social Posts, E-Mails und Anschreiben, Blog-Ratgeber/SEO, Bildoptimierung, virtuelles Home Staging, Visualisierungen, Grundrisse, Bild-zu-Video, Dokumentenprüfung, Dokumentenschwärzung. **Außer Textdemo und lokaler Dateivorschau** dürfen teure/anspruchsvolle Funktionen zunächst als gut gestaltete **Beta-/„API verbinden“-Ansicht** mit Eingaben, transparentem Status und beschriebenem Workflow entstehen. Bloßes Anklicken einer Fake-Ergebnisaktion ist verboten.

### 4. Rollen, Partnerzugriff und echtes Demo-Login (P0)

Implementiere mit Symfony Security eine **wirkliche Passwort-/Session-Anmeldung für den lokalen Demo-Betrieb**. Benutzer können sich anmelden/abmelden. Admin kann Demo-Benutzer verwalten, aktivieren/deaktivieren und Rollen/Regionen zuweisen. Seed-Accounts müssen **nur in `dev`** erzeugt werden, Passwörter gehasht sein, Demo-Zugangsdaten zur Laufzeit generiert oder per lokalem Seed-Parameter gesetzt werden. Keine festen Produktionspasswörter, kein eingebauter Dev-Rollenwechsel außerhalb von `dev`. Kontoanlage für echte Partner ausschließlich nach Admin-Freigabe – keine öffentliche Registrierung mit automatischer Freischaltung.

Rollen:
- `ROLE_AGENT`: eigene Objekte, eigene Leads, eigene Kampagnen, erlaubte Dokumente, eigene Nutzung.
- `ROLE_REGION_MANAGER`: zusätzlich **explizit zugewiesene** Teamdaten; Region allein bewirkt ausdrücklich **keine** Zugriffserlaubnis auf alle Objekte oder Leads.
- `ROLE_ADMIN`: Portalverwaltung, Inhalte, Freischaltungen; Zugriffe auf personenbezogene Makler-/Kundendaten nur nach explizit definierten notwendigen Policies.
- `ROLE_SUPER_ADMIN`: technisch administrative Rechte.

Datenmodell: `Organization/Tenant`, `Region`, `TeamMembership`, `User`, `Property`, `Lead`, `Task`, `Document`, `ContentPost`, `NewsPost`, `MediaAsset`, `UsageEvent`, `CreditLedger`, `AuditEvent`, `IntegrationConnection` (ohne gespeicherte Klartext-Secrets). Überall `createdAt`, `updatedAt`, sinnvolle Indizes. Zuweisung Owner-/Tenant-IDs. Symfony Voters/policies für objektbezogene Lese-, Schreib-, Download- und Adminrechte. **Alle** serverseitigen Endpunkte sichern, nicht nur die Navigation ausblenden. Schreibe echte Zugriffstests, z. B. Hildesheim-Makler kann Passau-Objekt nicht sehen. Nicht-Makler bekommen keine Kundendaten per globaler Suche.

**Späterer Website-Login:** Entwerfe `AuthBridge` / SSO-Migrationsweg und dokumentiere Optionen (gemeinsame Symfony-Userbasis bei gleicher Anwendung, vertrauenswürdiger zentraler Identity Provider OIDC bei getrennter App). **Kein behauptetes SSO und kein unsicheres automatisches Teilen von Sessions über Subdomains**. Ziel: die Partner nutzen später auf CHASE ihre individuellen Zugangsdaten.

### 5. P0: Liefere diese Module im ersten implementierten, testbaren Prototyp

#### 5.1 Dashboard (echtes Zentrum)
- Personalisierte Begrüßung, Name/Region aus angemeldetem Konto; vier Kennzahl-Karten aus lokal gespeicherten Daten (nicht hardcodete Fantasiezahlen); sechs Hauptkacheln; News-Widget, Aufgabenliste, zwei Schnellaktionen „Objekt anlegen“ und „Exposé erstellen“, Marketingkalender-Vorschau, Support-Kontakt.
- Klick auf Statistik oder Widget führt zu gefilterter Unterseite. Globale Suche nach zugänglichen Immobilien, Academy, Dokumenten und Nachrichten, gruppierte Ergebnisse mit Rechteprüfung. Keine fremden Daten.

#### 5.2 Meine Immobilien / Objektcockpit
- **Zentraler Kernworkflow:** Objektliste als Karten-/Tabellenansicht, Suche, Filter nach Status (Entwurf, Vorbereitung, aktiv, reserviert, verkauft, archiviert), Typ, Region, Eigentümer/User und Datum, Sortierung; **Objekt erstellen, bearbeiten, archivieren**, Detailseite mit Tabs „Übersicht“, „Medien“, „Dokumente“, „KI & Texte“, „Vermarktung“, „Checkliste“, „Aktivitäten“.
- Eingaben: interne Objekt-ID, Bezeichnung, Objektart, Vermarktungsart, Ort/Region, Adresse optional (nur mit Berechtigung), Wohnfläche, Nutzfläche, Grundstück, Zimmer, Baujahr, Energiekennwerte soweit vorhanden, Preis, Miete, Status, Quelle, Verantwortliche, Ausstattung, Notizen, Datenfreigaben, Zeitstempel. Pflichtfelder sinnvoll validieren.
- **Objektbezogene Schnellaktionen** führen mit vorausgefüllten faktenbasierten Daten in Exposé-Generator, Social Creator, Checkliste, Bildstudio. Ergebnisse speichern mit Versionshistorie/Quelle und „von Makler freigegeben“-Status.
- Medien hochladen/vorschauen (eindeutig begrenzte Dateitypen und Größen, private Speicherung, sichere Download-Route mit Rechteprüfung), keine öffentlichen Originaladressen oder Dokumente.
- Statuswechsel an einer sinnvollen Mini-Pipeline demonstrieren; vollständiges onOffice-sync-freies lokales CRUD für den PoC.

#### 5.3 onOffice-Anbindung – ehrlich, technisch vorbereitet
- **onOffice ist später die führende Datenquelle**, keine unnötige vollständige CRM-Nachentwicklung. Trenne interne Portalobjekte von externen CRM-IDs, lege Source-of-Truth-/Merge-Regeln fest. Für P0: Import-Adapter `MockOnOfficeAdapter` mit lokaler Fixture `data/demo_onoffice_objects.json`, Import-Vorschau und **echter lokaler Demo-Import** (Upsert anhand externer ID mit korrekt behandelter Zuweisung, kein doppeltes Anlegen); Ansicht „CRM-Verbindung: Demo / nicht mit onOffice verbunden“.
- `CrmProvider` kapselt `listProperties`, `getProperty`, `listTasks` optional, `syncProperties` / Pagination / Mapping / LastSync / Fehlerstatus; echten SDK/API-Client nur hinter Featureflag und Credentials in Umgebungsvariablen, **im PoC deaktiviert**. Nicht behaupten, dass eine Live-Synchronisation vorhanden ist.
- Phase später: read-only Imports zuerst, Schreibzugriffe/Automations-/Kontakt-Sync nur nach expliziter Bestätigung mit Konflikt- und Rechtekonzept. Offizielle Doku: `https://apidoc.onoffice.de/`; PHP SDK `https://github.com/onOfficeGmbH/sdk`. API erfordert laut Hersteller ein kostenpflichtiges Modul. Keine Credentials erfragen/benutzen für den PoC.

#### 5.4 KI-Studio – ein benutzbarer Kern
- Exposétext-, Lagebeschreibungs-, Social-Post-, E-Mail- und SEO-Blog-Modul als Tabs oder Toolseiten. Daten per Objektübernahme oder manueller Eingabe. Stil: sachlich, hochwertig, emotional, kurz. **Faktenquelle strikt beachten**: nichts zu Energieausweis, Ausstattung, ÖPNV, Größe, Entfernung, rechtlicher Lage oder Verkehrswert erfinden. Unbekanntes als „Angabe fehlt“ markieren. Rechtliche Pflichtangaben als Prüfliste zeigen.
- Voll funktionsfähiger **deterministischer Offline-Demo-Textprovider** für Überschrift, Kurzbeschreibung, Objektbeschreibung, Highlights, Social Caption, CTA; Änderungs-/Kopier-/Speicherfunktion, ggf. CSV/Markdown-Export. Klar als regelbasierter Beispielgenerator gekennzeichnet; **keine Behauptung echter KI**. Produktiver `OpenAiTextProvider` als Service mit deaktiviertem Featureflag vorbereiten, nur Backend-Key, Token-/Budgetlimits, Timeout, Fehlerzustand, geplante OpenAI API-Einbindung. Falls klar konfiguriert und verifiziert: optional echter Modus als gesondert aktivierbares Feature. Unaufgeforderte API-Calls verboten.
- Weitere KI-Werkzeuge: Oberflächen mit konkreten Inputs/Aktionsbeschreibungen und Status **„API-Anbindung folgt“**. Für PDF-Dokumentenanalyse: nur Hinweise mit Fundstellen/Quellen/Unsicherheit + manuelle Prüfung, nicht als Rechtsgutachten. Für KI-Bilder: Original erhalten und Bearbeitungen kennzeichnen. Für Grundriss keine neuen Maße erfinden; nicht als WoFlV-konform ausgeben. Für Marketing-Videos: Storyboard-/Sequenzvorschau aus vorhandenen Bildern, echter Videoexport nur bei vorhandener lokaler Bibliothek oder nach Backend-Integration. Niemals Bilder, PDFs oder Daten an Drittanbieter senden ohne ausdrückliche Konfiguration.

#### 5.5 Marketing & Social Media
- Redaktioneller **Content Creator**: Social Post aus Objekt, allgemeines Ratgeberthema oder lokale Kampagne; Zielkanal Instagram/Facebook/LinkedIn; Format Story/Reel/Feed; Textlänge, CTA, Hashtag-Vorschläge. Im P0 über Demo-Textprovider **tatsächlich benutzbar**, Text kopieren, als Entwurf speichern, Bearbeitung und Preview im gewählten Format, Markierung Entwurf/Freigegeben.
- **Marketingkalender** als Monats-/Wochenübersicht, Content-Entwürfe Datumsfeldern zuordnen, Drag & Drop nur falls zuverlässig, sonst saubere Datumsänderungsmaske. „Geplant“ bedeutet interne Planung, **nicht automatisch veröffentlicht**. SocialPublishingProvider vorbereitet, echte Meta-/LinkedIn-Authentifizierung/-Publikation **erst später** und nur nach expliziter Benutzerfreigabe.
- Wiederverwendbare CHASE-Kampagnenvorlagen für Bewertung, Eigentümer-Akquise, neu im Angebot, verkauft, Referenz, lokaler Markt, Ratgeber. Marketingcheckliste pro Objekt. Markenfarben stets CHASE-konform.

#### 5.6 Akquise & Leads
- Kleine **interne Demo-Leadpipeline** mit Status „Neu“, „Kontaktiert“, „Qualifiziert“, „Termin“, „Auftrag“, „Verloren“ und Benutzereigentum. **Lead anlegen, bearbeiten, Status ändern, zu Objekt oder Aufgabe zuordnen, suchen/filtern**. Nur synthetische Leads. Aktivitätsnotiz und nächste Aufgabe; keine automatischen Kaltakquise-Massenmails.
- Landingpage-/Widget-Builder als **Vorschau** (Immobilienbewertungsanfrage, Rückrufwunsch, Ratgeberdownload, Käufer-Suchprofil) mit individuell konfigurierbarem Titel und CTA. Echte öffentliche Veröffentlichung, Tracking, Kontaktübertragung, Consent-Management und automatisierte E-Mail-Funnels sind P2; Preview darf nicht als live verfügbares Leadformular erscheinen.
- Partner-Marketingaktionen und ROI/Conversion-Ansicht nur für tatsächlich verfügbare Daten. Keine erfundenen Leads/Kennzahlen als live deklarieren.

#### 5.7 Aufgaben & Objektchecklisten
- Aufgabenliste mit Eigentümer, Fälligkeit, Priorität, Status, Objektverknüpfung. Vollständiges lokales CRUD; Filter „Heute“, „Überfällig“, „Diese Woche“ (Zeitzone Deutschland). Objektvermarktungs-Checklisten als Vorlagen mit abhaken, z. B. Unterlagen, Energieausweis, Eigentümerfreigabe, Exposé, Portale, Marketing; keine pauschale Aussage rechtlicher Vollständigkeit.
- Hinweis „Outlook / Microsoft To Do Integration geplant“; keine unautorisierte externe Erstellung von Terminen.

#### 5.8 Dokumentencenter & Academy
- `data/demo_documents.json`, `data/demo_academy.json` mit Kategorien, Suche, Leseseiten, Lesezeichen / Favoriten; Admin kann lokale Inhalte verwalten. Dokument-Downloads sind **klar als unverbindliche Muster markiert**, keine vorgetäuschten rechtsverbindlichen Verträge. Dokumente dürfen nach Rollen/Freigaben ausgegeben werden, Downloads nur gesichert.
- Academy: Lernpfad „Makler-Onboarding“, Karten/Artikel, Lernfortschritt lokal speichern; kleine Checkliste für den Einstieg. Kein erfundenes Wissen als zertifizierte Pflichtschulung kennzeichnen.

#### 5.9 Werbekatalog
- Produkte aus `data/demo_advertising.json`, Karten/Kategorien, Favoriten/Merkliste und **funktionierende Anfragezusammenstellung als lokaler Entwurf**, niemals reale Bestellung ohne Anbindung. Preis nur, wenn ausdrücklich im Datensatz vorhanden, sonst „auf Anfrage“.

#### 5.10 News & Netzwerk
- **Interner CHASE-Newsbereich**: Beiträge aus `data/demo_news.json`, Kategorien „Unternehmen“, „Schulung“, „Marketing“, „System“, Datum, Priorität und angepinnte Meldungen. Admin kann Beiträge erstellen, bearbeiten, veröffentlichen / archivieren; Makler können lesen und als gelesen markieren. Benachrichtigungs-Badge basiert auf ungelesenen Meldungen. News in dieser Version **synthetische interne Demo-News**. Öffentliche Immobilien-Newsfeed/Marktdaten nicht ohne verlässliche Quellen/Lizenzen automatisiert erfinden oder ausgeben.
- Partnerverzeichnis mit explizit sichtbaren, synthetischen Profildaten und Regionen. Keine privaten Telefonnummern voraussetzen. Für echte Nutzer später Einwilligungs-/Sichtbarkeitsregelung.

#### 5.11 Immobilienbewertung
- Erfassungsformular zur Bewertungsvorbereitung; speichern und einem Objekt zuordnen. P0 nur **mathematisch transparente Demo-Rechenhilfe**, z. B. Jahresnettokaltmiete × vom Makler eingegebener Faktor, Eingaben und Rechenweg deutlich zeigen. Optional Rohertragskennzahl. **Keine belastbare Marktwert-/Verkehrswertermittlung ohne lizenzierte Marktdaten und Prüfung**. Ausgaben deutlich als unverbindliche Demo kennzeichnen. Für spätere Marktdaten / Bewertungsanbieter `PropertyValuationProvider` vorbereiten.

#### 5.12 Benutzerprofil und KI-Kosten
- Profil mit Region/Rolle, Datenänderungen wo erlaubt, Passwortänderung, Light/Dark Mode. Dashboard „KI-Nutzung & Guthaben“ mit eigenen UsageEvents, Tools, Demo-Credits, Transaktionsübersicht. Admin kann fiktives Budget pro User / Team setzen. **Keine echte Zahlung, keine Stripe-Anbindung im PoC**, keine konkreten KI-Preise ohne Providerangabe als verbindlich ausgeben. Die Kostenarchitektur soll später echte API-Providerkosten, Aufschläge, Gutschriften, Limits und Reservierungen erfassen können. Erfordernis eines serverseitigen Budgetchecks und atomarer Ledgerbuchungen dokumentieren.

### 6. Prioritäten / Meilensteine – verhindert unfertiges Feature-Sammeln

**P0 – MUSS als wirklich klickbarer, getesteter lokaler PoC geliefert werden**: Symfony-App startet; Login/Logout, Rollen und Zugriffsprüfung; visuell hervorragendes Dashboard mit sechs Kacheln; Sidebar; Objekt-CRUD + Objektansicht + lokale Demo-onOffice-Synchronisation; Aufgaben-CRUD; Leadpipeline-CRUD; KI-Demo-Textgenerator mit Objektübernahme/Speichern; Marketingkalender-Entwurf; Dokumente/Academy lesen/suchen; Werbemittel-Merkliste; News-Bereich; Demo-Nutzung; Grundlegender Admin für News und Nutzer. Fertige Workflows haben Vorrang vor Dekoration.

**P1 – NACH P0**: erweitertes Admin-CMS für alle Inhalte, Medienverwaltung mit mehr Dateitypen, differenziertere Objekt-Checklisten, interner PDF-Export mit klarer Musterkennzeichnung, E-Mail-Vorlagen, Vorher/Nachher-UI, Leadmagnet-Builder-Vorschau, stärkere Suchfunktion, fortgeschrittene Tests. Voraussetzung: P0 erfolgreich getestet.

**P2 – NUR SCHNITTSTELLE, MOCK ODER DOKUMENTIERTER BACKLOG**: echte onOffice-Verbindung, OpenAI API, Bild-/Video-Generator, rechtssichere PDF-Schwärzung, Grundriss-Neuzeichnung, professionelle Bewertung mit Marktdaten, Facebook/Instagram/LinkedIn-Veröffentlichung, Newsletter/Funnels, Outlook/To-Do, Website-Leadwidgets, SSO in bestehende CHASE-Website, Zahlungsabrechnung und Rechnungserstellung. **Nicht heimlich „halbfertig“ bauen. Keine externen Requests oder Geldkosten ohne ausdrücklich freigegebene Credentials und Zustimmung.**

**Wenn die verfügbare Umsetzungszeit nicht alle P0-Unterpunkte erlaubt:** Liefere eine kohärente, lauffähige vertikale Scheibe: Login → Dashboard → Objekt → Textgenerator → Entwurf speichern → Aufgaben/Marketing verknüpfen, danach erweitere kontinuierlich; offene Punkte klar ausweisen, nicht als fertig behaupten.

### 7. Echte Nutzerabläufe, die funktionieren müssen

1. Mit generiertem Test-Makleraccount anmelden → individuelles Dashboard → eigene Region/Objekte/Tasks sehen → ausloggen.
2. Neues Objekt erstellen → Daten bearbeiten → Exposé aus Objekt generieren (regelbasierter Demo-Modus) → Ergebnis speichern → im Objekt wiederfinden.
3. Objekt filtern → Status ändern → passenden Marketingpost erzeugen → als Entwurf speichern → im Kalender planen → Kalender zeigt Termin an.
4. Lead anlegen → Pipeline-Status ändern → Aufgabe erstellen → Dashboard-Statistik aktualisiert sich.
5. Demodatensatz über onOffice-Mock-Import importieren → Extern-ID-Upsert → zweiten Import erneut ausführen → **keine Dubletten** → Verbindung klar „Demo“.
6. Admin erstellt News-Meldung → Makler sieht neue Meldung/Badge → markiert gelesen → Badge aktualisiert sich.
7. Benutzer wechselt Hell/Dunkel, lädt Seite neu → Einstellung bleibt bestehen.
8. Benutzer A versucht direkte URL eines fremden Objekts/Leads → Server liefert 403/404 und niemals den Datensatz.
9. Suche: Treffer für erlaubte Dokumente, Academy-Inhalte und eigene Objekte; keine Treffer fremder Teams.
10. KI-Bild-, Video- und Grundriss-Seiten zeigen reale Upload-Vorschau/Workflow und transparente „Anbindung fehlt“-Meldung; keine Fake-Ergebnisse.

### 8. Sicherheits- und Datenschutzleitplanken

- DSGVO-geeignete Architektur ohne Behauptung automatischer DSGVO-Konformität. Keine echten Kundendaten in Fixtures oder Logs; keine echten Grundbücher. Secrets ausschließlich Umgebungsvariablen/Secret Store; `.env.local`, `var/`, Datei-Uploads, SQLite-DB gitignorieren. Alle Testkonten klar als Demo markieren.
- Passwort-Hashing, Symfony Security, sichere Sessions, Logout, CSRF für mutierende Formulare, serverseitige Validierung, Output-Escaping, Rate-Limiting des Logins, angemessene Upload-Limits, MIME/Dateiendungsprüfung, nicht öffentlich zugängliche Originaldateien, Download nur nach ACL. Auditlog für sensitive Aktionen und adminseitige Änderungen; beim Import ebenfalls Protokoll.
- Multi-Tenant-/Ownership-Prüfung **in Repository/Service/Controller**, nicht nur Frontend. Kein IDOR, CSRF, XSS, Missbrauch von Such- oder Downloadrouten. Beim Einsatz externer APIs später AVV, Drittlandtransfers, Datensparsamkeit, Lösch-/Aufbewahrungsregel und menschliche Prüfung. Kein API-Key im Client.
- „Dokumentenanalyse“ ist keine Rechtsberatung; keine verbindliche Prüfung von Grundbuch, Baulasten, Mietverträgen etc. without nachgewiesene Qualität und Prüfung. Eine optische schwarze Box auf PDF ist **keine** sichere Schwärzung; produktiv nur echte Entfernung sensitiver Inhalte und geprüfter Re-Export.
- Menschen müssen Objektinhalte, Bewertung, Social-Veröffentlichung und externe Texte freigeben; KI-generierte Fotos als Visualisierung kennzeichnen und Originalfotos bewahren. Kein automatischer Versand an Leads ohne angemessene Rechtsgrundlage und Abmelde-/Einwilligungsmanagement.

### 9. Daten und Designmaterial, die dir vorliegen

- `data/brand.json` – Branding und ursprüngliche sechs Dashboard-Module.
- `data/demo_users.json`, `data/demo_properties.json` – ausschließlich synthetische Makler/Regionen/Objekte.
- `data/demo_documents.json`, `data/demo_academy.json`, `data/demo_advertising.json`, `data/demo_ki_tools.json`, `data/demo_document_checks.json`, `data/demo_usage.json` – alte Starterdaten.
- `data/demo_onoffice_objects.json`, `data/demo_leads.json`, `data/demo_tasks.json`, `data/demo_news.json`, `data/demo_social_posts.json`, `data/demo_campaigns.json`, `data/demo_activity.json`, `data/demo_partner_directory.json` – zusätzliche Version-2.0-Daten.
- `docs/PRODUKTKONZEPT.md`, `docs/ROADMAP.md`, `docs/INTEGRATION.md`, `docs/SICHERHEIT_UND_GRENZEN.md`, `docs/REFERENZEN.md` – Produkt- und Technikvorgaben.

**Alle Fake-Datensätze sind als Demo gekennzeichnet**, keine Demo-Kontaktdaten an reale Empfänger senden. Datenimport auf idempotente Weise. Wenn die JSON-Feldformen vom Entity-Modell abweichen, baue einen expliziten Fixture-Mapping-Layer.

### 10. Was ich als Nutzer am Ende wirklich sehen will

- Eine startbare Website, lokal im Browser mit der Oberfläche aus dem Referenzdesign, vollständig auf Deutsch, professionell und nicht wie ein generisches Backend.
- Ein tatsächlich funktionierendes Login mit mindestens Makler- und Admin-Demokonto; sichere Demo-Passwortausgabe für lokalen Start.
- Realistische Objektübersichten in Hameln-Pyrmont, Hildesheim, Kassel, Passau; eigene Objekte statt einer ungeschützten gemeinsamen Liste.
- Exposé und Marketingpost aus einem Objekt generieren, Ergebnisse speichern, Marketingtermin planen; Dokumente/Academy/News/Aufgaben/Leads bedienbar.
- „Mit onOffice verbinden“ als ehrlicher Integrationseintrag mit lauffähigem **Mock-Import**, kein vorgetäuschtes CRM; später echte SDK-Anbindung möglich.
- Ein anschauliches Admin-Center mit Benutzern, Regionen, News und Demo-Verbrauch.
- Klar erkennbare Statuskennzeichnung **„funktioniert lokal“**, **„Demo-Regeln“**, **„externe API nicht verbunden“**, **„geplant“** pro Werkzeug.

### 11. Qualitätssicherung – konkret

- Teste mindestens: App startet, Login & Logout, Zugriff ohne Login, Rolle/Scope, CRUD Objekt, `onOffice`-Mock Import idempotent, CRUD Leads/Aufgaben, Demo-Text ohne halluzinierte Details, Speichern von Generaten, Marketingkalender, News-unread, globale Suche, Dark Mode, sechs Dashboard-Kacheln. Bei vorhandener Browserautomation möglichst Screenshots Desktop (1440px) und Mobile (390px), visuelle Layoutprüfung.
- Schreibe PHPunit Functional-/Service-Tests für wesentliche Endpunkte und Rechtefälle; wo nicht möglich, ausführbare Smoke-Skripte und manuelle Testprotokolle. Testergebnisse ehrlich mit ausgeführten Befehlen dokumentieren.
- Saubere Fehlerseite und leere Zustände. Keine 500er bei fehlenden Fixtures/optionalem API-Key. Eingaben robust validieren.
- README.md auf Deutsch mit Composer/DB/Seed/Start-Befehlen und Demo-Benutzerzugang, klar dokumentierter Abweichung von Produktivbetrieb. Screenshots falls erstellbar unter `docs/screenshots/`. **Kein angeblicher Deploy oder Live-Anbindung ohne Durchführung.**

### 12. Konkrete Arbeitsanweisung – JETZT

1. Lies `AGENTS.md`, diesen Auftrag, `docs/PRODUKTKONZEPT.md` und die JSON-Daten. Prüfe die vorhandene Laufzeit. Verwende das bestehende Arbeitsverzeichnis als Repositorywurzel. Keine ungefragten Änderungen außerhalb des Repos.
2. Schreibe eine **knappe Checkliste mit max. zehn Meilensteinen**, dann **beginne eigenständig zu programmieren**. Frage nicht nach Namen, Echtzugängen oder Hosting, sondern nutze vorhandene synthetische Daten.
3. Erzeuge/ergänze Symfony-Projekt, Migrationen, Entities, Fixtures, Twig-Views, CSS/JS, Security, Module. Baue nacheinander echte vertikale Flows, führe regelmäßig Tests aus.
4. Nach jedem Meilenstein: dokumentiere Implementierungsstand in `docs/IMPLEMENTIERUNGSSTATUS.md` als Tabelle: Bereich | Fertig/Lokal/Mock/Geplant | konkrete Route | Test. Priorisiere Login, Dashboard, Objekt-CRUD und Textgenerator zuerst.
5. Nach P0: Versuche mit CLI/Browser zu starten, behebe gefundene Bugs. Führe eine kurze visuelle Qualitätskontrolle durch. Lies die Abnahmekriterien oben noch einmal und gleiche sie mit dem Ergebnis ab.
6. Gib zum Abschluss **den lokalen Startbefehl, Demo-Login-Weg, die wichtigsten Links/Routen, getestete Funktionen und offene API-Integrationen** an. Kein langer Architekturaufsatz statt Code.

**Fang unmittelbar mit der Umsetzung an. Nutze die vorhandenen Dateien, nicht nur diesen Prompt.**
