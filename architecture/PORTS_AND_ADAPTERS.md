# Ports and adapters

Authoritative for the seam between the domain core and every host. This is the pattern that makes
"one implementation, many hosts" and "testable without an environment" both true at once.

## The idea

A **port** is a narrow interface, declared in `@app/shared`, for one capability the domain needs from
its host. An **adapter** is a concrete implementation of that port, living in the client or server
that owns the underlying host API. The domain depends only on the port; adapters are registered at
startup through the [DI container](DEPENDENCY_INJECTION.md).

The rule of thumb: **if it differs by host, or it is non-deterministic, it is a port.**

## Naming the interface and implementation

`Port` names the interface's architectural role; it is not an interface suffix. Name the interface
for what it supplies: use `…Provider` for an interchangeable capability provider,
`…Repository` for domain-shaped persistence, or a more specific role noun when one is clearer.
Reserve `…Adapter` for a concrete technology integration:

```ts
interface ContextRetrievalProvider {
  retrieve(query: ContextRetrievalQuery): Promise<readonly ContextRecord[]>;
}

class OramaContextRetrievalAdapter implements ContextRetrievalProvider {
  // Maps the provider contract to Orama.
}
```

Do not name the provider-neutral interface `…Adapter`; that hides which side of the boundary it
occupies and makes the concrete adapter appear to implement another adapter.

## The typical port catalog

Every project defines the ports its domain actually needs; this is the recurring set the stack has
converged on. Names are illustrative.

| Port | Abstracts | Web adapter (example) | Android adapter (example) | Test double |
| --- | --- | --- | --- | --- |
| **Storage** | Durable key/value persistence | IndexedDB | Capacitor Preferences | In-memory map |
| **Platform info** | Host facts (platform, capabilities) | Browser detection | Native bridge | Fixed struct |
| **Clock / Scheduler** | Delayed and repeated work | `setTimeout`-backed | `setTimeout`-backed | Manual test clock |
| **Randomness** | Any sampled outcome | Real RNG | Real RNG | Fixed sequence |
| **Audio** | Playing/looping/fading sound | Web Audio / `HTMLAudio` | Same via WebView | Silent recorder |
| **Device** | A physical peripheral | HTTP client to device | HTTP client to device | In-memory twin |

Two properties are non-negotiable:

- **Ports are narrow and role-specific.** A port exposes only the verbs its one capability needs. A
  fat "platform" port that does storage *and* audio *and* device I/O is a design smell — split it.
- **Every port has a safe default.** The core registers a no-op or in-memory default (silent audio, a
  no-op device, an in-memory store) so the domain runs fully before any real adapter exists, and so a
  capability a given host lacks simply stays inert. A client overrides only the defaults it improves.

## Repositories are ports with a domain shape

Persistence is a port whose interface is expressed in **domain terms** (save the active session, load
the saved locale) rather than storage terms (`get`/`put` by raw key). The repository interface lives
in the core; each platform's adapter maps it onto that platform's store. The domain never knows
whether it is talking to IndexedDB or a native preference store.

## The injection seam

Adapters never wire themselves. A client's startup:

1. constructs the platform's adapters,
2. registers the core's default services,
3. overrides the defaults with the platform adapters through their tokens,
4. mounts the shared UI.

Steps 2–4 are identical across clients; only step 1 — the adapter *set* — differs. That difference is
the *entire* platform-specific surface of an interactive client. Collapsing steps 2–4 into one shared
call is exactly the [shared bootstrap](../components/SHARED_UI.md).

For example, a project may inject a `StorageProvider` backed by IndexedDB on web and native storage on
Android, plus independent platform-information, audio, and device-capability ports, before mounting
the same UI. Nothing else about the two client compositions needs to differ.

## Why this matters

- **Testability:** a domain test constructs a service with in-memory and fixed adapters and asserts
  behavior with no DOM, network, or clock. See [testing](../practices/TESTING.md).
- **Portability:** adding a host means writing adapters, not touching the domain.
- **Honesty about capability:** a missing capability is an inert default, not a crash and not a fork.
