# PHP and Laravel applications

This document adapts the stack's architecture to a PHP application, including Laravel projects that
serve server-rendered HTML or a bounded HTTP API. It supplements the normative
[development principles](../principles/DEVELOPMENT_PRINCIPLES.md); it does not create a second set of
rules.

## Runtime and language baseline

- Use the PHP version recorded by the project (the stack baseline is PHP 8.4) and the framework
  version recorded in `composer.lock`. Do not rely on APIs from a different installed major version.
- Enable strict typing in application PHP files with `declare(strict_types=1);`.
- Declare parameter and return types, use constructor property promotion, and use TitleCase enum
  cases. Represent boundary data with named array-shape PHPDoc or dedicated value objects; do not
  pass unbounded `mixed` values across an application boundary.
- Keep application logic in PHP. Shell, configuration, and templates remain integration boundaries,
  not alternate places for domain rules.
- When a PHP server shares a wire boundary with a non-PHP client, treat it as a cross-language
  contract: define the normative shape once as described in
  [contracts](../architecture/CONTRACTS.md), then make each language implementation conform to it.

## Layering and dependency direction

Use the same dependency direction as the server blueprint, expressed through Laravel conventions:

```text
route → controller → form request/action → model or repository → infrastructure
```

- Routes declare HTTP shape and middleware only.
- Controllers are thin: they select an action, pass validated input, and choose a response. They do
  not contain authorization rules, persistence mutations, or query-specific business decisions.
- Form Requests normalize and validate untrusted input before an action runs, reject unexpected
  fields, and expose only validated values. `authorize()` must enforce the use-case boundary, not
  return `true` for sensitive operations without an explicit reason.
- Actions are single-purpose application use cases. They receive collaborators through constructors
  and return named domain results. Reusable rules belong in models or domain services, not in views.
- Eloquent models own relationships, casts, scopes, and invariants; repositories are appropriate when
  persistence needs to be swappable or the query is a domain-shaped port. Do not expose generic
  client-controlled CRUD as a shortcut.
- Infrastructure adapters implement contracts such as clocks, randomness, delivery providers, and
  external APIs. Bind them once in the composition root; domain code never reaches for a global
  service locator.

## Boundaries, identity, and data safety

Use [authorization and policy decisions](../architecture/AUTHORIZATION.md) as the authoritative
model for RBAC, ABAC, relationship scope, dynamic-policy administration, and policy-cache safety.

- Every networked composition uses authenticated principals and authorization on every read, search,
  ranking, and write that can expose or change user data. Apply owner/tenant scope inside the action or
  repository query, not only in a controller or view.
- Use Laravel's session/CSRF protections for browser flows and explicit request authentication for
  APIs. Apply CSRF and origin defenses to every state-changing browser route, including admin/RBAC
  forms, fetch/XHR mutations, assignment actions, uploads, deletes, and alternate endpoints.
  Configure cookies as `Secure`, `HttpOnly`, and an appropriate `SameSite` value in deployed
  environments. Define idle, absolute, and renewal behavior according to the authoritative
  [session-management practice](../security/SESSION_MANAGEMENT.md); namespace the cookie/store per
  application, use a production-capable shared handler where concurrency requires it, and fail
  clearly instead of falling back when the configured store is unavailable.
- Normalize localized digits, accepted separators, and locale-specific phone forms before validation;
  persist one canonical representation. Return catalog-backed validation errors in the active locale.
- Keep provider credentials in environment-backed secret management. Production logs omit OTP codes,
  full phone numbers, credentials, and provider response bodies. A local-development delivery logger
  is an explicit composition, never a production fallback or authentication bypass. Production
  adapters own a bounded transport timeout, and authentication responses containing challenge state
  use `Cache-Control: no-store`.
- Put related canonical writes in a transaction or define an idempotent recovery protocol. Lock rows
  when a check-then-write operation must be safe under concurrent requests.
- Versioned migrations own tables, indexes, ownership columns, foreign keys, and policy seeds.
  Repositories and request constructors do not mutate schema at runtime or swallow migration errors;
  startup verifies the required migration version and fails clearly when it is absent. Enable the
  database's foreign-key enforcement explicitly when the schema depends on it.
- Keep external delivery and HTTP calls outside database transactions. Use an outbox, idempotent
  recovery, or a narrow compensating write when provider success determines whether local state may
  remain.
- Apply [untrusted file handling](../security/UNTRUSTED_FILE_HANDLING.md) to Laravel uploads:
  framework validation is the request boundary, not permission to trust the client filename,
  extension, MIME claim, storage path, or later download request.
- Failure counters are canonical state: commit an OTP or similar attempt increment before returning
  the validation failure. Throwing inside the same transaction must not silently roll that increment
  back. Bound paid-provider work per principal/source and with an explicit application-wide ceiling.

## Durable background work

PHP jobs use the [Job Management contract](../architecture/BACKGROUND_JOBS.md) for handler/step
interfaces, saved results, ownership, retries, and common logging. Laravel queues or another queue
library can supply an adapter; serializing a whole use case does not establish its resumability.
Use the [shared-host trigger guidance](../architecture/BACKGROUND_JOBS.md#10-trigger-adapters-and-php-shared-hosting)
when no resident worker exists, and record the actual host request-time profile before enabling it.
The same guide owns PSR-3 diagnostic integration, lifecycle-event persistence, and the required
[process-interruption verification](../architecture/BACKGROUND_JOBS.md#13-required-verification-and-adoption).

## Determinism and tests

- Inject clocks, randomness, schedulers, and external ports. Tests use fixed or recording adapters and
  never sleep, call paid providers, or depend on wall-clock time.
- Add deterministic unit tests for each action rule and boundary. Feature tests prove middleware,
  authorization, tenant isolation, exact request validation, transaction behavior, and localized
  responses. Authentication tests also prove non-cacheable challenge responses, persisted attempt
  exhaustion, issuance bounds, and provider-failure cleanup. Use the repository's container runner
  for repeatable execution.
- Validate content and configuration at construction/startup where possible. A missing required
  production adapter fails clearly instead of silently selecting a permissive fallback.

## Tooling and delivery

- Application development runners consume the shared
  [development binding configuration](MONOREPO.md#development-server-execution-and-lan-reachability).
  Server-rendered browser flows also apply the
  [LAN browser access requirements](WEB_CLIENT.md#development-server-and-lan-reachability).
- Keep Composer dependencies locked and run dependency audits as part of the repository check. When
  the application builds frontend assets, commit the JavaScript lockfile and install with its frozen
  mode (`npm ci` or the package-manager equivalent).
- Run Laravel Pint on changed PHP files while iterating. Select relevant PHPUnit tests and the
  repository-wide check through the [verification gates](../practices/TESTING.md#change-specific-verification-gates),
  with execution governed by [pre-merge verification](../principles/WORKING_AGREEMENT.md#pre-merge-verification).
  In containerized projects, use the committed `bin/` runners rather than bypassing the project runtime.
- Keep `lang/fa` and `lang/en` (or the project's declared locale set) in exact key parity. UI strings
  live in catalogs; templates contain no user-facing literals. Use logical CSS properties and local,
  licensed fonts with recorded provenance.
- Run invasive PHP/system dependencies through the shared
  [containerized-toolchain practice](../practices/CONTAINERIZED_TOOLCHAINS.md); keep the committed
  runner as the public interface rather than duplicating its rules here.
- In server-rendered templates, emit deterministic cache-busting tokens on all static CSS and JS references
  using the deployment commit hash (`?v=<hash>`), falling back to `filemtime` in local development. Configure
  server headers (`.htaccess` or web server configuration) to serve HTML as `no-cache, must-revalidate` and
  static assets with controlled revalidation. See [deployment and HTTP caching](../practices/DEPLOYMENT_AND_CACHING.md).

## Rules

- PHP applications follow the blueprint's dependency direction through thin controllers and
  single-purpose actions.
- Strict types, explicit boundary types, injected non-determinism, and deterministic tests are
  mandatory.
- Authentication, authorization, owner/tenant scope, canonical validation, and safe writes are part
  of the first usable slice.
- Production adapters are explicit and fail clearly when missing; local fallbacks are opt-in and
  cannot bypass the normal security path.
- Server-rendered templates emit versioned static assets, and web server configuration enforces explicit HTTP cache-control policies.
