# Glossary

Shared vocabulary for the stack. Two purposes: define each term precisely, and enforce the naming
discipline from [development principles](principles/DEVELOPMENT_PRINCIPLES.md) §0 — **one concept, one
name, everywhere.** A second name for a thing that already has one is a bug.

## Architecture

- **Domain core** — `@app/shared`: the pure, framework- and DOM-free package that holds reusable
  product rules. TypeScript hosts depend inward on it; it depends on nothing above it. See
  [shared domain](architecture/SHARED_DOMAIN.md).
- **Port** — a narrow interface in the domain core for one host capability. See
  [ports & adapters](architecture/PORTS_AND_ADAPTERS.md).
- **Provider** — the preferred interface suffix for a port that supplies an interchangeable
  capability; the port is the architectural role, while the provider is its code-level contract.
- **Adapter** — a concrete implementation of a port, living in the client or server that owns the host
  API.
- **Repository** — a port whose interface is expressed in domain terms; the domain-shaped face of
  persistence.
- **Authorization policy** — the server-side decision over a principal, action, resource, and
  server-derived context. See [authorization and policy decisions](architecture/AUTHORIZATION.md).
- **RBAC** — role-based access control: named roles bundle stable capability permissions. It supplies
  coarse capability; it does not replace resource ownership or tenant predicates.
- **ABAC** — attribute-based access control: typed, server-derived attributes and predicates constrain
  a capability to a resource and context. Relationship-aware ownership and assignment checks are
  resource predicates within this policy model.
- **Container / token** — the DI container maps a typed token to a factory that builds an
  implementation. See [dependency injection](architecture/DEPENDENCY_INJECTION.md).
- **Store** — the in-memory observable UI projection (`AppState`) one client instance subscribes to;
  never canonical domain persistence. See
  [state management](architecture/STATE_MANAGEMENT.md).
- **Engine** — the domain state machine that walks the product's flow and owns branching.
- **Mediator** — the single service that turns a state's declared intent into coordinated, cancellable
  port calls (e.g. a director of timed effects).
- **Contract** — any shape that crosses a boundary (wire, storage, content, device), defined once and
  consumed everywhere. See [contracts](architecture/CONTRACTS.md).
- **OTP challenge** — server-owned, short-lived, single-use verification state that binds a
  one-time-code digest to a canonical identity. Marking it consumed is the atomic transition that
  prevents replay. See [server](components/SERVER.md#passwordless-phone-authentication).
- **Semantic device model** — a device driven by _meaning_ (a mode + parameters) that renders frames
  locally; never streamed frames.
- **Device twin** — an in-memory model of a physical device that interprets the same semantic contract
  the firmware does, for use without hardware.
- **Memory record** — in the current-state assistant-memory pattern, the application interpretation
  of the smallest coherent piece of durable conversational knowledge that can be corrected,
  expired, or deleted independently. It references its evidence instead of duplicating the source
  message and does not copy authoritative
  state owned by another application domain. See [AI subsystem](architecture/AI_SUBSYSTEM.md).
- **FactDB** — optional sourced knowledge whose proposition meanings are immutable and whose
  current views depend on versioned evidence. See [FactDB](architecture/FACT_DB.md).
- **Proposition / claim** — immutable meaning and a source's attributed assertion of that meaning,
  respectively. Evidence and correction relationships determine delivery eligibility; support is
  not proof of truth. See [FactDB](architecture/FACT_DB.md#meaning-and-identity).
- **Rolling summary** — revision-bound prose derived incrementally from authoritative sources,
  with explicit coverage and edit recovery. A brief may derive from its detailed text. See
  [rolling summaries](architecture/ROLLING_SUMMARIES.md).
- **Job Management** — the durable-work subsystem coordinated through `JobManager`; its `Job`,
  `JobStep`, checkpoint, attempt, trigger, and lease vocabulary is defined in
  [job management](architecture/BACKGROUND_JOBS.md#1-vocabulary-and-guarantees).
- **Job metadata** — creation-time target/correlation key-value data, such as `documentId`, used to
  identify and find jobs; business inputs are loaded separately on execution. See
  [job metadata and creation](architecture/BACKGROUND_JOBS.md#job-metadata-and-creation).
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
  validated at construction. A project gives it one concrete name (e.g. _story content_) and never a
  second. See [assets & content](practices/ASSETS_AND_CONTENT.md).
- **Layout definition** — the single source every content asset path derives from.
- **Message catalog** — the typed, per-locale-in-parity source of every UI string. See
  [i18n](practices/I18N.md).
- **Parity** — every key/content field present in every supported locale; a missing one fails early,
  it does not fall back silently.
- **Manual test clock** — the injected clock that drives the scheduler port in tests, so timed effects
  are asserted without waiting. See [testing](practices/TESTING.md).
- **Maintenance lock** — a deployment-owned, fail-closed marker checked before normal application
  composition; while active, dynamic requests receive a minimal `503` response. See
  [deployment and HTTP caching](practices/DEPLOYMENT_AND_CACHING.md).
- **Deployment update hook** — a narrow authenticated HTTPS control-plane endpoint used by deployment
  automation when the host cannot execute remote commands; it applies only fixed versioned updates
  and is never a general application or persistence route. See
  [deployment and HTTP caching](practices/DEPLOYMENT_AND_CACHING.md).

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
