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

## Components render and delegate

A component subscribes to the [observer store](../architecture/STATE_MANAGEMENT.md), renders from the
snapshot, and on user action calls an intent-level service method. It holds no domain rule, reaches no
port directly, and stores no business state locally. This keeps every rule in the testable core and
keeps the UI a thin, replaceable projection of state.

User-facing text comes from the typed [i18n](../practices/I18N.md) catalog — never an inline literal.

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
a recurring backport candidate between projects.

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

- One component per screen, shared by all clients; platform difference is a port, not a fork.
- Components subscribe to the store and delegate to services; they hold no rules and no port access.
- All user-facing strings come from the i18n catalog.
- Startup goes through the shared readiness gate; the bootstrap's only variable is the adapter set.
