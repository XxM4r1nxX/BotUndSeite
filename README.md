# BotUndSeite

Moderne, modulare Verwaltungsoberfläche für deinen Discord-Bot. Das Dashboard bietet Login, Rollen & Berechtigungen, Ticket-Transkripte und gesicherte API-Reiter im Discord-inspirierten Design.

## Features
- Login mit 24h-"angemeldet bleiben" Option.
- Automatisches Einrichten der MySQL-Datenbank (`ticketsystem_webseite`) und aller Tabellen/Standardrechte.
- Rollenbasiertes Berechtigungssystem mit Verwaltung im Dashboard.
- Ticket-Transkript-Modul: HTML-Uploads via API, Anzeige, Download und Löschung nach Rechten.
- Gesicherter API-Reiter mit zusätzlichem Passwort (`BotAPI`) und Berechtigung.
- Admin-Schalter für Wartungsmodus (nur berechtigte Nutzer).
- Animiertes, Discord-ähnliches UI mit erweiterbarem Modul-Aufbau.

## Starten
1. Stelle sicher, dass PHP mit PDO-MySQL aktiv ist und die MySQL-Zugangsdaten erreichbar sind (siehe `config.php`).
2. Lege den Projektordner in deinen Webserver-Dokumenten ab (oder starte `php -S localhost:8000`).
3. Rufe `index.php` im Browser auf und melde dich an.

## Merge-Hinweise
- Die Datei `.gitattributes` normalisiert Zeilenenden (LF), um Cross-Platform-Merge-Konflikte zu vermeiden.
- Falls GitHub Konflikte meldet, führe lokal ein `git pull --rebase` auf deinen Zielbranch aus und pushe erneut.
- Prüfe nach dem Rebase kurz die PHP-Syntax mit `php -l index.php dashboard.php config.php`.

## API: Ticket-Transkripte
- Endpoint: `POST /api/transcript_upload.php`
- Body (multipart/form-data):
  - `api_password` = `BotAPI`
  - `title` = Titel des Transkripts
  - `file` = HTML-Datei
- Antwort: JSON mit Download- und View-Link. Weitere Modul-APIs können nach dem gleichen Muster ergänzt werden.
