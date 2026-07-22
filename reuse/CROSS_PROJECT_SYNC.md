# Cross-project sync

Authoritative for how projects on this stack stay aligned with the shared source of truth — **this
blueprint** — and how improvements flow back into it. This is the mechanism that turns "we have a
stack" into "every project keeps the stack current, and the stack keeps every project consistent."

## The model: a hub, not pairs

**This blueprint repository is the canonical source** of the stack's rules, architecture, patterns,
and reusable concepts. Every project **conforms to the blueprint** and exchanges improvements *with
the blueprint* — not directly with its siblings.

```text
                        ┌───────────────────────────┐
                        │      dev-stack-blueprint   │   ← canonical source
                        │  rules · architecture ·    │
                        │  patterns · reusable ideas │
                        └───▲───────▲───────▲────────┘
             conform +      │       │       │      conform +
             propose  ──────┘       │       └──────  propose
                        ┌───────────┴───────────┐
                     project A   project B   project C …
```

This replaces the older pairwise model, in which two projects kept a bidirectional ledger against
each other. Pairwise sync does not scale past two projects and lets rules drift into N different
"latest" versions. With a hub, there is exactly one place the stack's truth lives, and each project's
only sync relationship is with that hub.

The governing rule ([development principles](../principles/DEVELOPMENT_PRINCIPLES.md) §0/§1) still
holds: **an unrecorded reusable improvement is an incomplete change.** Recording is part of the
definition of done — only now it is recorded against the blueprint.

## The blueprint-sync ledger

Each project keeps **one** lightweight ledger, `docs/BLUEPRINT_SYNC.md`, with two parts.

### 1. Conformance baseline (blueprint → project)

The exact blueprint revision this project has reconciled with: the blueprint repo path, the commit
hash, and the date conformed. When the blueprint advances, the project reviews the blueprint's diff
since this baseline, applies whatever changed **through its own domain boundaries**, and moves the
baseline forward. "What is new in the stack" is therefore a precise question — the diff since the
recorded commit — not a guess.

A project does not silently fall behind: a blueprint change that a project has not reconciled with is
visible as a stale baseline, not as invisible drift.

### 2. Upstream proposals (project → blueprint)

Improvements made **in the project** that belong in the stack — a new reusable pattern, a sharper
rule, a better shared utility. Each is a **candidate**, recorded here in the same change that creates
it, and carried to the blueprint through the blueprint's own [working
agreement](../principles/WORKING_AGREEMENT.md). Status per candidate:

| Status | Meaning |
| --- | --- |
| Proposed | A reusable improvement identified here, offered to the blueprint |
| Merged | The blueprint adopted it — record the blueprint commit hash |
| Rejected | Considered for the stack and declined, with the reason |
| Project-specific | Deliberately kept local; not a stack concern |

A healthy "Proposed" list is the stack's improvement pipeline made visible.

## What conforming means (and does not)

- **Conform to the rules and architecture, adapt to the domain.** A project applies the blueprint's
  principles, boundaries, and patterns; it does *not* copy another project's product assumptions. The
  blueprint is deliberately neutral so that conforming never imports someone else's domain.
- **Reconcile, do not overwrite.** Porting a blueprint change means re-expressing it through the
  project's own domain seams, exactly as porting a pattern always has.
- **The blueprint is edited through its own workflow.** A project proposes; the blueprint's owner
  merges. Do not edit the blueprint from a project session unless the owner asks explicitly.

## The recurring sync procedure

1. Read the blueprint's current normative core and any area document your work touches — **docs before
   diff**.
2. Compare the blueprint's revision against the project's recorded baseline; inspect the relevant
   diffs.
3. Classify each blueprint change: applies to this project, or project-irrelevant. Apply the relevant
   ones through the project's domain boundaries.
4. In the same pass, record any improvement made **here** as an upstream proposal.
5. Update the baseline and the proposals, then run the acceptance [`check`](../components/MONOREPO.md).

## For a brand-new project

Creating a project from this blueprint: read the [new project guide](../NEW_PROJECT_GUIDE.md), scaffold
against the blueprint, then create `docs/BLUEPRINT_SYNC.md` with the blueprint commit it was built
from as the conformance baseline. From then on it participates in the same hub exchange as every other
project.

## Migrating from a pairwise ledger

A project that previously kept a sibling-to-sibling ledger (e.g. an early `*_SYNC.md` against another
product) retires it once its reusable contents are represented in the blueprint: verify each still-
relevant candidate exists in the blueprint (add it there first if not), then replace the old ledger
with a `docs/BLUEPRINT_SYNC.md` whose baseline is the blueprint commit that absorbed them. Historical
provenance ("this idea originated in project X") may stay as a factual note; the *live* sync
relationship is now only with the blueprint.
