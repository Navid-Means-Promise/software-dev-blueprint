# AI provider operations

Authoritative for model-provider deadlines, transport resilience, endpoint/model routing, prompt
caching, usage accounting, and operational diagnostics. The
[AI subsystem](AI_SUBSYSTEM.md) owns model-output validation and application effects;
[AI evaluation](../practices/AI_EVALUATION.md) owns live comparison evidence. These policies live
behind provider adapters and do not change domain contracts when a provider is replaced.

## Bound the complete operation

Provider-side fallback does not remove the application's resilience responsibility: an upstream can
fail after fallback is exhausted or too late for the router to replay safely. Distinguish four
mechanisms, each with an explicit bound:

| Mechanism         | Trigger                                                          | Permitted response                                                                   |
| ----------------- | ---------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| Transport retry   | Transient network, timeout, overload, or provider unavailability | Replay the same model-analysis input with short bounded exponential backoff.         |
| Output repair     | A returned response fails application decoding or validation     | Use the [validation-aware repair protocol](AI_SUBSYSTEM.md#validation-aware-repair). |
| Endpoint fallback | An approved host of the same model is unavailable                | Let the router choose another policy-compliant endpoint.                             |
| Model fallback    | A fallback-eligible failure or sustained latency breach          | Select the next approved model with its own compatible request configuration.        |

Never transport-retry or model-fallback authentication, payment, permission, request-validation, or
content-policy failures. A rejected output stays in the bounded repair path; exhausting it is a
visible rejected-response failure, not permission to try models until one accepts the operation.

Set both per-request timeouts and a total interactive-operation deadline. Retries, repair calls,
backoff, and model fallback consume that total; no inner loop resets it. Bound retrieval rounds at
the orchestrator as well. Use a monotonic clock for elapsed time and injected clock/scheduler ports
for deterministic tests. Honor `Retry-After` only when it fits the remaining budget; otherwise fail
without an immediate retry. Avoid multiplying router, adapter, and orchestrator retry loops: declare
the maximum total provider requests and which layer spends each allowance.

A background pipeline may reserve several execution windows inside one fixed outer task deadline.
For example, three chances total share two retry allowances across extraction, repair, and selection;
a stage does not receive an independent three-chance loop. Each retry gets a bounded inner window
from the remaining reserved total, with two/four-second backoff. All preparation, waiting and calls
inside the task count toward that outer deadline; separately admitted startup discovery must have
its own reported bound. Set the active-job lease for the complete task. Reuse accepted intermediate
knowledge when only a derived projection failed, with normal freshness checks. SDK/router loops
must not multiply this host allowance or reset shared quotas/cooldowns.

Replay preserves the trusted turn envelope and performs no domain mutation before a final response
crosses application decoding, validation, and authorization. Desired-state commands and trusted
idempotency identities protect later delivery boundaries; provider retries never replay committed
effects.

## Admission and resource bounds

Apply the server's [per-principal resource/rate policy](../components/SERVER.md#identity-authority-and-tenant-isolation)
before expensive provider work. A turn admission is not unlimited permission for inner calls:
retrieval, repair, fallback, and hedging consume the declared operation allowances. Bound active
requests, queued work and queue wait, background embedding concurrency, and resident user-runtime
caches. Eviction must preserve durable state and must not discard an active turn's coordination.
Queue wait counts toward the user-facing deadline; align server and client timeouts so an expected
server workflow does not routinely outlive its caller. A client disconnect does not undo committed
effects; recovery follows [turn consistency](AI_SUBSYSTEM.md#turn-consistency-and-failure-recovery).

Before sending a request, budget the complete prompt, not only retrieved records: stable
instructions/tools, baseline/pinned values, recent conversation, current input, and tool results
must fit the selected model's context window with reserved output capacity. Bound input/media sizes,
returned response size, and cumulative tokens across continuations. Never trim serialized data into
invalid JSON or silently drop required instructions/evidence to make a request fit. Reject or
explicitly omit optional context under the declared completeness policy. Token estimates are
admission estimates, separate from provider-reported usage.

Reserve output for both hidden reasoning and visible content when the provider shares their token
budget. Different stages may use independently qualified reasoning settings: a short projection must
not inherit a reasoning allocation that consumes its entire response allowance. Fingerprint stage
overrides, preserve the total deadline/price policy, and retain reported reasoning usage and finish
reasons so truncation can be distinguished from invalid meaning or provider unavailability.

## Route within explicit policy

Keep endpoint routing and application model priority separate. Every permitted endpoint/model must
already satisfy the project's privacy, data residency, capability, and reliability policy. Within
that owner-approved, fully configured set, leave router fallback enabled by default. Pinning or
excluding an endpoint is an explicit policy decision, not a routine response to one transient
failure.

When cost is a requirement, enforce hard input/output unit-price ceilings on every primary and
fallback request where the provider supports them. Bound output tokens and aggregate request spend
separately: a unit-price ceiling alone does not cap a turn's cost. Within the eligible pool, prefer
health-aware routing; soft latency/throughput preferences can favor faster endpoints while retaining
slower fallback capacity. Sorting solely by headline price does not establish acceptable interactive
reliability. If no endpoint satisfies a hard constraint, report unavailability rather than silently
relaxing it.

Each configured model retains its own supported output, reasoning, and cache settings. An ordered
fallback must build a compatible request for the selected model instead of replaying another
model's vendor-specific options. Reasoning effort is a measured product choice; compare it under
the same quality, latency, and cost budgets before increasing it.

### Optional latency circuit breaker

A benchmark establishes a default priority, not permanent runtime health. When sustained provider
latency warrants adaptive routing, define a bounded per-model sample window, minimum sample count,
slow-call threshold relative to the product target, breach rate, and cooldown. Measure logical
model-call latency including its corrective calls, not only the last successful HTTP request.
Fallback-eligible availability failures may count as unhealthy; authentication, billing, contract,
internal-application, and rejected-output failures do not train this health signal.

Use a time-based cooldown and exactly one in-flight half-open recovery probe. A healthy probe clears
old samples; a slow or unavailable probe reopens the cooldown. Concurrent older completions must
not undo an open circuit. Latency preference alone must not skip the last eligible configured model;
hard policy constraints and the total deadline still apply. Document whether circuit state is local
to a process or shared across instances. Do not introduce adaptive routing without measurements
that justify its complexity.

## Prompt caching and truthful accounting

Keep stable instructions and the reusable response contract first, followed by dynamic turn data.
Use provider-supported cache breakpoints/affinity only behind the adapter. Affinity is an opaque
optimization, not a durable conversation identity or proof of a cache hit. Record reported cache-read
and cache-write tokens; do not add intent classifiers, alternate greeting prompts, or padding to
manufacture hits. Full-response caching is unsafe when exact replay of dynamic private or domain
context is not explicitly valid.

Count every attempted provider request and accumulate known usage across retrieval continuations,
rejected responses, repairs, retries, and model fallback, including a failed logical turn. Persist
billable usage independently of whether the proposed domain mutation commits. Do not attribute the
whole total to the final model when several models served requests; retain per-attempt route and
usage evidence. Missing provider accounting is unknown, not zero cost.

Distinguish catalog-price estimates from provider-reported billed cost: the selected endpoint can
charge differently from a model's headline price. Reconcile generation-level accounting when exact
cost or cache savings are needed. Include cache-write surcharges, which can make net savings
negative; if required accounting is missing, report savings as unavailable. Fetch diagnostic billing
metadata outside the timed user-response interval.

Specify failure behavior for accounting and telemetry stores. Diagnostic persistence failure is an
internal operation failure, not malformed model output or a reason for model fallback. Noncritical
telemetry failure must not replay analysis or suppress a committed result; report the gap and repair
it durably when available. If a billing/audit policy requires fail-closed admission, stop before
effects instead of discovering the requirement after commit or representing missing usage as zero.

## Diagnostics and sensitive evidence

Audit validation failures, bounded corrective retries, requested and served model versions, latency,
and reported token usage. Report failed request attempts, rejected outputs, retries, recovered
stages/tasks, exhausted tasks, and eventual completion separately. A final success never overwrites
an earlier failure receipt; usage and timing include every attempt, with unknown usage left unknown.
Correlate content-free stage durations/counts under a trusted turn ID:
queue wait, context preparation, model/provider work, retrieval, validation, usage persistence,
canonical transaction, and reply persistence. Define overlapping/parallel stages so their sum is not
misrepresented as end-to-end latency. Ordinary logs exclude message, prompt, memory, and plan text.

When protected raw diagnostics are enabled, retain the exact instructions and complete provider
response body—including additive routing metadata—under the application's separately protected
sensitive-payload access, retention, and deletion policy. Never capture transport credentials or
authorization headers. The [proportionate safeguard rule](../principles/DEVELOPMENT_PRINCIPLES.md#7-trust-boundaries-are-not-mvp-compromises)
permits the vendor-shaped artifact as bounded diagnostic evidence, not canonical application data.

When a router offers routing diagnostics, opt in explicitly. Independently decode a content-free
projection such as generation ID, requested model, strategy/region, selected provider/model,
attempt count, and each fallback attempt's provider, model, and status. Omit summaries, pipeline
payloads, raw upstream errors, headers, and unknown fields from that structured projection—not from
the protected complete-response artifact. Capture success and error paths under the diagnostic
retention policy.

## Verification

Recording transports and injected clocks/schedulers prove failure classification, request/deadline
bounds across combined retry/repair/fallback paths, per-model option mapping, hard-cap preservation,
usage from failed/rejected attempts, and safe diagnostic projections. If a latency breaker is
adopted, prove isolated slow samples, sustained breaches, concurrent probe exclusion, cooldown,
recovery, irrelevant-error exclusion, and last-model availability. Prove unknown accounting cannot
be reported as free usage or exact savings. Admission tests prove rate/concurrency/queue bounds,
whole-prompt/output reservation, caller/server deadline alignment, and that internal diagnostic
failures cannot trigger model repair/fallback or duplicate effects. Live-provider evidence follows
[AI evaluation](../practices/AI_EVALUATION.md) and the normal
[pre-merge verification policy](../principles/WORKING_AGREEMENT.md#pre-merge-verification).
