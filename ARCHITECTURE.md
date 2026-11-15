# Architecture

How this repository is meant to be built. Where the code differs today is listed under
"Known deviations". Change this file in the PR that changes the structure.

## What it does

A Kimai plugin with one page: the billable timesheet entries that are not yet exported,
grouped by customer and project, with their sums. From there the user marks entries as
exported ("bill"), takes that back within 15 minutes ("undo") or reopens them. The
plugin has no tables of its own; it reads and changes Kimai's entries.

## Building blocks

| Path | Its one task |
|---|---|
| `Controller/AbrechnungController.php` | The page and its actions (bill, undo, reopen): reads the request, asks for the work, builds the answer as page or JSON. |
| `Service/` (target; does not exist yet) | The work, one class per task: setting the export state of entries with its permission check and result message; grouping entries by customer and project with sums; the undo entries kept in the session. |
| `Repository/Query/AbrechnungQuery.php` | The filter of the page: customers, users, search term, state, period, sorting. The one place that knows which fields a filter has, their limits and defaults. |
| `Repository/OpenItemsRepository.php` | Every query for Kimai's entries: the list for a filter, entries by id. |
| `Form/AbrechnungToolbarForm.php` | The filter as the toolbar form of the page. |
| `EventSubscriber/` | Hooks into Kimai: the menu entry, the page's actions (`Actions/`). |
| `Resources/views/abrechnung/` | The page template. `Resources/views/_kit/` is the vendored Kimai plugin kit, never edited here. |
| `Resources/translations/` | All texts, German and English, same keys in both. `kpu.*.xlf` are the kit's texts, vendored and never edited here. |
| `AbrechnungBundle.php`, `DependencyInjection/`, `Resources/config/` | Registration in Kimai: services and routes. |
| `dev/` | Checks: translations, and `ci.sh`, which installs the release zip into Kimai and loads the pages. |
| `demo/`, `docs/`, `scripts/`, `tools/` | Not part of the plugin zip: demo data, landing page, release and check scripts. |

## Layers

```
 entry points   Controller (with its Form)   EventSubscriber
                     │  ask for the work, show the answer
                     ▼
 work           Service ─────────────► Kimai's services (saving an entry, permissions)
                     │
                     ▼
 storage        Repository (OpenItemsRepository) ──► Kimai's repositories
                     │
                     ▼
 model          AbrechnungQuery   Kimai's entities   (used by every layer above)
```

- Entry points use Kimai's building blocks for pages, forms, texts and the menu
  directly (page setup, data table, formatter, token and permission check).
- The controller holds no rule: who may change which entry, what undo requires, how
  entries are grouped and summed are services.
- Only `Repository/` loads entries. Nothing else uses Kimai's repositories.
- The filter describes itself: which fields it has, how it is read from an address and
  written into one, its limits and defaults. No other place lists the fields an
  address carries; the form and the query each use the fields they need.
- How the page's states (open, billed, all) map to Kimai's export states stands once,
  with the filter, in Kimai's named constants.
- An action exists once: bill, undo and reopen share one way to change the export state
  and one way to answer (page or JSON, success or error).
- The template writes a repeated control once and reuses it; what other plugins need
  too belongs in the kit in Kante.
- Nothing is public that only its own class uses.
- Rules have tests that run without Kimai where possible (period, grouping, old
  links); the end-to-end check calls every action.

## Where new things go

| Change | Touch |
|---|---|
| A new filter field | `Repository/Query/AbrechnungQuery.php`, `Form/AbrechnungToolbarForm.php`, the query in `Repository/OpenItemsRepository.php`, translations |
| A new column or sum on the page | The grouping service, `Resources/views/abrechnung/index.html.twig`, translations |
| A new action on entries | A route in the controller, the service that changes the export state, a button in the template, a call in `dev/ci.sh`, translations |
| A new query | `Repository/OpenItemsRepository.php` |
| A new page | `Controller/`, a template, `EventSubscriber/MenuSubscriber.php`, `EventSubscriber/Actions/`, translations (routes come from the controller's attributes) |
| A new text | `Resources/translations/messages.*.xlf` or `flashmessages.*.xlf`, German and English, same key |
| A design element that is missing | Knust or the kit in Kante, then sync; never `_kit/` here |
| A release | The steps in `RELEASE.md` |

## Known deviations

- There is no `Service/`: the controller builds the page, computes the navigation,
  groups and sums, changes the export state and keeps the undo entries, in one file of
  704 lines (`AUDIT.md`, finding 4).
- The controller loads entries from Kimai's repository itself for bill and undo
  (`AUDIT.md`, finding 5).
- Bill and undo run the same steps twice (`AUDIT.md`, finding 3).
- Which filter fields an address carries is listed in three places that already
  differ (`AUDIT.md`, finding 2).
- The mapping to Kimai's export states stands twice, once in bare numbers
  (`AUDIT.md`, finding 1); year limits and the default sorting stand several times
  (`AUDIT.md`, finding 8).
- The template writes the bill and reopen buttons six times (`AUDIT.md`, finding 6).
- Three constants of the controller are public without need (`AUDIT.md`, finding 9).
- No test checks a rule; the end-to-end check calls neither bill nor undo
  (`AUDIT.md`, finding 7).
