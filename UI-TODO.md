# UI-TODO – Umstellung auf kimai-plugin-ui

Grundlage: [kimai-plugin-ui](https://github.com/shrippen/kimai-plugin-ui) `GUIDELINES.md` und `CHECKLIST.md` (Kit 0.1.0),
Plan „AB Abrechnung“ aus dem UI-Leitfaden (Abschnitt 6) und die UI-Inventur.
Legende: `[x]` erledigt, `[ ]` offen (mit Grund).

## Kit

- [x] Kit 0.1.0 mit `bin/sync.sh` übernommen, eigener Commit (`Resources/views/_kit/`, `Resources/translations/kpu.*.xlf`)
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
- [x] Projekt-Zwischenzeilen mit `kit.group_header(…, {as_row: true, level: 2})` (Farbpunkt, Summen, „…“ mit Auswählen / Abrechnen / Zurücknehmen)
- [x] Zeilen über `tables.datatable_header` / `tables.data_table_footer` mit Spaltenklassen (`alwaysVisible`, `d-none d-md-table-cell`, `w-min`, `text-end`), Tabelle in `.kpu-table-wrap`
- [x] Zeilenknöpfe „Eintrag abrechnen“ ersetzt durch Checkbox (`kit.bulk_checkbox`) + Sammelleiste `kit.bulk_bar` („Abrechnen“, in Abgerechnet/Alle zusätzlich „Zurücknehmen“)
- [x] Knöpfe „Kunde abrechnen“ / „Projekt abrechnen“ ersetzt durch Gruppen-Checkbox im Tabellenkopf je Kunde und Aktionen im Gruppen-„…“
- [x] „…“ pro Zeile (`widgets.table_actions`): Abrechnen / Zurücknehmen / Bearbeiten (Kimai-Zeiteintrag im Modal, Liste lädt danach neu)
- [x] Feste Spaltenbreite `style="width:160px"` entfernt

### Status und Rückmeldung
- [x] Nach dem Abrechnen verlassen die Einträge die Liste (Neuladen), Hinweis „3 Einträge abgerechnet · Rückgängig“ (`KimaiPluginUi` Undo-Toast → `action=unmark`)
- [x] Durchstreichen + gelber „Rückgängig“-Knopf entfernt; abgerechnete Einträge (Filter Abgerechnet/Alle) mit `kit.status_badge('billed')`, offene mit `open`
- [x] Ohne JS: Sammelformular schickt normal ab, Ergebnis als `kpu_result`-Callout (`kit.result_callouts()`)
- [x] Fehler (keine Berechtigung, Speichern fehlgeschlagen, CSRF) als Kimai-Alert mit übersetzter Meldung statt Fehlercode
- [x] Rückgängig auch für Teamleiter ohne `edit_exported_timesheet`: Einträge, die dieselbe Sitzung in den letzten 15 Minuten abgerechnet hat, dürfen wieder geöffnet werden (sonst gilt weiter Kimais Regel)

### Kennzahlen und Leerzustand
- [x] `kit.kpi_bar`: Dauer offen, Betrag offen (hervorgehoben), Einträge, Kunden; Beträge je Währung
- [x] Betragskachel und Beträge nur mit `view_rate` (ist ein Betrag verborgen, entfallen Kachel und Summen; dann ist die Dauer hervorgehoben)
- [x] Leerzustand `kit.empty_state` mit „Filter zurücksetzen“ bzw. „Abgerechnete anzeigen“ statt Emoji-Karte

### Sprache
- [x] „Mitarbeiter“ → „Benutzer“, „Eintrag abrechnen“ → „Abrechnen“ (EN „Mark as billed“), Zustand „Abgerechnet“ („Billed“), Gegenaktion „Zurücknehmen“ („Reopen“)
- [x] Alle Texte über `abrechnung.*`-Keys (de + en, gleicher Bestand), Meldungen ohne Ausrufezeichen/Emoji
- [x] Nicht mehr benutzte Keys entfernt (`abrechnung.title`, `.month`, `.year`, `.all`, `.filter`, `.mark_customer`, `.mark_project`, `.mark_entry`, `.undo`, `.error_*`, `.empty`; Flash `abrechnung.marked_success`, `.unmarked_success`, `.skipped`, `.failed`)

### Darstellung
- [x] 390 px ohne waagrechtes Scrollen (vorher 615 px)
- [x] Dunkelmodus: keine eigenen Farben, nur Tabler-Klassen/Kit
- [x] Kein Inline-Handler, JS startet auf `kimai.initialized`, POST mit CSRF

## Doku
- [x] README und design.md verweisen auf die GUIDELINES, widersprüchliche Abschnitte (Knopf-Typen, Durchstreichen) ersetzt
- [x] `docs/abrechnung.md` als Hilfe-Seite

## Offen
- [ ] Automatische Tests: Das Repo hat kein Test-Setup (kein phpunit.xml, keine Tests); geprüft wurde live mit Playwright
- [ ] Gruppen-Checkbox im Kit: `kit.group_header` hat keinen Platz für eine Auswahl-Checkbox, deshalb sitzt sie im Tabellenkopf (Kit-Lücke gemeldet)
