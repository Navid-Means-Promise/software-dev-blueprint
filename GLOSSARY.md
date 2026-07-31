# Glossary

Shared vocabulary for the stack. Two purposes: define each term precisely, and enforce the naming
discipline from [development principles](principles/DEVELOPMENT_PRINCIPLES.md) §0 — **one concept, one
name, everywhere.** A second name for a thing that already has one is a bug.

## Architecture

- **Domain core** — `@app/shared`: the pure, framework- and DOM-free package that holds the product's
  rules. Everything depends on it; it depends on nothing above it. See
  [shared domain](architecture/SHARED_DOMAIN.md).
- **Port** — a narrow interface in the domain core for one host capability. See
  [ports & adapters](architecture/PORTS_AND_ADAPTERS.md).
- **Provider** — the preferred interface suffix for a port that supplies an interchangeable
  capability; the port is the architectural role, while the provider is its code-level contract.
- **Adapter** — a concrete implementation of a port, living in the client or server that owns the host
  API.
- **Repository** — a port whose interface is expressed in domain terms; the domain-shaped face of
  persistence.
- **Container / token** — the DI container maps a typed token to a factory that builds an
  implementation. See [dependency injection](architecture/DEPENDENCY_INJECTION.md).
- **Store** — the observable `AppState` the UI subscribes to; the Observer mechanism. See
  [state management](architecture/STATE_MANAGEMENT.md).
- **Engine** — the domain state machine that walks the product's flow and owns branching.
- **Mediator** — the single service that turns a state's declared intent into coordinated, cancellable
  port calls (e.g. a director of timed effects).
- **Contract** — any shape that crosses a boundary (wire, storage, content, device), defined once and
  consumed everywhere. See [contracts](architecture/CONTRACTS.md).
- **Semantic device model** — a device driven by *meaning* (a mode + parameters) that renders frames
  locally; never streamed frames.
- **Device twin** — an in-memory model of a physical device that interprets the same semantic contract
  the firmware does, for use without hardware.
- **Memory record** — the current application interpretation of the smallest coherent piece of
  durable conversational knowledge that can be corrected, expired, or deleted independently. It
  references its evidence instead of duplicating the source message and does not copy authoritative
  state owned by another application domain. See [AI subsystem](architecture/AI_SUBSYSTEM.md).
- **Memory-selection index** — a disposable, revision-bound projection that helps select memory
  records by time, semantic relevance, or application-defined selectors. A hit is only a reference;
  context assembly reloads the matching current memory record before use. See
  [experimental assistant context memory and retrieval](architecture/AI_CONTEXT_RETRIEVAL.md).
- **Authoritative application context source** — an application service or repository that owns
  domain state and exposes typed retrieval without transferring that authority to assistant memory
  or a search index. See
  [experimental assistant context memory and retrieval](architecture/AI_CONTEXT_RETRIEVAL.md).
- **Context retrieval origin** — why and when assistant context retrieval started: automatically
  from the new user message, predictively from a previous model response, or through an in-call model
  tool request. It is metadata, not the retrieval query. See
  [experimental assistant context memory and retrieval](architecture/AI_CONTEXT_RETRIEVAL.md).
- **Explicit context retrieval** — a schema-validated, application-specific typed query against an
  authoritative application source, distinct from lexical, vector, or hybrid relevance search.
  See [experimental assistant context memory and retrieval](architecture/AI_CONTEXT_RETRIEVAL.md).
- **Domain change proposal** — a non-mutating, item-reviewable diff against a domain revision; only
  its explicitly accepted subset may be revalidated and applied.

## Modules

- **`@app/shared`, `@app/ui`, `@app/web`, `@app/mobile`, `@app/server`, `@app/e2e`** — the workspaces,
  named by role. `@app` is the placeholder scope; a project substitutes its own. See
  [monorepo](components/MONOREPO.md).
- **Firmware** — the ESP-IDF / C++17 device module; another client, sharing the contract, not the
  code.
- **Shared bootstrap** — the one `@app/ui` startup call whose only per-client variable is the adapter
  set. See [shared UI](components/SHARED_UI.md).
- **Readiness gate** — the shared startup workflow that preloads startup-critical assets, reports
  localized progress, and offers retry.

## Practices

- **Content** — schema-validated product data ("one validated definition plus the assets it names"),
  validated at construction. A project gives it one concrete name (e.g. *story content*) and never a
  second. See [assets & content](practices/ASSETS_AND_CONTENT.md).
- **Layout definition** — the single source every content asset path derives from.
- **Message catalog** — the typed, per-locale-in-parity source of every UI string. See
  [i18n](practices/I18N.md).
- **Parity** — every key/content field present in every supported locale; a missing one fails early,
  it does not fall back silently.
- **Manual test clock** — the injected clock that drives the scheduler port in tests, so timed effects
  are asserted without waiting. See [testing](practices/TESTING.md).

## Workflow

- **Owner** — holds merge authority and product direction. See
  [working agreement](principles/WORKING_AGREEMENT.md).
- **Agent** — designs, implements, verifies, tests, and reports; never advances `main` or picks
  product direction unprompted.
- **Branch-guard hook** — the `PreToolUse` hook that denies edits while on `main`. See
  [AI agent workflow](practices/AI_AGENT_WORKFLOW.md).
- **Blueprint** — this repository; the canonical, hub source of the stack's rules, architecture, and
  patterns that every project conforms to.
- **Blueprint-sync ledger** — a project's `docs/BLUEPRINT_SYNC.md`: the blueprint commit it conforms
  to (conformance baseline) plus its improvements proposed upstream. See
  [cross-project sync](reuse/CROSS_PROJECT_SYNC.md).
- **Conformance baseline** — the blueprint revision a project has reconciled with; a stale one is
  visible drift, not silent drift.
- **Upstream proposal** — a reusable improvement made in a project and offered to the blueprint.
- **PRECISE YET CONCISE** — the house style: state exactly what is true, once, and no more. See
  [development principles](principles/DEVELOPMENT_PRINCIPLES.md) §0.
