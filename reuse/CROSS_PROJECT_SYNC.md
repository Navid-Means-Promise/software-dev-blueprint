# Cross-project reuse and sync

Authoritative for how sibling projects on this stack **deliberately** exchange improvements without
silently coupling. This is the mechanism that turns "we have a stack" into "each project makes the
stack better." It generalizes the ledger both existing projects already keep between them.

## The problem it solves

Projects on this stack share architecture, patterns, workflow, and design concepts, but they are
**different products**. Uncontrolled copying couples them (a change in one silently assumes something
about the other); no sharing at all wastes every improvement. The answer is a **recorded, deliberate
exchange**: reuse is intentional, classified, and written down in the same change that creates it.

The governing rule ([development principles](../principles/DEVELOPMENT_PRINCIPLES.md)): **an
unrecorded reusable improvement is an incomplete change.** Recording is not paperwork after the fact;
it is part of the definition of done.

## The sync ledger

Each project keeps one **sync ledger** per sibling relationship, in its `docs/`. The ledger has four
parts.

### 1. Audited baseline

The exact upstream revision this project has reviewed: sibling repo path, branch, commit hash, audit
date, and worktree cleanliness. A sync always compares the sibling's *current* revision against this
baseline, so "what is new upstream" is a precise question, not a guess.

### 2. Adoption ledger (inbound: sibling → this project)

Every element taken **from** the sibling, with its **reuse level** and how it was realized here:

| Reuse level | Meaning |
| --- | --- |
| Direct policy reuse | The same rule/config adopted verbatim |
| Adapted | The same idea, re-expressed for this product's domain |
| Concept | Only the approach was borrowed; the implementation is new |
| Reimplemented | Rebuilt here, often improved, from the sibling's idea |

Each row names the element, its reuse level, this project's implementation, and a note on what
differs. Adaptation is expected — never copy a sibling's domain assumption wholesale into this one.

### 3. Backport ledger (outbound: this project → sibling)

Improvements made **here** that may help the sibling. These are **candidates, not automatic edits** to
the other repository. Each has a status:

| Status | Meaning |
| --- | --- |
| Proposed | A reusable improvement identified here, offered to the sibling |
| Ported | The sibling adopted it — record the sibling's commit hash |
| Rejected | Considered and declined, with the reason |
| Not applicable | The sibling's domain makes it irrelevant |

Each row carries the date added, the candidate, why it may help the sibling, and the source location
here. A "Proposed" backlog is healthy — it is the stack's improvement pipeline made visible.

### 4. Recurring sync checklist

The repeatable procedure for a deliberate sync:

1. Read the sibling's current `README`, architecture, contributing, roadmap, and performance docs —
   **documentation before diff**.
2. Compare the sibling's revision against the audited baseline and inspect the relevant diffs.
3. Classify each change: shared architecture, reusable implementation, product-specific, or not
   applicable.
4. Port through **this** project's own domain boundaries — never copy a sibling's domain assumption
   into a different product.
5. Update the baseline, both ledgers, and the summary, then run the acceptance
   [`check`](../components/MONOREPO.md).

## Boundaries

- The ledger is updated **in the same change** that adopts an upstream element or creates a reusable
  one. Unrecorded reuse is an incomplete change.
- Do **not** edit a sibling project from this one unless the owner asks explicitly. A backport is a
  proposal until the owner directs it and the sibling's own workflow lands it.
- Reuse is classified and adapted, never blindly copied; a sibling's domain assumption stays in the
  sibling.

*Reference implementation:* A Cup of Mystery keeps `docs/24GEMS_SYNC.md` — an audited baseline against
`personal-growth-android-app`, an adoption ledger (monorepo shape, one shared UI, strict-TS policy,
ports/adapters, DI+observer, i18n, Fastify shape, firmware organization, Playwright, commit
convention), and a backport ledger of improvements made in Mystery that are offered back to 24Gems.

## For a brand-new project

When starting a new project from this blueprint, create its sync ledger against whichever existing
project it draws most from. Its first adoption ledger records what it took from the stack; from then
on it participates in the same bidirectional exchange as everyone else. See the
[new project guide](../NEW_PROJECT_GUIDE.md).
