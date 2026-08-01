# Shared domain core

Authoritative for the internals of `@app/shared` — the package every other module depends on and that
depends on nothing above it. This is where the product's rules live.

## The one hard rule

`@app/shared` is **framework-free and DOM-free.** No Lit, no browser globals, no `window`, no
Capacitor, no Fastify. The server may import it; it must never import the UI. Everything the domain
needs from a host arrives through a [port](PORTS_AND_ADAPTERS.md). Pure rules can therefore run in a
browser, an Android WebView, the server, or a unit test. For a networked product, the ability to run
code in a client does not grant that client authority: canonical use cases execute on the server.

## What lives here

| Concern                   | Responsibility                                                                                                                                                                    |
| ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Domain model**          | The product's entities and their invariants. Validity is checked at construction, not deferred to runtime.                                                                        |
| **Engine**                | An explicit state machine that walks the domain flow and owns branching decisions.                                                                                                |
| **Services**              | Application logic that coordinates the model, the engine, and ports for a use case.                                                                                               |
| **Ports**                 | The interfaces to every host capability. See [ports & adapters](PORTS_AND_ADAPTERS.md).                                                                                           |
| **DI container + tokens** | How implementations are wired at startup. See [dependency injection](DEPENDENCY_INJECTION.md).                                                                                    |
| **Store**                 | The observable application state the UI subscribes to. See [state management](STATE_MANAGEMENT.md).                                                                               |
| **i18n**                  | The typed multi-locale message catalog and the locale service. See [i18n](../practices/I18N.md).                                                                                  |
| **Shared utilities**      | Cross-cutting helpers that belong to no single feature — e.g. a thrown-value normalizer that turns any caught `unknown` into a typed message, used by both the UI and the server. |

## The engine is a state machine, not a bag of flags

Model domain flow as explicit states and transitions. The engine walks **states** — a state is one
beat of the flow — and a transition is the only way to move between them. Branching is a first-class
concept: a choice that is deterministic transitions immediately; a choice whose outcome is uncertain
first creates a _pending_ decision, and only an explicit resolution step samples the randomness
source.

Two properties fall out of this and are required:

- **Validity at construction.** The domain model validates its own graph when built — unreachable
  states, dead ends, and dangling asset references fail _then_, not on the unlucky play that reaches
  them. A malformed definition cannot become a running session.
- **Determinism under injection.** Randomness, time, and delayed work are ports (a randomness source,
  a scheduler). A test injects a fixed source and a manual clock and asserts the exact resulting
  state. Every branch, including both sides of a randomized outcome, is reproducible. See
  [testing](../practices/TESTING.md).

_Reference implementation:_ in A Cup of Mystery the engine walks scene-_states_ (several beats can
share one scene), a direct choice transitions at once, and a dice choice creates a pending roll that
only a `roll()` step resolves through an injected `DiceRoller`.

## Services coordinate; they do not leak

A service owns one use case and receives every collaborator through its constructor. Depending on
its role, it may advance a pure engine, call a bounded application-use-case provider, persist through
a server repository, or issue effects through a capability provider. It holds no reference to any
host API.

In a networked product, authoritative services execute in the server composition root. The UI
depends on a bounded application-use-case interface implemented by an authenticated transport; it
does not instantiate the repository-backed service in the client. Keeping service code in
`@app/shared` makes it reusable by the server and deterministic tests—it does not make client
execution authoritative.

### The mediator for coordinated effects

When entering a state should cause _several_ timed effects (sound, light, animation) in concert, that
choreography lives in **one** mediator service — not spread across components or the engine. Content
_declares_ what a state should do; the mediator compares the previous state with the new one, issues
each cue through the ports (with any delay expressed through the scheduler port so it is testable),
and cancels every unfired cue on the next transition. Keeping it in the core means the whole timeline
is verified without a real audio context or real waiting.

_Reference implementation:_ Mystery's `SceneAudioDirector` decides whether a scene's ambience bed
carries over or restarts, schedules each state's start/stop/effect cues through a `Scheduler` port,
and cancels pending cues on transition — so the UI's audio adapter only has to play, loop, and fade
what it is told.

## What does _not_ live here

- Rendering, layout, DOM, or any component — that is [the shared UI](../components/SHARED_UI.md).
- Any concrete host API (storage backend, HTTP framework, audio engine) — those are adapters in the
  owning client or server.
- Product **assets** (audio, images). The core references them through generated, typed paths; the
  bytes are build assets. See [assets & content](../practices/ASSETS_AND_CONTENT.md).
