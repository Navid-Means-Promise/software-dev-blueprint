# Development principles

The single normative source for **how** work is done on this stack. Every other document in this
repository is authoritative for one _area_ (architecture, a client, a practice) and points back here
for the rules. When something here and something there seem to conflict, this file wins.

These principles are binding on **every** change, however small — a one-line fix obeys them as fully
as a new subsystem.

---

## 0. PRECISE YET CONCISE

The house style, and the lens for every rule below. Say exactly what is true, and no more.

- **Precise:** state the real constraint, the real dependency direction, the real invariant. No
  vagueness that a reader has to guess past, no hand-waving where a fact belongs.
- **Concise:** one fact lives in exactly one place. Link to it; never restate it. Delete a sentence,
  a comment, or a whole document the moment it stops adding information the code or a sibling doc
  does not already carry.
- Applied to **code:** the shortest expression that is still obvious. Not code golf — clarity is the
  target, brevity is what clarity usually costs once the design is right.
- Applied to **names:** evaluate them in their qualified context. Standalone functions and broadly
  scoped objects need self-descriptive operations, such as `verifyPhoneOtpChallenge()` and
  `authenticationService.verifyPhoneOtpChallenge()`. Narrowly scoped objects avoid repeating the
  domain already named by their receiver: `otpChallengeService.verifyPhoneChallenge()`. This is a
  system-wide readability rule, not permission to maximize identifier length.
- Applied to **docs and comments:** explain the _why_ the code cannot say itself. Never narrate what
  the code already states. A comment that repeats its line is deleted, not maintained.
- Applied to **this repository:** describe intent and constraints in prose; drop into a code or
  contract block only where the thing is genuinely tricky or non-obvious. A precise description lets
  the implementing agent write more idiomatic code than a skeleton frozen here ever could.

Everything that follows is PRECISE YET CONCISE made specific.

---

## 1. DRY — one maintained source for each fact

A second independently maintained representation of the same behavior, contract, user-facing
string, or documented fact is a signal to establish one source and derive or reuse it. Superficially
similar code with different reasons to change is not duplication merely because its syntax matches.

- **Code:** when multiple callers encode the same behavior and have the same reason to change, a
  reusable module owns that concern and both callers depend on it. Do not abstract coincidental
  similarity or speculate about reuse; a premature shared abstraction couples unrelated changes as
  surely as duplication can drift. Platform bootstraps, build configuration, and error handling
  differ only in the values they inject — never in duplicated policy. A recurring example is one
  thrown-value normalizer that turns any caught `unknown` into a typed message for both UI and server.
- **Contracts:** a wire, storage, or content shape is defined **once** and consumed from that one
  place by every client, the server, and firmware. See [contracts](../architecture/CONTRACTS.md).
- **User-facing strings:** every string outside content lives once in the typed message catalog,
  with each supported locale in parity. No inline literals in components. See [i18n](../practices/I18N.md).
- **Facts:** one fact, one document. This is why the normative rules live only here and every entry
  point _links_ rather than restating.

DRY is not "avoid typing twice." It is "there is exactly one place the truth about X can change."

---

## 2. Object-oriented design

Model stateful or collaborative behavior as small, role-based classes: one responsibility, an
intention-revealing name, composition preferred over inheritance. Keep stateless transformations as
pure functions and immutable values; wrapping them in classes adds ceremony without encapsulation.

- **Depend on abstractions.** Domain services receive their collaborators as constructor parameters
  (ports) and are resolved from the DI container. No module-level singletons, no global lookups, no
  reaching into a service locator from inside a class.
- **Reuse the established patterns rather than inventing parallel ones.** The catalog is fixed and
  documented in [design patterns](DESIGN_PATTERNS.md): ports & adapters for every host or
  infrastructure
  capability, a container + tokens for wiring, a state machine for domain flow, a mediator for
  coordinating effects, an observer store for state propagation, repositories for persistence,
  strategies for interchangeable behavior, a semantic model for physical devices.
- **Keep decisions in the domain.** Reusable rules live in the shared domain core; authoritative
  networked use cases execute those rules on the server. UI components render and delegate—they
  never grant authority or decide a domain rule. See [shared domain](../architecture/SHARED_DOMAIN.md).
- **Follow SOLID as consequences, not slogans:** a class has one reason to change; it is open for
  extension through injected strategy, not modification; adapters are substitutable for their port;
  ports are narrow and role-specific rather than fat; high-level policy depends on port
  abstractions, and adapters depend on those same abstractions.

---

## 3. Determinism and testability

Design so that every rule can be proven without a DOM, a network, or real time.

- Anything non-deterministic — randomness, the clock, delayed work, I/O — enters a class through a
  port. Tests inject a fixed source (a fixed dice roll, a manual test clock) and assert exact
  outcomes. See [testing](../practices/TESTING.md).
- A domain unit test constructs the class with test doubles and asserts behavior. If a rule cannot be
  tested that way, the design has a hidden dependency to lift into a port.
- Effects that are _timed_ (audio cues, animations, retries) issue their timing through a scheduler
  port, so `delayMs` is asserted against a manual clock instead of waited out.

---

## 4. Strict typing

- TypeScript application code and reusable tooling logic are strict; PHP applications follow the
  [PHP/Laravel strict-typing rules](../components/PHP_LARAVEL.md); firmware is C++17.
  Environment-required declarative configuration and minimal shell hooks are allowed only at their
  integration boundary; they do not become an alternate application-logic language.
- No unbounded dynamic type, unchecked cast, or silently widened type crosses a module boundary. A
  boundary value has a named, checked type in its implementation language.
- The shared strict compiler baseline (`tsconfig.base.json`) and lint/format config live once at the
  monorepo root and every workspace extends them. See [monorepo](../components/MONOREPO.md).
- Indexed access is checked and optional properties distinguish omission from an explicit
  `undefined`; the baseline enables `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`,
  `verbatimModuleSyntax`, and `noUncheckedSideEffectImports`.

---

## 5. One implementation, many hosts

- The web and Android clients render the **same** UI component set. Never fork a screen by platform;
  inject the platform's behavior through a port and keep the screen identical. See
  [shared UI](../components/SHARED_UI.md).
- The domain core is framework- and DOM-free. The server may import it; it must never import the UI.
  The dependency direction is fixed in [architecture overview](../architecture/OVERVIEW.md).
- Physical devices receive **intent** through a semantic contract and render frames locally. Clients
  and firmware never stream animation frames to each other. See [contracts](../architecture/CONTRACTS.md).

---

## 6. Visual performance is a rule, not a polish step

Perpetual visual effects follow a hard budget from the first commit, not a later optimization pass.
Compositor-only animation, respect for reduced motion, and an adaptive lifecycle for any GPU canvas
are acceptance criteria. The full budget and its verification method are in
[UI performance](../practices/UI_PERFORMANCE.md).

---

## 7. Trust boundaries are not MVP compromises

For any networked product that stores user data, identity and authority exist before the first
externally usable slice. Feature scope may be reduced; the trust boundary may not.

- Every request resolves an authenticated principal on the server. Authorization and owner/tenant
  scope are enforced inside every read, search, ranking, and write path before data is returned or
  changed.
- Untrusted clients call bounded application use cases. They never choose ownership, server time,
  authorization scope, raw storage keys/queries, or whether a proposed effect is valid. The server
  validates the complete request and executes authoritative domain rules.
- Canonical mutations define transaction, concurrency, retry/idempotency, and failure behavior.
  Provider credentials stay server-side; public endpoints have resource/rate bounds; sensitive logs
  and durable data have explicit access, retention, and deletion policies.
- **Safeguards are proportionate and capability-preserving.** Security, privacy, retention, and data
  minimization controls mitigate a named risk with the narrowest control that preserves the
  capabilities required to operate, debug, audit, recover, and prove the system. Do not prohibit
  necessary data or behavior merely because it is sensitive or vendor-specific; prefer isolation,
  restricted access, encryption, bounded retention, and separate raw/structured stores when those
  controls address the risk. A broader prohibition must explain why narrower controls are
  insufficient. Provider routing is the recurring example: a bounded protected raw-response artifact
  retains the routing metadata needed to reconstruct fallback behavior, while ordinary queryable
  telemetry uses an allowlisted projection. The vendor-shaped object remains diagnostic evidence,
  not canonical application data.
- Every composition that accepts requests from a network client—including local development, E2E,
  demos, staging, and an MVP—uses the authenticated multi-user server boundary and the same
  authorization and tenant-isolation rules. Development credentials or a development identity
  provider may make sign-in convenient; they may not bypass those paths.
- Process-local repositories, fixed principals, and other authority substitutes are permitted only
  as explicitly constructed test doubles inside isolated unit or component tests. They are never
  exposed by a listening server, selected by a runtime fallback, or presented as a runnable
  application environment.

Security can mature in depth, but authentication, authorization, tenant isolation, server-side
validation, and a safe canonical write path are architectural foundations.

---

## 8. Repository-wide consistency is mandatory

New rules, guidance, examples, and recommendations **must not contradict or be inconsistent with any
existing content in this repository**. Before adding or merging content, review every document whose
scope it touches and reconcile the addition with the repository as a whole.

- Do not let newer text silently override, weaken, or create an ambiguous alternative to existing
  guidance. Preserve the normative hierarchy and the one-fact-one-source rule.
- Resolve every discovered inconsistency in the same coherent change. If the proposed content cannot
  be reconciled without an explicit owner decision, do not add or merge it.
- Do not resolve a disagreement by automatically choosing the more restrictive statement. "More
  restrictive" is not "more correct"; apply the proportionate, capability-preserving safeguard rule
  in §7.
- Review is semantic, not merely textual: different wording is inconsistent when it prescribes
  incompatible architecture, behavior, workflow, responsibility, or constraints.

## 9. Host-safe toolchains

A compiler or SDK that would install system packages, conflicting runtimes, legacy dependencies, or
platform-specific state is a host boundary. Run it through the project's committed, pinned
container workflow; do not repair a build by changing the workstation globally. The source tree is a
read-only input and only declared output/cache paths are writable. See
[containerized toolchains](../practices/CONTAINERIZED_TOOLCHAINS.md).

## 10. Definition of done

A change is done when all of the following are true — see [working agreement](WORKING_AGREEMENT.md)
for the workflow that enforces them:

- It was developed on its own branch, created **before** the first edit.
- The narrowest relevant tests pass while iterating, and the repository-wide check passes for a
  broad change. Shared UI changes additionally pass the browser acceptance flow in every supported
  locale; Android shell/bridge/adapter changes pass the relevant Android host tests.
- Any contract it touched was updated in the single source _before_ the code that depends on it.
- The repository-wide consistency review passed, and every affected area document agrees with this
  normative source.
- Any cross-project reuse it introduced or adopted is recorded in the same change. See
  [cross-project sync](../reuse/CROSS_PROJECT_SYNC.md).
- Any invasive or platform-sensitive toolchain runs through the committed host-safe workflow, and
  its claimed repeatability level was actually verified.
- The result — including failures and skipped steps — is reported honestly, and `main` is not
  advanced without the owner's explicit confirmation.

---

## How an agent should use this file

Read it before the first edit of every task. It is short by design. The area documents assume you
have; they describe _where_ things go and _how_ they are shaped, and rely on this file for _why_ and
_whether_.
