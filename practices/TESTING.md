# Testing

Authoritative for how correctness is proven. The stack's testing strategy follows directly from its
architecture: because the domain is [pure and injectable](../architecture/SHARED_DOMAIN.md), most
rules are verified as fast, deterministic unit tests with no environment at all.

## The layers

| Layer | Scope | How |
| --- | --- | --- |
| **Domain unit tests** | Every rule, transition, and boundary in `@app/shared` | Construct the class with test doubles; assert exact outcomes. No DOM, network, or real time. |
| **Server tests** | Route → service behavior, contract responses, the device twin | Exercise the server with in-memory adapters; assert responses and twin state. |
| **Browser acceptance (E2E)** | The shared UI as a user meets it | Drive the built web app in a real browser; the shared UI means this also covers the Android surface. |

## Determinism is the whole game

Every rule is tested by **injecting** its non-deterministic collaborators
([ports & adapters](../architecture/PORTS_AND_ADAPTERS.md)):

- **Randomness** → a fixed source, so both sides of a randomized branch are reproducible.
- **Time and delays** → a **manual test clock** driving the scheduler port, so timed effects (cues,
  animations, retries) are asserted by advancing the clock, not by waiting. A test for a 300 ms cue
  takes microseconds.
- **Storage / platform / audio / device** → in-memory or recording doubles, so persistence, effects,
  and device intent are asserted without a real backend.

If a rule cannot be tested this way, that is a design signal: a hidden dependency needs lifting into a
port. The test difficulty is diagnosing the architecture, doing its job.

*Reference implementation:* both projects inject a fixed randomness source to make every branch
reproducible, and Mystery drives its audio-cue timeline through a manual clock so the whole
choreography is asserted with no real audio context and no waiting.

## Content and contracts are tested at construction

Because the domain model [validates its own graph](../architecture/SHARED_DOMAIN.md), a malformed
content definition fails at construction. A unit test that constructs each shipped content bundle is
therefore also a contract test: unreachable states, dead ends, and dangling asset references surface
as test failures, not as an unlucky play in production.

## Browser acceptance

The E2E layer drives the real UI in every supported locale and both text directions at mobile widths,
covering the [i18n](I18N.md) and [UI performance](UI_PERFORMANCE.md) acceptance criteria. Isolate any
measurement that needs real GPU hardware into its own run that **rejects a software renderer**, so a
software-fallback result is never mistaken for animated-shader performance.

## Definition of done, restated for tests

- Run the **narrowest** relevant tests while iterating; run the repository-wide
  [`check`](../components/MONOREPO.md) before calling a broad change done.
- UI changes additionally pass the browser acceptance flow in every locale.
- New rules arrive **with** their deterministic tests; a rule without a test is unfinished.
- Report real results — a failing or skipped test is stated, never rounded up to "passing."

## Rules

- Inject non-determinism (randomness, time, I/O); assert exact outcomes with no environment.
- Use a manual clock for timed effects; never wait in a test.
- Validate content/contracts at construction and cover each bundle with a construction test.
- Keep hardware-GPU measurements in a run that rejects software rendering.
