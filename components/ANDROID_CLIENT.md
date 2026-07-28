# Android client

Authoritative for `apps/mobile`. Like the [web client](WEB_CLIENT.md), it is a thin host: the same
shared UI in a native wrapper, with Android's adapters injected. No screen is written twice.

## What it is

A **Capacitor** application. Capacitor wraps the built web bundle in a native Android shell and
exposes native capabilities through a bridge. The mobile app owns:

- the Capacitor project (`android/`, `capacitor.config`) and its Vite build of the shared UI;
- the **native adapters** for the domain ports — most importantly a **Preferences**-backed
  implementation of the storage/repository port;
- the mount call, identical in shape to the web client's.

The screens are `@app/ui`; the rules are `@app/shared`. The mobile app is the adapter set plus one
[bootstrap](SHARED_UI.md) call — the *entire* platform-specific surface.

## Why Capacitor rather than a separate native UI

The stack's non-negotiable is one UI implementation. A native-Android UI would fork every screen and
break that rule. Capacitor keeps the WebView rendering the exact same Lit components the web client
renders, while still allowing native capabilities through ports where they genuinely differ. The
seam is the adapter, never the screen.

## Storage adapter

The Android `PreferencesStorageAdapter` implements `StorageProvider` and maps the same domain-shaped
repository interface the web client maps onto IndexedDB. Two adapters, one interface, one domain that
knows neither. See [ports & adapters](../architecture/PORTS_AND_ADAPTERS.md).

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
- The storage adapter maps the domain repository interface onto Preferences.
- The bootstrap call matches the web client's; only the adapter set differs.
- Device-dependent performance is confirmed on real hardware before it is called done.
