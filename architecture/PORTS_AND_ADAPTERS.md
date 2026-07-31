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
interface ClipboardProvider {
  writeText(value: string): Promise<void>;
}

class BrowserClipboardAdapter implements ClipboardProvider {
  // Maps the provider contract to the browser Clipboard API.
}
```

Do not name the provider-neutral interface `…Adapter`; that hides which side of the boundary it
occupies and makes the concrete adapter appear to implement another adapter.
The optional [assistant context-retrieval design](AI_CONTEXT_RETRIEVAL.md) provides a fuller example
in which an Orama adapter implements narrow indexing and retrieval provider contracts.

## The typical port catalog

Every project defines the ports its domain actually needs; this is the recurring set the stack has
converged on. Names are illustrative.

| Port                            | Abstracts                                          | Web adapter (example)   | Android adapter (example) | Test double          |
| ------------------------------- | -------------------------------------------------- | ----------------------- | ------------------------- | -------------------- |
| **Application use cases**       | Bounded authenticated commands/queries             | HTTP server adapter     | HTTP server adapter       | Recording provider   |
| **Server repository**           | Canonical durable records; server composition only | —                       | —                         | In-memory repository |
| **Device-local settings/cache** | Non-canonical host-local values                    | IndexedDB               | Preferences / SQLite      | In-memory map        |
| **Platform info**               | Host facts (platform, capabilities)                | Browser detection       | Native bridge             | Fixed struct         |
| **Clock / Scheduler**           | Delayed and repeated work                          | `setTimeout`-backed     | `setTimeout`-backed       | Manual test clock    |
| **Randomness**                  | Any sampled outcome                                | Real RNG                | Real RNG                  | Fixed sequence       |
| **Audio**                       | Playing/looping/fading sound                       | Web Audio / `HTMLAudio` | Same via WebView          | Silent recorder      |
| **Device**                      | A physical peripheral                              | HTTP client to device   | HTTP client to device     | In-memory twin       |

Two properties are non-negotiable:

- **Ports are narrow and role-specific.** A port exposes only the verbs its one capability needs. A
  fat "platform" port that does storage _and_ audio _and_ device I/O is a design smell — split it.
- **Defaults fail safely.** Optional presentation/device capabilities may use a no-op default (silent
  audio, no device). Authority-bearing capabilities—authentication, canonical use cases,
  repositories, provider credentials—have no permissive or client-local production fallback;
  startup fails when required wiring is absent. Tests inject explicit in-memory doubles.

## Repositories are ports with a domain shape

Persistence interfaces are expressed in **domain terms** rather than storage terms (`get`/`put` by
raw key). A server-side domain service depends on its repository interface. A client-facing UI or
presenter instead depends on bounded application-use-case interfaces implemented by authenticated
HTTP adapters; it does not receive repository-shaped CRUD access. Device-local settings and caches
use separate narrow contracts so they cannot accidentally become competing application truth.

## The injection seam

Adapters never wire themselves. A client's startup:

1. constructs the platform's adapters,
2. registers pure core services and optional safe defaults,
3. supplies required use-case transports and any optional platform overrides through typed tokens,
4. verifies the required bindings and mounts the shared UI.

Steps 2–4 are identical across clients; only step 1 — the adapter _set_ — differs. That difference is
the _entire_ platform-specific surface of an interactive client. Collapsing steps 2–4 into one shared
call is exactly the [shared bootstrap](../components/SHARED_UI.md).

For example, a project may inject the same server-backed application-use-case providers on web and
Android, plus independent platform-information, audio, cache, and device-capability providers,
before mounting the same UI. Nothing else about the two client compositions needs to differ.

## Why this matters

- **Testability:** a domain test constructs a service with in-memory and fixed adapters and asserts
  behavior with no DOM, network, or clock. See [testing](../practices/TESTING.md).
- **Portability:** adding a host means writing adapters, not touching the domain.
- **Honesty about capability:** a missing optional capability is inert; a missing authority-bearing
  dependency fails startup instead of falling back to client-local authority.
