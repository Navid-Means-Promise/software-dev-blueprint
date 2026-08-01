# AI subsystem architecture

Authoritative for assistant orchestration, application-owned durable context, context-retrieval
choice, model-requested domain actions, and speech input/output. Every domain entity,
remembered-context category, tool name, and proposal item remains in the implementing project's
documentation.

## Boundary and dependency direction

An AI model is an interchangeable, untrusted interpreter at the edge of the application. It does not
own conversation memory, user context, or domain state, and it never writes a repository directly.
The shared domain validates and executes typed requests through the same services used by the UI.

```text
Shared assistant conversation UI
  ├─ AudioCaptureProvider
  │    └─ SpeechToTextProvider
  │         └─ server-observable text transcript
  └─ typed or transcribed text
       └─ AssistantConversationProvider (authenticated client transport)
            └─ server conversation use case
                 ├─ trusted principal, clock, scope, and policy
                 └─ AssistantTurnOrchestrator
                      ├─ relevant product-specific domain state
                      ├─ relevant records from MemoryRepository
                      └─ AssistantModelProvider
                           └─ decoded, authorized domain-tool requests
                                ├─ MemoryManagementService
                                ├─ product-specific application services
                                └─ DomainChangeProposalService

Assistant response text
  └─ TextToSpeechProvider
       └─ platform or remote speech-synthesis adapter
```

`AudioCaptureProvider`, `SpeechToTextProvider`, `AssistantModelProvider`, and
`TextToSpeechProvider` are independent.
A project may replace speech recognition, the assistant model, and speech synthesis separately.
Direct audio-to-audio model sessions are an additional adapter path; they must still deliver a
server-observable text representation and typed tool requests to the same orchestration and domain
services.

Provider credentials and model connections belong in the server that owns the provider adapter.
Interactive clients send text or media through bounded authenticated application endpoints; a
short-lived provider credential is not, by itself, a substitute for server-owned orchestration and
effect validation.

For browser/WebView clients, the authenticated server use case owns turn orchestration and durable
conversation, memory, usage, retrieval instructions, and application records. The client does not
assemble the authoritative prompt, select trusted scope/time/identity, execute retrieval, validate
model effects, or write repositories. Shared packages may still provide contracts and pure rules for
both hosts, but client-side evaluation is presentation feedback—not authority. Client storage is
limited to transient cache/session state and explicitly device-local preferences. Provider work,
embedding, derived-index maintenance, retention jobs, and durable audit storage execute on the
server. This blueprint defines no client-side offline write queue for assistant or application data.

## Application-owned durable context

An assistant may need durable user-supplied context beyond the current conversation. That context is
application data: a current-state repository stores it, the user can inspect and control it, and the
application selects the minimum relevant records for each request.

The blueprint does not prescribe a product-specific memory name, semantic category list, structured
assertion vocabulary, or claim of objective truth. When a project adopts general natural-language
assistant memory, use one canonical `MemoryRecord` collection. One record contains the
smallest coherent information that can be corrected, expired, or deleted independently. A correction
updates the same stable ID and revision; forgetting deletes the current record. The original raw
message remains separately available as evidence through an evidence-message reference, so canonical
memory does not duplicate the message in a `sourceText` field.

Assistant memory is not a universal replacement for application state. Route a sufficiently exact,
authorized domain mutation to the application service that owns that state, including related notes
or descriptions its contract supports. Do not save a duplicate memory record for the same
proposition. An uncertain intent may remain memory until it can satisfy the domain command contract;
successful promotion removes the now-redundant memory. Other durable user knowledge belongs in
memory unless another application domain already owns it. Casual conversation remains raw messages.
Promotion is one coordinated operation: commit the authoritative mutation before deleting memory,
and use a shared transaction or idempotent recovery when the stores cannot commit atomically.

Applications may derive revision-bound time scopes, semantic cues, embeddings, and product-specific
selection indexes from current memory. These are disposable lookup projections, not additional
sources of truth. Every hit reloads the canonical record and must match its exact revision. Relative
time is resolved with the injected clock and user time zone; date and clock-time precision remain
independent. See the complete optional pattern in
[experimental assistant context memory and retrieval](AI_CONTEXT_RETRIEVAL.md).

Canonical memory is the mutation commit point. Failure to maintain a disposable index after that
commit is a deferred-repair result, not failure of the accepted memory write. Exact retry identities
must converge without creating another canonical record or revision.

### Retrieval choice

For a requested domain operation, select only current, unexpired, authorized records that match the
requested domain and time scopes and are relevant to the operation. For a bounded corpus, start with
deterministic filtering and explicit domain references. Add lexical, embedding, vector, or hybrid
retrieval only for a measured conversational-recall requirement. Send the model selected canonical
content, never an unbounded archive or raw index entries by default.

A project that needs application-owned memory and relevance retrieval across raw messages, other
processed conversational sources, and application-specific data may explicitly adopt the
[experimental assistant context-memory-and-retrieval design](AI_CONTEXT_RETRIEVAL.md). That design
is optional, not the default stack requirement. A project may instead choose a ready-made
agent/context solution after documenting where authoritative application data, privacy controls,
and provider replacement boundaries remain.

## Typed domain tools and execution policy

Model output is untrusted boundary input even when the provider guarantees syntactically valid JSON.
The server decodes it into a closed union of named domain-tool requests, validates semantic values,
authorizes the operation, and executes only through application services.

### Model-output representation

Comprehensive application validation is mandatory. Provider-enforced structured output is an
optional decoding aid, never the trust boundary: the application still parses the response, decodes
only declared variants and fields, validates semantic and cross-record invariants, authorizes every
effect, and rejects invalid output with a bounded retry policy.

Choose the smallest provider-facing representation that remains clear to the model:

- use function calls for independently invocable reads and actions, exposing only the definitions
  relevant to the current stage when the provider supports that flow;
- use a compact prompt contract when one response must combine conversation text, hidden hints, and
  several optional typed requests; or
- use provider-enforced JSON Schema only while its supported subset, nesting, serialized size, and
  measured token cost remain acceptable.

A prompt contract may use a semi-flat TypeScript-like notation: name every object and union, and
make each complex field reference another named definition instead of repeating an inline nested
structure. Tell the model to return one JSON value with no undeclared fields; a provider's JSON MIME
mode may reinforce syntax without supplying a response schema. The notation guides generation but
is not executable validation.

The application contract and decoder remain authoritative. Do not let a handwritten prompt become
a competing source of truth: generate its model-facing representation from shared contract metadata
when practical; otherwise colocate it with the adapter and enforce structural parity in tests.
Measure the UTF-8 bytes and observed input tokens of the complete static request configuration, set a
regression budget, and split the interaction or relevant tool set when growth is no longer justified.
Audit the exact instructions, raw provider response, validation failure, bounded retry, model version,
latency, and reported token usage under the application's sensitive-log retention policy.

Mutation tools express the desired resulting state rather than a toggle and target the exact
revision the model was supplied: delivery retries must converge on the same state instead of
reversing a successful action. The application may derive an idempotency identity from its trusted
turn/request envelope; it never relies on a model-supplied claim that a request is explicit or unique.

Classify tools by effect:

| Effect class                                            | Execution policy                                                                                                                |
| ------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| Read-only query                                         | Execute after validation.                                                                                                       |
| Explicit, unambiguous user request                      | Execute the validated desired-state mutation and show a truthful receipt. A later conversational request may edit or delete it. |
| Durable-memory recording                                | Save current memory before asking about optional follow-up action; show a create/update/delete receipt.                         |
| Multi-part or interpretive domain change                | Create a proposal; do not mutate domain state.                                                                                  |
| Destructive, irreversible, or externally visible action | Require explicit confirmation immediately before execution.                                                                     |

The policy is domain-owned and provider-independent. A prompt may guide tool selection but cannot
grant authority.

## Domain change proposals

A `DomainChangeProposal` is a reviewable diff against a named base revision or hash. It contains
stable item identifiers, additions/removals/modifications, the durable-context record identifiers
motivating each item when applicable, detected conflicts, and a human-readable explanation.

The shared UI gives every proposal item an explicit `accepted`, `rejected`, or `undecided` review
state and offers accept-all/reject-all shortcuts without hiding item-level control. It shows current
and proposed values side by side or on the same domain visualization. Application requires at least
one accepted item, revalidates the base revision and every accepted item, and applies the accepted
subset atomically. Rejected items never reach the domain service.

The visual treatment is product-specific, but clarity is not: color alone never conveys acceptance,
conflict, or removal; the user can identify the affected entity and time; and confirmation remains
reachable without losing the review position.

## Speech input and speech output

The first implementation should normally convert speech to text before assistant reasoning:

1. `AudioCaptureProvider` records a push-to-talk utterance.
2. `SpeechToTextProvider` produces a transcript and confidence/alternative data.
3. `AssistantTurnOrchestrator` processes typed and spoken text identically.
4. When transcription or intent remains ambiguous, the assistant asks a concise clarification; a
   consequential operation follows its normal confirmation policy.
5. `TextToSpeechProvider` optionally speaks the final response.

Showing an editable transcript is a product UX choice, not an authority requirement. A product may
keep the transcript hidden and rely on clarification, confirmation, and truthful mutation receipts;
the server still retains the bounded evidence/audit data required by its declared policy.

This separation preserves provider choice, permits independent language-quality testing, and avoids
retaining raw audio. Raw recordings are deleted after transcription by default.

Prefer a platform speech-synthesis adapter for the first `TextToSpeechProvider` implementation when
its required locales pass device testing; it avoids a remote dependency and per-request service cost.
A remote speech-synthesis adapter remains replaceable and may be selected for consistent voice
identity or missing locale quality. `TextToSpeechProvider` exposes capability/voice availability and
completion/interruption results so an unavailable voice degrades to visible text rather than startup
failure.

## Persistence, privacy, and provider state

Durable assistant memory and transcripts can contain sensitive personal information. A production
design provides user-visible inspect/edit/delete/export controls, authenticates and authorizes every
server request, encrypts server databases and backups with managed key lifecycle, partitions every
query by owner/tenant before ranking, and records the minimum diagnostic content. Raw audio is not
retained by default.

Do not use a model provider's conversation identifier as durable product memory. Store short-term
conversation state under product control, send only the turns or summary required for the current
request, and allow a provider adapter to operate statelessly.

An explicitly constructed process-local repository may support an isolated deterministic unit or
component test. It is not a runnable development server, deployment mode, or fallback. Every
networked environment starts with authenticated principals, tenant-scoped durable storage, server
authorization and validation, defined transaction/concurrency behavior, and protected provider
credentials as required by
[development principles §7](../principles/DEVELOPMENT_PRINCIPLES.md#7-trust-boundaries-are-not-mvp-compromises).

## Required MVP compromise register

Every project that ships a reduced AI subsystem keeps a linked compromise register in its own
`docs/` directory. Each compromise records:

- a stable identifier and status;
- the complete production behavior being deferred;
- the temporary MVP behavior and why it accelerates delivery;
- privacy, correctness, cost, UX, and migration risks;
- safeguards that remain mandatory in the MVP;
- the measurable replacement trigger and target milestone; and
- affected contracts, storage, tests, and migration work.

An undocumented shortcut is not an MVP decision; it is invisible debt. The project architecture and
roadmap link to the register, and closing a compromise records the replacement rather than deleting
the history. Authentication, authorization, tenant isolation, server-side validation, canonical
write integrity, and secret protection are not eligible compromise entries; reduce feature breadth
instead of removing those foundations.

## Module ownership and tests

- `@app/shared` owns memory and application-domain contracts, reusable pure domain services and
  repository/provider interfaces, assistant orchestration policy, adopted context-retrieval
  contracts, tool policies, proposal rules, and all provider/host interfaces.
- `@app/ui` owns the shared conversation, optional transcript-correction, clarification,
  context-receipt, proposal-review, and mutation-receipt presentation.
- `apps/web` and `apps/mobile` own the bounded authenticated conversation transport, audio capture,
  platform speech synthesis, transient caches, and explicitly device-local preferences.
- `apps/server` owns the authenticated conversation use case, authoritative orchestrator execution,
  model-effect validation and domain-service execution, assistant-model and remote speech adapters,
  credentials, rate limits, canonical persistence, remote embedding, derived indexes, retention,
  and audits.

Domain tests use fixed clock, model, speech, repositories, and delivery test doubles. They prove
product-required temporal overlap, ambiguity preservation, save-before-follow-up ordering,
memory/domain ownership boundaries, exact-revision tool authorization, duplicate-delivery
convergence, stale-index rejection, partial proposal application, and revision conflicts without a
DOM, network, microphone, speaker, or real time. Provider-adapter tests additionally prove the chosen
output representation is actually sent, malformed or undeclared output cannot cross the decoder,
retry count is bounded, and the static prompt/schema/tool definitions stay within their declared
size budget. Keep a provider-backed smoke test outside the deterministic suite for detecting remote
API compatibility changes.
