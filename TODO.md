# TODO – Review-Befunde

Legende: ✅ = live reproduziert, 📖 = aus Code-Review

## P0
- [x] ✅ `mark` idempotent: expliziter Parameter action=mark|unmark vom Client, Server setzt statt toggelt (AbrechnungController.php:123)
  – ohne gültiges `action` → 400 `invalid_action`; wiederholtes `mark` bleibt `true`
- [x] ✅ CSRF-Token rendern und mit isCsrfTokenValid prüfen (auch Nicht-AJAX-Pfad)
  – Token-ID `abrechnung.mark`; AJAX → 400 `invalid_csrf_token`, Formular → Flash `action.csrf.error`

## P1
- [x] ✅ Team-Scoping: TimesheetQuery + setCurrentUser() bzw. Kimai's team-aware repository methods; Kunden-/User-Dropdowns ebenso (keine deaktivierten User); Beträge nur mit view_rate_own_timesheet/view_rate_other_timesheet
  – `TimesheetRepository::getTimesheetsForQuery()`; Dropdowns über `getQueryBuilderForFormType()`; Filter-IDs außerhalb der Dropdowns (z. B. `?user=3` für lead1) werden ignoriert; Beträge über Voter `view_rate`
- [x] ✅ year/month validieren (kein 500)
- [x] 📖 TimesheetRepository::setExported() statt saveTimesheet() bzw. Fehler pro Eintrag abfangen und melden (Lockdown → kein 500 mitten im Bulk)
  – Lockdown-Teil kein Fehler: `saveTimesheet()` validiert keinen Lockdown und der Voter `edit_export` prüft ihn nicht (wie in Kimai selbst). Fehler werden jetzt trotzdem pro Eintrag abgefangen und als `failed` gemeldet. `setExported()` bewusst nicht verwendet: kann nur markieren, schluckt Exceptions still, feuert keine Events
- [x] 📖 Monatsgrenzen in User-Zeitzone (DateTimeFactory createStartOfMonth/EndOfMonth; Anzeige mit Kimai date filters)
  – kein Fehler: Kimai setzt `date_default_timezone_set()` pro Request auf die User-Zeitzone (UserEnvironmentSubscriber), live mit Europe/Berlin-User geprüft. Trotzdem auf `DateTimeFactory` (`getStartOfMonth`/`getEndOfMonth`) und `date_short` umgestellt

## P2
- [x] 📖 JS: .catch + r.ok, Fehler anzeigen; übersprungene IDs melden
- [x] 📖 Joins (addSelect p,c,a,u) gegen N+1
  – Kein N+1 pro Zeile (Doctrine lädt jede Entität nur einmal), aber je ein Query pro Projekt/Tätigkeit/User. Durch `getTimesheetsForQuery()` lädt Kimai diese jetzt gebündelt (per Query-Log geprüft)
- [x] 📖 i18n: Template-Strings über Translation-Keys, englische .xlf ergänzen (Menü zeigt sonst roher Key auf /en)
- [x] 📖 Aufräumen: setHelp-Link auf nicht existierende Doku, unbenutztes countOpenItems, Jahresauswahl nur 4 Jahre
  – Jahresauswahl reicht jetzt bis zum ältesten offenen Eintrag
