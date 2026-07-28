# Dependency injection

Authoritative for how implementations are wired to the domain. The [ports & adapters](PORTS_AND_ADAPTERS.md)
pattern defines the seams; this document defines how the two sides meet at startup.

## What the container is (and is not)

A small, hand-written container. It maps a **typed token** to a factory that builds the
implementation, resolves dependencies transitively, and enforces explicit lifetimes. It is *not* a
reflection-based framework and needs no decorators on the domain classes — a domain service is a
plain class with constructor parameters, resolvable in a test without the container at all.

## Typed tokens

A token is a typed handle to a slot in the container. Because TypeScript has no runtime types, the
token — not an interface name — is what carries the type through resolution. This is one of the few
places where a snippet is clearer than prose:

```ts
// A token is branded with the type it resolves to.
const STORAGE = createToken<StorageProvider>('StorageProvider');
const SCHEDULER = createToken<Scheduler>('Scheduler');

container.registerSingleton(STORAGE, () => new IndexedDbStorageAdapter());
const storage = container.resolve(STORAGE); // typed as StorageProvider
```

Tokens live in one module so the set of injectable capabilities is enumerable in one place.

## Explicit lifetimes

Every registration states its lifetime. **Singleton** is the norm: one instance for the app,
constructed lazily on first resolve, reused thereafter. The point of making it explicit is to prevent
the silent-recreation bug where a service is unintentionally rebuilt per call and two parts of the app
end up with different state.

The container exposes a **`reset()`** so an integration test starts from a clean graph instead of
leaking singletons between cases. This is a small, deliberate capability — treat it as required, not
optional, because test isolation depends on it.

## Registering core services

The core provides a single `registerCoreServices()` that installs every default (the safe no-op /
in-memory adapters and the domain services built on them). A client calls it, then overrides only the
tokens it has real adapters for. Ordering follows the dependency graph: a service that depends on the
store and storage is registered after both, and resolves them inside its factory.

## The per-entity trap, and the factory that avoids it

The subtle case: a service that must exist **per selected entity**, not once for the whole app. It
cannot be a container singleton (there is no single instance), but it also must not reach into a
global to find its collaborators.

The resolution: the container holds a **factory** behind a token, not the entity service. The factory
is a singleton with the shared collaborators already injected; calling it mints a fresh per-entity
service. The per-entity service stays out of the singleton graph *and* never performs a global lookup.

```ts
// The container holds the factory (singleton), not the per-entity service.
const SESSIONS = createToken<SessionFactory>('SessionFactory');
container.registerSingleton(
  SESSIONS,
  () => new SessionFactory(container.resolve(CATALOG), container.resolve(STORAGE) /* … */),
);

// Selecting an entity mints a fresh, fully-wired service — no globals reached.
const session = container.resolve(SESSIONS).create(selectedId);
```

*Reference implementation:* Mystery holds a `StorySessionFactory` behind a token; picking a story on
the shelf calls the factory to build that story's session, so a per-story service never has to be a
container singleton and never looks its collaborators up.

## Rules

- Domain classes receive collaborators through their constructor. No class calls `container.resolve`
  on itself or reaches a module-level singleton.
- Only startup code (the client/server bootstrap and `registerCoreServices`) touches the container.
- One module owns the tokens; one function owns the core registrations.
- Lifetimes are always explicit, and `reset()` exists for test isolation.
