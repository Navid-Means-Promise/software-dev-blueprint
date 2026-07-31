# dev-stack-blueprint

The reusable blueprint for our application stack — architecture, patterns, development principles, and
reusable concepts — captured as documentation so a new project can be built from it.

**This repository is documentation only.** It contains `.md` files: no npm packages, no runnable code,
no dependencies. It is the *specification* a human or an AI agent reads before scaffolding a real
project on the stack.

## The stack in one paragraph

A **pure TypeScript domain core** (`@app/shared` — domain model, a state-machine engine, services,
ports, a DI container, an observer store, and bilingual i18n) is shared by **one Lit UI** (`@app/ui`)
that is mounted, unchanged, by a **web client** (Vite + IndexedDB adapter) and an **Android client**
(Capacitor + Preferences adapter). A **Fastify server** imports the same domain contracts, and
**ESP-IDF / C++17 firmware** implements a *semantic* device contract — so business rules exist once,
visual code exists once, platform code is a thin edge, and a physical device is just another client.
Everything lives in an npm-workspaces monorepo with a shared strict toolchain, deterministic testing,
and an owner-directed / agent-executed workflow.

These documents use `@app/*` as a placeholder scope; each implementing project supplies its own
domain vocabulary and package scope.

## How to use this repository

- **Building a new project?** Start at [`NEW_PROJECT_GUIDE.md`](NEW_PROJECT_GUIDE.md).
- **An AI agent?** Read [`CLAUDE.md`](CLAUDE.md) / [`AGENTS.md`](AGENTS.md), then the normative core
  below, before scaffolding anything.
- **Need a specific area?** Jump to its authoritative document in the map.

## The normative core (read first)

| Document | Authoritative for |
| --- | --- |
| [principles/DEVELOPMENT_PRINCIPLES.md](principles/DEVELOPMENT_PRINCIPLES.md) | PRECISE YET CONCISE, DRY, OOP, strict typing, determinism — the rules |
| [principles/WORKING_AGREEMENT.md](principles/WORKING_AGREEMENT.md) | Branching, merge authority, commits, definition of done, answers |
| [principles/DESIGN_PATTERNS.md](principles/DESIGN_PATTERNS.md) | The fixed pattern vocabulary and why each exists |

## Document map

**Architecture** — how modules are arranged
- [architecture/OVERVIEW.md](architecture/OVERVIEW.md) — topology, dependency direction, module map
- [architecture/SHARED_DOMAIN.md](architecture/SHARED_DOMAIN.md) — the pure `@app/shared` core
- [architecture/PORTS_AND_ADAPTERS.md](architecture/PORTS_AND_ADAPTERS.md) — the host seam
- [architecture/DEPENDENCY_INJECTION.md](architecture/DEPENDENCY_INJECTION.md) — container, tokens, lifetimes
- [architecture/STATE_MANAGEMENT.md](architecture/STATE_MANAGEMENT.md) — the observer store
- [architecture/CONTRACTS.md](architecture/CONTRACTS.md) — single-source contracts, semantic device model
- [architecture/AI_SUBSYSTEM.md](architecture/AI_SUBSYSTEM.md) — assistant orchestration, durable
  application context, typed domain tools, proposals, and speech adapters
- [architecture/AI_CONTEXT_RETRIEVAL.md](architecture/AI_CONTEXT_RETRIEVAL.md) — optional,
  experimental application-owned AI-chat context memory and retrieval

**Components** — the concrete modules
- [components/MONOREPO.md](components/MONOREPO.md) — workspaces, toolchain, budgets, `check`
- [components/SHARED_UI.md](components/SHARED_UI.md) — the one Lit UI + shared bootstrap
- [components/WEB_CLIENT.md](components/WEB_CLIENT.md) — Vite + IndexedDB
- [components/ANDROID_CLIENT.md](components/ANDROID_CLIENT.md) — Capacitor + Preferences
- [components/SERVER.md](components/SERVER.md) — Fastify, layered, device twin
- [components/FIRMWARE.md](components/FIRMWARE.md) — ESP-IDF / C++17, semantic engine

**Practices** — cross-cutting concerns
- [practices/I18N.md](practices/I18N.md) — default-language-first parity, RTL/LTR
- [practices/TYPOGRAPHY.md](practices/TYPOGRAPHY.md) — language-aware font roles, local delivery,
  responsive fitting
- [practices/UI_PERFORMANCE.md](practices/UI_PERFORMANCE.md) — compositor + GPU-canvas budgets
- [practices/TESTING.md](practices/TESTING.md) — deterministic domain tests, manual clock, E2E
- [practices/ASSETS_AND_CONTENT.md](practices/ASSETS_AND_CONTENT.md) — content-as-data, generated paths
- [practices/AI_AGENT_WORKFLOW.md](practices/AI_AGENT_WORKFLOW.md) — rulebook, branch-guard hook, summaries

**Reuse** — building more projects on the stack
- [reuse/CROSS_PROJECT_SYNC.md](reuse/CROSS_PROJECT_SYNC.md) — the blueprint-as-hub sync mechanism (conform + propose upstream)

**Reference**
- [NEW_PROJECT_GUIDE.md](NEW_PROJECT_GUIDE.md) — empty repo → running vertical slice
- [GLOSSARY.md](GLOSSARY.md) — shared vocabulary and naming discipline

## A note on this repository's own style

These docs practice what they preach: prose-first and PRECISE YET CONCISE. Code appears only where a
contract or mechanism is genuinely tricky (an exact wire shape, the DI factory-behind-a-token, the
branch-guard hook). Everything else is described precisely and left to the implementing agent, which
will write more idiomatic code from a clear description than from a skeleton frozen here.
