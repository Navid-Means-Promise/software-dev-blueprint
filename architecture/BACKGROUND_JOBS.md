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
| Job | One durably requested outcome for an authorized subject, identified by its registered class and metadata |
| JobStep | One named part of that job with a saved result; a large step can advance in bounded batches |
| Checkpoint | An accepted result or cursor, with the exact inputs and contract that make it reusable |
| JobAttempt | One claim of a job by a runner; it can execute a bounded number of steps |
| StepAttempt | One execution of a step or its next batch; retries have separate receipts |
| JobHandle | Checked reference returned to business logic for inspecting, tracking, or cancelling its job |
| JobMetadata | Checked key-value list assigned at creation for identifying, correlating, and finding work; it contains no copied business inputs |
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
| `JobManager` | Unified coordinator for enqueue/refresh, metadata lookup, cancel, and authorized retry; owns one execution session and delegates its scheduling loop to the runner |
| `JobRunner` | Coordinates admission, recovery, claim, step execution, and checkpoint/attempt commit within one run budget |
| `JobExecutor` | Execution port with `SequentialJobExecutor` and PHP's native `PhpThreadJobExecutor` adapters |
| `JobManagerLock` | Nonblocking ownership port admitting one active manager execution session across all triggers |
| `JobHandler` | Interface implemented by each job type; supplies its versioned definition and composed domain steps |
| `JobStep` | Interface implemented by each executable step; uses current attempt data/context and returns a `StepResult` |
| `JobDefinition` | Immutable value describing type, definition version, metadata schema, searchable fields, ordered stable step keys, and step policies |
| `JobExecutionContext` | Per-step capability object: trusted identity, job metadata, fresh attempt data, reusable checkpoint, upstream outputs, budget, cancellation check, and bound logger |
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
job identity/metadata and manager-owned execution envelope through its trusted transport without
adding a caller-supplied business-input payload.

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
checked identities and data. The runner supplies each step's fresh attempt data/output view in the context.
Use concrete or generic checked values as the language permits, not unbounded arrays crossing ports.
These names describe roles, not permission to create an interface and implementation for every DTO.

### PHP execution policy

One active `JobManager` can execute several independent jobs through a bounded pool of native PHP
threads. `JobRunner` is its internal scheduling loop, not a second independently active manager.
The manager lock, maximum concurrent jobs, and each provider's request concurrency are separate
limits. Steps within one job follow their declared order, and equivalent replacement jobs follow
the cancellation rules below.

Use `PhpThreadJobExecutor` when the active PHP runtime supports and has verified the
[`parallel` extension](https://www.php.net/manual/en/parallel.setup.php), which requires a ZTS build.
Otherwise use `SequentialJobExecutor` and execute one job at a time. Capability selection happens
at composition/startup for the actual web/CLI runtime; report the effective mode and fallback reason.
PHP thread support is an optional host capability, not a built-in guarantee or permission to change
the host's PHP installation. An operator may set the native pool size to one.

These are the two PHP execution modes. Do not add Fibers, coroutine/event-loop job execution,
`curl_multi` job batching, process pools, or HTTP fan-out as substitute concurrency mechanisms.
Ordinary blocking job code works in the sequential adapter and in separate native threads; no job
author must insert cooperative scheduling yields. Cooperative **cancellation** checks are still
required at safe points.

Each native thread bootstraps its own injected dependencies, database connection, logger, and
provider client. Pass checked identities/data rather than live PDO connections, transactions, or
other unsafe objects across the [native thread boundary](https://www.php.net/manual/en/parallel-runtime.run.php).
All workers share the admitted run deadline, durable scope/lease rules, and provider admission
policy. The manager retains its lock until every admitted worker stops; spawning a thread does not
extend the HTTP request lifetime. Startup failure can select sequential mode before any dispatch;
an uncertain partially dispatched operation follows recovery rather than blind duplicate execution.

## 3. Durable records and state transitions

Define the exact records once in the implementing project's contract module. The minimum durable
information is:

| Record | Required information |
| --- | --- |
| Job | ID, trusted tenant/owner scope and producer, type, definition/metadata-schema version, metadata, equivalence key, state, priority/fairness data, due time, optional absolute expiry, counters, cancellation reason/revision/timestamps, replacement links, created/updated timestamps |
| JobAttempt | ID, job, optional domain-owned captured-source receipt, trigger/run correlation, lease token/generation and expiry, start/end, outcome, safe reason, cumulative resource receipts |
| StepAttempt | ID, job attempt, stable step key and batch identity, input fingerprint, logical operation identity, start/end, outcome, safe error category, correlated external-request IDs |
| Checkpoint | Job/scope, step and batch key, schema/definition identity, exact input fingerprint and upstream checkpoint identities, output or protected durable reference, cursor/coverage, checkpoint revision, commit time |
| JobEvent | The common envelope in [logging](#9-common-logging-and-accounting), linked to its job, attempt, step, and external requests where applicable |

Outputs may remain in an application-owned repository, such as FactDB, with a checkpoint referencing
the committed revision/receipt. Do not copy canonical domain data into a second job-owned truth.
Large results use protected immutable artifacts or domain references, not log text or an unbounded
creation record. Store enough checked source/version identity to rebuild or explicitly reject lost state.
The backing storage must survive request/process restarts and routine host cleanup. Record and
verify its actual transaction/locking guarantees on that host; request memory, sessions, or an
ephemeral temporary directory are not the canonical queue. Schema ownership and explicit migrations
follow [server persistence](../components/SERVER.md#canonical-persistence-and-background-work).

The logical job states are:

| State | Meaning and permitted next action |
| --- | --- |
| `pending` | Ready or scheduled for `notBefore`; admission may claim it when due |
| `running` | Owned by one live lease; only that owner may commit execution progress |
| `cancelling` | Cancellation persisted and publication forbidden; the old worker or external call has not yet been confirmed stopped |
| `completed` | Required steps and final publication committed under the job's domain validation rules |
| `blocked` | Needs changed configuration, authority, capacity, input, or review; no automatic hot loop |
| `failed` | Automatic failure/resource allowance exhausted; preserved for inspection and authorized retry |
| `cancelled` | No further execution authorized for this request; retained external-effect uncertainty remains visible |

Transient failure normally returns a job to `pending` with a future due time and a failed attempt.
Yielding because no time/permit is available is a deferral, not a failed provider request. Internal
SQL state names may differ if their mapping preserves these meanings. New work after a terminal
job creates a new job identity and preserves the previous outcome, receipts, and replacement links.

Attempt outcomes distinguish `started`, `completed`, `failed`, `rejected`, `interrupted`, `yielded`,
`obsolete`, and `cancelled`. An interrupted call can additionally have an unknown external outcome.
A completed step attempt is not proof that the whole job completed. Only the runner's guarded durable
transition can declare completion; a handler log message cannot do so.

Persist jobs before dispatch. Source changes and desired work must commit together, or use a durable
outbox/reconciliation protocol that proves no change can be lost between those writes. Invalidate
old output synchronously with a source change; a delayed outbox delivery must not allow an old
source revision to publish. Do not let a retry create a fresh logical effect identity.
Bound metadata, backlog, retained receipts, and scans; apply tenant limits and an explicit rejection
or coalescing policy instead of dropping required work silently.
Resolve current domain authority at execution and publication, not only enqueue time. A recorded
initiator is audit context, not permanent authorization after access is revoked. Explicit system
maintenance jobs use their declared service authority and subject scope under the same policy model.

### Job metadata and creation

**Job creation accepts a registered job class and `JobMetadata`; it has no business-input arguments,
`input`/`payload` field, source snapshot, or per-job generation-options bag.** Metadata is an
extensible flat key-value list for target references and stable routing/correlation labels. Each
job definition declares required/allowed keys, scalar types (string, safe integer, boolean, or null),
key/value/total-size bounds and searchable fields. Complete canonical metadata determines equivalence. Fields
are job-type-specific; the core does not hard-code `documentId` or legal-document properties.

**Business callers pass the registered handler class, never a manually typed job-type string.**
The registry automatically resolves that class to the stable type string in its `JobDefinition`;
that definition is the single source for the type. Validate class/type uniqueness at startup and
reject unregistered classes. Persist the stable type string, so renaming a PHP class while retaining
its registered type does not change queued job identities. Do not derive persisted types from class
basenames or accept client-supplied class names.

For example, with `RollingCaseSummaryJob` registered as `case-summary.rolling`:

```php
$result = $jobManager->refreshJob(
    RollingCaseSummaryJob::class,
    new JobMetadata(['documentId' => 'LD-42']),
);
```

The manager obtains `case-summary.rolling` from the registration automatically; the caller supplies
only the class reference and identifying metadata. This identifies the document to summarize.
Its messages, edits, dynamic properties, document dates, participant details, and active generation
configuration are loaded through authorized repositories when execution begins. Do not hide those
inputs in metadata as long strings, encoded JSON, arrays,
serialized objects, or document copies. Required job options that describe mutable business state
belong to its canonical domain record/configuration, resolved by reference at execution time.

Creation metadata is immutable for that job identity. Changing the target creates distinct work;
coalescing preserves the stored identity map. Reusing a job
returns its canonical stored metadata/handle; a new caller's correlation can be recorded against
that handle without silently rewriting creation metadata. Scope, authority, leases, status, clocks,
scheduling/resource policy remain manager/host-owned fields; metadata cannot
override them or grant access to the referenced document. A projected subject column or metadata
index is derived from this canonical map, not another independently editable copy of the target.

Business logic can query `JobManager` by registered job class, metadata predicates, and states; the
same registry resolution supplies the stored type filter. For example, find jobs whose `documentId`
equals `LD-42` and whose state is pending, running, or cancelling.
Use checked filters over declared searchable fields, bounded indexed queries, and cursor pagination.
Apply trusted tenant/owner scope inside the query. Preserve scalar types and distinguish a missing
field from explicit null; metadata key order does not change equality. Reject unknown fields or
raw SQL/JSON-path expressions instead of accepting arbitrary query strings. A metadata lookup aids
tracking; the atomic enqueue/coalescing operation still owns race-safe equivalence decisions.

This restriction concerns **creation-time inputs**, not accepted step outputs or checkpoints.
Runtime attempt data and saved validated progress remain necessary and follow freshness checks.
An event-delivery job also queues metadata only, such as `eventId`; it loads that event's canonical
database record and current delivery/cancellation state on start. Immutable event content preserves
the historical event's meaning without an event-snapshot payload exception or substituting the
latest aggregate state for a different event.

### Fresh inputs and explicit refresh

**Golden rule: queue identifying metadata, and load current authorized database data when execution
actually starts.** Business logic decides which changes affect its jobs. The generic manager does
not inspect messages, understand model/table dependencies, maintain source revisions, or decide
whether domain data has become obsolete. Do not require a revision column on every model/table.

A small application service for the affected feature owns scheduling: for example, a
`CaseSummaryJobs` service is called by message creation/edit/deletion, document-detail changes, and
relevant participant changes. Controllers delegate to application actions. Save the mutation and
request its job refresh in one database transaction; if stores differ, use a transactional outbox.
A failure rolls both back. Database constraints enforce uniqueness and ownership; database triggers
do not decide which business jobs to cancel, reuse or create. Imports and maintenance writers use
the same application service. A bounded domain repair scan can detect missing/incompatible output
without turning the generic manager into a data-change detector.

`refreshJob(jobClass, metadata)` is an explicit instruction from business logic that this work is needed
again. Resolve the registered type and trusted scope, then atomically search exact canonical metadata
(scalar types matter; key order does not). Only active jobs count; terminal rows remain history.
The method does not ask a handler for a source revision or compare source timestamps.

| Equivalent active work | Atomic refresh decision | Outcome |
| --- | --- | --- |
| A queued job, including a queued successor or delayed retry | Preserve its ID, metadata and due/cooldown time; it loads fresh data when it starts | `Unchanged = 0` |
| Running or cancelling work with no queued successor | Request cooperative cancellation if needed and create one pending replacement at the tail of the queue | `Replaced = 2` |
| No active job (terminal history may exist) | Create one pending job at the tail of the queue | `Created = 1` |

`refreshJob()` returns only an integer: `0`, `1`, or `2` as defined above. It does not return a
job handle or an object containing the code. Business logic decides when to call it from relevant
business changes, before any return value exists; a handle does not make that decision. If a caller
needs job details later, use the separate scoped metadata search to obtain `JobHandle` values for
inspection, cancellation or retry. Repeated calls while a successor is queued return `0` and keep
that same queued job. Definition-version changes may block incompatible work; they must not bypass
this lifecycle contract.

For example, an edit action saves a message, then calls the feature's scheduling routine:

```php
$result = $jobManager->refreshJob(
    RollingCaseSummaryJob::class,
    new JobMetadata(['documentId' => 'LD-42']),
);
// $result is the integer 0, 1, or 2.
```

Provide a separate non-invalidating `enqueue(jobClass, metadata)` for initial scheduling/repair.
It returns equivalent active work without cancelling it. Repeated discovery/visitor wake-ups must
not call `refreshJob` on every active job, restart healthy work, revive manual cancellation, or reset
exhausted failure allowances. Business logic checks whether output is already current before asking
for repair. An explicit relevant mutation may request fresh work; historical failures stay visible
and shared provider cooldowns, spending ceilings and resource admission remain enforced.

Use a short per-key transaction/lock and a uniqueness constraint for race-safe lookup/cancel/insert.
A standalone “find, then insert” sequence is insufficient. At most one queued successor exists for
the same trusted scope + registered type + complete canonical metadata. It starts only after older
equivalent local execution has stopped or recovery proves it stopped. Business mutations and claims
serialize through the same store so either queued work starts with the committed new data, or an
already claimed attempt is cancelled and replaced. Generic lease tokens and ownership generations
fence writes after cancellation; they are execution identity, not domain source versions.

The job itself loads a consistent source snapshot at execution, and owns compatibility checks for
saved progress and publication. An optional domain fingerprint can combine document metadata,
message identities/edits and configuration; a last timestamp alone can miss deletes or equal-time
edits. Such a fingerprint belongs to that feature, not a required generic JobManager protocol.
Never preload creation-time source bodies or hide them in metadata. Do not mix independently loaded
old/new fields within an attempt. Reuse compatible accepted work according to the job's own rules.

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

A continuation identifies newly saved progress through a checked cursor or domain receipt. If an
unchanged continuation repeatedly returns the same progress identity, block/report that stalled
step rather than creating a visit-driven hot loop. Retrying unchanged work is a deferral/retry,
not evidence of another accepted batch.

A domain-owned result may commit separately, but then its stable operation receipt must let recovery
recognize that result and attach the missing job checkpoint without repeating the domain effect.
If the stores differ, use an outbox or explicit idempotent reconciliation protocol; do not imply two
independent writes are atomic. The same rule covers final publication before queue acknowledgement.

Never mark a cursor advanced before its result is durable and validated. Never keep accepted results
only in local variables, a session, the browser, a shutdown callback, or process-local memory. A
snapshot can be valid yet semantically incomplete: checkpointing proves saved progress, not AI truth.
An invalid AI output does not advance coverage or count as a successfully processed source.

## 5. Claims, leases, and checkpoint acceptance

### One active manager

All triggers compete for one nonblocking `JobManagerLock` per application/backing-store scope.
Busy requests return immediately; they never queue waiting for that lock. A process-local singleton
or an “is running” read followed by a write cannot enforce this across PHP requests. On a verified
single-host PHP filesystem, a dedicated process-held
[`flock(LOCK_EX | LOCK_NB)`](https://www.php.net/manual/en/function.flock.php) is one adapter option.
Keep its handle private to the manager and stable across deployments; do not replace/unlink its
lock file while owned. Other host adapters must prove equivalent cross-instance exclusion.
This lock guards execution sessions only. Business calls that enqueue, inspect, or cancel work use
their short scoped transactions without acquiring it, so an active manager cannot block updates
or cancellation requests from the rest of the application.

The manager holds this execution lock through worker shutdown/join. A time-based lease expiring is
not proof that the old manager or native thread stopped. Recovery cannot start overlapping local
execution merely because that timestamp passed. If exclusion cannot be established under the host's
failure model, keep execution blocked and report it privately. Job-level leases/fences below remain
necessary for attempt recovery, cancellation, and stale writes; they do not replace manager exclusion.

Create native worker pools and perform recovery/discovery only after acquiring that lock. A busy
wake-up must not initialize a pool or produce a run/failure receipt. If dispatch fails after a worker
may have started, settle that worker under the same lock before allowing replacement execution.

### Per-job ownership

Claim due work in a short atomic transaction or equivalent database operation. Create an attempt
and issue a unique lease token plus an increasing ownership generation. Every checkpoint, domain publication, renewal, and completion must reject the wrong
owner, an expired lease, cancellation/replacement generation, or erased scope. Domain repositories additionally reject incompatible captured source data. A queue-only token check is insufficient if an old process can still write domain results
directly.

Resolve trusted target scope and enqueue authority inside the transaction that reuses or replaces
work, or prove an equivalent conditional-write protocol. The manager's decision depends only on
registered identity and lifecycle state. Feature application services own source-change notification;
no second enqueue state machine is hidden in database source-change triggers.

Domain stores that commit independently of the job repository need an injected commit guard inside
their own transaction. Check the active attempt, cancellation, scope, and source revision at that
commit boundary. Checking only before a provider call or outside the domain transaction cannot
fence a result cancelled during that call. The guard remains a narrow port or callback; the reusable
store must not import the host application's job subsystem.

The generation/token is a **fence**: a late old owner cannot overwrite newer progress, even if its
provider response arrives successfully. Lease exclusion protects local writes, not third-party
side effects; those need the independent idempotency protocol below. Keep database write locks
short. No HTTP call, sleep, queue wait, or expensive computation runs while holding them.
Close or exhaust persistence cursors before such work as well. An unfinished SQLite read cursor
can retain a lock even without an explicit application transaction and stall other native workers.

Make claim, checkpoint acceptance, and recovery race-safe across real connections/processes. A refresh while an attempt runs must survive that attempt's completion. Old work may be retained
as historical output, but cannot complete its replacement. Checkpoint reuse against current domain
data requires the compatibility checks from the preceding section.

Set lease duration from the admitted step/run budget plus bounded commit/recovery margin. Optional
heartbeats renew only live ownership and cannot extend the enclosing hard request deadline. Do not
assume a PHP process can heartbeat while blocked inside one synchronous network call. A renewal
failure means stop before the next effect; a late result still needs fencing.

Use an authoritative persisted UTC clock for due times and expiry comparisons, and an injected
monotonic clock for elapsed run budgets. Do not persist a process's monotonic timestamp for another
process to compare. Clock uncertainty or stale database connections cannot become permission to
write after ownership has expired. Ownership expiry is an interruption with bounded recovery;
it is not a manual/business cancellation. Preserve an explicit cancellation reason when both occur.

## 6. Run budgets and unreliable hosts

One manager invocation lets its runner execute due jobs and their bounded **steps/batches** while
the request budget permits; it need not stop after one job or one step. Before each dispatch,
reserve its complete worst-case queue wait, connection, provider call,
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
provider SDK's live request queue: the latter does not preserve the durable job after PHP exits.
The runner remains independent of provider concurrency; [provider admission](AI_PROVIDER_OPERATIONS.md#admission-and-resource-bounds)
controls actual calls across workers, accounts, and job types within its declared quota scope.

Keep one active manager, and select sequential execution or a configured native thread limit under
the PHP policy above. Use the available request window productively until no due work or no safely
admissible step remains. Reserve time to settle workers and commit receipts/checkpoints; do not use
fatal PHP timeout as the normal return path. Every worker's external-call timeout must fit the same
remaining deadline. Declare host-specific time bounds rather than copying a generous CLI timeout
into shared hosting. Bound memory, admitted steps, and scheduling scans even with many tiny jobs.
All deployments share the same lock, quota, and pacing scope. Bound priority and tenant scheduling
so one large repair or noisy tenant cannot starve other due work.

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

Bind a durable external-call allowance to the exact operation inputs and policy across fresh PHP
processes. A prompt/input fingerprint and manager-owned retry generation can identify repeat-safe
generation work without copying prompts into logs or job metadata. An explicitly authorized retry
may create another bounded allowance; preserve earlier receipts and shared provider cooldowns.
Starting a new visit, runner, SDK instance, or job attempt cannot do so. Accepted batches with
different inputs have distinct operation identities.

A successful retry preserves every earlier failure/rejection/interruption receipt. Overall job
completion and first-attempt success are different metrics. Successful HTTP transport is not
semantic acceptance; inaccurate accepted AI content remains a functional failure under
[qualification](../practices/AI_EVALUATION.md#required-functional-acceptance-suite).

## 8. Recovery, cancellation, and version changes

Recovery is normal execution, not an emergency script. Each trigger invokes the same bounded
`JobRecovery`/`JobRunner` path. A persisted scan cursor lets large backlogs, expired leases, and
missing derived jobs be reconciled over multiple invocations.

After confirming old execution no longer owns its session, reclaim expired job ownership: atomically
close only unfinished job/step attempts as interrupted and preserve completed step receipts. Return
still-wanted work to pending; cancellation-requested, replaced, erased, or expired work stays
non-executable and is finalized as cancelled with its reason. Preserve its already queued successor.
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

### Cancellation and replacement

Every business use case that creates jobs must define when their result becomes obsolete, how it
tracks them by `JobHandle`/equivalence key, and how it cancels or replaces them. Persist a cancellation
reason (such as `superseded_input`, `expired`, `source_deleted`, or `manual`), its revision/time,
initiator where applicable, and any successor link. This is ordinary lifecycle behavior, not merely
an admin button or a transport retry failure. The atomic enqueue/replacement decision is defined in
[fresh inputs and explicit refresh](#fresh-inputs-and-explicit-refresh).

Persist cancellation and fence publication immediately. A queued job becomes cancelled without
execution; a running job becomes `cancelling` until it acknowledges stopping or recovery confirms
it stopped. Its checkpoint/publication guards reject further results from that old ownership even
while its thread or network call remains physically active. Never transfer its token to a successor.

Jobs check durable cancellation and execution ownership before each step, before an
external effect, after an external call, and inside the transaction accepting any output. Long local
loops add bounded periodic checks. Sequential execution uses these same checks; cancellation can be
requested by a separate web request even while the only job thread is blocked. Use bounded network
timeouts and transport cancellation where supported. Native
[`Future::cancel()`](https://www.php.net/manual/en/parallel-future.cancel.php) cannot interrupt an
internal function already running; neither that API nor a thread kill is a correctness guarantee.
Do not depend on forcibly killing a thread to roll back writes or refund an external request.

When a cancelled call eventually returns, retain its usage/outcome receipt, discard its obsolete
proposed result, and finish cancellation. A successor may reuse independently committed, still-valid
domain facts/checkpoints through explicit compatibility checks, but not unvalidated output from
the cancelled call. Do not erase valid earlier history or charge cancellation as a confirmed failed
HTTP request. Unknown external completion/usage remains unknown until reconciled.

Keep obsolete/discarded work distinct from transport failures and invalid output in administration
metrics. A successful response discarded after cancellation retains its transport outcome, timing,
and known tokens; it must not increase transport cooldown penalties.

Source erasure invalidates its jobs, outputs, artifacts, and derived copies according to retention
policy; retain only the minimum non-content tombstone needed to reject an old worker restoring
erased data. Cancellation cannot undo an already accepted external effect; event-delivery jobs
need their own domain expiry/compensation policy rather than the replaceable-summary policy.

### Definition changes and manual recovery

Definition, metadata schema, checkpoint schema, prompt, model, or algorithm changes require a declared
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
turn arbitrary exception messages/objects into unredacted durable evidence. Keep diagnostic codes
in a distinct namespace from authoritative lifecycle events, even if a handler supplies a lifecycle
code as its message. Bind the actual step-attempt ID to the logger when that attempt starts.

Lifecycle events include enqueue/coalesce, claim, fresh-input capture, step start, checkpoint saved,
step complete, deferral, retry scheduled, rejection, interruption, blocked/exhausted, cancellation
requested/acknowledged, replacement linked/reused, stale-result rejection, publication, and completion.
Persist authoritative lifecycle receipts with the state
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

CLI, a resident worker when available, cron HTTP, manual admin execution, and traffic-driven
execution enter the same manager lifecycle. They do not create another queue, retry policy, or
execution contract. Persist enable/disable mode, client wake interval, run budget, native job-thread
limit, and retry/resource policies through authorized administration. Suitable adapter names are
`JobWakeupController`, `TrafficJobTrigger`, `RunJobsCommand`, and a `QueueWorkerAdapter` when used.
Each enters the same manager lock and scheduling loop.

### Public wake-up contract

The wake-up URL is a deliberately public, fixed signal: “process eligible server-owned work if the
manager is idle.” It accepts no job, subject, tenant, handler, provider, retry, force-run, or budget
control. It neither creates business work nor acts using the visitor's account/cookie privileges.
User login, role permissions, CSRF tokens tied to a session, and an application-level call-rate limit
are not required for this narrow signal. Its authorization boundary follows the
[public-entry contract](AUTHORIZATION.md#deliberately-public-entry-points); actual jobs still enforce
their current domain authority, source revisions, quotas, and provider pacing.

Acquire the manager lock without waiting, before recovery scans or expensive composition where
practical. A busy wake-up performs a minimal check and immediately returns an empty `200 OK`.
Do not allocate an attempt, wait for a worker slot, write an unbounded per-call diagnostic, or add
an artificial runner cooldown for this no-op path. Request handling still has a small resource cost;
manager exclusion guarantees no duplicate execution, not zero-cost HTTP traffic.

If idle, the admitted manager uses the request window to run eligible jobs, dispatching native
threads only under the execution policy above. It keeps the HTTP request open and sends no progress,
heartbeat, HTML, JSON, job ID, statistics, or diagnostic body. When there is no eligible work, or
insufficient time for another safe step and worker cleanup, return an empty `200 OK` with no-store
headers. A normal return does not mean the backlog is empty or every job succeeded. Error handling
keeps any unexpected error response empty and records details only in protected administration.

Use nearly all the verified host allowance while preserving checkpoint/cleanup margin. PHP's
[`max_execution_time`](https://www.php.net/manual/en/info.configuration.php#ini.max-execution-time)
alone is not a portable wall-clock limit; account for the host's web-server/FPM limits as well.
An unexpected hard timeout or disconnect is recoverable interruption, not the normal loop exit.
Browser connection lifetime does not extend the server's execution allowance. Release session locks
before work and prevent output from jobs, providers, and framework error pages reaching this URL.

### Traffic, cron, and privileged controls

A separate browser request can hold this window open while the visible page stays usable. Each
page sends at most one wake-up at a time and can use a configurable polling interval; those client
choices do not impose a server-side frequency limit on valid wake-ups. Due times and provider
cooldowns still govern the work itself. HEAD must not execute work. Cached pages, closed/suspended
browsers, killed PHP requests, and no-traffic periods provide no scheduling guarantee.

A host cron service may call the same fixed public wake-up URL and receives the same empty response;
it needs no secret merely for supplying that execution opportunity. A distinct cron/control route
that changes scheduling, forces work, accepts controls, or exposes receipts is protected: authenticate
its caller and authorize that capability. Prefer a rotatable secret header; URL-only hosting controls
require hashed secret storage, constant-time comparison, private URL/access-log handling, no-store,
and no redirect/referrer exposure. Browser-session management actions use normal authentication,
authorization, CSRF, and origin defenses. The public signal cannot access those controls or logs.

An optional ordinary-response FPM trigger may finish a page response through
[`fastcgi_finish_request()`](https://www.php.net/manual/en/function.fastcgi-finish-request.php)
before entering the same manager lock. This does not change the separate wake-up URL's empty-response
contract or guarantee continued runtime: the host's
[FPM termination policy](https://www.php.net/manual/en/install.fpm.configuration.php) still applies.
Keep the explicit wake-up request available without early-finish support. No slow model work belongs
inside ordinary rendering or an unbounded shutdown callback. Long cooldowns remain persisted due
times; release the request when no work can run rather than occupying it with long idle sleeps.

Cron is preferable for steady opportunities. Enabling it does not register an external scheduler.
Disabled automatic execution preserves durable work and exposes its paused condition in admin;
manual/CLI execution follows explicit host policy and the same ownership/resource limits.

## 11. Shared administration

Provide one authorized job administration area for all job types, with narrower capabilities for
viewing diagnostics, retrying, cancelling, rebuilding, and changing runtime settings. Scope every
query and mutation, including alternate endpoints and exports, under the same authorization policy.
Keep unrelated tenant content inaccessible even when record IDs collide.

The common views show:

- Backlog by state/type, due/oldest age, last successful run, next due work, and missing trigger activity.
- Job target revision, completed/remaining steps or known work units, valid saved progress, active
  ownership/expiry, retry due time, and a plain explanation of the current blocked/failed reason.
- Queued/running/cancelling jobs by producer, equivalence key, subject, and strategy, with handles,
  the captured source revision, cancellation reason/request time/stop confirmation, and navigable
  predecessor/successor links. New requests are visible immediately, before any worker starts them.
- Creation metadata rendered as labeled key-value fields and filterable through each job type's
  declared searchable keys, such as `documentId`; distinguish it from fresh source data and logs.
- The single active manager, effective native-thread/sequential mode, configured/effective job slots,
  occupied workers, fallback reason, current request deadline, and last checkpoint observation.
- A stable ordered timeline of attempts, checkpoints, retries, interruptions, and publication.
- Request success/failure/rejection counts and consumed known/unknown resources, including failures.
- Filters for time range, job type, job/subject reference, state, severity, step, attempt, reason,
  producer, equivalence key, replacement chain, trigger, provider, model, and account reference where
  available.
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

For example, a business action requests a summary for document D; metadata search can identify it as J1. While J1
is queued, a message arrives: the action calls `refreshJob`, which returns 0 and keeps J1. Its worker
loads current messages/fields on start. While J1 is running, an old message is edited: the edit
transaction calls `refreshJob`, marks J1 cancelling and queues J2. Another edit before J2 starts reuses
J2. The manager compares no source revisions. J1's late response is accounted for but cannot publish.
After J1 stops, J2 loads fresh data and reuses only domain-verified compatible accepted progress.

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

1. **Contract and lifecycle:** unknown handler/version rejection, checked metadata/output/cursor,
   one-step and multi-step jobs, allowed transitions, disabled execution, and finite resource bounds.
   Prove automatic class-to-type resolution, unregistered-class/duplicate-registration rejection,
   and persisted type stability when a handler class is renamed without changing its registered type.
   Include atomic equivalent-job lookup/create, producer tracking, current completed-output no-op,
   repeated enqueue, and distinct event-ID lookup without queued input snapshots.
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
   Prove one active manager across trigger types, a nonwaiting busy path, and no overlap after a
   lease expires while the old session still executes. On PHP, prove native thread slot bounds,
   isolated worker dependencies, provider limits across threads, worker cleanup, and sequential
   selection when thread support is absent. Exercise the real native adapter on a capable test
   runtime before claiming threaded support; fake executors prove policy only.
5. **Remote uncertainty:** provider idempotency/query reconciliation, repeat-safe duplicate generation
   accounting, irreversible-effect blocking, and abandoned “started” AI records closed as unknown.
6. **Budgets and retry:** remaining request-time admission, queue wait/backoff counted, a step that
   never fits shown blocked, bounded discovery and fair scheduling, cumulative allowances across
   visits, cooldown preservation, and no nested retry multiplication.
7. **Cancellation (mandatory for every job type):** test cancellation before the first effect,
   during an external call, after its response, between phases, and immediately before domain commit.
   Include repeated cancellation, cancellation concurrent with completion/refresh, successor reuse,
   stale worker return, partial committed progress, and interrupted cancellation recovery. Assert
   no partial/corrupt publication, no duplicate irreversible effects, accurate usage receipts and
   no replacement overlap. A new job type is incomplete without its applicable normal/edge tests.
8. **Logging and admin:** automatic lifecycle events for every job type, diagnostics with trusted
   context, failed sink behavior, unique request usage totals, unknown/partial usage, interrupted
   attempts, indexed filters/pagination, scoped access, CSRF, safe rendering, and protected artifacts.
9. **Triggers and deployment:** public wake-up without a login/session, exact no-control decoding,
   empty bodies for busy/no-work/budget-end paths, no application call-rate gate, traffic bursts/tabs,
   no early-finish capability, connection abort, no-traffic pause, and no HEAD execution. Protected
   controls retain unauthenticated/unauthorized rejection, CSRF, and cron-secret rotation. Verify
   previous-schema migration, definition upgrade, restart, source erasure, and deployment rollback.
10. **Fresh inputs and supersession:** append/edit/metadata changes before queued work starts; a
   consistent start-time snapshot; source-change versus claim races; running cancellation plus
   one successor atomically; repeated changes reuse the queued successor; no successor dispatch
   until equivalent old execution stops. Verify expiry, manual queued cancellation, cancellation
   during native/blocking calls, bounded local cancellation checks, old result rejection at every
   commit boundary, recovery never revives cancelled work, and retained compatible checkpoints,
   replacement links, and known/unknown usage in both execution modes and the admin timeline.
11. **Metadata-only creation and lookup:** reject business-input arguments and representative
    nested/encoded source bodies that violate declared metadata schemas; reject wrong scalar types,
    unknown/reserved fields, and size overflow.
    Prove required-reference validation, immutable metadata on reuse, key-order-independent identity,
    missing/null and string/integer distinctions, declared metadata filters, bounded indexed pages,
    tenant/owner isolation with colliding metadata values, safe admin rendering, and no arbitrary
    query execution. Verify both state-derived jobs and event-ID jobs load their canonical records
    when execution starts, and incompatible legacy input payloads cannot run without migration.

The process-death harness uses barriers or recorded signals to choose interruption points, not
arbitrary sleep timings, production workers, paid providers, or the developer's normal database.
Run it in the project's isolated toolchain. If the platform cannot run a required process test,
report that gate unavailable; do not substitute mocked timeouts and claim hard-interruption proof.

For adoption, record the host capability profile and time limits, registered job definitions,
checkpoint compatibility policy, canonical storage/event owners, retry/resource ceilings, migration
and recovery procedure, log retention/access, and verification evidence. Map existing job records
and provider ledgers explicitly; preserve accepted outputs and historical receipts during migration.
Drain or stop executors before changing the ownership protocol. Legacy workers may not participate
in the new manager lock; marking their rows interrupted during migration cannot prove they stopped.
Preflight existing referential-integrity defects before rebuilding legacy tables. Preserve orphan
attempt receipts in an explicit blocked legacy identity or protected archive; never silently delete
them or turn missing business identity into runnable work. Compare pre/post integrity defects so
unrelated existing corruption is distinguished from new migration damage.
When upgrading payload-based queued jobs, an explicit migration may extract validated reference
metadata and reconnect it to canonical sources. Never rename the old input blob to `metadata` or
silently treat its stale contents as fresh data. Block unmappable jobs for review; keep legitimate
accepted checkpoints only under the declared compatibility checks.
This document specifies the target contract. A blueprint update alone does not change runtime code,
qualify a model, enable a worker, or prove an implementing project's conformance.
