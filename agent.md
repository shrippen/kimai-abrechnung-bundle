# Working rules for this repository

The rules for all shrippen repositories are in `RULES.md` (writing, code, commits, bug
fixes, repository, GUI). It is a synced copy from the rules repository; do not edit it
here. This file holds only what is particular to this repository. A Claude Code session on
the owner's machine has the rules through the global `CLAUDE.md` already and reads
`RULES.md` only for its GUI rule, before work on a user interface.

## Project

Abrechnung (`AbrechnungBundle`, Kimai ≥ 2.65): billing overview of billable timesheets that are not
yet exported, grouped by customer; marks them as exported (with undo) or reopens them.

- `Controller/AbrechnungController.php` (routes `abrechnung_*`, rights via Kimai voters such as
  `view_invoice`, `edit_export`) → `Repository/OpenItemsRepository.php` (queries) → Kimai entities.
  No own tables.
- Texts in `Resources/translations/*.{de,en}.xlf`, same keys in both.
- Checks (CI, `.gitea/workflows/ci.yml`): PHP syntax 8.1–8.4, `php dev/check-translations.php`,
  and `dev/ci.sh`, which installs the release zip into Kimai 2.67 and loads the pages.
- `demo/` (screenshots) and `dev/` never ship: `.gitattributes` keeps them out of the zip,
  `services.yaml` excludes them.

## Release

Release steps: `RELEASE.md`. Before a release, load the skill `release-changelog`. A release
is published, so the owner confirms it in words.

## Repository (this project)

- Everything else: `RULES.md` (Repository) and the skill `pr-review`.
