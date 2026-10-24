# Hosted browser access for development agents

Authoritative for an optional, owner-authorized credential that lets development agents and browser
automation sign into a deployed application without coordinating interactive OTP delivery. This
capability supports diagnosis and UI acceptance on the actual hosting service, including restricted
shared hosting. It authenticates an existing account through the application; all subsequent reads
and writes obey [authorization policy](../architecture/AUTHORIZATION.md).

## Enablement and authority

Enable this login method only when the owner requests hosted access. Keep it disabled until a valid
credential, expiry, and target account are configured. Use the least privilege the task needs; an
existing administrator may be the explicit target when administrative UI access is required.

Resolve the target from server-owned configuration and current persistence. Authentication never
creates, promotes, reactivates, or changes the account. Reject missing or disabled accounts and
missing required capabilities. The caller cannot select an account, role, tenant, or policy result.
This configured credential binding is distinct from a fixed principal returned without identity
verification. Record the authentication method so any product-specific step-up requirement can
still distinguish it from an interactive login.

Credential possession permits sign-in within that binding. It does not authorize arbitrary live
operations: the owner's task and the application's policies still determine what may be inspected
or changed. Keep the ordinary user login flow independently usable.

## Credential and revocation

Generate a dedicated opaque key with a cryptographically secure source and at least 256 bits of
entropy. Store the raw value only in an authorized local secret source; store a one-way digest,
target binding, expiry, and credential revision on the server outside the public document root.
A SHA-256 digest is suitable for this randomly generated key; this is not a password-storage rule.
Compare the supplied key's digest in constant time after bounded format validation. Never reuse a
deployment-hook token, provider key, or user password for this purpose.

Choose and document a finite credential lifetime separately from browser-session lifetime. Provide
rotation and revocation commands that do not print the key. Revocation must explicitly disable the
remote credential: deleting a local value or sending an omitted/blank deployment setting is
insufficient when omission preserves the old remote value. Use the allowlisted configuration update
and explicit-clear semantics in [deployment configuration](../practices/DEPLOYMENT_AND_CACHING.md#deployment-managed-runtime-configuration).
Verify the resulting remote state without returning secret values.

Bind issued sessions to the credential revision and target. Before authorizing each request, reject
an expired, disabled, rotated, or rebound credential and resolve current account status and policy.
Revocation therefore invalidates existing credential-derived sessions on their next request, as
well as refusing new sign-ins. An account-wide session revision remains effective too.

## Credential-to-session exchange

Expose one bounded, dedicated `POST` login endpoint. Accept the key in one named request header;
reject credential transport through URLs, query strings, bodies, or cookies. Normal application
routes do not interpret that header as identity. Apply exact request decoding, generic failures,
and proportionate resource bounds from the [authentication boundary](../architecture/AUTHORIZATION.md#browser-and-api-trust-boundaries).

Require HTTPS as established by the server transport or an explicitly configured trusted proxy.
An arbitrary forwarded-protocol header cannot prove TLS. Any local HTTP exception is explicit,
loopback-only, and unavailable in deployed composition. The endpoint applies normal origin and
login-CSRF defenses: automation first obtains guest CSRF state through the application's supported
flow, then sends that token and the credential in the same browser context.

After verification, use the normal session manager and persist only the principal identifier and
grant metadata needed for lifecycle checks. Identifier/CSRF rotation, cookie protections, store
isolation, logout, and clock-driven lifetime follow [session management](SESSION_MANAGEMENT.md).
Define idle and absolute limits for these privileged sessions; neither may outlive credential
expiry. For example, a project may choose 15 minutes idle and one hour absolute; these values are
project policy. Passive polling must follow the declared activity policy.

Return a minimal success response with `Cache-Control: no-store`, no credential reflection, and no
redirect. Audit sign-in and credential lifecycle operations with bounded metadata identifying the
account, authentication method, credential revision, outcome, and server time. Do not log keys,
digests, CSRF tokens, or session identifiers.

## Browser tooling and evidence

A project-owned helper loads the secret directly into the request operation under the
[agent credential-access boundary](../practices/AI_AGENT_CONTEXT.md#context-discovery-vs-model-context-ingestion).
Do not put its value in model context, command arguments, chat, or committed configuration.

1. Create a fresh browser context and verify an exact configured HTTPS origin. Send the credential
   header only on the login request to that origin, with redirect following disabled. Global browser
   headers can leak credentials to assets, other origins, redirects, or later navigation.
2. Verify the authenticated identity through the application's session endpoint, then navigate the
   requested UI with the session cookie. Wait for observable page/control readiness, including
   transitions, before asserting or interacting.
3. Bound the operational scope. A read-only diagnostic run prevents incidental mutations, automatic
   job wake-ups, notifications, paid inference, or third-party calls that the visited screens would
   otherwise cause. Deliberate checks with external effects use the separately authorized
   [live-check runner](../practices/TESTING.md#live-provider-smoke-checks).
4. Log out in cleanup even after an assertion fails, close the context, and report cleanup failures.
   Discard cookies and authenticated storage rather than exporting a reusable browser profile.

Use an allowlisted evidence projection: deployed revision, origin, route/status, identity-match
result, UI assertions, cookie protection flags, and logout result. Browser/network exceptions can
include request headers; sanitize them at the helper boundary before emitting diagnostics. Disable
automatic login tracing/HAR capture. Screenshots, DOM extracts, or deeper diagnostics needed for the
task use bounded private storage and redaction/retention appropriate to their data; they must not
capture credentials or reusable session material.

## Verification and adoption

Implementing projects add deterministic tests with injected time and credential sources, plus
HTTP/browser tests for the real composition:

- Missing, malformed, incorrect, expired, or disabled keys never establish a session. Unknown or
  disabled targets, removed required capabilities, and caller-supplied identity are rejected.
- Rotation, revocation, rebinding, and account-session revision changes invalidate already-issued
  grants. Exact credential, idle, and absolute expiry boundaries follow the common session tests.
- Login preserves normal session/CSRF rotation and cookie protections; ordinary login, logout,
  authorization, and tenant isolation remain functional. Key headers on unrelated routes grant
  nothing. Wrong methods, unexpected inputs, failed origin/CSRF checks, plaintext transport, and
  forged forwarded-protocol headers fail closed.
- Transport/helper tests prove exact-origin targeting, no redirect forwarding or global key header,
  no secret in errors/artifacts, cleanup on failure, and explicit remote revocation semantics.

Test production composition in a process actually started with production settings. Editing an
environment file cannot prove this if inherited process variables take precedence. Local-only
exceptions must not mask the deployed transport or authentication rules.

At adoption or after relevant runtime/deployment changes, run the bounded browser check against the
actual host and record its deployed revision and observed results. Verify a successful privileged
page and interaction as well as unauthenticated rejection: a `401`/`403` alone cannot prove that
authenticated dependencies work on the hosting runtime. This complements
[release healthchecks](../practices/DEPLOYMENT_AND_CACHING.md#post-deployment-verification-smoke-healthcheck);
it does not require an unrelated live run for every documentation change. A skipped or unavailable
hosted check is reported as such. Suite execution follows the
[pre-merge policy](../principles/WORKING_AGREEMENT.md#pre-merge-verification).

## Standards basis

- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
  — protected credential transport, generic failures, and authentication auditing.
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html#possible-csrf-vulnerabilities-in-login-forms)
  — login requests also need CSRF defenses.
