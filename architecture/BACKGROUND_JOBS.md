# Durable background jobs

Authoritative for resumable application jobs, including PHP shared hosting without a resident
worker. Follow [ports and adapters](PORTS_AND_ADAPTERS.md), [authorization](AUTHORIZATION.md),
[provider operations](AI_PROVIDER_OPERATIONS.md), and [verification](../practices/TESTING.md).

## Ownership and execution

The application enqueues typed, server-owned work. A generic queue owns deduplication, due times,
leases, attempts and recovery; injected handlers own the actual use cases. Persist jobs before
dispatch. Never run arbitrary client-supplied callables, URLs, commands or serialized objects.
An interchangeable algorithm uses a strategy port; selecting a strategy does not migrate or erase
another strategy's state. Each strategy can reconstruct derived data from authoritative sources.

Keep a durable desired input revision. Enqueueing the same work is idempotent; newer inputs must
survive completion of an older leased attempt. Claim and finish with lease tokens and revision
checks in short transactions. Model/network work happens outside locks. Expired leases are
recoverable; late results from an older owner cannot overwrite newer progress. Record every failed
attempt separately from the job's eventual status. Source erasure cancels its work and prevents
in-flight jobs from recreating derived data.

A runner processes a bounded number of steps within a monotonic time budget. Reserve enough time
for a handler before starting it. Handlers checkpoint partial progress and are idempotent under
at-least-once execution. Retry transient failures with capped backoff and preserve cumulative
failure counts; validation, permission or configuration problems remain visible rather than
causing an immediate loop. Account quotas, provider cooldowns and application spending limits
remain authoritative. Successful transport is not semantic acceptance. AI handlers still require
their own [functional qualification](../practices/AI_EVALUATION.md).

## Triggers on shared hosts

CLI, authenticated cron HTTP and traffic-driven execution call the same runner. Persist the selected
mode, interval, run budget and batch size in authorized administration. Provide a disable mode.
Cron secrets are random, rotatable, stored as hashes and compared in constant time. Prefer an
authorization header; URL-secret support for limited hosting schedulers requires private treatment
of URLs/access logs, no redirects/referrers, no-store responses and an explicit operational note.
HTTP responses expose only content-free runner receipts, never job payloads or source text.

Traffic is an opportunity to run due work, not a reliable clock. Use a shared lease and due time
to prevent visitor bursts from launching competing workers. Finish the ordinary response before
slow work where the server supports it, release session locks, and keep execution bounded. A PHP
shutdown callback alone does not mean the response has finished. When early response completion is
unavailable, use a separate same-origin browser request with CSRF protection; do not put AI work in
ordinary rendering or an unbounded shutdown callback. Cached pages, closed browsers, killed PHP
requests and periods with no traffic provide no execution guarantee. Cron is preferable for steady
progress. Configure budgets to fit the actual host's request/runtime limits; leases provide recovery,
not permission to exceed those limits.

## Administration and verification

Show the selected strategy, enabled processors, backlog, progress, retries, failures, stale leases,
last run, next due work and blocked configuration. Keep published output, processing progress and
provider-request statistics distinct. Trigger changes and retries require server-side authorization
and CSRF protection. Reconciliation scans are bounded and resume across large backlogs.

Verify competing claims, expired leases, late completion, new input during execution, crashes between
checkpoints/publication, source deletion, backoff, disabled modes, trigger authentication, CSRF,
bounded dispatch, and strategy switching. Use injected clocks/providers for deterministic tests;
real-provider tests remain separately authorized and bounded.
