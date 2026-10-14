# New project guide

How to go from an empty repository to a running vertical slice on this stack. Follow it in order. Each
step links to the document that is authoritative for it; read that document, do not improvise past it.

This guide is deliberately about **sequence and decisions**, not code. The
[development principles](principles/DEVELOPMENT_PRINCIPLES.md) apply from step one.

## Before you scaffold: the decisions to make

A capable agent can build every module below. What it cannot invent is the product. Settle these
first, because they shape the domain, the ports, and the contracts:

1. **The product and its domain.** What are the entities, and what is the flow the
   [engine](architecture/SHARED_DOMAIN.md) walks as a state machine?
2. **Locales.** The default locale and every other, in full parity ([i18n](practices/I18N.md)).
3. **Typography.** The interface and display faces for each locale, plus the supported local font
   formats and compatibility target ([typography](practices/TYPOGRAPHY.md)).
4. **Which injected boundaries each composition needs.** Clients need bounded authenticated
   application-use-case transports plus any optional host capabilities such as audio or a physical
   device. The server needs identity, canonical repositories, provider integrations, a clock, and
   other infrastructure required by its use cases
   ([ports & adapters](architecture/PORTS_AND_ADAPTERS.md)).
5. **Whether there is a physical device**, and if so, its **semantic contract** — the modes and
   parameters, meaning only, no frames ([contracts](architecture/CONTRACTS.md)).
6. **Identity and data authority.** Define the principal/tenant model, canonical server store,
   authorization policy (RBAC plus any ABAC/relationship predicates), dynamic-policy bootstrap and
   recovery path, and bounded use cases before wiring any networked client data flow
   ([authorization](architecture/AUTHORIZATION.md), [server](components/SERVER.md)).
7. **The package scope** (e.g. `@yourproject/*`).
8. **Specialized toolchains.** Identify SDKs or compilers that must be isolated from the host and the
   exact target matrix they support
   ([containerized toolchains](practices/CONTAINERIZED_TOOLCHAINS.md)).

The project conforms to **this blueprint** as its single source of stack rules and architecture, and
records that relationship in a [blueprint-sync ledger](reuse/CROSS_PROJECT_SYNC.md) — it does not pair
off against another project.

## Build order

Build inside-out: the core first, then the UI, then the hosts. Each layer is testable before the next
exists.

1. **Monorepo skeleton.** npm workspaces, the shared strict `tsconfig.base.json`, one ESLint + one
   Prettier config, the shared bundler-config factory, and the root `check` script. See
   [monorepo](components/MONOREPO.md). Also install the agent workflow: the single-source rulebook,
   `CLAUDE.md` / `AGENTS.md` pointers, and the branch-guard hook
   ([AI agent workflow](practices/AI_AGENT_WORKFLOW.md)). Configure and verify
   [workspace context controls](practices/AI_AGENT_CONTEXT.md) for the supported agents before
   dependency installation or automatic indexing. When using Antigravity, follow its
   [`.antigravityignore` setup](practices/AI_AGENT_CONTEXT.md#google-antigravity).

   If a specialized toolchain was identified, add its committed container definition and root runner
   now. Pin external inputs before product code depends on them; expose image/setup/build scripts and
   add tests for the runner's host-isolation guards.

2. **The domain core (`@app/shared`).** In dependency order:
   - the **ports** required by pure rules and client presentation; only genuinely optional
     presentation/device capabilities receive no-op defaults, while authority-bearing ports have no
     default;
   - the **DI container**, typed **tokens**, and `registerCoreServices()`
     ([dependency injection](architecture/DEPENDENCY_INJECTION.md));
   - the **observer store** and `AppState` ([state management](architecture/STATE_MANAGEMENT.md));
   - the **domain model** with construction-time validation, and the **engine** state machine
     ([shared domain](architecture/SHARED_DOMAIN.md));
   - the **services** (and any mediator for coordinated effects);
   - the **i18n** catalog and locale service ([i18n](practices/I18N.md)).
     Write the deterministic unit tests **with** each rule ([testing](practices/TESTING.md)). Their
     execution follows [pre-merge verification](principles/WORKING_AGREEMENT.md#pre-merge-verification).

3. **The shared UI (`@app/ui`).** The component set, the [shared bootstrap](components/SHARED_UI.md)
   seam that takes an adapter set, and the bounded startup readiness gate. Choose and record the
   component-wiring option, render store state, and delegate user intent; all text comes from the
   catalog and shared [typography](practices/TYPOGRAPHY.md) roles. Obey the
   [UI performance](practices/UI_PERFORMANCE.md) budget from the first animated surface.

4. **The server.** Use Fastify in `@app/server`, importing TypeScript-only shared contracts, or use
   the [PHP/Laravel adaptation](components/PHP_LARAVEL.md), conforming to a normative cross-language
   contract when non-PHP clients share a wire boundary. Keep thin routes → controller →
   service/action → repository; authentication, [authorization policy](architecture/AUTHORIZATION.md),
   [session lifecycle](security/SESSION_MANAGEMENT.md), tenant-scoped durable persistence,
   transaction/concurrency policy, bounded use cases, an
   [untrusted-file boundary](security/UNTRUSTED_FILE_HANDLING.md) when uploads exist, a health
   endpoint, content/catalog endpoints, and—if there is a device—the in-memory semantic twin
   ([server](components/SERVER.md)). Use in-memory repositories only as explicit doubles in isolated
   tests. Every runnable networked
   composition, including local development and E2E, already obeys the trust boundary in
   [development principles §7](principles/DEVELOPMENT_PRINCIPLES.md#7-trust-boundaries-are-not-mvp-compromises).
   Wire the [development runners](components/MONOREPO.md#development-server-execution-and-lan-reachability)
   before device testing.

5. **The web client (`@app/web`).** Vite config from the shared factory, HTTP/browser adapters, one
   bootstrap call ([web client](components/WEB_CLIENT.md)). Configure its
   [LAN browser access](components/WEB_CLIENT.md#development-server-and-lan-reachability).
   This is the first place the whole stack runs end-to-end.

6. **Content and assets.** Define the content schema, the one layout definition every asset path
   derives from, and generate placeholder assets from the contract so loading and timing paths run
   before real media exists ([assets & content](practices/ASSETS_AND_CONTENT.md)).

7. **The Android client (`@app/mobile`).** Capacitor project, HTTP/native adapters, the same bootstrap
   call ([Android client](components/ANDROID_CLIENT.md)). No screen is rewritten.

8. **Firmware (`firmware/`)**, if there is a device. ESP-IDF / C++17, an allocation-free engine that
   renders the [semantic contract](architecture/CONTRACTS.md) locally with a physical safety ceiling
   ([firmware](components/FIRMWARE.md)). Compile every supported target through the committed
   [container workflow](practices/CONTAINERIZED_TOOLCHAINS.md), with target-specific configuration,
   dependency locks, and output directories.

9. **Acceptance.** Playwright over the shared UI in every locale and both text directions; exercise
   the declared principal/data/interaction matrix for rendered contrast, occlusion, clipping, and
   reachability; isolate any real-GPU measurement into a run that rejects software rendering
   ([testing](practices/TESTING.md), [rendered UI integrity](practices/RENDERED_UI_INTEGRITY.md)).

10. **Delivery and caching.** Configure web server `Cache-Control` policies, verify deterministic asset
   cache-busting tokens in HTML templates, record deployment revisions, and run automated post-deploy
   healthchecks ([deployment and HTTP caching](practices/DEPLOYMENT_AND_CACHING.md)).

## The vertical slice as the milestone

Aim first for a **thin end-to-end slice**, not a complete product: the smallest real flow that
exercises the engine, the store, one authenticated server-owned persisted use case, the shared UI on
web, and—if applicable—the device twin. A running slice proves the architecture; breadth comes
after.

_Reference:_ A Cup of Mystery's first slice was two opening choices, a deterministic dice gate, two
endings, persistence, a device mock, bilingual RTL, and a firmware engine — the whole stack end to
end, one narrow story wide.

## When the slice runs

Set up the project's [blueprint-sync ledger](reuse/CROSS_PROJECT_SYNC.md): create `docs/BLUEPRINT_SYNC.md`
recording the blueprint commit this project was built from as its conformance baseline. From here the
new project participates in the same hub exchange as every other project — conforming to the blueprint
and proposing its own reusable improvements upstream.

## Completion workflow applies throughout

Every step uses the
[working agreement's single completion checklist](principles/WORKING_AGREEMENT.md#3-definition-of-done-single-authoritative-checklist).
Apply it when the task branch is ready to merge, including a separately delivered phase. Area
documents define the applicable requirements and verification methods; this guide does not create a
phase-specific copy of that checklist.
