# Sicherheit, rechtliche Grenzen und Datenschutz

- Demo-Daten sind **synthetisch**. Keine echten E-Mails oder Kontaktformulare ungewollt versenden.
- Sicherheitsziel bei Multi-Tenant-Portal: Owner-Scope und rollenbasierte Datensatzrechte unabhängig von Navigation, inklusive Dateidownload, Suche und API.
- Passwörter hashen, MFA für echte Admins später prüfen, Session-/CSRF-Schutz, sichere Cookies, Rate-Limit, Adminaktionen protokollieren, sichere Log-Ausgaben ohne Inhalte.
- Datenschutz: Rollen und Zweckbindung, Speicherbegrenzung, Uploadbeschränkungen, AVV und Drittlandtransfer je API prüfen. Eine Software ist nicht allein wegen EU-Hosting automatisch DSGVO-konform.
- Dokumente: Quelle, Seitenzahl, Unsicherheit und menschliche Prüfung bei Analysen. Echte sichere Schwärzung erfordert irreversible Entfernung statt grafischer Überdeckung. Nichts davon in P0 behaupten.
- Bewertung: Kein belastbarer Verkehrswert ohne geeignete lizensierte Datenbasis und fachliche Prüfung. Demo-Berechnung ist mathematisches Spielbeispiel.
- Generierte Bilder: Als Visualisierung kenntlich machen, Original erhalten, architektonische Merkmale nicht unbemerkt verändern.
- Social und Leads: Keine Veröffentlichung oder Werbung per Mail ohne ausdrückliche Freigabe, Plattformberechtigung und tragfähige datenschutz-/wettbewerbsrechtliche Grundlage.
- API-Kosten: Nutzerbezogene Quote, Kreditsperre, separate Providerpreise und Budgetalarm serverseitig, atomare Belastungen beim späteren Echtbetrieb.
- Admin darf nicht allein durch Zugehörigkeit zur Regionalleitung personenbezogene fremde Kundendaten sehen; explizite Rechtezuordnung.
