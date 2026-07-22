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
              │ web adapter │                     │ Capacitor   │
              │ (e.g. IDB)  │                     │ adapter     │
              └─────────────┘                     └─────────────┘

     @app/server  ──imports──▶  @app/shared contracts (never the UI)
     firmware     ──implements──▶  the semantic device contract in docs
```

The one direction that matters: **arrows only point up and inward, toward the domain core.** The core
imports nothing above it — not the UI, not a client, not a framework, not the DOM. A violation of
this direction is an architecture bug regardless of whether it compiles.

## Module map

| Module | Package name (example) | Owns |
| --- | --- | --- |
| `packages/shared` | `@app/shared` | Pure domain, engine, services, ports, DI container + tokens, observer store, i18n |
| `packages/ui` | `@app/ui` | The one component set both clients render, and the shared bootstrap |
| `apps/web` | `@app/web` | Web host: bundler entry, web storage adapter, optional static-host target |
| `apps/mobile` | `@app/mobile` | Android host: Capacitor project, native storage adapter |
| `apps/server` | `@app/server` | HTTP API, contract endpoints, in-memory device twin, optional web hosting |
| `firmware` | — | ESP-IDF / C++17 device engine implementing the semantic device contract |
| `e2e` | `@app/e2e` | Browser acceptance tests over the shared UI |
| `tooling` | — | Shared build config (bundler factory, budgets) and content generators |
| `docs` | — | Per-project contracts, architecture notes, and roadmaps |

`@app` is the neutral placeholder scope; a real project picks its own (`@mystery/*`, `@gems/*`, …).

## How the boundaries are enforced

- The core declares **ports** for every host capability and never imports an adapter. See
  [ports & adapters](PORTS_AND_ADAPTERS.md).
- Adapters live in the client/server that own the host API, and are registered at startup through the
  [DI container](DEPENDENCY_INJECTION.md).
- The UI subscribes to the [observer store](STATE_MANAGEMENT.md) and delegates every decision to the
  domain; it holds no business rules.
- Shared **contracts** — wire, storage, content — are defined once and consumed everywhere. See
  [contracts](CONTRACTS.md).

## Where each layer's detail lives

- Domain core internals: [shared domain](SHARED_DOMAIN.md)
- The clients, server, and firmware as concrete hosts: the [components](../components/) set
- Cross-cutting concerns (i18n, performance, testing, assets): the [practices](../practices/) set
