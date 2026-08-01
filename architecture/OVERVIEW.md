# Architecture overview

Authoritative for **module boundaries and dependency direction**. It describes how the modules are
arranged to satisfy the [development principles](../principles/DEVELOPMENT_PRINCIPLES.md); it does not
restate the rules.

## The shape in one sentence

A pure domain core is shared by one UI implementation, which is mounted by every interactive client;
the server imports the same domain contracts; physical devices implement a semantic contract — so
business rules exist once, visual code exists once, and platform code is a thin edge.

## Dependency graph

```text
                          ┌─────────────────────────┐
                          │       @app/shared       │
                          │ domain · engine ·       │
                          │ services · ports ·      │
                          │ DI · store · i18n       │
                          └───────────▲─────────────┘
                                      │  (imports)
                          ┌───────────┴─────────────┐
                          │        @app/ui          │
                          │ shared components,      │
                          │ shared bootstrap        │
                          └──────▲───────────▲──────┘
                                 │           │
                     ┌───────────┘           └───────────┐
                     │                                   │
              ┌──────┴──────┐                     ┌──────┴──────┐
              │  apps/web   │                     │ apps/mobile │
              │ HTTP/cache  │                     │ HTTP/native │
              │ adapters    │                     │ adapters    │
              └──────┬──────┘                     └──────┬──────┘
                     └──────── authenticated ────────────┘
                          bounded runtime requests
                                      │
                                      ▼
                               ┌──────────────┐
                               │ @app/server  │
                               │ authority ·  │
                               │ persistence  │
                               └──────┬───────┘
                                      │ imports
                                      └──────────────▶ @app/shared

     firmware     ──implements──▶  the semantic device contract in docs
```

For compile-time dependencies, **imports point inward toward the domain core.** The core imports
nothing above it—not the UI, not a client, not a framework, not the DOM. The client-to-server arrow
is a runtime network request, not a package import. Confusing those two arrow types would make the
diagram contradictory; a compile-time violation remains an architecture bug even when it compiles.

## Module map

| Module            | Package name (example) | Owns                                                                                                                            |
| ----------------- | ---------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `packages/shared` | `@app/shared`          | Pure domain, engine, services, ports, DI container + tokens, observer store, i18n                                               |
| `packages/ui`     | `@app/ui`              | The one component set both clients render, and the shared bootstrap                                                             |
| `apps/web`        | `@app/web`             | Untrusted web host: bundler entry, bounded HTTP/platform adapters, transient cache, optional static-host target                 |
| `apps/mobile`     | `@app/mobile`          | Untrusted Android host: Capacitor project, bounded HTTP/native adapters, transient cache                                        |
| `apps/server`     | `@app/server`          | Authenticated authoritative use cases, canonical persistence, provider/derived-index workers, device twin, optional web hosting |
| `firmware`        | —                      | ESP-IDF / C++17 device engine implementing the semantic device contract                                                         |
| `e2e`             | `@app/e2e`             | Browser acceptance tests over the shared UI                                                                                     |
| `tooling`         | —                      | Shared build config (bundler factory, budgets) and content generators                                                           |
| `docs`            | —                      | Per-project contracts, architecture notes, and roadmaps                                                                         |

`@app` is the neutral placeholder scope; a real project substitutes its own package scope.

## How the boundaries are enforced

- The core declares **ports** for every host capability and never imports an adapter. See
  [ports & adapters](PORTS_AND_ADAPTERS.md).
- Adapters live in the client/server that own the host API, and are registered at startup through the
  [DI container](DEPENDENCY_INJECTION.md).
- The UI renders state from the [observer store](STATE_MANAGEMENT.md) and delegates every decision to
  the domain; UI code holds no business rules.
- Shared **contracts**—wire, persistence, content—are defined once; every applicable consumer
  imports or conforms to that definition. See
  [contracts](CONTRACTS.md).
- Networked clients submit bounded intent; only the server authenticates, authorizes, validates, and
  commits canonical effects. See [development principles §7](../principles/DEVELOPMENT_PRINCIPLES.md#7-trust-boundaries-are-not-mvp-compromises).

## Where each layer's detail lives

- Domain core internals: [shared domain](SHARED_DOMAIN.md)
- Assistant orchestration, durable user context, domain tools, and speech:
  [AI subsystem](AI_SUBSYSTEM.md)
- The clients, server, and firmware as concrete hosts: the [components](../components/) set
- Cross-cutting concerns (i18n, performance, testing, assets): the [practices](../practices/) set
