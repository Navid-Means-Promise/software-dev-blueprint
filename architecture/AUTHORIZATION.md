# Authorization and policy decisions

Authoritative for server-side authorization, dynamic RBAC, attribute-based access control (ABAC),
and relationship-aware resource policies. It supplements the trust-boundary rules in
[development principles](../principles/DEVELOPMENT_PRINCIPLES.md) §7; it does not create a client-side
authority model.

## The decision contract

Every protected use case evaluates one typed policy decision:

```text
authorize(principal, action, resource, context) → Allow | Deny
```

The policy input is server-owned:

- **Principal** is the authenticated account and its current roles, capabilities, tenant, and status.
- **Action** is a bounded application intent such as `case.read`, `case.assign`, or `message.send`.
- **Resource** is the domain record, collection scope, or resource relationship being accessed.
- **Context** contains server-derived facts such as ownership, assignment, time window, request
  origin, rate state, and revision. Client-supplied roles, permissions, owner IDs, tenant IDs,
  server timestamps, or policy results are ignored or rejected.

Policy evaluation denies by default. An unknown principal, action, resource, attribute, or policy
version is not an implicit allow. Missing authority-bearing wiring fails composition/startup; it does
not select a permissive local fallback.

## RBAC, ABAC, and relationship scope

Use the smallest model that expresses the rule and combine them deliberately:

- **RBAC** maps named roles to stable capability permissions. A permission names an application
  capability, not a screen or database table. Roles bundle capabilities for people with the same
  responsibility.
- **ABAC** evaluates typed attributes and predicates such as tenant equality, account state,
  resource classification, time window, or verified ownership. Attributes are server-derived or
  verified through a trusted provider.
- **Relationship-aware policy** handles facts such as `principal owns case`, `principal is assigned
  to case`, or `principal supervises tenant`. It is a resource predicate, not a new client-side role.

RBAC supplies coarse capability; ABAC and relationship predicates constrain that capability to the
resource and context. Do not create a new role for every resource state or tenant exception. Do not
use a role check as a substitute for an ownership predicate.

Permission slugs, role identifiers, resource actions, and attribute names are contracts. Define each
once, validate them at the boundary, and reject unknown values rather than silently dropping them.

## Where policy lives

The policy service is an application/domain collaborator with an explicit interface. It receives the
authenticated principal and a domain-shaped resource or resource query; it does not read session
globals, resolve a container, inspect raw request arrays, or construct database queries from client
input.

Authorization is enforced twice where the use case has both a request and a persistence boundary:

1. the HTTP/application boundary authenticates the principal, decodes the exact request shape, and
   checks the use-case capability;
2. the action or repository applies the owner/tenant/relationship predicate inside every read,
   search, ranking, attachment/download, and write path.

Collection reads must carry the policy scope into the query. Fetching all records and filtering them
afterward is not an authorization boundary. Every alternate representation of a resource—HTML page,
JSON endpoint, export, attachment, background job, and internal callback—uses the same policy.

Clients may hide controls based on their last-known policy snapshot, but that is presentation only.
The server re-evaluates authority for every protected request and never trusts a client-visible role
or button.

## Deliberately public entry points

Public use cases are explicitly registered and bounded; absence of a session does not silently make
a protected action public. Their contract defines accepted input, permitted effect, response, and
resource limits. They do not invent a fixed authenticated principal or borrow the visitor's cookie
privileges. Protected data and management operations retain the decision contract above.

A [job-manager wake-up signal](BACKGROUND_JOBS.md#public-wake-up-contract) can be public because it
supplies an execution opportunity for already-authorized server-owned work. Its precise no-control,
empty-response, exclusion, and budget rules are owned there. The manager executes actual jobs under
their current domain/service authority; the anonymous signal confers no subject or tenant authority.
Session-bound CSRF rules apply when a route uses session privileges; they do not require adding a
login/session solely to host this public signal. Resource policy is proportionate to the actual
contract; a per-caller rate limiter is not universally mandatory for a wake-up no-op path.

## Browser and API trust boundaries

Session-cookie browser flows use `Secure`, `HttpOnly`, and suitable `SameSite` cookies plus CSRF and
origin defenses for every state-changing request. This includes administrative and RBAC forms,
fetch/XHR mutations, assignment actions, deletes, uploads, and alternate action endpoints—not only
the primary user form. Server-enforced idle/absolute expiry, identifier rotation, store isolation,
and invalidation follow [session management](../security/SESSION_MANAGEMENT.md).

Token/API flows authenticate explicitly and apply their CORS, origin, replay, and rate/resource
limits at the API boundary. Both flows use an exact decoder: malformed JSON, unexpected fields,
wrong scalar/array types, oversized values, and unknown identifiers are rejected before an action
runs.

Error responses expose stable, catalog-backed client messages. Storage, SQL, provider, stack, and
policy-internal details stay in restricted diagnostics with a correlation identifier.

## Dynamic policy administration

Changing roles, permissions, assignments, or policy definitions is itself an authority-bearing use
case. It must:

- require a separately authorized administrative capability;
- validate every referenced role, permission, attribute, and scope before mutation;
- commit role metadata, role-permission links, and user-role links atomically;
- prevent self-escalation and preserve at least one independently usable administrator, unless an
  explicit recovery workflow is being executed;
- protect bootstrap/system roles and permissions from accidental deletion or loss of the capability
  needed to administer them;
- record who changed what, the previous and resulting policy revision, and the reason where the
  product requires auditability; and
- invalidate or version policy caches before a changed privilege can be used.

The first administrator is provisioned through a documented, authenticated deployment or recovery
workflow. A fresh install must not depend on an unreachable admin page, a fixed principal, or a
runtime authentication bypass.

## Persistence, migrations, and cache safety

Policy tables, ownership columns, indexes, and constraints are owned by versioned, restart-safe
migrations. Repositories and request constructors do not create, alter, seed, or silently repair the
schema at runtime. Startup verifies the applied migration version and fails clearly when required
schema is absent.

SQLite or other embedded databases must explicitly enable the foreign-key and transaction semantics
the schema relies on. A declared cascade is not evidence that a runtime adapter enforces it.

Policy changes and their dependent links use one transaction or an explicitly idempotent recovery
protocol. Check-then-write assignment paths define concurrency behavior. A process-local policy cache
has a bounded lifetime, an explicit invalidation/version mechanism, and test isolation; stale grants
must not survive revocation.

## Required tests

For every protected use case, deterministic tests prove:

- unauthenticated, unknown-principal, and disabled-principal rejection;
- role capability plus ABAC/relationship scope, including cross-tenant and cross-assignee isolation;
- equivalent authorization for HTML, JSON, attachment, export, and background paths;
- exact request decoding, unknown-field rejection, bounds, and stable error responses;
- browser CSRF and origin defenses for every state-changing route;
- policy revocation/cache invalidation and concurrent assignment behavior;
- atomic rollback when role metadata or link updates fail;
- last-administrator, protected-system-role, and bootstrap/recovery invariants; and
- migration upgrade, restart, foreign-key, and seed idempotency from the previous schema.

## Rules

- Authorization is a server-side policy decision over an authenticated principal, action, resource,
  and server-derived context.
- RBAC grants coarse capability; ABAC and relationship predicates constrain it to the resource and
  context.
- Deny protected use cases by default, authorize every protected representation/data path, explicitly
  bound public contracts, and never trust client authority claims.
- Dynamic policy changes are authenticated, atomic, audited where required, cache-safe, and protected
  against self-escalation and loss of the last administrator.
- Migrations own policy schema; runtime repositories do not mutate or silently repair it.
