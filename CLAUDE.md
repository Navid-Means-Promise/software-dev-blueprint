# Claude Code instructions

This repository is the **documentation-only blueprint** for the application stack. It contains no
runnable code — only the specification a project is built from.

**Before scaffolding or advising on any project built on this stack, read
[`principles/DEVELOPMENT_PRINCIPLES.md`](principles/DEVELOPMENT_PRINCIPLES.md) — the single normative
source — then [`principles/WORKING_AGREEMENT.md`](principles/WORKING_AGREEMENT.md).** Everything else
is authoritative in its own area and assumes you have.

## Where things are

| Area | Document |
| --- | --- |
| The rules (principles) | [`principles/DEVELOPMENT_PRINCIPLES.md`](principles/DEVELOPMENT_PRINCIPLES.md) |
| Workflow, branching, merges, answers | [`principles/WORKING_AGREEMENT.md`](principles/WORKING_AGREEMENT.md) |
| Pattern vocabulary | [`principles/DESIGN_PATTERNS.md`](principles/DESIGN_PATTERNS.md) |
| Module boundaries + dependency direction | [`architecture/OVERVIEW.md`](architecture/OVERVIEW.md) |
| The pure domain core | [`architecture/SHARED_DOMAIN.md`](architecture/SHARED_DOMAIN.md) |
| AI subsystem, durable context, domain tools, and speech | [`architecture/AI_SUBSYSTEM.md`](architecture/AI_SUBSYSTEM.md) |
| Optional experimental AI-chat context memory and retrieval | [`architecture/AI_CONTEXT_RETRIEVAL.md`](architecture/AI_CONTEXT_RETRIEVAL.md) |
| Ports & adapters, DI, store, contracts | the rest of [`architecture/`](architecture/) |
| Clients, server, firmware, monorepo | the [`components/`](components/) set |
| i18n, typography, performance, testing, assets, agent workflow | the [`practices/`](practices/) set |
| Building more projects on the stack | [`reuse/CROSS_PROJECT_SYNC.md`](reuse/CROSS_PROJECT_SYNC.md) |
| Bootstrapping a new project | [`NEW_PROJECT_GUIDE.md`](NEW_PROJECT_GUIDE.md) |

## Non-negotiable when working *from* this blueprint

1. **PRECISE YET CONCISE, DRY, OOP with dependency inversion, strict typing** — the rules in the
   normative core apply to every project built here.
2. **One domain core, one UI, ports for every host difference.** Never fork a screen by platform.
3. **Contracts are single-source.** Define a wire/storage/content shape once; consume it everywhere.
4. **Branch first; the owner merges.** In a real project, a `PreToolUse` hook enforces this — see
   [`practices/AI_AGENT_WORKFLOW.md`](practices/AI_AGENT_WORKFLOW.md).
5. **Record cross-project reuse in the same change** — see
   [`reuse/CROSS_PROJECT_SYNC.md`](reuse/CROSS_PROJECT_SYNC.md).

## Editing this repository itself

This repo is documentation. Keep it prose-first and PRECISE YET CONCISE: one fact in one document,
link rather than restate, and add a code block only where a mechanism is genuinely tricky. The same
branch-first / owner-merges workflow in [`principles/WORKING_AGREEMENT.md`](principles/WORKING_AGREEMENT.md)
applies to changes here.

`AGENTS.md` carries this same pointer for other agent tooling; keep the two in sync.
