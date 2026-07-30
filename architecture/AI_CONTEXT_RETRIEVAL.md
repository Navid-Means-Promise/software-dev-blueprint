# Experimental assistant context retrieval

> **Status: optional and experimental.** This is an intentionally innovative design candidate for
> application-owned AI-chat context retrieval, not a mandatory stack rule. A project may adopt it,
> adapt it after recording the differences, or instead use a ready-made agent/context solution that
> owns context handling behind the project's application boundary.

This guide applies only when a project explicitly chooses this design. The general
[AI subsystem rules](AI_SUBSYSTEM.md)—the model is untrusted, application data remains
application-owned, provider state is not durable product memory, and every operation is authorized
by the application—still apply under either choice.

## Separate the three axes

Do not encode origin, retrieval method, and data source in one growing union. They answer independent
questions:

| Axis | Question | Choices in this design |
| --- | --- | --- |
| **Origin** | Why and when did retrieval start? | automatic predictive, model-directed predictive, model-directed in-call |
| **Method** | How is data selected? | relevance search or application-specific explicit retrieval |
| **Source** | Where does authoritative data come from? | raw messages, processed context, or product-specific repositories and ledgers |

An origin carries audit and policy metadata. A query describes what to retrieve. A source owns the
current records. Keeping them separate prevents query fields from being duplicated inside trigger
types. Model-directed origins may use either retrieval method; automatic predictive retrieval uses
relevance search.

Baseline context injection is not a fourth retrieval origin. A small bounded set of runtime values
and pinned records—such as the current instant, time zone, locale, or explicitly pinned identity
data—may be included on every turn without a relevance query. Generate runtime values from their
authoritative providers instead of storing them as memories.

## Context sources and authority

The source catalog is product-defined and extensible. It normally begins with:

- **Raw conversation messages.** These preserve what was said and provide evidence and conversational
  recall. A user message is evidence of the user's statement; an assistant message is not evidence
  that its content is true.
- **Processed context records.** These may include user-confirmed memories, model-inferred memories,
  or conversation summaries derived from raw messages. They retain provenance back to the source
  messages, an explicit authority level, lifecycle state, and revision.
- **Application-specific repositories and ledgers.** These remain authoritative for their own domain
  data and expose typed, explicit retrieval operations such as lookup by identifier or a bounded
  date range. The context subsystem never replaces those repositories with copies.

If processed memory is stored as natural language, do not add subject/predicate/object fields merely
in anticipation of a future query mechanism. Store a standalone canonical statement containing the
smallest coherent information that can be corrected, expired, superseded, retracted, or deleted
independently. Closely coupled details with the same provenance and lifecycle may share one record;
unrelated facts that can change independently do not. Adopt structured assertions later only when a
measured use case needs structured querying.

Raw messages, processed records, and application state are not competing copies of one truth: they
have different meanings. Raw messages are evidence, processed records are the application's current
interpretation, and domain repositories are authoritative domain state. Search indexes, embeddings,
summaries used only for acceleration, and predictive caches are derived projections rather than new
sources of truth.

### Searchable application data

An application source may publish selected free-text fields into the relevance index while retaining
definitive retrieval through its typed repository methods. The source defines its smallest useful
retrieval unit: a record, an aggregate, or another bounded domain block. A search hit points to that
unit, and the subsystem reloads the complete current unit from its authoritative source before prompt
assembly.

Use **explicit** to name the retrieval method; the hydrated result is **definitive** only because it
comes from the source that owns that application data.

## The three retrieval origins

### 1. Automatic predictive context retrieval

After the server receives a new user message but before the model sees it, relevance search runs
using **only that new message** as the source of `queryText`. It does not silently expand the query
with earlier turns. Provider-independent normalization and the declared query-length limit may still
apply. Previous turns may appear in the bounded recent-conversation window, while context that the
previous model turn predicted is supplied through the next origin.

This origin is automatic because application code starts it and predictive because lexical,
semantic/vector, or hybrid search estimates which stored records will help. It is not a model tool
call. The new raw message is excluded from its own search results; save it before orchestration when
required, but index it only after this search or pass its reference as an explicit exclusion.

### 2. Model-directed predictive context retrieval

A completed model turn may return hidden, typed retrieval instructions for a future turn alongside
its visible answer. The application validates and stores accepted instructions with a short
application-assigned turn expiry. On the next eligible turn it executes each instruction and supplies
the permitted results to the model.

The application does not rerank or discard those results against the next user message: deciding
their anticipated relevance was the model's job. It still enforces ownership, authorization,
lifecycle, source allowlists, query schemas, and hard result/token limits. Identity deduplication
against other retrieval results is not a relevance decision. If a hard limit truncates a result, the
model receives an explicit incomplete-result marker.

Store the accepted query and, when safe, cached source references—not copied canonical content.
Resolve current records and recheck access on the consuming turn. Volatile application-specific
queries execute against their authoritative source on that turn so prediction cannot freeze stale
domain state.

### 3. Model-directed in-call context retrieval

After seeing the current prompt, the model may discover that context is still missing and issue a
typed tool call. The application validates and executes the query, returns bounded results to the
same model interaction, and lets the model continue. The user need not receive an intermediate
"cannot answer" message.

Limit retrieval rounds and repeated equivalent queries. A prompt can suggest a tool call, but cannot
authorize a source, enlarge a budget, or create a new explicit operation.

## Provider-neutral contracts

The names below define roles, not one storage engine or model SDK. An implementing project narrows
`TSourceId` to its closed source-ID union and `TExplicitQuery` to a schema-validated closed union of
its own typed retrieval operations.

```ts
interface ContextRecordReference<TSourceId extends string> {
  readonly sourceId: TSourceId;
  readonly recordId: string;
  readonly revision?: string;
}

interface ContextAccessScope {
  readonly principalId: string;
  readonly ownerId: string;
  readonly tenantId?: string;
}

type ContextRetrievalOrigin =
  | {
      readonly type: 'automatic-predictive';
      readonly userMessageId: string;
    }
  | {
      readonly type: 'model-directed-predictive';
      readonly assistantResponseId: string;
      readonly instructionId: string;
    }
  | {
      readonly type: 'model-directed-in-call';
      readonly turnId: string;
      readonly toolCallId: string;
    };

interface RelevanceContextRetrievalQuery<TSourceId extends string> {
  readonly method: 'relevance-search';
  readonly queryText: string;
  readonly sourceIds: readonly TSourceId[];
  readonly excludedSourceReferences: readonly ContextRecordReference<TSourceId>[];
}

type ContextRetrievalQuery<
  TSourceId extends string,
  TExplicitQuery extends { readonly method: 'explicit' },
> = RelevanceContextRetrievalQuery<TSourceId> | TExplicitQuery;

interface ContextRetrievalLimits {
  readonly maximumResults: number;
  readonly maximumQueryCharacters: number;
}

interface ContextPromptBudget {
  readonly maximumPromptTokens: number;
}

interface ContextRetrievalRequest<
  TSourceId extends string,
  TExplicitQuery extends { readonly method: 'explicit' },
> {
  readonly requestId: string;
  readonly origin: ContextRetrievalOrigin;
  readonly query: ContextRetrievalQuery<TSourceId, TExplicitQuery>;
  /** Created by trusted application code; never accepted from model output. */
  readonly accessScope: ContextAccessScope;
  /** Assigned by application policy; never accepted from model output. */
  readonly limits: ContextRetrievalLimits;
  /** Applied after authoritative records are resolved and rendered. */
  readonly promptBudget: ContextPromptBudget;
}

interface ModelPredictiveContextRetrievalInstruction<
  TSourceId extends string,
  TExplicitQuery extends { readonly method: 'explicit' },
> {
  readonly instructionId: string;
  readonly query: ContextRetrievalQuery<TSourceId, TExplicitQuery>;
}

interface PendingModelPredictiveContextRetrievalInstruction<
  TSourceId extends string,
  TExplicitQuery extends { readonly method: 'explicit' },
> {
  readonly instruction: ModelPredictiveContextRetrievalInstruction<
    TSourceId,
    TExplicitQuery
  >;
  /** Assigned by application policy; the model cannot extend its own instruction lifetime. */
  readonly expiresAtTurnSequenceExclusive: number;
}
```

The model supplies only the proposed predictive instruction. Trusted application code adds its
request identifier, origin, access scope, retrieval limits, prompt budget, and expiry after
validation. An automatic request always uses `RelevanceContextRetrievalQuery` with the newly received
message as the only source of `queryText` and excludes that message's own source reference.

An explicit query is not a string operation name plus arbitrary parameters. The product defines a
closed discriminated union whose variants carry their fully typed values. Its provider delegates
each variant to the existing application service or repository; it never accepts SQL, storage keys,
or an unrestricted endpoint name from the model.

### Disposable search projection

```ts
interface ContextSearchDocument<TSourceId extends string> {
  readonly documentId: string;
  readonly sourceReference: ContextRecordReference<TSourceId>;
  readonly searchableText: string;
  readonly accessPartition: {
    readonly ownerId: string;
    readonly tenantId?: string;
  };
}

interface ContextRetrievalHit<TSourceId extends string> {
  readonly sourceReference: ContextRecordReference<TSourceId>;
  readonly score: number;
  readonly matchedBy: 'lexical' | 'vector' | 'hybrid';
}

type ContextRetrievalOmissionReason =
  | 'result-limit'
  | 'prompt-token-limit'
  | 'source-unavailable'
  | 'record-unavailable';

interface ContextRetrievalResult<TItem> {
  readonly items: readonly TItem[];
  readonly isComplete: boolean;
  readonly omissionReasons: readonly ContextRetrievalOmissionReason[];
}

interface ContextSearchIndexProvider<TSourceId extends string> {
  upsert(
    documents: readonly ContextSearchDocument<TSourceId>[],
  ): Promise<void>;

  remove(documentIds: readonly string[]): Promise<void>;
}

interface ContextRetrievalProvider<TSourceId extends string> {
  retrieve(
    accessScope: ContextAccessScope,
    query: RelevanceContextRetrievalQuery<TSourceId>,
    limits: ContextRetrievalLimits,
  ): Promise<ContextRetrievalResult<ContextRetrievalHit<TSourceId>>>;
}

interface RetrievedContextItem<
  TSourceId extends string,
  TAuthority extends string,
> {
  readonly sourceReference: ContextRecordReference<TSourceId>;
  readonly promptText: string;
  readonly authority: TAuthority;
}

interface ContextSourceProvider<
  TSourceId extends string,
  TAuthority extends string,
> {
  readonly sourceId: TSourceId;

  resolve(
    accessScope: ContextAccessScope,
    references: readonly ContextRecordReference<TSourceId>[],
  ): Promise<
    ContextRetrievalResult<RetrievedContextItem<TSourceId, TAuthority>>
  >;
}

interface ExplicitContextRetrievalProvider<
  TSourceId extends string,
  TExplicitQuery extends { readonly method: 'explicit' },
  TAuthority extends string,
> {
  retrieve(
    accessScope: ContextAccessScope,
    query: TExplicitQuery,
    limits: ContextRetrievalLimits,
  ): Promise<
    ContextRetrievalResult<RetrievedContextItem<TSourceId, TAuthority>>
  >;
}
```

`ContextSearchDocument` is a normalized input to an index adapter, not the adapter's physical schema
and not a canonical context record. A concrete integration may implement both narrow providers:

```ts
class OramaContextRetrievalAdapter<TSourceId extends string>
  implements
    ContextSearchIndexProvider<TSourceId>,
    ContextRetrievalProvider<TSourceId>
{
  // Maps the application contracts to Orama's private index representation.
}
```

Search returns references. Product-owned `ContextSourceProvider` implementations reload current
records, validate their revision, lifecycle, validity, and access, and render them as clearly
delimited prompt data. An application-specific `ExplicitContextRetrievalProvider` returns the same
resolved item and completion shapes. This lets every origin share authorization, deduplication,
budgeting, partial-result signaling, and audit behavior without forcing domain repositories behind a
generic storage API. The implementing project defines `TAuthority` as its own closed authority
vocabulary.

`isComplete` is true if and only if `omissionReasons` is empty. Multiple reasons can coexist when,
for example, a budget truncates one source while another source is unavailable. Prompt-token limits
are applied by `AssistantContextAssemblyService` after resolution, not by an index adapter that has
never seen the rendered prompt text.

## Turn orchestration

```text
baseline context ───────────────────────────────┐
new user message ──automatic predictive search─┤
pending model prediction ──validated retrieval─┼─resolve/deduplicate/budget─▶ model
bounded recent conversation ───────────────────┘                              │
                                                                              ├─tool request─▶ bounded retrieval ─┐
                                                                              │                                  │
                                                                              ◀──────────────────────────────────┘
                                                                              └─final answer + next-turn queries
```

For each new user turn, `AssistantContextAssemblyService`:

1. constructs the trusted access scope and baseline context;
2. takes the bounded recent-conversation window required for dialogue continuity;
3. runs automatic predictive relevance retrieval from only the new message while excluding the
   message itself as a result;
4. executes every unexpired predictive instruction accepted from the previous model response;
5. resolves current authoritative records and removes only inaccessible, invalid, stale, retracted,
   or identity-duplicate references;
6. applies declared per-origin and total hard budgets, marking any partial result;
7. sends the assembled context and new message to `AssistantModelProvider`;
8. services bounded, validated in-call retrieval requests through the same retrieval path; and
9. validates and stores any next-turn predictive instructions returned with the final response.

Independent automatic and predictive requests may execute concurrently. Context ordering is stable
and source authority/provenance is visible to the model. Conflicting permitted records are preserved
and labeled rather than silently merged into a fabricated fact.

## Security, lifecycle, and observability

- Apply tenant/owner partitions inside relevance search before nearest-neighbor ranking, then
  reauthorize every hydrated source record. Filtering only after a cross-owner vector search can leak
  information through results or scores.
- Treat retrieved content as untrusted quoted data, never as system instructions. Retrieval does not
  weaken prompt-injection defenses or tool authorization.
- Propagate source deletion, permanent deletion, supersession, retraction, and access changes to
  search projections and predictive caches. A stale hit is skipped and scheduled for reindexing.
- Decide explicitly whether searchable text or embeddings may leave the application's trust
  boundary. Remote embedding creates a data disclosure that requires the same review as sending
  prompt content to a model provider.
- Bound query length, result count, prompt tokens, predictive instructions, in-call rounds, and
  equivalent repeated queries. Report source failure and truncation to the model rather than
  pretending retrieval was complete.
- Audit origin, validated query, source references and revisions, policy rejections, truncation,
  latency, provider/model token use, and tool-loop termination. Full sensitive payload logging needs
  separate protected access, retention, and deletion policy.

## Required tests for adopters

Use fixed model, retrieval, source, clock, and token-budget test doubles. Prove:

- automatic retrieval derives its query only from the newly received message and cannot return that
  message as its own result;
- predictive instructions expire deterministically and their permitted results are not rejudged for
  relevance against the next message;
- invalid or unauthorized explicit queries never reach a repository;
- access partitioning prevents cross-owner and cross-tenant retrieval;
- stale revisions, deletion, retraction, and supersession cannot resurrect old context;
- duplicate references converge without dropping distinct conflicting records;
- partial failures and hard-budget truncation are visible to the model;
- retrieved prompt injection cannot alter tool authority or system rules; and
- in-call limits terminate repeated or cyclic retrieval requests.

Evaluate this design against representative conversations before adoption. Retrieval precision,
recall, latency, token cost, privacy, correction behavior, and user control—not architectural
novelty—decide whether it is preferable to a ready-made agent/context solution.
