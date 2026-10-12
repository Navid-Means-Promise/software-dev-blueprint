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

### Development server and LAN reachability

The Vite config maps the shared [development binding configuration](MONOREPO.md#development-server-execution-and-lan-reachability)
to `server.host` (`0.0.0.0` or `true` for LAN access). Keep Vite's host and file-serving protections;
add only controlled development hostnames to `server.allowedHosts`, and allow only the required
origins if cross-origin asset access is needed. See
[Vite's server options](https://vite.dev/config/server-options).

For a same-origin API proxy, browser adapters use relative API URLs. Configure the proxy target as
an address reachable from the Vite process: `127.0.0.1:<api-port>` when both processes share a network
namespace, or the backend's service address when they run in separate containers. Configure hot-reload
connections to use the device-visible host and forward WebSockets through any reverse proxy.

LAN browser testing uses HTTPS with a certificate trusted by the test device to preserve
[browser-session protections](../architecture/AUTHORIZATION.md#browser-and-api-trust-boundaries)
and secure-context APIs. Plain HTTP on a LAN address lacks the localhost exception in
[Secure Contexts](https://www.w3.org/TR/secure-contexts/#is-origin-trustworthy).
[WebOTP](https://wicg.github.io/web-otp/) testing also requires a device-resolvable domain hostname
matching the SMS origin binding.

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
