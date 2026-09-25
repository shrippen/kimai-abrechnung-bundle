# Abrechnung – Hilfe

Die Seite **Rechnungen › Abrechnung** zeigt alle abrechenbaren, beendeten Zeiteinträge, die noch nicht abgerechnet
(in Kimai: „exportiert“) sind – gruppiert nach Kunde und Projekt.

## Zeitraum und Filter

- **Monat | Jahr** wählt die Einheit, **‹ ›** blättert, **Heute** springt in den aktuellen Zeitraum.
- Ohne Zeitraum („Alle Zeiträume“) zeigt die Seite alle offenen Einträge. Dorthin kommt man über das orange
  **×** („Filter entfernen“) neben der Suche.
- Das Filter-Symbol öffnet Kimais Filter: Kunde(n), Benutzer, Status (**Offen**, **Abgerechnet**, **Alle**) und Sortierung.
  Mit dem Lesezeichen wird die Auswahl als Standard gespeichert.
- Die Kennzahlen oben zeigen Dauer, Betrag (nur mit Berechtigung, Beträge zu sehen), Anzahl Einträge und Kunden
  der aktuellen Auswahl.

## Abrechnen

- Einträge per Checkbox auswählen (die Checkbox im Tabellenkopf wählt alle Einträge des Kunden), dann unten in der
  Leiste **Abrechnen**.
- Einzelne Einträge, ganze Projekte oder Kunden auch über das **…**-Menü der Zeile bzw. Gruppe.
- **Alle sichtbaren abrechnen** oben rechts rechnet alle Einträge der aktuellen Auswahl ab.
- Abgerechnete Einträge verschwinden aus der Liste. Der Hinweis oben bietet **Rückgängig** an.
- Unter Status **Abgerechnet** lassen sich Einträge mit **Zurücknehmen** wieder öffnen
  (Berechtigung „Exportierte Einträge bearbeiten“; eigene Abrechnungen der letzten 15 Minuten gehen auch ohne).

„Abgerechnet“ ist Kimais Kennzeichen „exportiert“. **Im Kimai-Export öffnen** öffnet Kimais Export mit derselben Auswahl.

## Berechtigungen

| Aktion | Berechtigung |
|---|---|
| Seite anzeigen | `view_invoice` |
| Abrechnen | `edit_export_own_timesheet` / `edit_export_other_timesheet` |
| Zurücknehmen | zusätzlich `edit_exported_timesheet` |
| Beträge sehen | `view_rate_own_timesheet` / `view_rate_other_timesheet` |

Sichtbar sind nur die eigenen Einträge und die der Teams, die man leitet (Admins sehen alle).
