# TODO – Review-Befunde

Legende: ✅ = live reproduziert, 📖 = aus Code-Review

## P0
- [ ] ✅ `mark` idempotent: expliziter Parameter action=mark|unmark vom Client, Server setzt statt toggelt (AbrechnungController.php:123)
- [ ] ✅ CSRF-Token rendern und mit isCsrfTokenValid prüfen (auch Nicht-AJAX-Pfad)

## P1
- [ ] ✅ Team-Scoping: TimesheetQuery + setCurrentUser() bzw. Kimai's team-aware repository methods; Kunden-/User-Dropdowns ebenso (keine deaktivierten User); Beträge nur mit view_rate_own_timesheet/view_rate_other_timesheet
- [ ] ✅ year/month validieren (kein 500)
- [ ] 📖 TimesheetRepository::setExported() statt saveTimesheet() bzw. Fehler pro Eintrag abfangen und melden (Lockdown → kein 500 mitten im Bulk)
- [ ] 📖 Monatsgrenzen in User-Zeitzone (DateTimeFactory createStartOfMonth/EndOfMonth; Anzeige mit Kimai date filters)

## P2
- [ ] 📖 JS: .catch + r.ok, Fehler anzeigen; übersprungene IDs melden
- [ ] 📖 Joins (addSelect p,c,a,u) gegen N+1
- [ ] 📖 i18n: Template-Strings über Translation-Keys, englische .xlf ergänzen (Menü zeigt sonst roher Key auf /en)
- [ ] 📖 Aufräumen: setHelp-Link auf nicht existierende Doku, unbenutztes countOpenItems, Jahresauswahl nur 4 Jahre
