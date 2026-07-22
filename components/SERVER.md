# Server

Authoritative for `apps/server`. A **Fastify** service whose routes are thin and whose business logic
lives in classes — and which imports the same domain contracts as the clients, never the UI.

## Layering

```text
route  →  controller  →  service  →  repository
(HTTP)     (validate)     (rules)     (persistence)
```

- A **route** parses and validates the HTTP request and delegates. It contains no business rule.
- A **controller** maps the validated request to a service call and shapes the response.
- A **service** owns the rule, exactly as in the [shared domain](../architecture/SHARED_DOMAIN.md) —
  and where the rule already exists in `@app/shared`, the server imports it rather than reimplementing
  it.
- A **repository** handles persistence behind a domain-shaped interface.

Business rules never live in a route closure. As the server grows (accounts, sessions, telemetry,
fleet management), new capability follows these same four layers rather than thickening the routes.

## What it imports — and what it must not

The server **imports `@app/shared` contracts**: the wire and content shapes it serves are the single
source shared with the clients (see [contracts](../architecture/CONTRACTS.md)), so a served payload
cannot drift from what a client expects. The server **must never import `@app/ui`** — it has no UI
layer; the dependency direction in the [overview](../architecture/OVERVIEW.md) forbids it.

## The device twin

Where the product has a physical device, the server hosts an **in-memory semantic twin** of it:
routes parse the request and delegate to a device-state class that interprets the
[semantic device contract](../architecture/CONTRACTS.md) — the same contract the firmware implements.
This gives the clients a device to talk to without hardware, and gives tests an assertable device
model. The twin renders *meaning* into state exactly as the firmware renders it into light; neither
streams frames.

## Typical endpoints

The recurring baseline: a health endpoint, content/catalog endpoints that serve the shared content
bundles, and the device-twin endpoints. Optionally, the server statically hosts the built
[web client](WEB_CLIENT.md) so a single origin serves both the API and the app.

## Security and dependencies

Server dependencies carry a real audit surface (static file serving, request parsing). Keep them
current and clear known-vulnerable lines rather than pinning to them — a resolved audit finding is
part of a clean [definition of done](../principles/WORKING_AGREEMENT.md).

## Rules

- Routes are thin; rules live in services; persistence lives behind repositories.
- The server imports shared contracts, reuses shared domain rules, and never imports the UI.
- A device twin interprets the same semantic contract the firmware does; it never streams frames.
- Keep the dependency surface audited and current.
