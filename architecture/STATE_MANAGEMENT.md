# State management

Authoritative for how application state reaches the UI. The mechanism is the **Observer** pattern in a
small store — no reactive framework, no external state library.

## The store

A single client-side `Store<AppState>` holds the UI's observable projection. It exposes a way to read
the current snapshot, to apply an update that produces the next snapshot, and to subscribe for
change notifications. It is registered as a [DI singleton](DEPENDENCY_INJECTION.md) so every part of
one client instance sees the same projection.

`AppState` is a plain, typed, serializable shape—the minimum the UI needs to render: the current
locale, the active view, loading/error state, and server-derived view state. It is neither the domain
model nor canonical persistence. Client presenters/use-case adapters publish results into it after
the server accepts a canonical operation.

## The flow

```text
UI delegates intent ──▶ bounded use-case provider ──▶ authenticated server
          ▲                                                │
          │                                      authoritative result
          │                                                ▼
          └──── UI renders ◀──── Store ◀──── client presenter/adapter
```

The UI **renders and delegates**. It reads state and produces markup; on user action it calls an
intent-level service method. It never decides a domain rule, never mutates the store directly with
business logic, and never talks to a port. How a project distributes that connection across its
component tree is a documented [project decision](../components/SHARED_UI.md#component-wiring-is-a-project-decision).
This is [development principles](../principles/DEVELOPMENT_PRINCIPLES.md) §2 made concrete at the UI
edge.

## Why an observer store rather than a framework

- **The core stays framework-free.** The store is plain TypeScript in `@app/shared`; the UI framework
  subscribes to it, not the other way round. Swapping the UI framework would not touch state
  management.
- **Determinism.** State transitions are ordinary function calls on ordinary data, asserted in a unit
  test without a DOM. See [testing](../practices/TESTING.md).
- **One propagation mechanism.** Every stateful UI surface derives application state from the same
  store. Passing its current snapshot into a passive presentation component is ordinary rendering,
  not another source of state.

## Boundaries

- The store carries **view state**, not domain entities. Services translate domain state into the
  minimal snapshot the UI needs.
- Cosmetic, per-frame animation state never enters the store. A GPU canvas owns its own render loop;
  driving cosmetics through store updates would cause needless re-renders and is forbidden by
  [UI performance](../practices/UI_PERFORMANCE.md).
- The store is in-memory and is rebuilt from authenticated server queries plus narrowly defined
  device-local preferences. It is never written through as canonical domain persistence. This
  blueprint defines no offline write queue; user-data mutations require a server response.
