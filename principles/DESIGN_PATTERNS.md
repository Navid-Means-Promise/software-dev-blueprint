# Design patterns

The fixed pattern vocabulary of the stack. [Development principles](DEVELOPMENT_PRINCIPLES.md) §2
requires reusing these rather than inventing parallel ones. Each has a settled role; use the named
pattern for its job and do not introduce a second mechanism that does the same work.

Architecture documents show where each lives; this file is the _why each exists_ index.

---

## The catalog

| Pattern                             | Role on the stack                                             | Detail                                                          |
| ----------------------------------- | ------------------------------------------------------------- | --------------------------------------------------------------- |
| **Ports & Adapters** (Hexagonal)    | Keep the domain and UI independent of any host API            | [ports & adapters](../architecture/PORTS_AND_ADAPTERS.md)       |
| **Dependency Injection**            | Wire concrete implementations once, at startup                | [dependency injection](../architecture/DEPENDENCY_INJECTION.md) |
| **State Machine**                   | Make domain flow and branching explicit and testable          | [shared domain](../architecture/SHARED_DOMAIN.md)               |
| **Mediator**                        | Turn one declared intent into a set of coordinated port calls | [shared domain](../architecture/SHARED_DOMAIN.md)               |
| **Observer**                        | Propagate state to the UI without a reactive framework        | [state management](../architecture/STATE_MANAGEMENT.md)         |
| **Repository**                      | Keep persistence swappable behind a domain-shaped interface   | [ports & adapters](../architecture/PORTS_AND_ADAPTERS.md)       |
| **Authorization Policy**             | Decide capability and resource scope from server-owned context | [authorization](../architecture/AUTHORIZATION.md)               |
| **Strategy**                        | Replace one behavior without touching its callers             | below                                                           |
| **Factory (behind a token)**        | Create per-entity services without a global lookup            | [dependency injection](../architecture/DEPENDENCY_INJECTION.md) |
| **Semantic Device Model**           | Send meaning to hardware; let firmware render frames          | [contracts](../architecture/CONTRACTS.md)                       |
| **Cursor-Paginated Prepend Stream** | Grow ordered feeds without unbounded reads or scroll jumps    | below                                                           |

---

## Why these, specifically

**Ports & Adapters** is the backbone. Every capability that differs by composition or is external or
non-deterministic—bounded client transport, canonical server persistence, device-local settings,
platform facts, audio, a physical device, the clock, randomness—is represented by a narrow port and
an adapter in the composition root that owns the integration. This is what makes "one
implementation, many hosts" (principles §5) possible and what makes the domain testable (principles
§3).

**Dependency Injection** is how those adapters reach the domain. Services declare their collaborators
as constructor parameters; a container resolves them from typed tokens at startup. Lifetimes are
**explicit**, and the container can be reset for test isolation. No service reaches into a global.

**State Machine** models domain flow as explicit states and transitions rather than scattered
booleans. Branching decisions (including anything randomized) are visible, enumerable, and
reproducible under an injected source of randomness.

**Mediator** owns "what should happen when we enter this state" in one place. Content _declares_
intent (what to play, what to light, with what timing); the mediator translates that into ordered
port calls and owns their cancellation. Nothing else needs to know the choreography.

**Observer** propagates state. A small store holds application state and notifies its UI subscribers
on change; the UI re-renders from the new snapshot. No reactive-framework machinery, no manual wiring
of every consumer.

**Repository** keeps persistence domain-shaped and swappable. Authoritative server services talk to
canonical repository interfaces implemented in the server composition. Networked clients use
bounded application-use-case transports instead of implementing those repositories. A separate
narrow client repository is appropriate only for state that is inherently device-local, never as a
second canonical store.

**Authorization Policy** turns an authenticated principal, bounded action, domain resource, and
server-derived context into an explicit allow/deny decision. RBAC supplies coarse capability while
ABAC and relationship predicates constrain that capability to the resource. Policy evaluation stays
inside the server boundary and is reused by every representation of the use case. See
[authorization and policy decisions](../architecture/AUTHORIZATION.md).

**Strategy** is how interchangeable behaviors are selected — a randomness source, an effect
implementation, a scheduler. The caller depends on the interface; the concrete strategy is injected.
Swapping it (including for a deterministic test double) changes nothing at the call site.

**Factory behind a token** solves the per-entity-service problem: when a service must exist _per
selected entity_ (not once for the app), the container holds the **factory**, not the instance, so a
per-entity service stays out of the singleton graph without ever looking its collaborators up
globally.

**Semantic Device Model** is the rule for physical hardware. The wire contract carries _meaning_ —
a mode and its parameters — and the firmware renders the actual frames locally from that meaning.
This keeps the link cheap, keeps timing correct on-device, and lets a server or client hold a
faithful in-memory twin of the device for testing.

**Cursor-Paginated Prepend Stream** is the shared pattern for a chat, activity feed, audit view, or
other ordered surface that opens at its newest edge and reveals older items as the user scrolls up.
The server performs a bounded keyset read; the controller prepends one page; the view preserves the
user's visible anchor. This keeps database work, network payloads, client growth, and visual movement
independent instead of hiding all four concerns in a scroll callback.

## Cursor-paginated prepend stream

The shared contract returns items in presentation order, an opaque cursor for the immediately older
page, and `null` when history is exhausted. Define one stable total order with a unique tie-breaker,
such as `(createdAt, id)`. The cursor encodes that boundary but remains server-owned: clients may
return it only to the same bounded, authenticated use case. The server validates its exact shape,
applies owner/tenant scope inside the indexed query, reads `pageSize + 1` rows to determine whether
another page exists, and never substitutes offset pagination or an in-memory full-history slice.

The controller owns `idle`, `loading`, and `error` history states, permits only one older-page request
at a time, de-duplicates by stable item ID, and prepends a successful page. A failed page retains its
cursor so retry does not add a synthetic error item to the conversation or feed.

The presentation component requests another page at a small threshold near its older edge. Before a
prepend render it captures `scrollTop` and `scrollHeight`; afterward it restores
`scrollTop + (newScrollHeight - oldScrollHeight)`. Initial hydration opens at the newest edge. Later
appends follow only when the user was already near that edge or explicitly initiated the new item;
they never drag a user who is reading history back to the bottom. Loading and retry affordances sit
at the history boundary, use localized catalog text, and expose a status to assistive technology.

Acceptance tests prove bounded page size, stable ordering and exhaustion, malformed-cursor rejection,
tenant isolation, one in-flight load, ID de-duplication, retry, and browser scroll-anchor preservation.
A concurrent append at the newest edge must not duplicate or skip older pages.

---

## Anti-patterns (rejected on this stack)

- **Service locator / global singletons** inside domain classes. Collaborators are injected, never
  fetched.
- **Framework or DOM types in the domain core.** The core is pure; UI concerns stay in the UI.
- **Per-platform screen forks.** One UI implementation; platform difference enters through a port.
- **Streaming animation frames to hardware.** Send semantic intent; render on the device.
- **Offset pagination or full-history slicing for a growing stream.** Use a stable keyset cursor and
  a bounded indexed query.
- **Unconditional scroll-to-bottom after a message-list update.** Preserve a prepend anchor and
  follow the newest edge only when the interaction calls for it.
- **A second name or a second definition for one concept.** One name, one source (principles §1, §0).
