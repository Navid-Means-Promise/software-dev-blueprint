# Android client

Authoritative for `apps/mobile`. Like the [web client](WEB_CLIENT.md), it is a thin, untrusted host:
the same shared UI in a native wrapper, with Android's adapters injected. No screen is written twice.

## What it is

A **Capacitor** application. Capacitor wraps the built web bundle in a native Android shell and
exposes native capabilities through a bridge. The mobile app owns:

- the Capacitor project (`android/`, `capacitor.config`) and its Vite build of the shared UI;
- authenticated HTTP adapters for bounded application use cases, native-capability adapters, and
  bounded transient cache/session state;
- the mount call, identical in shape to the web client's.

The screens are `@app/ui`; reusable pure rules/contracts are `@app/shared`; authoritative use cases
execute on the server. The mobile app is the adapter set plus one [bootstrap](SHARED_UI.md) call—the
_entire_ platform-specific surface. Client validation may improve feedback but grants no authority.

## Why Capacitor rather than a separate native UI

The stack's non-negotiable is one UI implementation. A native-Android UI would fork every screen and
break that rule. Capacitor keeps the WebView rendering the exact same Lit components the web client
renders, while still allowing native capabilities through ports where they genuinely differ. The
seam is the adapter, never the screen.

## Persistence adapter

For a networked application, Android calls the same bounded server-owned use cases as web rather than
exposing a generic repository/storage transport. Preferences/SQLite may hold settings that are
inherently device-local, bounded caches, pending offline operations, or one-time migration input;
they do not become competing application truth. A deliberately local-only product is a separate
architecture variant. See
[ports & adapters](../architecture/PORTS_AND_ADAPTERS.md).

## Build flow

The mobile client builds the web bundle, syncs it into the native project, and opens/builds the
Android project through Capacitor's CLI (`build` → `cap sync` → `cap open/run android`). These verbs
live as scripts so the flow is one command per step.

## Device and platform notes

- A physical device link uses the same [semantic device contract](../architecture/CONTRACTS.md) as
  the web client; only the transport adapter is Android's.
- Performance validation that depends on real GPU/thermal behavior must ultimately run on a physical
  phone; an emulator or a desktop browser is not a substitute for that final check. See
  [UI performance](../practices/UI_PERFORMANCE.md).

## Rules

- One UI, shared with web; platform difference is an adapter, never a screen fork.
- Canonical operations use bounded authenticated server APIs; no generic storage or client-authority
  fallback exists.
- A deliberately local-only product is a separate architecture, not a networked fallback.
- The bootstrap call matches the web client's; only the adapter set differs.
- Device-dependent performance is confirmed on real hardware before it is called done.
