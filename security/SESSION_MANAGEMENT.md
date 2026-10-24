# Session management

Authoritative for browser-session lifetime, identifier rotation, principal freshness, and backing
store isolation. Authentication and authorization policy remain authoritative in
[authorization and policy decisions](../architecture/AUTHORIZATION.md); this document owns what
happens to an authenticated session between those policy decisions.

## Server-enforced lifetime

Define a risk-appropriate idle timeout and absolute timeout. A rolling cookie may extend recent
activity only until the absolute limit. An optional renewal interval rotates a long-lived session
identifier without extending the absolute authenticated lifetime.

The server derives creation, activity, renewal, and expiry from an injected clock and invalidates
expired state server-side. Deleting or expiring a browser cookie alone does not terminate a stolen
session. Define which authenticated requests count as activity; passive polling or background
refreshes do not keep a session alive unless that behavior is an explicit product requirement.

## Identifier and authority transitions

Rotate the session identifier at authentication, privilege or identity transition, and the declared
renewal boundary. Logout and expiry invalidate the server record and clear the cookie; do not merely
remove a principal field while preserving the same authenticated identifier. Rotate CSRF state
across authentication boundaries.

Store a stable principal identifier and session revision rather than a durable snapshot of roles or
account status. Resolve current disablement, revocation, and policy state before authorizing a
request. Products that support global logout, credential recovery, or high-risk privilege changes
increment or revoke the relevant session revision so already-issued sessions cannot retain obsolete
authority.

For credential-derived automation sessions, the additional binding and revocation contract is in
[hosted browser access](HOSTED_BROWSER_ACCESS.md#credential-and-revocation).

## Cookie and backing-store isolation

The cookie protections, CSRF checks, and origin checks are defined by the
[browser trust boundary](../architecture/AUTHORIZATION.md#browser-and-api-trust-boundaries).
Additionally, namespace the cookie and backing store per application, and scope cookie path and
domain no wider than the product requires.

Use an isolated server-side store whose retention matches the lifecycle, and fail startup when the
required store cannot be initialized. Multi-worker and multi-instance compositions use a shared
store or an explicitly proven affinity/concurrency model; process-local or host-default storage must
not silently change authentication behavior between environments. Cleanup is bounded and observable
so expired records do not accumulate indefinitely or disappear before their declared lifetime.

## Required tests

Deterministic tests use a fixed clock and prove:

- the exact idle, absolute, and renewal boundaries;
- rolling activity without extension beyond the absolute lifetime;
- identifier and CSRF rotation at every declared authority transition;
- server-side invalidation on logout and expiry;
- disabled, revoked, and stale-revision principal rejection;
- cookie and store isolation between applications; and
- concurrent requests at a renewal or expiry boundary.

## Rules

- The server, not the cookie or client clock, enforces idle and absolute lifetime.
- Rotation never extends the absolute authenticated lifetime.
- Authentication, logout, and privilege transitions rotate or invalidate both session and CSRF
  state as declared.
- Current principal and policy state outrank authority cached when the session was created.
- Cookie names, scopes, and backing stores are isolated per application and behave consistently
  across every deployed worker.

## Standards basis

- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
  — server-enforced idle and absolute expiry, identifier renewal, and invalidation
