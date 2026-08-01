# Web client

Authoritative for `apps/web`. It is a thin, untrusted host: a bundler entry point, the browser
adapters, and nothing else. Reusable pure rules/contracts live in `@app/shared`, authoritative use
cases execute on the server, and all screens live in `@app/ui`.

## What it is

A **Vite** application whose entire job is to construct the browser's adapters and call the
[shared bootstrap](SHARED_UI.md). The web app owns:

- the HTML entry document and the Vite config (built from the shared
  [bundler-config factory](MONOREPO.md), overriding only web specifics);
- authenticated HTTP adapters for bounded application use cases, browser-capability adapters, and
  bounded transient cache/session state;
- the mount call.

That is the whole client. A screen, reusable rule, or canonical record that lives here rather than in
its owning UI/shared/server module is misplaced. Client validation may improve feedback, but the
server repeats complete validation and authorization before any effect.

## Persistence adapter

For a networked application, web adapters call bounded server-owned use cases rather than exposing a
generic repository/storage transport. IndexedDB may hold bounded response caches, device-local
preferences, or a one-time legacy migration source, but it is not another source of truth and does
not queue canonical writes. A local-authoritative product is outside this server-oriented blueprint.
The domain never learns which transport/store it uses. See
[ports & adapters](../architecture/PORTS_AND_ADAPTERS.md).

## Optional device link

Where the product includes a physical device, the web client can inject a device adapter that speaks
the [semantic device contract](../architecture/CONTRACTS.md) over HTTP to the device or its server
twin. With no device configured, the domain uses the no-op device adapter and the rest of the
application remains usable through its normal server connection; only the device capability stays
inert. This is optional capability handling, not a code fork or an offline-authority mode.

## Hosting

The built web app is static assets. It can be served by any static host, and the
[server](SERVER.md) can optionally host it directly so one origin serves both the API and the app.

## Rules

- The web app holds adapters and a bootstrap call — no screens, no rules, no domain state.
- Its Vite config extends the shared factory and overrides only web-specific values.
- Canonical operations use bounded authenticated server APIs; no generic storage or client-authority
  fallback exists.
- A local-authoritative product is outside this server-oriented blueprint, not a networked fallback.
- A missing device is an inert default, never a branch in shared code.
