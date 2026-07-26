# AI subsystem architecture

Authoritative for assistant orchestration, application-owned durable context, model-requested domain
actions, and speech input/output. Every domain entity, remembered-context category, tool name, and
proposal item remains in the implementing project's documentation.

## Boundary and dependency direction

An AI model is an interchangeable, untrusted interpreter at the edge of the application. It does not
own conversation memory, user context, or domain state, and it never writes a repository directly.
The shared domain validates and executes typed requests through the same services used by the UI.

```text
Shared assistant conversation UI
  ├─ AudioCapturePort
  │    └─ SpeechToTextPort
  │         └─ reviewed text transcript
  └─ typed or reviewed text transcript
       └─ AssistantOrchestrationService
            ├─ relevant product-specific domain-state data
            ├─ relevant records from AssistantContextRepository
            └─ AssistantModelPort
                 └─ typed, schema-validated domain-tool requests
                      ├─ AssistantContextService
                      ├─ product-specific application services
                      ├─ DomainChangeProposalService
                      └─ UserActionHistoryService

Assistant response text
  └─ TextToSpeechPort
       └─ platform or remote speech-synthesis adapter
```

`AudioCapturePort`, `SpeechToTextPort`, `AssistantModelPort`, and `TextToSpeechPort` are independent.
A project may replace speech recognition, the assistant model, and speech synthesis separately.
Direct audio-to-audio model sessions are an additional adapter path; they must still deliver a
reviewable transcript and typed tool requests to the same orchestration and domain services.

Provider credentials belong in the server that owns the provider adapter. A client connects directly
to a real-time provider only with a short-lived, narrowly scoped credential issued by that server.

## Application-owned durable context

An assistant may need durable user-supplied context beyond the current conversation. That context is
application data: a domain-shaped repository stores it, the user can inspect and control it, and the
application selects the minimum relevant records for each request.

The blueprint does not prescribe a domain name, a category list, or a claim of objective truth for
these records. Each product defines one closed domain vocabulary in its own repository and
documentation. The reusable requirement is that durable context has one product-owned source instead
of parallel stores for different context categories.

The application repository, not provider conversation state, is the source for future decisions.
Preserve the user's source text alongside the normalized interpretation so a correction never
depends on reconstructing what the user originally said. A correction appends a replacement linked
to the superseded record; a retraction archives the record instead of erasing its provenance.
Permanent deletion remains a separate privacy operation.

### Temporal meaning

When durable context can change relevance over time, validity and applicability are different:

- **Validity range** bounds when the record may affect domain decisions. Either bound may be absent
  for an open-ended record; the upper bound is exclusive.
- **Temporal applicability** describes when inside that validity range the record applies. It may
  cover the complete range, arbitrary exact intervals on arbitrary dates, recurring local-time
  windows, or a union of exact and recurring rules.

The contract must express the distinction explicitly:

```ts
type ContextWeekday =
  | 'monday'
  | 'tuesday'
  | 'wednesday'
  | 'thursday'
  | 'friday'
  | 'saturday'
  | 'sunday';

interface AssistantContextValidity {
  /** RFC 3339 instant; absent means no lower validity bound. */
  readonly validFrom?: string;
  /** RFC 3339 instant; absent means no upper validity bound. */
  readonly validUntilExclusive?: string;
  /** IANA time-zone identifier used to resolve every local calendar/time value. */
  readonly timeZone: string;
  readonly sourceTemporalExpression?: string;
  /** RFC 3339 instant; present when a relative source expression was resolved. */
  readonly temporalExpressionResolvedAt?: string;
  readonly applicability:
    | { readonly kind: 'continuous' }
    | {
        readonly kind: 'rules';
        readonly anyOf: readonly (
          | {
              readonly kind: 'exact-interval';
              /** RFC 3339 instant. */
              readonly startsAt: string;
              /** RFC 3339 instant. */
              readonly endsAtExclusive: string;
            }
          | {
              readonly kind: 'recurring-local-time-window';
              readonly weekdays: readonly ContextWeekday[];
              /** Local 24-hour time in HH:mm:ss format. */
              readonly startsAtLocalTime: string;
              /** Local 24-hour time in HH:mm:ss format. */
              readonly endsAtLocalTime: string;
              /** Zero for the same local day; one for a window that crosses midnight. */
              readonly endDayOffset: 0 | 1;
            }
        )[];
      };
}
```

An exact interval is never converted into a recurrence merely because it mentions a day or time.
`rules.anyOf` is non-empty and means that the context record applies when any rule matches; the outer
validity range still bounds every rule.
Relative language such as "this week" is resolved when recorded using the injected clock, the user's
time zone, and the product's week-boundary rule. Store both the original expression and its absolute
resolution.

If a useful field remains ambiguous, save the source assertion first and mark the normalized record
as needing clarification. The assistant asks one focused question before taking any action that
depends on the unresolved meaning. Declining an optional follow-up action never discards the saved
context.

### Retrieval

For a requested domain operation, select durable context records that:

1. are not retracted or superseded;
2. match the requested domain scope;
3. overlap the requested time range when the record is temporally scoped; and
4. are relevant to the requested operation.

Start with structured filtering over lifecycle, validity, applicability, product-defined category,
and explicit domain references. Do not introduce embedding or vector retrieval until measured corpus
size or retrieval quality demonstrates that structured selection is insufficient. Send the model
only the selected minimum context, never the complete archive by default.

## Typed domain tools and execution policy

Model output is untrusted boundary input even when the provider guarantees syntactically valid JSON.
Decode it into a closed union of named domain-tool requests, validate semantic values, authorize the
operation, and execute only through application services.

Every mutation carries an idempotency key. Mutation tools express the desired resulting state rather
than a toggle: delivery retries must converge on the same state instead of reversing a successful
action.

Classify tools by effect:

| Effect class | Execution policy |
| --- | --- |
| Read-only query | Execute after validation. |
| Explicit, reversible user request | Execute once, record it in User Action History, and expose Undo. |
| Durable-context recording | Save before the assistant asks about optional follow-up action; expose Edit and Undo. |
| Multi-part or interpretive domain change | Create a proposal; do not mutate domain state. |
| Destructive, irreversible, or externally visible action | Require explicit confirmation immediately before execution. |

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

## User Action History, Undo, and Redo

Record **domain mutations initiated through the UI or assistant**, not presentation interactions such
as opening a tab. Each `UserActionHistoryEntry` identifies the actor, idempotency key, timestamp,
forward command, compensating command, affected entity revisions, and current applied/undone state.

Undo and redo execute compensating or forward commands through the current application service; they
do not restore an unchecked storage snapshot. Revalidation detects a later edit or sync conflict
instead of silently overwriting it. A multi-item accepted proposal is one atomic history entry whose
compensating command reverses exactly the accepted subset.

The history may back an immediate Undo notification and a persistent History screen. Retention,
cross-device ordering, and compaction are explicit product policies.

## Speech input and speech output

The first implementation should normally be text-centered:

1. `AudioCapturePort` records a push-to-talk utterance.
2. `SpeechToTextPort` produces a transcript and confidence/alternative data.
3. The user can correct the transcript before a consequential or ambiguous operation.
4. `AssistantOrchestrationService` processes typed and spoken text identically.
5. `TextToSpeechPort` optionally speaks the final response.

This separation preserves provider choice, permits independent language-quality testing, and avoids
retaining raw audio. Raw recordings are deleted after transcription by default.

Prefer a platform speech-synthesis adapter for the first `TextToSpeechPort` implementation when its
required locales pass device testing; it avoids a remote dependency and per-request service cost.
A remote speech-synthesis adapter remains replaceable and may be selected for consistent voice
identity or missing locale quality. `TextToSpeechPort` exposes capability/voice availability and
completion/interruption results so an unavailable voice degrades to visible text rather than startup
failure.

## Persistence, privacy, and provider state

Durable assistant context, transcripts, and action history can contain sensitive personal
information. A production design provides user-visible inspect/edit/retract/delete/export controls, encrypts
sensitive durable storage using platform-appropriate facilities, authenticates every server request,
and records the minimum diagnostic content. Raw audio is not retained by default.

Do not use a model provider's conversation identifier as durable product memory. Store short-term
conversation state under product control, send only the turns or summary required for the current
request, and allow a provider adapter to operate statelessly.

A simple key/value adapter may prove a domain contract during an early vertical slice, but it is not
an implicit approval for unbounded context, transcript, or action-history storage. Choose a durable
adapter before size, query, encryption, synchronization, or retention requirements exceed the
documented limits.

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
the history.

## Module ownership and tests

- `@app/shared` owns the domain-shaped assistant-context types/services/repository ports, assistant
  orchestration, tool policies, proposal rules, User Action History, and all four provider/host
  ports.
- `@app/ui` owns the shared conversation, transcript-review, context-receipt, proposal-review, and
  Undo/Redo presentation.
- `apps/web` and `apps/mobile` own audio-capture and platform speech-synthesis adapters.
- `apps/server` owns assistant-model, remote speech-recognition, remote speech-synthesis, credential,
  rate-limit, and provider-transport adapters.

Domain tests use fixed clock, model, speech, repositories, and idempotency test doubles. They prove
any product-required temporal overlap, ambiguity preservation, save-before-follow-up ordering, tool
authorization, duplicate-delivery convergence, partial proposal application, revision conflicts, and
Undo/Redo compensation without a DOM, network, microphone, speaker, or real time.
