# Testing

Authoritative for how correctness is proven. The stack's testing strategy follows directly from its
architecture: because the domain is [pure and injectable](../architecture/SHARED_DOMAIN.md), most
rules are verified as fast, deterministic unit tests with no environment at all.

## The layers

| Layer                         | Scope                                                         | How                                                                                                |
| ----------------------------- | ------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| **Domain unit tests**         | Every rule, transition, and boundary in `@app/shared`         | Construct the class with test doubles; assert exact outcomes. No DOM, network, or real time.       |
| **Server tests**              | Route → service behavior, contract responses, the device twin | Exercise the server with in-memory adapters; assert responses and twin state.                      |
| **Browser acceptance (E2E)**  | Shared UI plus web/server integration                         | Drive the built web app against the real server composition in a browser.                          |
| **Android host tests**        | Native shell, bridge, permissions, and adapters               | Run focused smoke/instrumented tests on the packaged client and real-device checks where required. |
| **Toolchain/firmware builds** | Container runner and every declared hardware target           | Build from locked inputs; test isolation guards and collect checksummed artifacts.                 |

## Determinism is the whole game

Every rule is tested by **injecting** its non-deterministic collaborators
([ports & adapters](../architecture/PORTS_AND_ADAPTERS.md)):

- **Randomness** → a fixed source, so both sides of a randomized branch are reproducible.
- **Time and delays** → a **manual test clock** driving the scheduler port, so timed effects (cues,
  animations, retries) are asserted by advancing the clock, not by waiting. A test for a 300 ms cue
  takes microseconds.
- **Repository / bounded transport / platform / audio / device** → explicit in-memory, fixed, or
  recording doubles, so persistence, effects, and device intent are asserted without a real
  backend. These are test constructions, never runtime fallbacks. The explicit local-development SMS
  delivery decorator described in [server](../components/SERVER.md) is a runtime adapter, not a test
  double; it still runs the normal challenge and session path, is never installed in staging or
  production, and is covered by composition tests.

If a rule cannot be tested this way, that is a design signal: a hidden dependency needs lifting into a
port. The test difficulty is diagnosing the architecture, doing its job.

_Reference implementation:_ both projects inject a fixed randomness source to make every branch
reproducible, and Mystery drives its audio-cue timeline through a manual clock so the whole
choreography is asserted with no real audio context and no waiting.

## Content and contracts are tested at construction

Because the domain model [validates its own graph](../architecture/SHARED_DOMAIN.md), a malformed
content definition fails at construction. A unit test that constructs each shipped content bundle is
therefore also a contract test: unreachable states, dead ends, and dangling asset references surface
as test failures, not as an unlucky play in production.

## Browser acceptance

The E2E layer drives the real UI in every supported locale and both text directions at mobile widths,
covering the shared web-rendered behavior, [i18n](I18N.md), and browser-side
[UI performance](UI_PERFORMANCE.md) acceptance criteria. It also exercises the risk-based principal,
data, interaction, and viewport matrix from [rendered UI integrity](RENDERED_UI_INTEGRITY.md), using
standards-based accessibility tooling plus bounded application-specific geometry checks.
The [audit architecture](RENDERED_UI_INTEGRITY.md#test-architecture) owns fail-closed state discovery
and state-specific readiness requirements. Browser acceptance does not prove Capacitor packaging,
native bridge behavior, Android permissions, or device-specific
performance; focused Android tests cover those boundaries. Isolate any measurement that needs real
GPU hardware into its own run that **rejects a software renderer**, so a software-fallback result is
never mistaken for animated-shader performance. Localized-form acceptance enters every supported
digit/format representation, proves canonical values reach the boundary, and asserts that invalid
input produces a catalog message in the active locale rather than browser-native text in another
language.

Localized forms also satisfy the [editing and contextual validation contract](I18N.md#localized-input-and-validation).
Cover the valid completion path alongside client rejection and authoritative server rejection;
server-rendered forms additionally exercise their supported behavior without JavaScript.

Structured data panels also pass [structured data verification](STRUCTURED_DATA_PRESENTATION.md#verification).
The audit must reach the populated composition and assert that representative fields actually render.

Timing profiles follow the [UI performance acceptance method](UI_PERFORMANCE.md#acceptance-method),
which owns metric selection, probe validation, and evidence requirements. They complement the
deterministic animation-property audit.

When the server owns canonical state, browser tests assert through server-visible records and reset
or namespace that state explicitly. Do not mistake a fresh browser context for data isolation. Add
restart tests for the durable adapter, concurrent-mutation tests, migration precedence/idempotency,
derived-index rebuild/cache reuse, background-worker degradation, and deletion propagation.

Security tests are functional tests, not a later audit substitute. For every user-owned use case,
prove unauthenticated rejection, forged identity/scope rejection, cross-tenant read/search/write
isolation, exact request decoding, stale-revision handling, transactional rollback, retry
convergence, and rate/resource bounds. Prove generic storage/internal-provider routes are absent or
inaccessible in production composition. Browser-session deployments also test origin and CSRF
defenses on every state-changing route, including administrative and alternate representations. For
RBAC/ABAC use cases, cover role capability plus resource/relationship predicates, policy revocation
and cache invalidation, protected bootstrap/last-administrator invariants, and equivalent
authorization for HTML, JSON, attachments, exports, and background jobs. Use at least two principals
with colliding record identifiers so accidental global lookup cannot pass unnoticed. Migration tests
open the previous schema, verify records and foreign-key constraints, restart the adapter, and prove
seed/idempotency behavior.

Deployment-control tests prove the maintenance lock fails closed before application composition,
unauthenticated or malformed update-hook requests cannot mutate state, fresh initialization is
idempotent, and a failed update does not publish a healthy revision marker. Destructive-reset tests
use an isolated database, require every confirmation gate, open and verify the pre-reset backup, and
prove the ordinary deployment path never erases unexpected records.

Projects enabling credential-based hosted browser automation also satisfy the
[hosted access verification matrix](../security/HOSTED_BROWSER_ACCESS.md#verification-and-adoption).

## Live provider smoke checks

A test that spends money, contacts a real person, mutates a third-party account, or produces another
externally visible effect is an operational check outside the deterministic suite. Expose it
through a separately named runner that refuses to act unless it receives both an explicit
confirmation value and an explicit bounded target. Keep it out of the normal `test`/`check` path and
ordinary CI; use least-privilege test credentials, cap the number and cost of operations, and redact
credentials and personal data from output.

Provider contract tests still use recording/fake transports to prove request mapping, errors,
timeouts, and retries without a live call. A basic opt-in smoke check proves the deployed credential,
network, and provider integration it actually exercised; model functionality requires the acceptance
evaluation below. Report a skipped live check as skipped, never as passing coverage.

AI capabilities require a [functional acceptance suite](AI_EVALUATION.md#required-functional-acceptance-suite)
that qualifies primary and fallback candidates against product behavior. The same evaluation guide
owns multi-turn scenarios, semantic assessment, latency/cost measurements, and reproducible reports.

## Change-specific verification gates

- Select the **narrowest** relevant tests for scoped changes; broad changes require the
  repository-wide [`check`](../components/MONOREPO.md). Execution timing, failure repair, and result
  reuse follow the [pre-merge verification policy](../principles/WORKING_AGREEMENT.md#pre-merge-verification).
- Shared UI changes additionally pass the browser acceptance flow in every locale; Android
  shell/bridge/adapter changes also pass the relevant Android host tests.
- New or materially changed UI includes the [visual completion review](RENDERED_UI_INTEGRITY.md#visual-completion-and-product-continuity)
  and [localized numeric presentation](I18N.md#display-numbers-in-the-active-locale) checks. Functional,
  contrast and overflow success alone do not establish a finished product surface.
- Firmware or container changes pass the host-safe workflow's clean all-target build and guardrail
  tests. See [containerized toolchains](CONTAINERIZED_TOOLCHAINS.md).
- Application development-runner or network configuration changes pass
  [LAN verification](../components/MONOREPO.md#verification) in implementing projects.
- Changes affecting shipped AI behavior or model selection satisfy
  [candidate functional qualification](AI_EVALUATION.md#required-functional-acceptance-suite).
- Durable job/step changes satisfy the [Job Management verification matrix](../architecture/BACKGROUND_JOBS.md#13-required-verification-and-adoption),
  including real process interruption and fresh-process recovery when lifecycle, leases, checkpoints,
  publication, or external-effect handling changes. Execution changes prove native PHP thread
  behavior on a capable runtime and sequential fallback without it; input/lifecycle changes prove
  metadata-only creation/scoped lookup, fresh start-time loading, atomic equivalent-job
  reuse/replacement, cancellation races, and admin visibility. Provider timeouts alone do not cover
  this gate.
- Payment/provider/wallet changes satisfy the [payment verification matrix](../architecture/PAYMENTS.md#required-verification-and-adoption), including separate-process accounting races and ambiguous-outcome recovery.
- New rules arrive **with** their deterministic tests; a rule without a test is unfinished.

## Rules

- Inject non-determinism (randomness, time, I/O); assert exact outcomes with no environment.
- Use a manual clock for timed effects; never wait in a test.
- Validate content/contracts at construction and cover each bundle with a construction test.
- Keep hardware-GPU measurements in a run that rejects software rendering.
- Keep externally visible or billable provider checks in explicit, confirmation-gated runners.
