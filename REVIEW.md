# Review guide

Every PR is reviewed before it is merged (rule in `RULES.md` and the skill `pr-review`): by
PR-Agent (owner, 22.10.2026); a new Claude agent checks the **Wichtig** of earlier rounds,
and reviews when PR-Agent fails. The reviewer knows nothing of the session that wrote the
change: it gets the PR's intent, its diff and the repository, and this file. The owner does
not read code; this review is the second reader.

## What failure looks like here

Abrechnung (AbrechnungBundle) is a Kimai plugin: it lists billable, not yet exported
entries by customer and project, marks them as exported ("bill"), undoes that within
15 minutes, or reopens them. No tables of its own. A bug that matters changes real
entries or hides them from the invoice:

- **Wrong entries billed:** a bill, undo or reopen that touches entries other than the
  ones shown or selected, skips the `edit_export` check per entry, or reopens exported
  entries without `edit_exported_timesheet` (undo of one's own bill within its window
  needs none).
- **Wrong list:** a filter (customers, users, search, state, period, sorting) that drops
  or adds entries, a month or year boundary off by one, an old link that redirects to
  another period, an export link whose selection differs from the page.
- **Wrong sums:** duration or amount per group that differs from its entries, rates
  shown to a user without `view_rate`.
- **Undo that should refuse:** after 15 minutes, by another user or session, or for
  entries changed since.
- **Breaks Kimai:** a POST without CSRF check, a 500 on the page, a route that fails.
- **Looks wrong:** own colours, sizes or kit copies instead of Knust and the kit; text
  missing in German or English.
- **Ships what it must not:** `demo/` or `dev/` in the release zip.
- **Lies:** README, CHANGELOG or comments that contradict the code.

## What to check in particular

- Layers: as in `ARCHITECTURE.md`; new code goes where it says.
- New translation keys exist in both `Resources/translations/*.de.xlf` and `*.en.xlf`
  (`php dev/check-translations.php`).
- The kit in `Resources/views/_kit/` and `Resources/translations/kpu.*.xlf` change only by
  the kit's `sync.sh`.
- A new or changed action is called in `dev/ci.sh`.
- Release: tag equal to `version` in `composer.json`; a section `## [X.Y.Z] — date` in
  `CHANGELOG.md` in English and German (the release workflow fails without it); the
  same entry at the top of `#changes` in `docs/index.html`, footer version bumped
  (`RELEASE.md`).
- Bug fixes: the cause removed, with a test that failed before (`dev/ci.sh` or a new one).

## What the owner confirms here

"What is published" (`RULES.md`) means here: a release (a tag that GitHub builds into
the plugin zip), a change to the release workflow, a new public page under `docs/`.

## Skip

- `Resources/views/_kit/`, `Resources/translations/kpu.*.xlf` (vendored kit), `docs/shots/`.

## How to report

- Every finding names file and line and the concrete input or state that
  goes wrong. No guess without evidence; if unsure, say so.
- Each finding gets one label: **Wichtig** (a bug from the list above,
  must be fixed or answered before the merge), **Kleinigkeit** (optional,
  at most five) or **Bestand** (the bug was there before this PR; not
  blocking).
- The report starts with the count, e.g. "2 Wichtig, 1 Kleinigkeit" (the
  session puts the heading above it). The count of **Wichtig** holds every
  one still open, also from an earlier round, e.g. "1 Wichtig (offen aus
  Runde 1)". Only when none is open it says plainly: "Nichts zu beheben."
- In a later round: say for each open **Wichtig** of the last round whether
  it is fixed, or whether its answer is accepted, with the reason. PR-Agent
  sees no earlier round: it reports only its own findings with their labels,
  and a new Claude agent closes the open ones (skill `pr-review`).
- Findings in German, code names as they are.
