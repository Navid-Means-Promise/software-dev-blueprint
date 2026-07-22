# State management

Authoritative for how application state reaches the UI. The mechanism is the **Observer** pattern in a
small store — no reactive framework, no external state library.

## The store

A single `Store<AppState>` holds the application's observable state. It exposes a way to read the
current snapshot, to apply an update that produces the next snapshot, and to subscribe for change
notifications. It is registered as a [DI singleton](DEPENDENCY_INJECTION.md) so every part of the app
sees the same state.

`AppState` is a plain, typed, serializable shape — the minimum the UI needs to render: the current
locale, the active view, session-derived view state. It is not the domain model itself; services own
the domain model and publish the derived view state the UI consumes.

## The flow

```text
Service advances the engine ──▶ writes next AppState into the Store
                                        │  (Observer notifies)
                                        ▼
              UI components read the snapshot and render
              User acts ──▶ component calls a service method ──▶ (loop)
```

Components **render and delegate**. A component reads state and produces markup; on user action it
calls an intent-level service method. It never decides a domain rule, never mutates the store
directly with business logic, never talks to a port. This is [development principles](../principles/DEVELOPMENT_PRINCIPLES.md)
§2 made concrete at the UI edge.

## Why an observer store rather than a framework

- **The core stays framework-free.** The store is plain TypeScript in `@app/shared`; the UI framework
  subscribes to it, not the other way round. Swapping the UI framework would not touch state
  management.
- **Determinism.** State transitions are ordinary function calls on ordinary data, asserted in a unit
  test without a DOM. See [testing](../practices/TESTING.md).
- **One propagation mechanism.** Every consumer updates the same way. There is no second path for
  "just this one component" — that would violate DRY (principles §1).

## Boundaries

- The store carries **view state**, not domain entities. Services translate domain state into the
  minimal snapshot the UI needs.
- Cosmetic, per-frame animation state never enters the store. A GPU canvas owns its own render loop;
  driving cosmetics through store updates would cause needless re-renders and is forbidden by
  [UI performance](../practices/UI_PERFORMANCE.md).
- Persistence of state across launches is a [repository port](PORTS_AND_ADAPTERS.md) concern, not the
  store's — the store is in-memory; a service rehydrates it from storage at startup and writes
  through on change.
