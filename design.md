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

### Gruppierung
- Kunde → Projekt → Einträge
- Jede Ebene zeigt Summen (Dauer + Betrag)

### Button-Typen
1. **"Eintrag abrechnen"** (pro Zeile) – einzelnen Eintrag abrechnen / zurücknehmen
2. **"Projekt abrechnen"** (pro Projektgruppe) – alle Einträge des Projekts
3. **"Kunde abrechnen"** (pro Kundengruppe) – alle Einträge des Kunden
4. **"Alle sichtbaren abrechnen"** (oben) – alle sichtbaren Einträge

### AJAX-Verhalten
- Alle Buttons nutzen AJAX (fetch mit `X-Requested-With: XMLHttpRequest`)
- Kein Seitenreload – Einträge werden visuell durchgestrichen
- Button wechselt zu "Rückgängig" (gelb)
- Zweiter Klick → zurücknehmen (Normalzustand)
- Der Client schickt immer die gewünschte Aktion (`action=mark|unmark`), der Server setzt den Status (kein Toggle). Doppelklicks oder Bulk über teils markierte Einträge können so nichts versehentlich zurücknehmen
- Fehler (HTTP/Netzwerk) und übersprungene/fehlgeschlagene IDs werden per Kimai-Alert angezeigt
- **Statusbasiert**: Server liefert `states: {id: bool}` – Client aktualisiert alle Buttons basierend auf tatsächlichem Zustand
  - Kunde-Rückgängig → Projekt-Button wechselt ebenfalls zurück (wenn nicht alle Einträge markiert)

### Filter
- Monat, Jahr, Kunde, Mitarbeiter
- Filter werden per GET-Formular gesetzt; ungültige Werte (z. B. `month=13`) werden ignoriert statt einen Fehler zu erzeugen
- Bei GET-Formular: normaler HTTP-Submit mit Redirect

### Styling
- Keine eigenen Farben – Kimai-Theming wird verwendet
- Kunden- und Projekt-Farbcircles: `widgets.label_dot()` Macro aus Kimais `macros/widgets.html.twig`
- Buttons: Kimai-Standard (btn-success, btn-outline-success, btn-outline-warning)

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
- Kunden-/Mitarbeiter-Dropdowns über `getQueryBuilderForFormType()` (sichtbare Kunden, aktive User, Team-Scoping); Filterwerte außerhalb dieser Listen werden ignoriert
- Gruppierung in PHP (nach Customer → Project)

### Controller
- `AbrechnungController`: GET-Index + POST-Mark
- AJAX-Erkennung: `X-Requested-With: XMLHttpRequest` Header
- CSRF-Token (`abrechnung.mark`) wird im AJAX- und Formular-Pfad geprüft
- `action=mark|unmark` setzt `exported` für alle übergebenen IDs (idempotent); Rechte pro Eintrag über den Voter `edit_export`, Zurücknehmen zusätzlich `edit_exported_timesheet`
- Fehler beim Speichern werden pro Eintrag abgefangen und gemeldet
- Response: `{success, states: {id: bool, ...}, skipped: [id], failed: [id]}`
- Beträge nur mit `view_rate` (Voter) sichtbar; Summen werden ausgeblendet, sobald ein Eintrag der Gruppe verborgen ist

### Twig
- Template erbt von `base.html.twig`
- Importiert `macros/widgets.html.twig` für `label_dot()`
- JavaScript im `javascripts`-Block mit `kimai.initialized` Event-Listener

## Offene Punkte / Zukunft

- Badge-Anzahl im Sidebar-Menü (optional, Tabler-kompatibel prüfen)
- Export der Abrechnungsübersicht (CSV/PDF)
- Zeitraum-Schnellauswahl (dieser Monat, letzter Monat, etc.)
