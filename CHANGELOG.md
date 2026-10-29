# Changelog

Release notes, newest first. Each section is the text of the release on Gitea and GitHub and the
entry in `#changes` on the landing page (`docs/index.html`).

## [1.1.1] — 2026-10-29

### English

A fix for billing in Firefox: after billing, no entries stay selected.

#### Fixed
- After "Mark as billed", Firefox ticked the next open entries, because it restores checkboxes by
  their position when the page reloads and the billed rows were gone. The selection now starts empty
  after every load.

#### Improved
- Buttons, notices and status labels follow the current Kimai plugin kit (0.9.1).

All changes: https://github.com/shrippen/kimai-abrechnung-bundle/compare/v1.1.0...v1.1.1

### Deutsch

Eine Korrektur fürs Abrechnen in Firefox: Nach dem Abrechnen bleibt kein Eintrag markiert.

#### Behoben
- Nach „Abrechnen“ markierte Firefox die nächsten offenen Einträge, weil er Checkboxen beim Neuladen
  nach ihrer Position wiederherstellt und die abgerechneten Zeilen fehlten. Die Auswahl ist jetzt nach
  jedem Laden leer.

#### Verbessert
- Buttons, Hinweise und Statusanzeigen folgen dem aktuellen Kimai-Plugin-Kit (0.9.1).

Alle Änderungen: https://github.com/shrippen/kimai-abrechnung-bundle/compare/v1.1.0...v1.1.1

## [1.1.0] — 2026-09-02

### English

Each entry row now names its activity.

#### New
- New column "Activity" in the entry list, between description and user.

All changes: https://github.com/shrippen/kimai-abrechnung-bundle/compare/v1.0.0...v1.1.0

### Deutsch

Jede Eintragszeile nennt jetzt ihre Tätigkeit.

#### Neu
- Neue Spalte „Tätigkeit“ in der Eintragsliste, zwischen Beschreibung und Mitarbeiter.

Alle Änderungen: https://github.com/shrippen/kimai-abrechnung-bundle/compare/v1.0.0...v1.1.0

## [1.0.0] — 2026-09-01

### English

The first release: one page with every billable entry that is not exported yet, grouped by customer
and project.

#### New
- Customer → project → entries, with sums of duration and amount.
- Bill a single entry, a project, a customer or everything at once, without reloading; billed entries
  are struck through and can be undone.
- Filter by month, year, customer and user.
- Menu item "Billing" under Invoices.

#### Note
- No own database tables and no migration: the plugin only sets Kimai's `exported` flag. Remove it by
  deleting the folder.
- Needs the permissions `view_invoice` and `edit_export_*_timesheet`; Kimai 2.65 or newer, PHP 8.1 or
  newer.

### Deutsch

Die erste Version: eine Seite mit allen abrechenbaren, noch nicht exportierten Einträgen, gruppiert
nach Kunde und Projekt.

#### Neu
- Kunde → Projekt → Einträge, mit Summen für Dauer und Betrag.
- Einen Eintrag, ein Projekt, einen Kunden oder alles auf einmal abrechnen, ohne Neuladen; abgerechnete
  Einträge werden durchgestrichen und lassen sich zurücknehmen.
- Filter nach Monat, Jahr, Kunde und Mitarbeiter.
- Menüpunkt „Abrechnung“ unter Rechnungen.

#### Hinweis
- Keine eigenen Datenbanktabellen und keine Migration: Das Plugin setzt nur Kimais Flag `exported`.
  Entfernen durch Löschen des Ordners.
- Braucht die Rechte `view_invoice` und `edit_export_*_timesheet`; Kimai 2.65 oder neuer, PHP 8.1 oder
  neuer.
