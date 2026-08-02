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
model. The twin renders _meaning_ into state exactly as the firmware renders it into light; neither
streams frames.

## Typical endpoints

The recurring baseline: a health endpoint, content/catalog endpoints that serve the shared content
bundles, and the device-twin endpoints. Optionally, the server statically hosts the built
[web client](WEB_CLIENT.md) so a single origin serves both the API and the app.

## Canonical persistence and background work

For networked web/mobile products, the server owns durable application data.
Repositories expose domain-shaped operations and keep database schemas private; shared contracts
still define record identity, authorization scope, revisions, and wire shapes. Browser/native stores
are caches or migration inputs, never a second writable truth.

Remote AI/provider work and durable derived indexes also belong here. Search documents and vector
caches are projections that can be rebuilt from canonical records. Background workers perform
expensive document embedding, reindexing, retention, and repair without holding a user request open;
the request path exposes a deterministic degraded mode while work is pending.

Process-local repositories and fixed-principal substitutes are allowed only when explicitly
constructed inside isolated unit or component tests. They are not selectable server modes. Every
listening environment—including local development, E2E, demos, staging, and an MVP—uses the
authenticated multi-user boundary, tenant-scoped queries, and the canonical persistence adapter
with defined transactions, revision/conflict handling, and restart recovery. It does not expose
generic storage CRUD to an untrusted client; routes represent bounded application use cases.

In server-owned relational schemas, a table normally names its own primary key `id`; foreign keys
name the referenced role, such as `otp_challenge_id`. Domain and wire identifiers remain explicit
(`otpChallengeId`) even when the private row key is `otp_challenges.id`. Schema changes use
data-preserving, restart-safe migrations, with tests that open the previous schema and prove both its
records and constraints survive.

## Identity, authority, and tenant isolation

The server derives the principal from an authenticated session or token and derives ownership,
tenant scope, server time, and policy limits itself. Client- or model-supplied versions of those
values are ignored or rejected. Every repository query—including full-text/vector candidate
selection—applies the principal's permitted partition before reading, ranking, or mutating records.

Use a maintained authentication implementation and document credential issuance, expiry, rotation,
revocation, and recovery. Password authentication uses a current password-hashing construction and
never stores recoverable passwords. Session/token secrets remain server-side. Browser cookie
sessions use `Secure`, `HttpOnly`, and suitable `SameSite` settings plus CSRF and origin defenses.
Validate exact request shapes at the HTTP boundary, authorize again at the use-case boundary, and
execute related canonical mutations in one transaction or through an explicitly idempotent recovery
protocol. Apply per-principal resource and rate limits before expensive provider work.

Local development may use seeded credentials or a development identity provider only when requests
still traverse the normal authentication, authorization, and tenant-isolation paths. A trusted
identity header, global fixed user, or authentication-disable switch must not exist in a listening
composition.

One-time client-data migration is a separate authenticated use case: strictly decode its bounded
input, replace claimed ownership with the authenticated principal, preserve newer server state, and
commit atomically and idempotently. It never leaves a permanent generic persistence endpoint behind.

These are the server-specific consequences of
[development principles §7](../principles/DEVELOPMENT_PRINCIPLES.md#7-trust-boundaries-are-not-mvp-compromises),
not optional hardening for after launch.

## Security and dependencies

Server dependencies carry a real audit surface (static file serving, request parsing). Keep them
current and clear known-vulnerable lines rather than pinning to them — a resolved audit finding is
part of a clean [definition of done](../principles/WORKING_AGREEMENT.md).

Credentials enter deployment through environment-backed secret management; tracked configuration,
documentation, fixtures, and examples contain placeholders only. Rotate any credential exposed in
source, logs, issue trackers, chat, test output, or another unintended channel.

## Rules

- Routes are thin; rules live in services; persistence lives behind repositories.
- The server imports shared contracts, reuses shared domain rules, and never imports the UI.
- A device twin interprets the same semantic contract the firmware does; it never streams frames.
- Keep the dependency surface audited and current.
- Own canonical durable records and expensive background/index work for networked thin clients.
- Expose bounded authenticated use cases, never generic client-controlled persistence.
- Enforce authorization and owner/tenant partitions before every read, search, ranking, and write.
- A local-authoritative product is outside this server-oriented blueprint, not a networked MVP
  shortcut.
