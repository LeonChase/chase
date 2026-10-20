# Entwicklungsregeln – CHASE Partnerportal 2.0 (Codex)

- Lies `CODEX_MASTERPROMPT.md` zuerst. Projektziel ist **ausführbarer Code**, nicht nur Planung.
- Beachte `design/dashboard_referenz.png` und das Branding unter `data/brand.json`. Bestehende sechs Dashboard-Kacheln beibehalten. Zusätzliche Bereiche in eine Sidebar.
- Lokal isolierter Symfony-PoC, KEIN Zugriff auf die produktive Website, KEIN unautorisierter API-Aufruf, KEIN kostenpflichtiger Dienst.
- Deutschsprachige Anwendung, de-DE-Zahlen/Datumsformate; EUR ohne erfundene Produktivkosten. Responsive, modern, gut zugänglich.
- Keine erfundenen echten Kontakte, Telefonnummern, Mailadressen, Leads, Rechtstatsachen oder Marktdaten. Synthese-Datensätze nur mit „Demo“/Fixture-Kennzeichnung.
- Symfony Security echtes Demo-Login, Hash-Passwörter, CSRF, Rechteprüfungen auf Datensatzebene, Tenant-/Owner-Trennung. Niemals nur Frontend-seitig filtern.
- Bei fehlendem externem Provider: benutzbarer Demo-Workflow oder ehrlich deaktivierte Funktion. Keine Fake-Ergebnisse. onOffice nie als „verbunden“ markieren ohne Credentials, echten API-Test und Zustimmung.
- Kein externer Upload / Social Publish / E-Mail-Versand ohne explizit eingerichtete Integration und Benutzerbestätigung.
- Für KI-Text in der Demo regelbasierten Offline-Provider implementieren, keine Fakten hinzufügen. Bilder/Videos nicht als KI-generiert ausgeben, wenn nur UI-Vorschau.
- Datenmodelle über Migrationen, Fixtures idempotent. Versioniere generierte Inhalte; Admin-Aktionen protokollieren.
- Tests für Tenant-Leaks, Objektzugriff, Login, Mock-Import, Textgenerator. Ehrliche Statusangaben.
- Berücksichtige `docs/PRODUKTKONZEPT.md` und `docs/SICHERHEIT_UND_GRENZEN.md`. Projektfortschritt in `docs/IMPLEMENTIERUNGSSTATUS.md` festhalten.
- Bestehende Dateien beim Wiederaufnehmen erhalten; keine destruktiven Reset-Kommandos ohne Grund.
