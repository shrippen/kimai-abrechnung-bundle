# Design-Entscheidungen: Kimai Abrechnung Bundle

## Kernkonzept

Das Plugin betrachtet "abrechnen" als eigenständiges Feature, getrennt vom Kimai-Export.
Der User trackt in einem externen Rechnungsprogramm, welche Einträge bereits eine Rechnung haben.
Das Plugin zeigt offene Positionen und ermöglicht das Abhaken.

## Datenmodell

- **Keine eigene Tabelle** – das Plugin liest/schreibt das bestehende `exported`-Flag auf der Timesheet-Entität
- Der `billable`-Flag wird als Filter verwendet (nicht verändert)
- Nach dem Abhaken ist der Eintrag auf der Kimai-Export-Seite als "Exportiert: Ja" sichtbar
- Import-/Rechnungs-Plugins sehen dieselben Daten; gespeichert wird per `TimesheetService::saveTimesheet()` (wie Kimais API `PATCH /api/timesheets/{id}/export`, inkl. Timesheet-Events)

## Menü

- Position: Unter "Rechnungen" (ID: `invoices`) via `ConfigureMainMenuEvent::getInvoiceMenu()`
- Priorität: -10 (nach Kimai-Kern, damit das Rechnungs-Menü bereits existiert)
- Icon: `invoice` (aus Kimais `tabler.yaml`-Mapping → `fas fa-file-contract`)
- Berechtigung: `view_invoice`

## UI-Struktur

Verbindlich ist der gemeinsame UI-Leitfaden [kimai-plugin-ui](https://github.com/shrippen/kimai-plugin-ui)
(`GUIDELINES.md`, `CHECKLIST.md`). Hier steht nur, wie die Abrechnung ihn umsetzt; bei Widerspruch gilt der Leitfaden.

### Seitenkopf
- Titel „Abrechnung · <Zeitraum>“, Kontextzeile (Status · Kunden · Benutzer · Suche) über `kit.context_line`
- Seitenaktionen über `PageActionsEvent` (`AbrechnungActionsSubscriber`): „Alle sichtbaren abrechnen“, „Im Kimai-Export öffnen“
- Zeitraum über `kit.period_nav` (Monat | Jahr). „Alle Zeiträume“ ist keine Einheit, sondern der Zustand ohne Zeitraum-Filter
  (Standard); zurück dorthin über Kimais „Filter entfernen“
- Filter über Kimais Toolbar (`DataTable::setSearchForm()`): Suche, Kunden, Benutzer, Status (Offen/Abgerechnet/Alle), Sortierung

### Liste
- Kunde → Projekt → Einträge; Kunde als `kit.group_header` (Kartenkopf), Projekt als Zwischenzeile (`as_row`, `level: 2`),
  beide mit Farbpunkt und Summen (Dauer, Betrag)
- Zeilen mit Kimais `macros/datatables.html.twig` (Kopf/Fuß, Spaltenklassen für Mobil)
- Auswahl per Checkbox und Sammelleiste `kit.bulk_bar`; Gruppen-Auswahl mit Kit 0.2: Kopf-Checkbox = ganzer Kunde (`kit.bulk_select_group`), Checkbox im Projektkopf (`group_header({select})`), Zeilen mit Gruppen-Schlüsseln `c<Kunde>`/`c<Kunde>-p<Projekt>`; kein eigenes Auswahl-Skript
- „…“ pro Zeile und Gruppe: Abrechnen/Zurücknehmen als Sofort-Aktion (`data-kpu-post`)

### Abrechnen und Rückgängig
- Abrechnen ist umkehrbar: sofort ausführen, Seite neu laden, Hinweis mit „Rückgängig“ (Kit-Toast → `abrechnung_undo`, Rückgängig-Fenster nach GUIDELINES 3.5)
- Der Client schickt immer die gewünschte Aktion (`action=mark|unmark`), der Server setzt den Status (kein Toggle).
  Doppelklicks oder Sammelaktionen über teils abgerechnete Einträge nehmen so nichts versehentlich zurück
- Abgerechnete Einträge erscheinen nur mit Status-Filter „Abgerechnet“/„Alle“, als `kit.status_badge('billed')`
- Fehler (keine Berechtigung, Speichern fehlgeschlagen) als Kimai-Alert mit übersetzter Meldung

## Berechtigungen (wiederverwendet)

| Berechtigung | Verwendung |
|---|---|
| `view_invoice` | Seite anzeigen |
| `edit_export_own_timesheet` | Eigene Einträge abhaken |
| `edit_export_other_timesheet` | Fremde Einträge abhaken |
| `edit_exported_timesheet` | Bereits exportierte zurücksetzen |

Keine eigenen Permissions – vermeidet Rollen-Duplikate im Kimai-Admin.

## Deployment

- Plugin-Ordner: `AbrechnungBundle/` im Kimai-Plugin-Verzeichnis
- **Keine Migration** nötig (keine DB-Änderungen)
- Cache-Clear reicht (wird beim Container-Neustart automatisch ausgeführt)
- Rollback: Ordner löschen + Neustart

## Technische Details

### Repository
- `OpenItemsRepository`: nutzt `TimesheetRepository::getTimesheetsForQuery()` mit `TimesheetQuery` (`setCurrentUser()` → Team-Berechtigungen wie in Kimai; Kimai lädt Projekt/Kunde/Tätigkeit/User gebündelt)
- Filter: billable, nicht exportiert, beendet
- Monats-/Jahresgrenzen über `DateTimeFactory` in der Zeitzone des Users
- Kunden-/Benutzer-Filter über Kimais `CustomerType`/`UserType` (sichtbare Kunden, aktive Benutzer, Team-Scoping); ungültige Werte setzt Kimais Suchformular zurück
- Gruppierung in PHP (nach Customer → Project)

### Controller
- `AbrechnungController`: GET-Index, POST-Mark, POST-Undo
- AJAX-Erkennung: `X-Requested-With: XMLHttpRequest` Header
- CSRF-Token (`abrechnung.mark`) wird im AJAX- und Formular-Pfad geprüft
- `action=mark|unmark` setzt `exported` für alle übergebenen IDs (idempotent); Rechte pro Eintrag über den Voter `edit_export`, Zurücknehmen zusätzlich `edit_exported_timesheet` (wie Kimais API, ohne Ausnahme)
- `undo/{id}`: Rückgängig-Fenster nach GUIDELINES 3.5 – Mark legt `abrechnung.undo.<id>` in der Session ab (Benutzer, IDs, vorheriger Zustand, `modified_at` je Eintrag, Zeit); Undo nur mit diesem Eintrag, gleicher Benutzer, ≤ 15 min, nur diese IDs, nur unveränderte Einträge; danach wird der Eintrag gelöscht. Innerhalb des Fensters ist `edit_exported_timesheet` für die Rücknahme der eigenen Abrechnung nicht nötig (vom Product Owner freigegeben), `edit_export` schon
- Fehler beim Speichern werden pro Eintrag abgefangen und gemeldet
- Response: `{success, states: {id: bool, ...}, changed: [id], skipped: [id], failed: [id], message, undo}`
- Beträge nur mit `view_rate` (Voter) sichtbar; Summen werden ausgeblendet, sobald ein Eintrag der Gruppe verborgen ist

### Twig
- Template erbt von `base.html.twig`, nutzt das Kit (`@Abrechnung/_kit/macros.html.twig`) und Kimais `macros/widgets.html.twig` / `macros/datatables.html.twig`
- JavaScript im `javascripts`-Block mit `kimai.initialized` Event-Listener

## Offene Punkte / Zukunft

- Badge-Anzahl im Sidebar-Menü (optional, Tabler-kompatibel prüfen)
- Export der Abrechnungsübersicht (CSV/PDF)
