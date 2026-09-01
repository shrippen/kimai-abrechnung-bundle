# Kimai Abrechnung Bundle

Ein Kimai Plugin für die Abrechnungsübersicht. Zeigt alle abrechenbaren, noch nicht exportierten Zeiteinträge – gruppiert nach Kunde und Projekt.

## Features

- Übersicht aller offenen (abrechenbar + unexportiert + beendet) Zeiteinträge
- Gruppierung nach **Kunde → Projekt → Einträge**
- Filter: Monat, Jahr, Kunde, Mitarbeiter
- **AJAX-Toggle**: Eintrag/Projekt/Kunde/Alle abrechnen ohne Seitenreload
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
| Bereits exportierte Einträge abwählen | `edit_exported_timesheet` |

## Technik

- **Keine eigene Datenbank** – nutzt das bestehende `exported`-Flag der Timesheet-Entität
- **AJAX-Endpunkte**: POST `/de/abrechnung/mark` mit `X-Requested-With: XMLHttpRequest`
- **Persistenz**: `TimesheetService::saveTimesheet()` – gleicher Code-Pfad wie Kimais Export-Button
- **Rollback**: Plugin-Ordner löschen + Container-Neustart, keine DB-Migrationen nötig

## Lizenz

GPL-3.0-or-later
