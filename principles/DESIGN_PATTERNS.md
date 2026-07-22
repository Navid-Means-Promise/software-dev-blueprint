# Design patterns

The fixed pattern vocabulary of the stack. [Development principles](DEVELOPMENT_PRINCIPLES.md) §2
requires reusing these rather than inventing parallel ones. Each has a settled role; use the named
pattern for its job and do not introduce a second mechanism that does the same work.

Architecture documents show where each lives; this file is the *why each exists* index.

---

## The catalog

| Pattern | Role on the stack | Detail |
| --- | --- | --- |
| **Ports & Adapters** (Hexagonal) | Keep the domain and UI independent of any host API | [ports & adapters](../architecture/PORTS_AND_ADAPTERS.md) |
| **Dependency Injection** | Wire concrete implementations once, at startup | [dependency injection](../architecture/DEPENDENCY_INJECTION.md) |
| **State Machine** | Make domain flow and branching explicit and testable | [shared domain](../architecture/SHARED_DOMAIN.md) |
| **Mediator** | Turn one declared intent into a set of coordinated port calls | [shared domain](../architecture/SHARED_DOMAIN.md) |
| **Observer** | Propagate state to the UI without a reactive framework | [state management](../architecture/STATE_MANAGEMENT.md) |
| **Repository** | Keep persistence swappable behind a domain-shaped interface | [ports & adapters](../architecture/PORTS_AND_ADAPTERS.md) |
| **Strategy** | Replace one behavior without touching its callers | below |
| **Factory (behind a token)** | Create per-entity services without a global lookup | [dependency injection](../architecture/DEPENDENCY_INJECTION.md) |
| **Semantic Device Model** | Send meaning to hardware; let firmware render frames | [contracts](../architecture/CONTRACTS.md) |

---

## Why these, specifically

**Ports & Adapters** is the backbone. Every capability that differs by host — storage, platform
facts, audio, a physical device, the clock, randomness — is a narrow port in the domain and a thin
adapter per platform. This is what makes "one implementation, many hosts" (principles §5) possible
and what makes the domain testable (principles §3).

**Dependency Injection** is how those adapters reach the domain. Services declare their collaborators
as constructor parameters; a container resolves them from typed tokens at startup. Lifetimes are
**explicit**, and the container can be reset for test isolation. No service reaches into a global.

**State Machine** models domain flow as explicit states and transitions rather than scattered
booleans. Branching decisions (including anything randomized) are visible, enumerable, and
reproducible under an injected source of randomness.

**Mediator** owns "what should happen when we enter this state" in one place. Content *declares*
intent (what to play, what to light, with what timing); the mediator translates that into ordered
port calls and owns their cancellation. Nothing else needs to know the choreography.

**Observer** propagates state. A small store holds application state and notifies subscribers on
change; components subscribe and re-render. No reactive-framework machinery, no manual wiring of
every consumer.

**Repository** keeps persistence domain-shaped and swappable. The domain talks to a repository
interface; each platform provides the adapter (one backing store on web, another on Android) without
the domain knowing which.

**Strategy** is how interchangeable behaviors are selected — a randomness source, an effect
implementation, a scheduler. The caller depends on the interface; the concrete strategy is injected.
Swapping it (including for a deterministic test double) changes nothing at the call site.

**Factory behind a token** solves the per-entity-service problem: when a service must exist *per
selected entity* (not once for the app), the container holds the **factory**, not the instance, so a
per-entity service stays out of the singleton graph without ever looking its collaborators up
globally.

**Semantic Device Model** is the rule for physical hardware. The wire contract carries *meaning* —
a mode and its parameters — and the firmware renders the actual frames locally from that meaning.
This keeps the link cheap, keeps timing correct on-device, and lets a server or client hold a
faithful in-memory twin of the device for testing.

---

## Anti-patterns (rejected on this stack)

- **Service locator / global singletons** inside domain classes. Collaborators are injected, never
  fetched.
- **Framework or DOM types in the domain core.** The core is pure; UI concerns stay in the UI.
- **Per-platform screen forks.** One UI implementation; platform difference enters through a port.
- **Streaming animation frames to hardware.** Send semantic intent; render on the device.
- **A second name or a second definition for one concept.** One name, one source (principles §1, §0).
