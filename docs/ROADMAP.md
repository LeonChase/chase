# Roadmap und Abnahmereihenfolge

## Sprint 1 – bedienbarer Kern
- Symfony-Bootstrap, SQLite-Migrationen, Demo-Fixtures, Authentifizierung/Logout, Rollen, Objekt- und Lead-Rechte.
- Dashboard nach Referenz, responsive Sidebar, Dark Mode, Suche.
- Objekt-CRUD, Detailseite, Status, Owner-Scope, drei Tests.
- Ergebnis: Benutzer kann sich anmelden, Objekt anlegen und sein Objekt wiederfinden.

## Sprint 2 – produktiver Arbeitsfluss mit Demo-Fakten
- Objektbezogener regelbasierter Exposé-/Social-Generator, Speichern von Versionen.
- Aufgabenliste und Objektchecklisten, Lead-CRUD/Pipeline.
- Marketingkalender mit Entwürfen, News/Unread; sicheres Demo-onOffice-Upsert.
- Ergebnis: Objekt -> Exposé -> Post -> Kalender und Lead -> Aufgabe funktionieren vollständig lokal.

## Sprint 3 – Wissens- und Partnerhub
- Academy, Dokumente/Downloads, Werbekatalog/Merkliste, Partnerverzeichnis, Admin-Center mit News und Useraktivierung.
- Suche/Filter, KI-Kosten-Demo, Upload-Preview, Test- und UX-Politur.
- Ergebnis: erster überzeugender Partnerportal-Pilot.

## Sprint 4 – Integrationsvorbereitung und optionaler erster echter Service
- Prüfbare Adapter-/OAuth-Konzeption, anpassbare Mappings, onOffice-Read-only-Test nach separater Freigabe.
- Echte OpenAI-Textgenerierung erst mit AVV-/Datenfreigabe, Budgetlimits und explizitem Test-Key.
- Später Bilder, Grundriss, Video, Bewertungsanbieter, Social Publishing, SSO.

## Qualitäts-Gates
1. Jeder neu zugängliche Endpoint hat Auth-/Rechteschutz.
2. Es ist möglich, mit Demo-Accounts unterschiedlicher Regionen echte Zugriffstests auszuführen.
3. Keine Fake-Ergebnisse. Jeder Button tut erkennbar etwas oder ist mit Begründung deaktiviert.
4. Die Datenbank startet aus einem frischen Checkout über klare Befehle.
5. Auf Desktop und 390px Mobile ist die Startseite bedienbar.
6. Kennzahlen kommen aus DB statt magischen Zahlen.
7. Kein echter externer Daten- oder Geldverkehr.
