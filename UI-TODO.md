# UI-TODO – Umstellung auf kimai-plugin-ui

Grundlage: [kimai-plugin-ui](https://github.com/shrippen/kimai-plugin-ui) `GUIDELINES.md` und `CHECKLIST.md` (Kit 0.2.0),
Plan „AB Abrechnung“ aus dem UI-Leitfaden (Abschnitt 6) und die UI-Inventur.
Legende: `[x]` erledigt, `[ ]` offen (mit Grund).

## Kit

- [x] Kit 0.1.0 mit `bin/sync.sh` übernommen, eigener Commit (`Resources/views/_kit/`, `Resources/translations/kpu.*.xlf`)
- [x] Kit 0.2.0 mit `bin/sync.sh` übernommen, eigener Commit („Update UI kit to 0.2.0“)
- [x] Kit-Templates über `@Abrechnung/_kit/…` eingebunden, CSS/JS je einmal nach `{{ parent() }}`

## Seite `/de|en/abrechnung`

### Seitenkopf
- [x] Titel „Abrechnung · Mai 2025“ / „Abrechnung · 2025“ / „Abrechnung · Alle Zeiträume“ (im Controller gebaut, `LocaleFormatter::monthName`)
- [x] Kontextzeile (`kit.context_line`): Status · Kunden · Benutzer · Suche
- [x] `setActionName('abrechnung')` + `AbrechnungActionsSubscriber` (PageActionsEvent): „Alle sichtbaren abrechnen“ (Icon `success`) und „Im Kimai-Export öffnen“ (Icon `export`, mit gleicher Auswahl vorbelegt, nur mit `create_export`)
- [x] Großer grüner Knopf „Alle sichtbaren abrechnen“ im Inhalt entfernt
- [x] `setHelp()` auf `docs/abrechnung.md` im Plugin-Repo
- [x] Keine zweite Überschrift im Inhalt

### Zeitraum und Filter
- [x] Filterkarte mit vier `<select>` + „Filtern“ ersetzt durch Kimai-Toolbar (`DataTable::setSearchForm()` + `tables.actions()`): Suchfeld, Filter-Dropdown mit Kunde(n), Benutzer, Status, Sortierung; Filter-Badge, Standardfilter (Lesezeichen) und „Filter entfernen“ wie in Kimai
- [x] Kunden-/Benutzerauswahl über Kimais `CustomerType`/`UserType` (Team-Scoping wie bisher: nur sichtbare Kunden, aktive Benutzer der geleiteten Teams)
- [x] Zeitraum über `kit.period_nav` mit Segment **Monat | Jahr**, ‹ › und „Heute“; Zeitraum steht in der URL (`?period=2025-05` bzw. `?period=2025`)
- [x] Entscheidung „Alle“: kein drittes Segment (GUIDELINES 3.1: nur echte Einheiten). Ohne `period` zeigt die Seite **alle Zeiträume** (Standard, wie bisher „Alle/Alle“); das Label lautet dann „Alle Zeiträume“, ‹ › sind deaktiviert, „Heute“ springt in den aktuellen Monat. Zurück zu „Alle Zeiträume“ über Kimais „Filter entfernen“ (der Zeitraum zählt als Filter) oder „Filter zurücksetzen“ im Leerzustand
- [x] Alte Links (`?year=&month=&customer=&user=`) werden auf die neuen Parameter umgeleitet
- [x] Neuer Status-Filter Offen | Abgerechnet | Alle (damit Abgerechnetes sichtbar und zurücknehmbar ist; Standard: Offen)

### Liste
- [x] Kunden-Karten mit `kit.group_header` (Farbpunkt, Summe Dauer + Betrag, „…“ mit Abrechnen / Zurücknehmen / Nur diesen Kunden zeigen)
- [x] Projekt-Zwischenzeilen mit `kit.group_header(…, {as_row: true, level: 2})` (Farbpunkt, Summen, Gruppen-Checkbox über `group_header({select})`, „…“ mit Abrechnen / Zurücknehmen; Menüpunkt „Auswählen“ entfällt)
- [x] Zeilen über `tables.datatable_header` / `tables.data_table_footer` mit Spaltenklassen (`alwaysVisible`, `d-none d-md-table-cell`, `w-min`, `text-end`), Tabelle in `.kpu-table-wrap`
- [x] Zeilenknöpfe „Eintrag abrechnen“ ersetzt durch Checkbox (`kit.bulk_checkbox`) + Sammelleiste `kit.bulk_bar` („Abrechnen“, in Abgerechnet/Alle zusätzlich „Zurücknehmen“)
- [x] Knöpfe „Kunde abrechnen“ / „Projekt abrechnen“ ersetzt durch Gruppen-Checkbox (Kunde im Tabellenkopf über `kit.bulk_select_group`, Projekt im Projektkopf) und Aktionen im Gruppen-„…“
- [x] Kit 0.2: Zeilen-Checkboxen mit Gruppen-Schlüsseln `c<Kunde>`, `c<Kunde>-p<Projekt>` (`kit.bulk_checkbox(…, groups)`); eigene Klasse `abrechnung-select-group` und das komplette Seiten-Skript entfernt (Auswahl, „teilweise“, Abgleich macht kit.js; es gibt keinen Seiten-Code mehr, der die Auswahl zählt – bei Bedarf `kpu:selection-change`)
- [x] Kit 0.2: Abrechnen/Zurücknehmen in Zeilen-, Projekt- und Kunden-„…“ sowie Seitenaktion „Alle sichtbaren abrechnen“ als Sofort-Aktion `data-kpu-post` (JSON `{message, undo}` → Neuladen + Rückgängig-Toast)
- [x] „…“ pro Zeile (`widgets.table_actions`): Abrechnen / Zurücknehmen / Bearbeiten (Kimai-Zeiteintrag im Modal, Liste lädt danach neu)
- [x] Feste Spaltenbreite `style="width:160px"` entfernt

### Status und Rückmeldung
- [x] Nach dem Abrechnen verlassen die Einträge die Liste (Neuladen), Hinweis „3 Einträge abgerechnet · Rückgängig“ (Kit-Undo-Toast → `abrechnung_undo`)
- [x] Durchstreichen + gelber „Rückgängig“-Knopf entfernt; abgerechnete Einträge (Filter Abgerechnet/Alle) mit `kit.status_badge('billed')`, offene mit `open`
- [x] Ohne JS: Sammelformular schickt normal ab, Ergebnis als `kpu_result`-Callout (`kit.result_callouts()`)
- [x] Fehler (keine Berechtigung, Speichern fehlgeschlagen, CSRF) als Kimai-Alert mit übersetzter Meldung statt Fehlercode
- [x] Rückgängig-Fenster nach GUIDELINES 3.5 (vom Product Owner freigegeben): eigene Undo-Route `POST /abrechnung/undo/{id}` mit Session-Eintrag pro Aktion (Benutzer, IDs, vorheriger Zustand, Änderungszeit, Zeitpunkt); nur derselbe Benutzer, dieselbe Sitzung, ≤ 15 min, nur die IDs der Aktion, nur unveränderte Einträge, einmalig. Nur darin dürfen Teamleiter ohne `edit_exported_timesheet` ihre eigene Abrechnung zurücknehmen; `action=unmark` verlangt jetzt immer `edit_exported_timesheet` (alte Sitzungs-Ausnahme pro ID entfernt)

### Kennzahlen und Leerzustand
- [x] `kit.kpi_bar`: Dauer offen, Betrag offen (hervorgehoben), Einträge, Kunden; Beträge je Währung
- [x] Betragskachel und Beträge nur mit `view_rate` (ist ein Betrag verborgen, entfallen Kachel und Summen; dann ist die Dauer hervorgehoben)
- [x] Leerzustand `kit.empty_state` mit „Filter zurücksetzen“ bzw. „Abgerechnete anzeigen“ statt Emoji-Karte

### Sprache
- [x] „Mitarbeiter“ → „Benutzer“, „Eintrag abrechnen“ → „Abrechnen“ (EN „Mark as billed“), Zustand „Abgerechnet“ („Billed“), Gegenaktion „Zurücknehmen“ („Reopen“)
- [x] Alle Texte über `abrechnung.*`-Keys (de + en, gleicher Bestand), Meldungen ohne Ausrufezeichen/Emoji
- [x] Nicht mehr benutzte Keys entfernt (`abrechnung.title`, `.month`, `.year`, `.all`, `.filter`, `.mark_customer`, `.mark_project`, `.mark_entry`, `.undo`, `.error_*`, `.empty`, `.select` (Kit 0.2); Flash `abrechnung.marked_success`, `.unmarked_success`, `.skipped`, `.failed`)

### Darstellung
- [x] 390 px ohne waagrechtes Scrollen (vorher 615 px)
- [x] Plural-Strings decken 0 ab (`{1}…|[0,Inf[…`), auch das neue `abrechnung.result.conflict`
- [x] Dunkelmodus: keine eigenen Farben, nur Tabler-Klassen/Kit
- [x] Kein Inline-Handler, JS startet auf `kimai.initialized`, POST mit CSRF

## Doku
- [x] README und design.md verweisen auf die GUIDELINES, widersprüchliche Abschnitte (Knopf-Typen, Durchstreichen) ersetzt
- [x] `docs/abrechnung.md` als Hilfe-Seite

## Offen
- [x] ~~Gruppen-Checkbox im Kit~~: mit Kit 0.2 (`group_header({select})`, `bulk_select_group`) erledigt
- [ ] Automatische Tests: Das Repo hat kein Test-Setup (kein phpunit.xml, keine Tests); geprüft wurde live mit Playwright
