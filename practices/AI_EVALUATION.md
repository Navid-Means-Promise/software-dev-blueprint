# AI evaluation

Authoritative for functional qualification and live quality, latency, and cost evaluation of
assistants, retrieval, and speech. Deterministic tests still prove application invariants; a model
benchmark supplies observational evidence about a particular prompt, provider route, configuration,
and workload. Run billable evaluations through the explicit bounded runner required by
[live provider smoke checks](TESTING.md#live-provider-smoke-checks), outside the ordinary
deterministic suite and CI.

## Required functional acceptance suite

Every shipped AI capability must have a maintained, product-owned functional acceptance suite.
Define its scenarios and pass criteria before comparing candidate models. Each case specifies the
initial application state, conversation/input, expected visible behavior and canonical effects,
forbidden effects, and how the outcome is asserted. Cover normal use, multi-turn follow-ups,
ambiguity/clarification, corrections, read-only requests, invalid or unsupported actions, and
previously observed failures for the capability. Include supported locales and required tool/read
protocols. Grade observable behavior and resulting state under the real application contract.

Every selectable candidate, including a fallback model, must pass the required functional cases for
its assigned capabilities before adoption or promotion. Exercise the actual candidate through the
production prompt, decoder, orchestration, and applicable retrieval path; fake-model tests prove
application safeguards but cannot establish a candidate's functional ability. Keep mandatory cases
separate from optional quality scores: low price, fast replies, or a high aggregate pass rate cannot
compensate for a failed required function or an unauthorized write. Document repeat-run counts and
per-case acceptance thresholds in advance so stochastic results cannot be cherry-picked. Any
observed forbidden effect fails qualification regardless of aggregate scores.

Rerun affected acceptance cases when a model/version, prompt, response contract, tool policy,
retrieval strategy, or routing configuration changes shipped behavior. Retain passing evidence for
the exact evaluated configuration before approving the change; unavailable, skipped, or incomplete
required cases leave qualification pending. Use the
[pre-merge verification cadence](../principles/WORKING_AGREEMENT.md#pre-merge-verification) for changes
on a task branch and the explicit bounded live runner above. This requirement does not place live
provider calls in ordinary CI.

Required boundary cases include prompt injection in user/retrieved text, forged tool authority,
cross-user data access, stale revisions, and attempts to treat an assistant suggestion as consent.
Use controlled model-output fixtures to prove application rejection regardless of what a provider
generates, and live candidate cases to assess correct tool selection and evidence use. A dangerous
proposal blocked by the application is distinct from a committed forbidden effect; record both the
rejection/repair and final outcome. For time-sensitive capabilities, fix the trusted clock/time zone
and cover relative-date rollover, daylight-saving ambiguity, past/future restrictions, and expiry.
Add correction/deletion/restart journeys to detect resurrection of obsolete context.

Qualify fallback models independently with fallback disabled, then exercise the configured routing
path under controlled primary failure. Record whether bounded repair was needed; passing after
repair does not erase the cost, latency, or initial rejection.

## Preserve the behavior being investigated

Use synthetic data by default and isolate each candidate's canonical state. Exercise the production
prompt, decoder, orchestration, retrieval, and routing configuration. A simplified benchmark prompt
or permissive decoder does not prove production behavior. Isolate a single model when comparing
model capability; separately label a production-routing replay that includes fallback.

Maintain ordered multi-turn journeys covering product-required fact creation, correction, recall,
ambiguity, domain actions, and memory/domain ownership. Include read-only turns with explicit
assertions that no canonical records changed: a plausible answer can conceal an unsupported write.
Check resulting records/revisions and truthful receipts, not just that some tool was called.

Preserve exact prior user/assistant turns when reproducing a contextual failure. A fresh short
question without its original exchange cannot reproduce ellipsis, objections, corrections, or
rejection of a suggestion. Include supported locales, casual language, code-switching, and the same
behavior expressed across several domain topics, so a scenario-specific phrase fix cannot masquerade
as a general improvement. For retrieval, use a labeled corpus with expected relevant records,
known irrelevant distractors, and access partitions; measure precision/recall as well as latency.

Before changing prompts, retrieval, reasoning effort, or models, inspect what context actually
reached the failed turn. Diagnose missing context, irrelevant context, contract ambiguity, semantic
reasoning, and transport failure separately. Repair the shared cause and replay the original failure
plus representative neighboring cases. Do not infer missing memory from an unhelpful reply alone.

## Retrieval and speech qualification

Evaluate embedding/retrieval candidates independently from assistant generation. Use expected hits,
permitted supporting hits, irrelevant distractors, and queries requiring no stored context. Include
multilingual paraphrases, cross-language queries, social/external questions, and unsupported personal
claims. Report false positives and misses separately, with per-language results; a greeting that
retrieves an unrelated private memory is a relevance failure even if the assistant ignores it.
Calibrate thresholds per model/configuration and verify them on separate acceptance cases so tuning
does not become its own proof. Exercise each fallback vector space and the assembled retrieval path,
including incomplete coverage and source rehydration. The
[embedding adapter contract](../architecture/AI_CONTEXT_RETRIEVAL.md#embedding-adapter-contract)
defines runtime validity separately from relevance quality.

When speech ships, qualify transcription and synthesis for supported locales/devices independently
of the assistant model. Include recognition of negation, names, numbers, dates/times, ambiguous
transcripts, unavailable voices, permission denial, cancellation, and interruption. A text-only
journey does not qualify a voice pipeline. Resulting domain effects still use the same functional
assertions as typed turns.

## Separate state correctness from language quality

Use deterministic assertions for authorized effects, no-op/read-only turns, exact revisions,
evidence scope, uncertainty preservation, and absence of duplicate memory/domain state. These
assertions remain decisive even if the visible answer sounds good.

Evaluate natural-language meaning against a complete behavioral rubric rather than exact wording
or keyword presence. An independent semantic judge may assist, but its output is another untrusted
provider response: validate case IDs, uniqueness, completeness, declared fields, and bounded
evidence-based reasons. Delimit candidate text and conversation as data; they cannot rewrite the
rubric. Record judge model/configuration, latency, usage, and cost separately from the candidate.
An unavailable or malformed judge result leaves evaluation incomplete; it does not become a pass or
evidence of candidate failure.

Retain the original candidate reply, state assertions, rubric version, and judgment so disputed
judgments can be reviewed or rescored without regenerating the answer. A rubric correction is a new
evaluation of the preserved evidence; report both the original and corrected result and why it
changed. A model judge does not replace deterministic authorization or mutation validation.

### Test the evaluator

Unit-test scenario assertions, judge decoders, aggregation, and checkpoint validation with fixed
passing and failing fixtures. Prove that a required write cannot pass as a conversational no-op,
wrong-target/partial writes cannot satisfy an expected effect, read-only cases reject mutations,
and missing/duplicate cases or judge failures cannot inflate a score. Include quoted rubric
injection and keyword-only replies when reviewing judge behavior. Review representative judgments
against their rubrics before relying on automatic scores; a strict JSON decoder alone proves no
semantic judging accuracy.

## Measure the user interaction and its costs

Measure the complete response interval and content-free stage timings using the
[provider operations guidance](../architecture/AI_PROVIDER_OPERATIONS.md#diagnostics-and-sensitive-evidence).
Report per-turn latency, distribution and budget misses, retrieval/model round counts, validation
repairs, provider failures, prompt growth, and input/output/cache usage. Separate visible-response
time from background indexing drain, judge work, and post-run accounting. Functional quality and
network-sensitive latency assertions are separate outcomes; when timing enforcement is disabled,
say so. Do not infer interactive performance from aggregate suite wall time alone.

Report known generation, embedding, judge, and accounting-operation costs separately and state any
exclusions. Use the requested and actually served provider/model routes and
[accounting rules](../architecture/AI_PROVIDER_OPERATIONS.md#prompt-caching-and-truthful-accounting);
headline catalog prices and configured cache hints do not prove billed cost or savings.

Keep availability and quality outcomes distinct. An unavailable route, rate limit, credential/key
spending limit, interrupted run, or missing evaluation is an operational/incomplete result, not a
zero-score model-quality result. A returned response that violates the required contract is a real
compatibility failure on the tested route. Report attempted/completed/evaluated counts and failures
alongside any pass rate rather than silently omitting failed turns.

## Make comparisons reproducible

Version or fingerprint the source revision, prompt/response contract, decoder/protocol, ordered
scenario definitions and initial-state fixtures, assertions/thresholds, rubrics and judge
model/protocol, candidate model and reasoning settings, embedding configuration, routing/fallback
policy, price/output limits, retry/deadline bounds, and relevant latency policy. Record run time,
requested and served versions/routes, and cache observations; remote providers remain variable even
when the local fingerprint matches.

Checkpoint completed work atomically with its identity and ordered results. Resume only a validated
completed prefix under the same fingerprint; a changed prompt, scenario list, model order, or policy
requires a separate run. Preserve partial reports and record the interruption instead of combining
incompatible runs into a better-looking score. Resume at completed candidate boundaries; restarting
mid-journey requires restoring validated application state/transcript or replaying that journey from
the start. Evidence containing private conversations follows the same protected diagnostic policy
as runtime captures.

Choose a default only after repeated clean runs meet the product's correctness, interaction-latency,
and cost requirements together. Keep per-scenario regressions visible; a higher aggregate pass rate
does not excuse unsupported writes or unacceptable response times. Benchmark selection establishes
the initial configuration; optional runtime health routing remains owned by
[provider operations](../architecture/AI_PROVIDER_OPERATIONS.md#optional-latency-circuit-breaker).
