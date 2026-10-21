# Job management and resumable execution

Authoritative for durable application jobs, their resumable steps, execution attempts, logs, and
administration. These requirements apply whenever work must survive its initiating request or
process, including unreliable networks and PHP shared hosting without a resident worker.
A framework queue may implement the transport; it does not replace the checkpoint and recovery
contract here. This is a specification, not a claim that an existing project already implements it.

Follow [ports and adapters](PORTS_AND_ADAPTERS.md), [dependency injection](DEPENDENCY_INJECTION.md),
[authorization](AUTHORIZATION.md), and the [contract source rule](CONTRACTS.md#the-single-source-rule).
AI-specific admission, quotas, request accounting, and qualification remain owned by
[provider operations](AI_PROVIDER_OPERATIONS.md) and [AI evaluation](../practices/AI_EVALUATION.md).

## 1. Vocabulary and guarantees

Use **Job Management** for the subsystem and `JobManager` for its application-facing coordinator.
Use these names consistently rather than introducing a parallel `TaskManager` with the same duties:

| Term | Meaning |
| --- | --- |
| Job | One durably requested outcome for an authorized subject and a desired input revision |
| JobStep | One named part of that job with a saved result; a large step can advance in bounded batches |
| Checkpoint | An accepted result or cursor, with the exact inputs and contract that make it reusable |
| JobAttempt | One claim of a job by a runner; it can execute a bounded number of steps |
| StepAttempt | One execution of a step or its next batch; retries have separate receipts |
| Trigger | An opportunity to ask the runner to process due work; it does not own that work |
| Lease | Temporary ownership with an expiry and a token that guards writes |

In ordinary UI prose, “task” may describe work. Persisted types, APIs, and class names use `Job` and
`JobStep`; do not create synonyms for those same entities.

Assume **at-least-once execution**: a process can stop after doing work but before acknowledging it.
A valid saved step is reused; only unfinished or invalidated work repeats. This is resumability,
not a promise of exactly one external request or the ability to continue a half-generated LLM reply.
An external service may finish a request whose response the application never receives. Symfony's
[idempotent-handler guidance](https://symfony.com/doc/current/messenger.html#writing-idempotent-handlers)
explains this delivery boundary; a queue alone cannot prevent repeated effects.

Traffic-driven execution provides eventual progress only while suitable requests continue arriving,
dependencies recover, and work remains within its declared resource/retry policy. No trigger can
promise completion during an indefinite outage or an absence of execution opportunities. A runner
must report pending, blocked, failed, or uncertain work honestly rather than manufacture success.

## 2. Module boundary and component names

The reusable core owns lifecycle transitions, scheduling policy, attempt identity, checkpoint
acceptance, recovery, and event contracts. It imports no application domain, HTTP framework, provider
SDK, global environment, or UI. Host adapters own persistence, trigger delivery, secrets, and rendering.
Use `JobManagement` for the module/namespace, qualified by the implementing project's vendor/domain.
Keep it within the existing domain/application boundaries; extract a Composer package or shared
library only when actual reuse justifies it. There is no parallel subsystem named `TaskManagement`.

Use the existing State Machine, Mediator, Repository, Strategy, and Ports & Adapters patterns from
[the pattern catalog](../principles/DESIGN_PATTERNS.md); no new inheritance framework is needed.
Prefer these language-neutral names and narrow roles:

| Component | Kind and responsibility |
| --- | --- |
| `JobManager` | Application service for enqueue, inspect, cancel, and authorized retry; delegates execution to the runner |
| `JobRunner` | Coordinates admission, recovery, claim, step execution, and checkpoint/attempt commit within one run budget |
| `JobHandler` | Interface implemented by each job type; supplies its versioned definition and composed domain steps |
| `JobStep` | Interface implemented by each executable step; receives checked input and execution context and returns a `StepResult` |
| `JobDefinition` | Immutable value describing type, definition version, payload contract, ordered stable step keys, and step policies |
| `JobExecutionContext` | Per-step capability object: trusted identity, reusable checkpoint, upstream outputs, remaining budget, cancellation check, and bound logger |
| `StepResult` | Checked outcome: complete, continue with saved progress, defer/retry, block, or cancel; includes typed result/reference and safe reason |
| `JobRepository` | Persistence port for atomic enqueue, claims, guarded checkpoint commits, attempt transitions, cancellation, and bounded recovery scans |
| `JobRecovery` | Reconciles expired ownership, unfinished attempts, committed results, and missing work through bounded repository operations |
| `JobRetryPolicy` | Injected strategy classifying retryable failures and calculating persisted due times within cumulative limits |
| `JobBudget` | Immutable allowances for a run/step: time, steps, request count, bytes, and other required resources |
| `JobEventRecorder` | Port that records the common event envelope; lifecycle events participate in the durable transition protocol |
| `ContextualJobLogger` | Bound diagnostic logger that adds trusted job/step/attempt identity and applies logging policy |
| `JobLogRepository` | Read port for authorized, indexed event filtering, timelines, and aggregate reports |

Inject clocks, identifiers, randomness when used, domain services, and external providers through
existing narrow ports. A `JobExecutionContext` is not a service locator: it cannot resolve arbitrary
services, choose ownership, run SQL, or expose the whole `JobManager` back to a handler. Construct
handlers and steps at the composition root. Validate their registered type/version and step-key
uniqueness at startup. Unknown persisted types or incompatible versions become visible blocked
jobs; never deserialize client-selected classes/untrusted executable objects or invoke
client-supplied callables, commands, or URLs. A framework-owned queue wrapper may carry the checked
job identity/payload through its trusted transport without replacing this application contract.

Use a simple ordered sequence by default. A dependency graph, parallel step execution, or child jobs
requires a demonstrated workload; then define dependency completion, cancellation, and join semantics
explicitly. Do not introduce a graph engine just to run extraction followed by summary selection.
Each step has one responsibility, stable identity, declared input/output contracts, and a bounded
execution cost. Compose steps; do not require a `BaseJob` superclass, static registration, or global
logging. A one-step job implements the same contracts without artificial phases.

For PHP, declare strict types and checked DTOs under the host's [PHP conventions](../components/PHP_LARAVEL.md).
A minimal interface boundary is:

```php
interface JobHandler
{
    public function definition(): JobDefinition;
}

interface JobStep
{
    public function key(): string;
    public function execute(JobExecutionContext $context): StepResult;
}
```

`JobDefinition` associates those step implementations in the composition root; storage contains only
checked identities and data. The runner supplies each step's typed input/output view in the context.
Use concrete or generic checked values as the language permits, not unbounded arrays crossing ports.
These names describe roles, not permission to create an interface and implementation for every DTO.

## 3. Durable records and state transitions

Define the exact records once in the implementing project's contract module. The minimum durable
information is:

| Record | Required information |
| --- | --- |
| Job | ID, trusted tenant/owner scope and subject, type, definition/payload version, deduplication key, desired input revision, state, priority/fairness metadata, due time, optional absolute expiry, counters, cancellation state, created/updated timestamps |
| JobAttempt | ID, job and claimed input revision, trigger/run correlation, lease token/generation and expiry, start/end, outcome, safe reason, cumulative resource receipts |
| StepAttempt | ID, job attempt, stable step key and batch identity, input fingerprint, logical operation identity, start/end, outcome, safe error category, correlated external-request IDs |
| Checkpoint | Job/scope, step and batch key, schema/definition identity, exact input fingerprint and upstream checkpoint identities, output or protected durable reference, cursor/coverage, checkpoint revision, commit time |
| JobEvent | The common envelope in [logging](#9-common-logging-and-accounting), linked to its job, attempt, step, and external requests where applicable |

Outputs may remain in an application-owned repository, such as FactDB, with a checkpoint referencing
the committed revision/receipt. Do not copy canonical domain data into a second job-owned truth.
Large results use protected immutable artifacts or domain references, not log text or an unbounded
job payload. Store enough checked input/version identity to rebuild or explicitly reject lost state.
The backing storage must survive request/process restarts and routine host cleanup. Record and
verify its actual transaction/locking guarantees on that host; request memory, sessions, or an
ephemeral temporary directory are not the canonical queue. Schema ownership and explicit migrations
follow [server persistence](../components/SERVER.md#canonical-persistence-and-background-work).

The logical job states are:

| State | Meaning and permitted next action |
| --- | --- |
| `pending` | Ready or scheduled for `notBefore`; admission may claim it when due |
| `running` | Owned by one live lease; only that owner may commit execution progress |
| `completed` | Required steps and final publication committed for the desired input revision |
| `blocked` | Needs changed configuration, authority, capacity, input, or review; no automatic hot loop |
| `failed` | Automatic failure/resource allowance exhausted; preserved for inspection and authorized retry |
| `cancelled` | No further execution authorized for this request; retained external-effect uncertainty remains visible |

Transient failure normally returns a job to `pending` with a future due time and a failed attempt.
Yielding because no time/permit is available is a deferral, not a failed provider request. Internal
SQL state names may differ if their mapping preserves these meanings. A completed job receiving new
inputs becomes pending for that target; earlier success/failure receipts remain historical facts.

Attempt outcomes distinguish `started`, `completed`, `failed`, `rejected`, `interrupted`, `yielded`,
`obsolete`, and `cancelled`. An interrupted call can additionally have an unknown external outcome.
A completed step attempt is not proof that the whole job completed. Only the runner's guarded durable
transition can declare completion; a handler log message cannot do so.

Persist jobs before dispatch. Source changes and desired work must commit together, or use a durable
outbox/reconciliation protocol that proves no change can be lost between those writes. Deduplication
uses a trusted logical identity such as scope + job type + subject, with event identity added for
jobs whose outcomes are not replaceable. Do not let a retry create a fresh logical effect identity.
Bound payloads, backlog, retained receipts, and scans; apply tenant limits and an explicit rejection
or coalescing policy instead of dropping required work silently.
Resolve current domain authority at execution and publication, not only enqueue time. A recorded
initiator is audit context, not permanent authorization after access is revoked. Explicit system
maintenance jobs use their declared service authority and subject scope under the same policy model.

## 4. Designing resumable steps

Split work at meaningful durable boundaries: loading a bounded source batch, extracting accepted
knowledge, updating detailed memory, selecting a brief, publishing an output, or exporting a chunk.
Do not put a whole long conversation inside one step merely because a queue calls it a single job.
A batch cursor must have stable ordering, exact coverage, and a defined response to input mutation;
a message count or timestamp alone cannot identify what was processed.

For each step, document:

1. Its authoritative input snapshot, input/contract fingerprint, and prerequisites.
2. Its maximum indivisible work unit and expected worst-case budget.
3. Its accepted output, validation, saved cursor, and next-step condition.
4. Its retry safety, external-effect identity, and uncertain-outcome recovery.
5. Its invalidation rules, cancellation checks, and completion/publication condition.

A checkpoint is reusable only when its relevant inputs, upstream results, and generation/algorithm
contract still match. New appended messages can extend a valid prefix; an old edit can invalidate
only the affected suffix when the strategy supports that recovery. Never reset all saved work just
because execution moved to another process. Conversely, never retain stale output to avoid paying
for necessary reprocessing. Algorithms choose compatibility through an explicit strategy contract.

Save accepted progress at the end of **every** successful step or batch, before starting its successor.
The normal `StepResult` protocol makes this the runner's responsibility, so authors cannot forget a
manual checkpoint call. Commit output/reference, cursor, checkpoint revision, step outcome, and
lifecycle event atomically with lease/input checks. Return `continue` to save partial progress and
schedule the next batch; this does not mean the whole step or job is complete.

A domain-owned result may commit separately, but then its stable operation receipt must let recovery
recognize that result and attach the missing job checkpoint without repeating the domain effect.
If the stores differ, use an outbox or explicit idempotent reconciliation protocol; do not imply two
independent writes are atomic. The same rule covers final publication before queue acknowledgement.

Never mark a cursor advanced before its result is durable and validated. Never keep accepted results
only in local variables, a session, the browser, a shutdown callback, or process-local memory. A
snapshot can be valid yet semantically incomplete: checkpointing proves saved progress, not AI truth.
An invalid AI output does not advance coverage or count as a successfully processed source.

## 5. Claims, leases, and checkpoint acceptance

Claim due work in a short atomic transaction or equivalent database operation. Capture the desired
input revision, create an attempt, and issue a unique lease token plus an increasing ownership
generation. Every checkpoint, domain publication, renewal, and completion must reject the wrong
owner, an expired lease, cancellation, erased scope, or incompatible input revision. A queue-only
token check is insufficient if an old process can still write domain results directly.

The generation/token is a **fence**: a late old owner cannot overwrite newer progress, even if its
provider response arrives successfully. Lease exclusion protects local writes, not third-party
side effects; those need the independent idempotency protocol below. Keep database write locks
short. No HTTP call, sleep, queue wait, or expensive computation runs while holding them.

Make claim, checkpoint acceptance, and recovery race-safe across real connections/processes. Enqueue
of a newer desired revision while an attempt runs must survive that attempt's completion. Old work
may be retained as historical output, but cannot mark the new target complete. Checkpoint reuse
against the new target requires the explicit compatibility checks from the preceding section.

Set lease duration from the admitted step/run budget plus bounded commit/recovery margin. Optional
heartbeats renew only live ownership and cannot extend the enclosing hard request deadline. Do not
assume a PHP process can heartbeat while blocked inside one synchronous network call. A renewal
failure means stop before the next effect; a late result still needs fencing.

Use an authoritative persisted UTC clock for due times and expiry comparisons, and an injected
monotonic clock for elapsed run budgets. Do not persist a process's monotonic timestamp for another
process to compare. Clock uncertainty or stale database connections cannot become permission to
write after ownership has expired.

## 6. Run budgets and unreliable hosts

One runner invocation executes a bounded number of **steps/batches**, not an unbounded number of
whole jobs. Before each one, reserve its complete worst-case queue wait, connection, provider call,
permitted retry/backoff, validation, persistence, and response margin. All execution uses the same
remaining run deadline; entering a step or retry cannot reset it. Startup discovery has its own
reported allowance inside the host's total request budget.

For HTTP triggers, the admitted run budget must fit the measured/configured PHP, host, and proxy
request limits applicable to that mode. It also deducts time already spent in bootstrap, session
handling, and ordinary rendering if the same request hosts the runner. A useful bound is:

```text
usable time = min(configured run allowance, remaining host/request allowance) - safety margin
start a step only when its entire admitted allowance fits usable time
```

If insufficient time remains, save/schedule and return promptly. If an indivisible step can never
fit that host, show `step_budget_too_large` or equivalent as a blocked reason. Reduce its batch,
choose a qualified faster operation, use a provider's durable asynchronous operation with a saved
operation ID and separate polling steps, or move execution to a suitable worker. Checkpointing
cannot make a synchronous 90-second LLM call reliably finish inside a 30-second request limit.

Do not wait in a web request merely to spend a long retry delay or acquire a busy provider permit.
Persist the next due time and yield when admission cannot fit. Distinguish job backlog from the
provider SDK's live request queue: the latter does not preserve the job payload after PHP exits.
The runner remains independent of provider concurrency; [provider admission](AI_PROVIDER_OPERATIONS.md#admission-and-resource-bounds)
controls actual calls across workers, accounts, and job types within its declared quota scope.

Start conservatively with one active runner and one step/batch per traffic invocation. Make these
limits configurable and increase them only with evidence. Declare host-specific time bounds rather
than copying a generous CLI timeout into shared hosting. Concurrent deployments must agree on the
same lease, quota, and pacing scope. Bound priority and tenant scheduling so one large repair or
noisy tenant cannot starve other due work.

## 7. External calls, retries, and uncertain outcomes

Before an external call, persist its step-attempt identity and stable logical operation key. Use
provider idempotency or queryable operation IDs when available. A provider response is validated
before its result becomes a checkpoint. Accepted intermediates must be saved before later calls.
A recoverable protected response artifact can reduce replay, but it must have an explicit identity,
validation, retention, and authorization policy and still cannot close every response/commit race.

If a process dies after a remote effect but before saving its receipt:

- For an idempotent/queryable operation, reconcile or replay using the same operation identity.
- For a repeat-safe computation such as generation, a new request may be required; record possible
  duplicate computation/cost and do not invent the missing request's token usage.
- For an irreversible operation without a safe reconciliation mechanism, block for review rather
  than blindly repeating a payment, notification, or destructive action.

Never advertise exactly-once external effects based solely on a local unique constraint. An outbox
prevents lost delivery intent; the receiver's idempotency/reconciliation still determines duplicate
external effects. Replaying a summary must not replay an already committed application mutation.

`JobRetryPolicy` classifies transient transport/server failures, provider cooldowns, validation
rejections, lost authority, stale inputs, and permanent configuration errors separately. Transient
failures use persisted capped exponential backoff, bounded jitter where appropriate, and server
retry-after constraints. Authentication, incompatible schema, unsupported handlers, exhausted
capacity, and rejected meaning need the declared repair/review policy, not a tight retry loop.

Declare a cumulative attempt, request, cost, and elapsed-work allowance for each logical step/input
version across **all** visits and triggers. A brief in-process retry is allowed only within that
allowance and the current deadline. SDK, router, step, and job loops cannot each silently multiply
it. Rescheduling cannot reset counters or remove quota/cooldown reservations. Set explicit finite
automatic-failure limits and an observable exhausted state; authorized retry can extend the policy
without erasing history. New inputs may get a new declared allowance, while lifetime totals remain.
Deferral before an effect is dispatched does not consume a failed-provider-request allowance.
Record whether dispatch was observed when the transport supports it; otherwise leave that outcome
unknown. An absolute business expiry, when present, also survives rescheduling and cannot be reset
by another visitor or retry.

A successful retry preserves every earlier failure/rejection/interruption receipt. Overall job
completion and first-attempt success are different metrics. Successful HTTP transport is not
semantic acceptance; inaccurate accepted AI content remains a functional failure under
[qualification](../practices/AI_EVALUATION.md#required-functional-acceptance-suite).

## 8. Recovery, cancellation, and version changes

Recovery is normal execution, not an emergency script. Each trigger invokes the same bounded
`JobRecovery`/`JobRunner` path. A persisted scan cursor lets large backlogs, expired leases, and
missing derived jobs be reconciled over multiple invocations.

When reclaiming expired ownership, atomically close only unfinished job and step attempts as
interrupted, preserve completed step receipts, and make unfinished work due according to policy.
Reconcile correlated provider-call records too:
change abandoned “started” records to interrupted/unknown according to their accounting contract.
Do not leave them appearing permanently active, and do not label unobserved provider completion a
confirmed failure or zero-cost request. Keep the interruption and any later reconciled receipt;
late callbacks cannot revive cancelled ownership or advance stale progress.
The recovery timestamp is when interruption was detected, not proof of when the process stopped.
Keep last observation, recovery time, and any unknown end time/duration distinguishable.

Reconstruct the next step from valid durable checkpoints and authoritative domain receipts. A
missing queue acknowledgement after publication becomes a no-op acknowledgement, not another
publication or generation request. If a checkpoint's artifact is missing or corrupt, invalidate that
checkpoint and downstream dependents and use the declared rebuild path; report the loss. Do not
skip prerequisites or call incomplete derived state complete.

Cancellation is persisted and checked before each effect and commit. It cannot undo an already
accepted external effect, and a blocking provider call may finish before cancellation is observed.
Show cancellation requested separately from confirmed stopped work when needed. Source erasure
invalidates its jobs, outputs, artifacts, and derived copies according to retention policy; retain
only the minimum non-content tombstone needed to reject an old worker restoring erased data.

Definition, payload, checkpoint schema, prompt, model, or algorithm changes require a declared
compatibility decision. Version manifests identify relevant inputs per step, so a new display
projection need not discard compatible accepted facts. A migration/rebuild creates a new target
and preserves receipts; it never reinterprets a stored step key/index under unrelated new code.
Pause admission during incompatible deployment/migration transitions and follow
[deployment controls](../practices/DEPLOYMENT_AND_CACHING.md#maintenance-gated-release-handoff).
Changing a selected strategy does not migrate or erase another strategy's independent state.

Manual retry reuses valid checkpoints and retains historical failure/resource totals. It cannot
steal a still-live lease or erase a suspected external effect. Explicit full rebuild is a distinct,
authorized operation that records why compatible saved work is being discarded.

## 9. Common logging and accounting

Every job uses the same event pipeline. The manager/runner records lifecycle events automatically;
handlers add domain diagnostics through the `ContextualJobLogger` supplied by their execution
context. Do not make each job type write its own file, table shape, or admin screen. Keep logging
as an injected collaborator rather than inheritance or a static `JobManager::log()` call.

In PHP, use/decorate [PSR-3's `Psr\Log\LoggerInterface`](https://www.php-fig.org/psr/psr-3/)
for diagnostics, including its standard levels and context conventions. `ContextualJobLogger` binds
trusted correlation fields and normalizes context into the event contract; do not replace PSR-3 with
a competing generic logger interface. Job-specific stable event codes and bounded detail schemas
are registered with the job definition. UI labels come from localized catalogs, not stored markup.
For example, a step can call
`$context->logger()->info('summary.batch.validated', ['processedMessages' => 8]);` without supplying
its own job ID or deciding whether a checkpoint committed. The runner records that later transition.

The common `JobEvent` envelope contains:

- Schema version, unique event ID, UTC occurrence/recording times, and stable timeline ordering.
- Trusted scope, job ID/type/definition version, subject reference, run/trigger, job-attempt ID,
  step key, step-attempt ID, and logical-operation/correlation IDs when applicable.
- Stable event code, standard severity, outcome/reason category, and bounded allowlisted details.
- Relevant checkpoint/input revision and counters, plus duration measurements with named scope.
- External request correlation and optional provider/model/account references and usage receipts.

Correlations are required when applicable: enqueue has no execution-attempt ID yet; a step event
must identify its step attempt. Use checked event variants/optional fields rather than fabricated
empty IDs. Provider-specific fields remain absent for non-provider jobs.

The recorder derives reserved identity fields; a handler's log context cannot overwrite scope,
lease, outcome, or correlation. Use opaque references instead of personal identifiers. Reject or
sanitize unrecognized detail fields through the declared boundary policy. A PSR-3 diagnostic bridge
must handle unexpected context safely without breaking its lenient logging contract; it does not
turn arbitrary exception messages/objects into unredacted durable evidence.

Lifecycle events include enqueue/coalesce, claim, step start, checkpoint saved, step complete,
deferral, retry scheduled, rejection, interruption, blocked/exhausted, cancellation, stale-result
rejection, publication, and completion. Persist authoritative lifecycle receipts with the state
transition, or via a transactional outbox with explicit pending delivery. Diagnostic sink failure
must not repeat an already committed effect; an unavailable required lifecycle/accounting commit
cannot be reported as a successful transition. The logger is not the source of job truth.

Provider adapters correlate their existing request receipts with the common job timeline. Keep one
canonical usage owner per external request and derive totals from unique receipts, rather than
adding the same tokens from a step log, attempt log, and provider log. Track successes, failures,
rejections, retries, recoveries, interruptions, queue wait, HTTP duration, active execution, and total
wall time separately. Total wall time includes scheduled waiting; overlapping durations cannot be
summed as though sequential. Unknown token/cost/time values stay unknown, and partial known totals
are labeled partial. An eventual success does not erase earlier consumed resources.

Ordinary queryable events exclude source messages, prompts, outputs, credentials, authentication
tokens, cron secrets, full provider bodies, and uncontrolled exception text. Where investigation needs raw
content, retain a separately protected, bounded diagnostic artifact with access audit, retention,
and erasure controls; log only its safe reference. Render details as escaped text and authorize
artifact access separately. Define indexed retention/compaction without pruning live checkpoints,
needed idempotency receipts, or uncertainty evidence before their recovery window closes.

## 10. Trigger adapters and PHP shared hosting

CLI, a resident worker when available, authenticated cron HTTP, manual admin execution, and
traffic-driven execution use the same durable lifecycle. Triggers do not create a second queue,
retry policy, or execution contract. Persist enable/disable mode, interval, run budget, step/batch
limit, concurrency, and retry/resource policies through authorized administration.
Suitable host adapter names are `TrafficJobTrigger`, `CronJobController`, `RunJobsCommand`, and
`QueueWorkerAdapter` where a queue framework is used. They validate their entry boundary and call
the runner; they do not embed another execution/retry loop.

**Traffic mode:** a visit asks whether due work can run; it is not a reliable scheduler. Use a shared
admission lease and due time so many visitors cannot launch competing runners. A separate same-origin
CSRF-protected browser request may execute a bounded run while the visible page stays usable. That
background HTTP request remains alive until it responds; it does not create an independent daemon.
Browsers send at most one runner request at a time per page and obey the interval. Server-side
admission remains authoritative across tabs and visitors, including disconnects and retries.

Release PHP session locks before slow work. On a supported FPM host,
[`fastcgi_finish_request()`](https://www.php.net/manual/en/function.fastcgi-finish-request.php)
can finish the response before PHP continues, but the process still executes under host policy.
FPM's [request termination settings](https://www.php.net/manual/en/install.fpm.configuration.php)
can apply to this continued execution. Configure and verify that host profile; do not assume a
shutdown callback, `ignore_user_abort`, `set_time_limit`, or a flushed response guarantees survival.
Without a supported early-finish path, use the separate runner request or an explicit cron/worker.
No slow model work belongs inside ordinary page rendering or an unbounded shutdown callback.

Cached pages, closed/suspended browsers, killed PHP requests, and periods without visits give no
execution guarantee. Reserve foreground capacity; a post-response job still consumes server
resources. Do not keep HTTP workers occupied sleeping through long cooldowns or draining a backlog.

**Cron mode:** authenticate the bounded endpoint using a random, rotatable secret stored as a hash
and compared in constant time. Prefer an authorization header. If a limited host requires a secret
in its URL, treat URLs and access logs as private, suppress redirects/referrers, use no-store
responses, and document that operational exposure. Reject HEAD execution. Expose only content-free
runner receipts. Browser-triggered runs require an authenticated principal and the normal
authorization, CSRF, and origin checks. Their traffic signal may only ask for already-authorized due
work; it cannot pick a job, subject, provider, handler, ownership scope, or arbitrary scheduling
parameters.

Cron is preferable for steady progress. Enabling it does not imply an external scheduler was
registered. Disabled automatic execution preserves durable work and exposes its paused condition;
manual/CLI execution follows the host's explicit policy and all the same safety/resource limits.

## 11. Shared administration

Provide one authorized job administration area for all job types, with narrower capabilities for
viewing diagnostics, retrying, cancelling, rebuilding, and changing runtime settings. Scope every
query and mutation, including alternate endpoints and exports, under the same authorization policy.
Keep unrelated tenant content inaccessible even when record IDs collide.

The common views show:

- Backlog by state/type, due/oldest age, last successful run, next due work, and missing trigger activity.
- Job target revision, completed/remaining steps or known work units, valid saved progress, active
  ownership/expiry, retry due time, and a plain explanation of the current blocked/failed reason.
- A stable ordered timeline of attempts, checkpoints, retries, interruptions, and publication.
- Request success/failure/rejection counts and consumed known/unknown resources, including failures.
- Filters for time range, job type, job/subject reference, state, severity, step, attempt, reason,
  trigger, provider, model, and account reference where available.
- Authorized retry/cancel/rebuild controls, shared-host budgets/mode, and visible pending/exhausted work.

Progress is based on committed steps/coverage, not estimated LLM token output or a guessed percentage.
Show an unknown total honestly. Counts distinguish jobs from steps, execution attempts, and provider
requests. Published UI output is separate from processing progress; an older displayed summary must
be labeled stale if policy permits keeping it. Strategy-specific counters extend the shared report
without replacing its log envelope or building another incompatible job manager.

Use indexed bounded filters and cursor pagination with a unique ordering tie-breaker. Detail
expansion must not load an entire backlog or all logs into a browser. Checkpoint/output viewers have
separate protected access and retention. Admin retries retain history, are idempotent, use revision
checks, and cannot take a running job away from its current owner.

## 12. Summary example and interruption boundaries

Consider a rolling-summary job with 24 messages and eight-message batches.
`CaseSummary\Rolling\UpdateDetailedSummaryStep` advances the `detail` checkpoint,
`SelectBriefStep` produces `brief`, and `PublishSummaryStep` commits `publish`. These are stable
step identities under a registered job type such as `case-summary.rolling`; provider choice is
configuration, not a new job type. The same update step can execute several saved batches.

| Work | Durable result | What a later run reuses |
| --- | --- | --- |
| Update detailed memory with messages 1–8 | Detailed text plus exact coverage/context fingerprint | This accepted prefix |
| Update with messages 9–16 | New detailed checkpoint | Both accepted batches |
| PHP stops during the call for messages 17–24 | No accepted result for that batch | Resume at 17–24; do not redo 1–16 |
| Finish 17–24, then PHP stops while generating the brief | Detailed coverage through 24 | Generate only the brief |
| Brief publication commits, then PHP stops before queue completion | Published revision and operation receipt | Reconcile completion without another AI call |

If message 5 changes, earlier checkpoints may need invalidation under the
[rolling strategy's recovery policy](ROLLING_SUMMARIES.md#historical-edits-and-recovery). That is
necessary reprocessing after changed inputs, distinct from repeating valid work after a crash.

A [FactDB](FACT_DB.md) job can compose `ExtractFactsStep`, `SelectSummaryStep`, and
`PublishSummaryStep`, saving each accepted extraction batch before summary selection.
If selection fails, reuse the current accepted facts. Both strategies can be independent registered
job types. Selecting the default display strategy queues catch-up without deleting the other's
state; publication checks the selected strategy and source revision before updating that display.

These examples require fitting each individual provider call into the host budget. A saved
checkpoint cannot recover a response that never reached durable storage, prove no fact was omitted,
or allow a stale owner to publish after a later edit.

## 13. Required verification and adoption

Write tests alongside implementation and execute the applicable gates under
[the verification policy](../practices/TESTING.md#change-specific-verification-gates). Inject clocks,
providers, identifiers, and randomness for deterministic lifecycle/policy tests. Real providers remain
separately authorized and bounded; their quality tests do not substitute for crash recovery tests.

Required evidence includes:

1. **Contract and lifecycle:** unknown handler/version rejection, checked payload/output/cursor,
   one-step and multi-step jobs, allowed transitions, disabled execution, and finite resource bounds.
2. **Durable progress:** valid prefix reuse, output-before-next-step ordering, batched continuation,
   projection-only retry, missing/corrupt artifact recovery, and exact source/contract invalidation.
3. **Real interruption:** an isolated durable database and controlled fake external service; stop a
   worker without cleanup before/during a call, after its response but before checkpoint, after
   checkpoint before the next step, and after publication before queue acknowledgement. Start a
   fresh process and prove the expected resume point, preservation of completed step outcomes and
   interruption history, and no stale publication.
4. **Concurrency:** independent connections/processes competing for claims; expiry/reclaim and late
   old writes; cancellation, erasure, or new input while a call runs; an old heartbeat cannot renew
   newer ownership. Simulated exceptions alone do not prove process-death recovery.
5. **Remote uncertainty:** provider idempotency/query reconciliation, repeat-safe duplicate generation
   accounting, irreversible-effect blocking, and abandoned “started” AI records closed as unknown.
6. **Budgets and retry:** remaining request-time admission, queue wait/backoff counted, a step that
   never fits shown blocked, bounded discovery and fair scheduling, cumulative allowances across
   visits, cooldown preservation, and no nested retry multiplication.
7. **Logging and admin:** automatic lifecycle events for every job type, diagnostics with trusted
   context, failed sink behavior, unique request usage totals, unknown/partial usage, interrupted
   attempts, indexed filters/pagination, scoped access, CSRF, safe rendering, and protected artifacts.
8. **Triggers and deployment:** unauthenticated/unauthorized browser-trigger rejection, traffic
   bursts/multiple tabs, no early-finish capability, connection abort, no-traffic pause, cron secret
   rotation/HEAD rejection, previous-schema migration, definition upgrade, restart, source erasure,
   and rollback/maintenance compatibility.

The process-death harness uses barriers or recorded signals to choose interruption points, not
arbitrary sleep timings, production workers, paid providers, or the developer's normal database.
Run it in the project's isolated toolchain. If the platform cannot run a required process test,
report that gate unavailable; do not substitute mocked timeouts and claim hard-interruption proof.

For adoption, record the host capability profile and time limits, registered job definitions,
checkpoint compatibility policy, canonical storage/event owners, retry/resource ceilings, migration
and recovery procedure, log retention/access, and verification evidence. Map existing job records
and provider ledgers explicitly; preserve accepted outputs and historical receipts during migration.
This document specifies the target contract. A blueprint update alone does not change runtime code,
qualify a model, enable a worker, or prove an implementing project's conformance.
