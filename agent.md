# Working rules for this repository

## GUI rule

- This project is a Kimai plugin. Its GUI is generated from Knust (`kimai/knust/` in shrippen/Kante),
  the Kante spinoff that adapts Kante to Kimai's look, and the UI kit (`shrippen/kimai-plugin-ui`,
  `kpu-*` markers and macros), not inspired by them: use their tokens, classes, markers, macros and
  components as they are. The kit in `Resources/views/_kit/` changes only through `bin/sync.sh`.
- No own colours, fonts, sizes, radii, shadows, focus styles, animation timings, no own copy or
  variant of a component that Knust or the kit has. Raw values (`#hex`, `px` for controls) are a bug.
  Colours needed in JavaScript (charts) are read at runtime from `--knust-*` with a fallback.
- A missing element is added to Knust or the kit first, then used here. Never solve it locally.
  Where it would also help other projects, it is added to Kante as well
  (https://github.com/shrippen/shrippen.github.io, `kante/`).
- Rule text for all projects: https://github.com/shrippen/Kante/blob/main/AGENT-RULE.md

## Repository rule

- This repository lives on Gitea (`git.arianw.de`). GitHub is only a push mirror of it.
- Changes arrive as pull requests only: work on a branch, open a PR, leave the merge to the owner (who merges on Gitea; the mirror follows).
- Never merge a PR, push to `main` (or any default branch), push tags or publish releases on GitHub. A merge there is overwritten by the next Gitea push.
- Never force-push a branch that someone else's PR depends on.
