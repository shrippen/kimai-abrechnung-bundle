# Kimai Abrechnung Bundle

Ein Kimai Plugin für die Abrechnungsübersicht. Zeigt alle abrechenbaren, noch nicht exportierten Zeiteinträge – gruppiert nach Kunde und Projekt.

## Features

- Übersicht aller offenen (abrechenbar + nicht exportiert + beendet) Zeiteinträge
- Gruppierung nach **Kunde → Projekt → Einträge** mit Summen (Dauer, Betrag) und Kennzahlen-Leiste
- Zeitraum **Monat | Jahr** (oder alle Zeiträume), Kimai-Filter für Kunden, Benutzer, Status (Offen/Abgerechnet/Alle) und Suche
- **Abrechnen** per Checkbox-Auswahl und Sammelleiste, pro Zeile/Projekt/Kunde über das „…“-Menü oder „Alle sichtbaren abrechnen“
- Abgerechnete Einträge verlassen die Liste, der Hinweis bietet **Rückgängig**
- Link in Kimais Export mit derselben Auswahl
- Menüpunkt unter „Rechnungen“, Hilfe: [docs/abrechnung.md](docs/abrechnung.md)

## Oberfläche

Die Oberfläche folgt dem gemeinsamen UI-Leitfaden der Plugins
[kimai-plugin-ui](https://github.com/shrippen/kimai-plugin-ui) (`GUIDELINES.md`, `CHECKLIST.md`). Das UI-Kit liegt in
`Resources/views/_kit/` und `Resources/translations/kpu.*.xlf` und wird nur per `bin/sync.sh` aktualisiert, nie von Hand.
Stand der Umstellung: [UI-TODO.md](UI-TODO.md).

## Voraussetzungen

- Kimai >= 2.65.0
- PHP >= 8.1

## Installation

### Manuell

1. Verzeichnis `AbrechnungBundle` in `/var/plugins/` kopieren
2. Container neu starten oder `bin/console cache:clear --env=prod` ausführen
3. Fertig – der Menüpunkt "Abrechnung" erscheint unter "Rechnungen"

### Über Git

```bash
cd /var/plugins/
git clone https://github.com/shrippen/kimai-abrechnung-bundle.git AbrechnungBundle
docker restart kimai_app
```

## Berechtigungen

Das Plugin verwendet bestehende Kimai-Berechtigungen:

| Aktion | Berechtigung |
|--------|-------------|
| Seite anzeigen | `view_invoice` |
| Einträge abrechnen/abwählen | `edit_export_own_timesheet` / `edit_export_other_timesheet` |
| Abgerechnete (= exportierte) Einträge zurücknehmen | zusätzlich `edit_exported_timesheet` (Ausnahme: „Rückgängig“ für Einträge, die dieselbe Sitzung in den letzten 15 Minuten abgerechnet hat) |
| Beträge sehen | `view_rate_own_timesheet` / `view_rate_other_timesheet` |

Sichtbar sind – wie auf Kimais Zeiterfassungs-Seiten – nur die eigenen Einträge und die der Teams, die man leitet (Admins sehen alle). Die Kunden- und Benutzer-Filter (Kimais `CustomerType`/`UserType`) enthalten ebenfalls nur sichtbare Kunden bzw. aktive Benutzer.

## Technik

- **Keine eigene Datenbank** – nutzt das bestehende `exported`-Flag der Timesheet-Entität
- **Abfrage**: `TimesheetRepository::getTimesheetsForQuery()` mit `TimesheetQuery` (inkl. Team-Berechtigungen, wie Kimais eigene Listen)
- **Filter**: `AbrechnungQuery` + `AbrechnungToolbarForm` (Kimai-Toolbar, `handleSearch()` inkl. Standardfilter); Zeitraum als `?period=YYYY-MM` bzw. `?period=YYYY`, ohne `period` alle Zeiträume. Alte Parameter `year`, `month`, `customer`, `user` werden umgeleitet
- **Endpunkt**: POST `/de/abrechnung/mark` mit `ids[]` (oder `timesheets[]`), `action=mark|unmark` (Formular oder Query) und CSRF-Token `_token` (ID `abrechnung.mark`). Der Endpunkt setzt den Status (kein Toggle) und ist damit idempotent. Mit `X-Requested-With: XMLHttpRequest` oder `Accept: application/json` antwortet er mit `{success, states: {id: bool}, changed: [id], skipped: [id], failed: [id], message, undo: {url, token, ids}}` (HTTP 422, wenn nichts geändert werden konnte), sonst mit Redirect und Ergebnis-Hinweis
- **Persistenz**: `TimesheetService::saveTimesheet()` pro Eintrag – gleicher Code-Pfad wie Kimais API-Endpunkt `PATCH /api/timesheets/{id}/export` (inkl. Timesheet-Events). Kimais Export-Button nutzt dagegen `TimesheetRepository::setExported()` (DQL-Bulk-Update ohne Events, kann nur markieren)
- **Rollback**: Plugin-Ordner löschen + Container-Neustart, keine DB-Migrationen nötig

## Lizenz

GPL-3.0-or-later
