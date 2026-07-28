# Web client

Authoritative for `apps/web`. It is a thin host: a bundler entry point, the browser adapters, and
nothing else. All rules live in `@app/shared`; all screens live in `@app/ui`.

## What it is

A **Vite** application whose entire job is to construct the browser's adapters and call the
[shared bootstrap](SHARED_UI.md). The web app owns:

- the HTML entry document and the Vite config (built from the shared
  [bundler-config factory](MONOREPO.md), overriding only web specifics);
- the **browser adapters** for the domain ports — most importantly an **IndexedDB** implementation of
  the storage/repository port;
- the mount call.

That is the whole client. A screen, a rule, or a piece of state that lives here rather than in
`@app/ui` / `@app/shared` is misplaced.

## Storage adapter

The web `IndexedDbStorageAdapter` implements `StorageProvider` and maps the domain-shaped repository
interface onto IndexedDB. The domain never learns which store it uses — swapping stores would touch
only this adapter. See [ports & adapters](../architecture/PORTS_AND_ADAPTERS.md).

## Optional device link

Where the product includes a physical device, the web client can inject a device adapter that speaks
the [semantic device contract](../architecture/CONTRACTS.md) over HTTP to the device or its server
twin. With no device configured, the domain uses the no-op default and everything works offline;
the device port simply stays inert. This is capability-by-default, not a code fork.

## Hosting

The built web app is static assets. It can be served by any static host, and the
[server](SERVER.md) can optionally host it directly so one origin serves both the API and the app.

## Rules

- The web app holds adapters and a bootstrap call — no screens, no rules, no domain state.
- Its Vite config extends the shared factory and overrides only web-specific values.
- The storage adapter maps the domain repository interface onto IndexedDB and nothing leaks upward.
- A missing device is an inert default, never a branch in shared code.
