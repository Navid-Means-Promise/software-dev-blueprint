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

## Layering and dependency direction

Use the same dependency direction as the server blueprint, expressed through Laravel conventions:

```text
route → controller → form request/action → model or repository → infrastructure
```

- Routes declare HTTP shape and middleware only.
- Controllers are thin: they select an action, pass validated input, and choose a response. They do
  not contain authorization rules, persistence mutations, or query-specific business decisions.
- Form Requests normalize and validate untrusted input before an action runs. `authorize()` must
  enforce the use-case boundary, not return `true` for sensitive operations without an explicit
  reason.
- Actions are single-purpose application use cases. They receive collaborators through constructors
  and return named domain results. Reusable rules belong in models or domain services, not in views.
- Eloquent models own relationships, casts, scopes, and invariants; repositories are appropriate when
  persistence needs to be swappable or the query is a domain-shaped port. Do not expose generic
  client-controlled CRUD as a shortcut.
- Infrastructure adapters implement contracts such as clocks, randomness, delivery providers, and
  external APIs. Bind them once in the composition root; domain code never reaches for a global
  service locator.

## Boundaries, identity, and data safety

- Every networked composition uses authenticated principals and authorization on every read, search,
  ranking, and write that can expose or change user data. Apply owner/tenant scope inside the action or
  repository query, not only in a controller or view.
- Use Laravel's session/CSRF protections for browser flows and explicit request authentication for
  APIs. Configure cookies as `Secure`, `HttpOnly`, and an appropriate `SameSite` value in deployed
  environments.
- Normalize localized digits, accepted separators, and locale-specific phone forms before validation;
  persist one canonical representation. Return catalog-backed validation errors in the active locale.
- Keep provider credentials in environment-backed secret management. Production logs omit OTP codes,
  full phone numbers, credentials, and provider response bodies. A local-development delivery logger
  is an explicit composition, never a production fallback or authentication bypass.
- Put related canonical writes in a transaction or define an idempotent recovery protocol. Lock rows
  when a check-then-write operation must be safe under concurrent requests.

## Determinism and tests

- Inject clocks, randomness, schedulers, and external ports. Tests use fixed or recording adapters and
  never sleep, call paid providers, or depend on wall-clock time.
- Add deterministic unit tests for each action rule and boundary. Feature tests prove middleware,
  authorization, tenant isolation, exact request validation, transaction behavior, and localized
  responses. Use the repository's container runner for repeatable execution.
- Validate content and configuration at construction/startup where possible. A missing required
  production adapter fails clearly instead of silently selecting a permissive fallback.

## Tooling and delivery

- Keep Composer dependencies locked and run dependency audits as part of the repository check.
- Run Laravel Pint on changed PHP files and the narrowest relevant PHPUnit tests while iterating; run
  the repository-wide check before handoff. In containerized projects, use the committed `bin/`
  runners rather than bypassing the project runtime.
- Keep `lang/fa` and `lang/en` (or the project's declared locale set) in exact key parity. UI strings
  live in catalogs; templates contain no user-facing literals. Use logical CSS properties and local,
  licensed fonts with recorded provenance.

## Rules

- PHP applications follow the blueprint's dependency direction through thin controllers and
  single-purpose actions.
- Strict types, explicit boundary types, injected non-determinism, and deterministic tests are
  mandatory.
- Authentication, authorization, owner/tenant scope, canonical validation, and safe writes are part
  of the first usable slice.
- Production adapters are explicit and fail clearly when missing; local fallbacks are opt-in and
  cannot bypass the normal security path.
