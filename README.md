# Kimai Abrechnung Bundle

Ein Kimai Plugin für die Abrechnungsübersicht. Zeigt alle abrechenbaren, noch nicht exportierten Zeiteinträge – gruppiert nach Kunde und Projekt.

## Features

- Übersicht aller offenen (abrechenbar + unexportiert + beendet) Zeiteinträge
- Gruppierung nach **Kunde → Projekt → Einträge**
- Filter: Monat, Jahr, Kunde, Mitarbeiter
- **AJAX**: Eintrag/Projekt/Kunde/Alle abrechnen ohne Seitenreload
- Einträge werden visuell durchgestrichen, mit "Rückgängig"-Option
- Farbcircles für Kunden und Projekte (nutzt Kimai-eigenes Styling)
- Sidebar-Menüpunkt unter "Rechnungen"

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
| Abgerechnete (= exportierte) Einträge wieder abwählen | zusätzlich `edit_exported_timesheet` |
| Beträge sehen | `view_rate_own_timesheet` / `view_rate_other_timesheet` |

Sichtbar sind – wie auf Kimais Zeiterfassungs-Seiten – nur die eigenen Einträge und die der Teams, die man leitet (Admins sehen alle). Die Kunden- und Mitarbeiter-Filter enthalten ebenfalls nur sichtbare Kunden bzw. aktive Mitarbeiter.

## Technik

- **Keine eigene Datenbank** – nutzt das bestehende `exported`-Flag der Timesheet-Entität
- **Abfrage**: `TimesheetRepository::getTimesheetsForQuery()` mit `TimesheetQuery` (inkl. Team-Berechtigungen, wie Kimais eigene Listen)
- **AJAX-Endpunkt**: POST `/de/abrechnung/mark` mit `X-Requested-With: XMLHttpRequest`, Parametern `timesheets[]`, `action=mark|unmark` und CSRF-Token `_token` (ID `abrechnung.mark`). Der Endpunkt setzt den Status (kein Toggle) und ist damit idempotent; Antwort: `{success, states: {id: bool}, skipped: [id], failed: [id]}`
- **Persistenz**: `TimesheetService::saveTimesheet()` pro Eintrag – gleicher Code-Pfad wie Kimais API-Endpunkt `PATCH /api/timesheets/{id}/export` (inkl. Timesheet-Events). Kimais Export-Button nutzt dagegen `TimesheetRepository::setExported()` (DQL-Bulk-Update ohne Events, kann nur markieren)
- **Rollback**: Plugin-Ordner löschen + Container-Neustart, keine DB-Migrationen nötig

## Lizenz

GPL-3.0-or-later
