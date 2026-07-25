# Shared UI

Authoritative for `@app/ui` — the one component set that both interactive clients render. The
governing rule from [development principles](../principles/DEVELOPMENT_PRINCIPLES.md) §5: **never fork
a screen by platform.**

## One implementation, both clients

`@app/ui` is a set of **Lit** web components. The web app and the Android app (a Capacitor WebView)
mount the *same* components. There is no `WebScreen` and `AndroidScreen`; there is one screen, and any
behavior that genuinely differs by platform enters through a [port](../architecture/PORTS_AND_ADAPTERS.md),
injected at startup. If you find yourself branching a component on the platform, the branch belongs in
an adapter instead.

Lit is the chosen framework because it is small, standards-based (custom elements), and renders
identically in a browser and a WebView. The choice is deliberate, but the *principle* — one UI, ports
for difference — outranks the framework; the store and domain are framework-free precisely so the UI
layer could be swapped without touching them.

## The UI renders and delegates

The UI renders state from the [observer store](../architecture/STATE_MANAGEMENT.md) and delegates user
intent to application services. UI code holds no domain rule, reaches no port directly, and stores no
business state locally. Application dependencies are supplied from the composition root; no component
resolves them from the DI container.

User-facing text comes from the typed [i18n](../practices/I18N.md) catalog — never an inline literal.
Components consume shared semantic [typography](../practices/TYPOGRAPHY.md) tokens rather than
choosing families independently.

### Component wiring is a project decision

The blueprint does not prescribe how a project distributes the store subscription and service
delegation across its component tree. The document consumer chooses the option that best fits the
project, records that choice in the project's architecture document, and applies it consistently.

**Option A — directly connected components.** A component receives the store and relevant application
service through composition, subscribes to the store, renders its snapshot, and calls intent-level
service methods. This keeps a small cohesive component self-contained without permitting a
DI-container lookup or direct port access.

**Option B — passive presentation components (proposal).** A container receives the store and
application services, owns the subscription, and maps the snapshot into readonly presentation models.
A leaf receives those models through typed properties and publishes typed, bubbling, composed custom
events that describe user intent. The leaf may own ephemeral UI state — open/closed state, a form
draft, focus, gesture, and animation lifecycle — but it does not acquire the store, navigation, a
repository, a port, or an application service. Consider this option when a visual unit is reusable,
has a distinct interaction lifecycle, or would otherwise couple presentation mechanics to
orchestration.

Both options use the same store and application services; they differ only in where the UI connection
is owned. A project may choose differently for separate component boundaries when it documents a
clear ownership rule and does not create a second source of application state.

## The shared bootstrap seam

The startup of the two clients is nearly identical: register core services, override the platform
adapters, mount the root component. The *only* real difference is which adapters get injected.
Collapse that into **one shared bootstrap** in `@app/ui` that takes the adapter set as its argument;
each client's entry point becomes a single call passing its own adapters.

```ts
// @app/ui/bootstrap.ts — the seam. Only `adapters` differs per client.
export function bootstrap(adapters: PlatformAdapters, mountPoint: Element): void {
  registerCoreServices();
  registerPlatformAdapters(adapters); // overrides the core defaults
  mountAppShell(mountPoint);
}

// apps/web/src/main.ts
bootstrap({ storage: new IndexedDbStorage(), platform: webPlatformInfo /* … */ }, document.body);

// apps/mobile/src/main.ts
bootstrap({ storage: new PreferencesStorage(), platform: nativePlatformInfo /* … */ }, document.body);
```

This is the tangible payoff of ports & DI: a client is its adapter set plus one bootstrap call. It is
a recurring candidate to propose upstream to the blueprint.

## Startup readiness gate

Every playable/interactive surface sits behind **one shared readiness workflow** owned by `@app/ui`,
not duplicated per client. Before the main UI renders, it loads any split renderer code and the
startup-critical assets, reports progress in every supported locale, and offers a retry path. The gate
is for *startup-critical* assets only — it is not a license to download an unbounded catalog; larger
content prefetches later, after the first interaction. Detail and rationale:
[assets & content](../practices/ASSETS_AND_CONTENT.md) and [UI performance](../practices/UI_PERFORMANCE.md).

## Rendering discipline

Any rich or animated visual (a GPU canvas, an SVG scene, layered ambience) obeys the
[UI performance](../practices/UI_PERFORMANCE.md) budget from its first commit: compositor-only
perpetual animation, an adaptive and pausable lifecycle for a GPU canvas, and a static fallback where
the capability is absent. Cosmetic animation stays inside its renderer and never drives store updates
or component re-renders.

## Rules

- One screen implementation per route, shared by all clients; it may compose smaller components, and
  platform difference is a port, not a fork.
- The document consumer chooses and records the component-wiring option; UI code holds no domain
  rules, port access, or DI-container lookup under either option.
- All user-facing strings come from the i18n catalog.
- Startup goes through the shared readiness gate; the bootstrap's only variable is the adapter set.
