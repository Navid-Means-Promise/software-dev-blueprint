# dev-stack-blueprint

The reusable blueprint for our application stack — architecture, patterns, development principles, and
reusable concepts — captured as documentation so a new project can be built from it.

**This repository is documentation only.** It contains prose and local documentation assets: no
application packages, runtime dependencies, or runnable product code. It is the _specification_ a
human or an AI agent reads before scaffolding a real project on the stack.

## The stack in one paragraph

A **pure TypeScript domain core** (`@app/shared` — domain model, a state-machine engine, services,
ports, a DI container, an observer store, and bilingual i18n) is shared by **one Lit UI** (`@app/ui`)
that is mounted, unchanged, by a **web client** (Vite) and an **Android client** (Capacitor). Both use
thin authenticated HTTP/platform adapters while a **Fastify server** owns authoritative use cases,
tenant-scoped canonical persistence, and provider work and imports the same domain contracts.
**ESP-IDF / C++17 firmware** implements a _semantic_ device contract—so business rules exist once,
visual code exists once, platform code is a thin edge, and a physical device is just another client.
Everything lives in an npm-workspaces monorepo with a shared strict toolchain, deterministic testing,
and an owner-directed / agent-executed workflow.

A standalone server-rendered PHP product, or a PHP server behind non-PHP clients, uses the documented
Laravel adaptation and cross-language contract rules; it does not import TypeScript implementations
across the language boundary.

These documents use `@app/*` as a placeholder scope; each implementing project supplies its own
domain vocabulary and package scope.

## How to use this repository

- **Building a new project?** Start at [`NEW_PROJECT_GUIDE.md`](NEW_PROJECT_GUIDE.md).
- **An AI agent?** Read [`CLAUDE.md`](CLAUDE.md) / [`AGENTS.md`](AGENTS.md), then the normative core
  below, before scaffolding anything.
- **Need a specific area?** Jump to its authoritative document in the map.

## The normative core (read first)

| Document                                                                     | Authoritative for                                                     |
| ---------------------------------------------------------------------------- | --------------------------------------------------------------------- |
| [principles/DEVELOPMENT_PRINCIPLES.md](principles/DEVELOPMENT_PRINCIPLES.md) | PRECISE YET CONCISE, DRY, OOP, strict typing, determinism — the rules |
| [principles/WORKING_AGREEMENT.md](principles/WORKING_AGREEMENT.md)           | Branching, merge authority, commits, definition of done, answers      |
| [principles/DESIGN_PATTERNS.md](principles/DESIGN_PATTERNS.md)               | The fixed pattern vocabulary and why each exists                      |

## Document map

**Architecture** — how modules are arranged

- [architecture/OVERVIEW.md](architecture/OVERVIEW.md) — topology, dependency direction, module map
- [architecture/AUTHORIZATION.md](architecture/AUTHORIZATION.md) — authenticated policy decisions,
  RBAC, ABAC, relationship scope, and dynamic-policy safety
- [architecture/SHARED_DOMAIN.md](architecture/SHARED_DOMAIN.md) — the pure `@app/shared` core
- [architecture/PORTS_AND_ADAPTERS.md](architecture/PORTS_AND_ADAPTERS.md) — the host seam
- [architecture/DEPENDENCY_INJECTION.md](architecture/DEPENDENCY_INJECTION.md) — container, tokens, lifetimes
- [architecture/STATE_MANAGEMENT.md](architecture/STATE_MANAGEMENT.md) — the observer store
- [architecture/CONTRACTS.md](architecture/CONTRACTS.md) — single-source contracts, semantic device model
- [architecture/AI_SUBSYSTEM.md](architecture/AI_SUBSYSTEM.md) — assistant orchestration, durable
  application context, typed domain tools, proposals, and speech adapters
- [architecture/AI_PROVIDER_OPERATIONS.md](architecture/AI_PROVIDER_OPERATIONS.md) — bounded
  provider operations, routing, latency recovery, caching, accounting, and diagnostics
- [architecture/AI_CONTEXT_RETRIEVAL.md](architecture/AI_CONTEXT_RETRIEVAL.md) — optional,
  experimental application-owned AI-chat context memory and retrieval
- [architecture/FACT_DB.md](architecture/FACT_DB.md) — optional immutable propositions, versioned
  evidence, correction dependencies, and reusable knowledge-store contract
- [architecture/ROLLING_SUMMARIES.md](architecture/ROLLING_SUMMARIES.md) — optional maintained prose
  context, revision coverage, historical-edit recovery, and derived UI briefs
- [architecture/BACKGROUND_JOBS.md](architecture/BACKGROUND_JOBS.md) — Job Management interfaces,
  resumable steps, native PHP threads/sequential execution, metadata-only creation/lookup,
  fresh inputs/replacement, shared logs/admin, and bounded public wake-up execution

**Components** — the concrete modules

- [components/MONOREPO.md](components/MONOREPO.md) — workspaces, toolchain, budgets, `check`
- [components/SHARED_UI.md](components/SHARED_UI.md) — the one Lit UI + shared bootstrap
- [components/WEB_CLIENT.md](components/WEB_CLIENT.md) — Vite + HTTP/browser adapters
- [components/ANDROID_CLIENT.md](components/ANDROID_CLIENT.md) — Capacitor + HTTP/native adapters
- [components/SERVER.md](components/SERVER.md) — Fastify, canonical persistence, background work, device twin
- [components/PHP_LARAVEL.md](components/PHP_LARAVEL.md) — PHP 8.4/Laravel layering, boundaries, and delivery
- [components/FIRMWARE.md](components/FIRMWARE.md) — ESP-IDF / C++17, semantic engine

**Practices** — cross-cutting concerns

- [practices/I18N.md](practices/I18N.md) — default-language-first parity, RTL/LTR, localized numbers,
  input editing and contextual validation
- [practices/TYPOGRAPHY.md](practices/TYPOGRAPHY.md) — language-aware font roles, local delivery,
  responsive fitting, and optical alignment
- [practices/UI_PERFORMANCE.md](practices/UI_PERFORMANCE.md) — rendering budgets, continuity, and measurement validity
- [practices/MOBILE_RESPONSIVENESS.md](practices/MOBILE_RESPONSIVENESS.md) — small-screen viewports, zero-overflow principle, multi-column vertical alignment, touch ergonomics
- [practices/STRUCTURED_DATA_PRESENTATION.md](practices/STRUCTURED_DATA_PRESENTATION.md) — compact
  typed key-value panels, adaptive row spanning, and accessible full values
- [practices/RENDERED_UI_INTEGRITY.md](practices/RENDERED_UI_INTEGRITY.md) — visual completion,
  product continuity, contrast, occlusion, clipping, and state-matrix audits
- [practices/TESTING.md](practices/TESTING.md) — deterministic domain tests, manual clock, E2E
- [practices/AI_EVALUATION.md](practices/AI_EVALUATION.md) — candidate functional qualification,
  assistant/retrieval/speech evaluation, and reproducible latency/cost comparisons
- [practices/ASSETS_AND_CONTENT.md](practices/ASSETS_AND_CONTENT.md) — content-as-data, generated paths
- [practices/DEPLOYMENT_AND_CACHING.md](practices/DEPLOYMENT_AND_CACHING.md) — HTTP cache matrix, asset versioning, post-deploy healthchecks
- [practices/CONTAINERIZED_TOOLCHAINS.md](practices/CONTAINERIZED_TOOLCHAINS.md) — repeatable,
  host-safe compiler and SDK environments
- [practices/AI_AGENT_WORKFLOW.md](practices/AI_AGENT_WORKFLOW.md) — rulebook, branch-guard hook, summaries
- [practices/AI_AGENT_CONTEXT.md](practices/AI_AGENT_CONTEXT.md) — development-agent discovery,
  file-access boundaries, and indexing/upload verification

**Security** — runtime and supply-chain boundaries

- [security/SESSION_MANAGEMENT.md](security/SESSION_MANAGEMENT.md) — server-enforced lifetime,
  rotation, principal freshness, and backing-store isolation
- [security/HOSTED_BROWSER_ACCESS.md](security/HOSTED_BROWSER_ACCESS.md) — optional owner-authorized
  agent sign-in without interactive OTP, credential revocation, and hosted UI verification
- [security/UNTRUSTED_FILE_HANDLING.md](security/UNTRUSTED_FILE_HANDLING.md) — upload validation,
  private storage, authorized retrieval, and failure consistency
- [security/README.md](security/README.md) — isolated image/repository audits, vulnerability policy,
  and SBOM evidence

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
