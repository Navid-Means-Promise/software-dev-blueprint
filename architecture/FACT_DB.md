# FactDB: immutable propositions and sourced assertions

Authoritative for the optional, reusable FactDB contract. Adopt it when a product needs atomic
knowledge with explicit evidence and correction dependencies, especially when sources can be edited.
It is not a requirement to migrate simple [current-state assistant memory](AI_CONTEXT_RETRIEVAL.md#current-state-assistant-memory).
Follow the [contract source rule](CONTRACTS.md), [authorization boundary](AUTHORIZATION.md), and
[AI qualification requirements](../practices/AI_EVALUATION.md).
Hosts scheduling extraction and projections use [Job Management](BACKGROUND_JOBS.md) for resumable
steps and common attempt/log administration; FactDB remains the owner of accepted knowledge.

## Meaning and identity

A proposition is one independently assertable meaning. Separate information that can be corrected
independently; retain the subject, time, units, polarity, and uncertainty needed to interpret each
assertion. Use the simple structured content contract below; it imposes no legal fields, entity
ontology, or fixed qualifier taxonomy. Negation belongs in the predicate, without a separate polarity
field. Schema validation helps extraction but does not prove semantic atomicity.

Proposition meaning is immutable. A meaning change creates a different proposition ID, not a new
semantic version of the old ID. Different wording with equivalent meaning resolves to an existing
ID. Equivalence is evaluated within the same knowledge scope and qualifiers. Different periods,
assertors, or modalities must not be merged when they change meaning. Related or contradictory
propositions are not duplicates merely because their vocabulary overlaps.

An assertion records a source's support for a proposition, not proof of objective truth. Attribution
comes from its primary source. A question used to interpret an answer is context evidence, not an
assertion that the question's content is true. Two parties' conflicting assertions remain available
and attributed; the store never resolves truth through recency, similarity, or support count alone.

Semantic atomicity and equivalence require reviewed extraction/reconciliation. Database constraints
can enforce exact identity and reference integrity, not guarantee semantic equivalence of arbitrary
language. Automatic analyzers must qualify on decomposition, paraphrases, distinct qualifiers,
conflicting speakers, negation, ellipsis, and edited-source journeys. Unreviewed model text does not
become canonical knowledge merely because it passes a JSON decoder.

## Contract v2

This document is the language-neutral source. Implementations expose checked named types, with
camelCase field names in serialized records. IDs are opaque ASCII strings matching
`[A-Za-z0-9][A-Za-z0-9._:-]{0,127}`. Integer counters are nonnegative safe integers; source versions
start at one. Timestamps are RFC 3339 UTC instants. A scope has `tenantId` and `scopeId`; every record,
lookup, relationship, uniqueness check, and transaction is partitioned by both.

| Record | Fields and meaning |
| --- | --- |
| Scope | `tenantId`, `scopeId`; constructed from trusted host authorization, never model-selected |
| SourceReference | `id`, `version`; identifies exactly one immutable source representation |
| SourceVersion | `reference`, `position`, `actor`, `text`, `occurredAt`; position and original occurrence time remain stable across edits |
| PropositionContent | `subject`, `predicate`, `object`, `qualifiers`; one assertion with flexible circumstances |
| Qualifier | `type`, `value`; both ordinary text, no proposition/claim reference |
| Proposition | `id`, the four PropositionContent fields, `createdAt`; immutable meaning, no proposition revision field |
| Claim | `id`, `propositionId`, `source`, `context`, `dependsOn`, `supersedes`, `contradicts`, `createdAt`; immutable assertion and dependency bundle |
| SourceAnalysis | `source`, `claimIds`, `reviewId`; current reviewed interpretation of that source |
| SourceAnalysisDraft | `source`, `claims`; one source interpretation inside a reconciliation batch |
| ReconciliationBatch | `operationId`, `expectedRevision`, `reviewId`, `propositions`, `analyses`; one atomic multi-source semantic decision |
| AppliedOperation | `id`, `fingerprint`, `revision`; retry identity for a committed reconciliation |
| FactView | `proposition`, `status`, `claims`; interpreted status with usable attributed assertions |
| KnowledgeSnapshot | `scope`, `revision`, `facts`, `claims`, `sources`, `pendingSources`, `supersededClaimIds`, `disputedClaimIds`, `complete`; a consistent bounded read |
| SourceAttribution | `reference`, `actor`, `position`, `occurredAt`; summary evidence without original source text |
| SummaryInput | `scope`, `revision`, `facts`, `evidence`; complete current fact views with attribution |

Snapshot `facts`, `claims`, and current `sources` are maps keyed by their scoped IDs; `pendingSources`
and claim-ID collections are lists. SummaryInput `facts` and `evidence` are lists. The adapter's
private aggregate/storage shape is not a network API. `status` uses `supported`, `superseded`,
`disputed`, or `unsupported`, with the interpretation specified below.

`source` in a Claim is its primary SourceReference. `context` is a distinct list of additional
SourceReferences. The other three lists contain Claim IDs. This produces a many-to-many relation:
one source can assert multiple propositions; independent claims from several sources can support
one proposition. Keep primary support and contextual dependency distinguishable. A reply such as
"No" must cite the question needed to interpret it; otherwise editing the question would leave an
incorrectly supported assertion behind.

Source versions increase by one. Replaying the same current version and content is a no-op;
reusing a version for different content is rejected. Withdrawing a source removes its current head,
not the meanings of propositions it once supported. Reinstatement needs a fresh source version.
Sources may represent messages, document properties, or other authorized evidence; application
domains retain authority over their original records.

### Reconciliation plan

A checked plan contains `operationId`, `expectedRevision`, `source`, `reviewId`, `propositions`, and
`claims`. The host supplies the authorized scope separately. `source` must identify its current
head. `reviewId` identifies the human review or qualified analyzer configuration accepted by the host;
the identifier itself is not proof that a model is qualified.

Each proposed proposition contains `key` and the four content fields. `subject` and `predicate` are
nonblank text; `object` is nonblank text or null. `qualifiers` is a list of text `type`/`value` pairs.
A conditional assertion keeps its condition as literal qualifier text on that same assertion;
never create a separate proposition for the condition or assert its premise occurred. Time, scope,
reported viewpoint and explicitly stated certainty can also be qualifiers. Do not infer confidence.
A stated reason may remain a text qualifier; separately storing an explicitly asserted reason is
allowed, never required. There is no reasoning graph, `reasonClaimRefs`, condition reference or
additional polarity field. Independently asserted facts elsewhere in a message are still extracted
with correct attribution. "Amount and date are unknown" needs separate amount/date propositions.
Reported speech retains the reported speaker in its content/qualifiers as well as primary-source
attribution; source authorship alone must not erase whose view is being reported.

Trim surrounding whitespace without changing exact inner values. The canonical exact-identity
encoding uses field order `subject`, `predicate`, `object`, `qualifiers`; qualifier order is insignificant
and is sorted by UTF-8 byte order of type then value. Reject repeated identical pairs. Multiple
qualifiers of the same type with different values are allowed. Bounds in UTF-8 bytes: subject and
predicate 2,048 each, object 4,096, qualifier type 128 and value 2,048, at most 12 qualifiers and 8,192
bytes for the entire unescaped-Unicode/slash JSON content. Different structures may still mean the
same thing; qualified semantic reconciliation must reuse existing IDs in that case.

Each claim draft contains `key`, `proposition`, `context`, `dependsOn`, `supersedes`, and `contradicts`. Draft references have `kind`
(`existing` or `proposed`) and `id`. Proposed IDs are local keys resolved by the application; they
cannot select persistent IDs. Resolve equivalent propositions by existing ID. Exact duplicate
structured content converges mechanically, while semantic equivalence remains the reviewer's responsibility.
Unused drafts, duplicate keys/references, unknown or cross-scope references, self-links, and cycles
are rejected. Every claim's primary source is the plan's source.

Applying a plan replaces the current analysis of that source; an empty claims list records a
reviewed source with no assertions. Previously stored claims remain immutable provenance, rather
than being overwritten to represent a new meaning. Existing claim references must refer to the
current analysis of their primary source, and every evidence version must still be current.

`supersedes` means an explicit correction, not automatic preference for a newer claim. The default
contract permits it only from a later source position by the same actor; another actor's competing
claim uses `contradicts`. Changes to this authority policy require an explicit host-domain contract.
`dependsOn` records reliance on another assertion. A dependency on a superseded or disputed assertion
needs reconsideration; it cannot silently inherit that assertion as current truth.

### Batched reconciliation and source handoff

A batch contains globally unique proposition/claim draft keys and chronologically ordered, distinct
SourceAnalysisDraft entries. Every requested source must have an analysis, including sources with
no assertions. Proposed proposition keys may be reused across analyses; proposed claim references
may target the same or an earlier analysis, never a later one. Resolve local keys to server-generated
IDs. Preflight the complete decision and publish one knowledge revision and one batch receipt;
invalid later entries cannot leave a committed prefix. Exact replay and mismatched identity reuse
follow the single-plan rules. Keep per-source and aggregate batch capacities explicit.

A provider-neutral analysis representation/decoder may be reusable alongside the core. It does not
own network credentials, transport, admission, usage accounting, host metadata, or model qualification.
Keep source text and host context explicitly delimited as untrusted data. Include complete selected
sources, relevant contextual originals and the current scoped knowledge needed for equivalence and
relationship decisions. Reject oversized required context rather than silently dropping propositions
or evidence. Prompt instructions do not establish semantic correctness.

Distinguish sources selected for analysis from supporting context in the serialized request. A
context-only source must not acquire an accidental replacement analysis. Compact request-local
aliases may represent existing proposition/claim IDs when their checked mapping belongs to that
exact scoped snapshot. Keep aliases disjoint from supplied source IDs and new draft keys; resolve
them to canonical IDs before core validation. Unknown aliases and references to replaced claims
remain invalid. Compaction must preserve meanings, attribution, chronology and relationships.

Hosts must coordinate original-source changes and FactDB freshness. A transactionally dirtied source
revision plus a complete bounded manifest is a valid durable idempotent handoff: synchronize changed
heads and withdrawals atomically, reject stale analysis, and compare both the original-source
watermark and knowledge revision before publishing derived outputs. A store-local `complete` flag
alone cannot prove no source change is waiting outside the store. Persist incomplete progress so
unchanged sources invalidated by dependencies remain scheduled after edited-source checkpoints catch
up. A projection failure retains accepted knowledge and retries projection, rather than treating the
canonical analysis as rolled back. Host-owned fields used to interpret sources need explicit versioned
context dependencies or an equally conservative invalidation policy.

## Revisions, invalidation, and views

The scope's knowledge revision is a concurrency token, not a proposition version. Source publication,
withdrawal, and reconciliation commit atomically against an expected revision. Provider calls run
outside transactions. A plan generated against a changed snapshot is rejected before publication.
An identical operation replay converges without another claim or proposition; reusing an operation
ID for a different plan is rejected.

A claim is usable only when it belongs to its source's current analysis, its source and context
versions are current, and its referenced claims remain usable. A source change invalidates affected
claims and propagates through recorded dependencies, including correction relationships. This finds
known affected analyses without semantic search. It does not prove the extractor recorded every
dependency; completeness must be evaluated, and recovery may require broader source review.

Views distinguish `supported`, `superseded`, `disputed`, and `unsupported` propositions. Supported
means currently sourced, not verified true. Contradictory current claims remain attributed and
visible. Old meanings with no usable claims are not delivered as current facts. A view also exposes
`pendingSources` and `complete`; a summary input accessor rejects incomplete views. Selection indexes,
current views, summaries, and UI briefs are derived projections, not mutable proposition content.
Summary input excludes individually superseded claims even when another source still supports the
same proposition, and preserves each delivered claim's source attribution and chronology. It does not turn
"supported" into "verified", drop conflicting speakers, or require sending all original source text.

Reanalyzing an unchanged source can be necessary when its context or a related claim changed.
Replace its current analysis using current evidence and explicit relationships; do not invent a new
message occurrence or treat an old message edit as a new conversational turn.

## Reference-based summaries

FactDB can supply the maintained knowledge directly; a rolling or detailed prose summary is not a
required memory layer or intermediate step for a UI brief. An optional detailed narrative is an
independent derived feature, never another canonical knowledge copy.
[Rolling summaries](ROLLING_SUMMARIES.md) remain a separate reusable alternative for workloads that
choose maintained prose context instead of proposition-based knowledge.

When exact wording, attribution and values matter more than paraphrase, let the model select and
order existing propositions and let code render their structured content with trusted source
attribution. A checked request-local catalog may use compact references, resolved only against
that scoped snapshot. Reject unknown/duplicate references, unexpected content fields and over-budget
selections; never truncate content or accept model-supplied replacement text.
Render subject, object when present, and predicate in order, with every qualifier displayed as
`type: value`. This deterministic display is derived from content, not another mutable prose field.
A host requiring a different language ordering owns and qualifies that projection policy. Preserve all usable
assertors of each selected proposition. Selecting one side of a recorded dispute must retain the
related opposing assertions and mark the disagreement, rather than presenting a winner.

Host-owned document properties and generic metadata can have separately selectable references.
Render their canonical labels/display values without duplicating them as mutable propositions or
imposing a fixed semantic schema. A host may require particular document fields, such as current
participant names, independently of model selection; include that policy in prompt/render budgets
and qualification identity. Keep document participant context distinct from assertion attribution.
Historical attribution must not transfer to a newly assigned
participant. Derived caches retain the selected canonical references and the knowledge/source
revisions needed to reject stale publication. Changing projection strategy invalidates prose caches,
not otherwise valid accepted knowledge or evidence checkpoints.

Selection reduces opportunities to invent prose; it does not prove extraction accuracy, dependency
completeness, appropriate emphasis or coverage. Qualify the complete extraction/selection/rendering
path and test deterministic reference rejection, exact values, attribution, dispute closure,
capacity failure and projection-only recovery through the real host composition.

## Module and host responsibilities

The reusable core owns checked values, immutable identity, evidence/dependency validation, impact
propagation, view construction, and concurrency/idempotency rules. It receives repositories,
identifiers, clocks, and optional analyzers through narrow ports. It imports no application domain,
provider SDK, UI, HTTP framework, or global environment. Persistence and model integrations are
adapters. Implementations may be language-specific; PHP/TypeScript interoperability follows this
contract rather than pretending one language can directly import the other's library.

The host owns authentication, domain permissions, source access and ordering, review policy,
semantic candidate selection, and model qualification. A core repository is a trusted server port,
not a generic public CRUD API. Never accept a model-chosen tenant, scope, source version, timestamp,
or persistent identifier as authority. A provider adapter proposes a plan; domain validation and
scope checks decide whether it can commit.

Choose one canonical owner for each knowledge representation. A FactDB-backed assistant can derive
a current-memory delivery view; it must not maintain another independently mutable MemoryRecord
copy of the same knowledge. Existing domain records remain authoritative for their own facts.

## Persistence, bounds, and deletion

Explicit versioned migrations own durable storage; constructors never install schema. Enable foreign
keys, partition relationships by scope, and enforce proposition/source/claim immutability in the
durable adapter as well as the core. Reads use a consistent snapshot; writes use compare-and-swap
and one short transaction. Missing schema, stale state, capacity exhaustion, or invalid evidence
fails explicitly instead of returning a partial view as complete.

Declare limits for sources, retained versions, propositions, claims, operation receipts, context and
dependency fan-out, and analysis batch size. A bounded aggregate implementation is valid when its
limits and cost are explicit; large scopes require indexed/partitioned adapters rather than hidden
truncation. Evidence content follows the host's protected retention policy. Immutability prevents
meaning reassignment, not authorized erasure.

Scope erasure removes source text, propositions, evidence, claims, analyses, and operation receipts.
Retain only a non-content scope tombstone and advanced revision to reject stale work; do not recreate
an erased scope through a delayed worker retry. Do not expose retained provenance through ordinary
logs, cross-tenant search, or unfiltered model prompts.

### Upgrade from prose content

Retain v1 immutable proposition/claim/source records and their hashes as historical provenance.
Do not mechanically guess subject/predicate/object from old prose or reassign existing IDs. Claims
using legacy prose are ineligible for current views; their source analyses and recorded dependents
become pending for fresh structured extraction. Legacy IDs cannot receive new support. Exclude
legacy content from the analyzer's reusable proposition catalog. A successful reanalysis produces
new structured IDs and preserves historical records. Invalidate derived summaries and schedule the
host handoff through an explicit migration; retain provider usage/account history. Unchanged empty
source analyses can remain valid. Never mark an incomplete upgrade as a complete summary.

## Required verification

Alongside [AI semantic qualification](../practices/AI_EVALUATION.md), deterministic tests prove:

- two atomic propositions from one source and one proposition supported by multiple sources;
- immutable IDs, exact-duplicate convergence, and existing-ID reuse without meaning reassignment;
- structured round trips, order-independent qualifier identity, literal negation, conditions without
  invented premises, optional reason qualifiers, unknown-value separation and partial corrections;
- v1 upgrades retain immutable provenance, reject legacy reuse and require fresh extraction before
  summary publication; malformed qualifier values, nested references and model-owned attribution fail;
- old-source edits, withdrawals, and reinstatement, preserving independent valid support;
- context-question edits, transitive dependencies, correction chains, and empty analyses;
- contradictory speakers remain disputed; an old edit cannot mechanically supersede a later source;
- no dangling, cross-scope, self-referential, cyclic, or stale evidence/claim references;
- operation replay, mismatched operation reuse, concurrent snapshots, rollback, and restart;
- multi-source batch rollback, cross-analysis key resolution, complete source coverage, strict analyzer
  decoding, durable source-handoff freshness, and superseded-assertion attribution;
- request-local reference isolation/collision rejection and complete prompt budgets using
  production-length identifiers and dense corroborating evidence;
- explicit capacity failures, incomplete-summary rejection, migration ownership, and erasure.

Use colliding IDs in different tenants/scopes and real durable-adapter tests. Fake semantic reviewers
prove orchestration only; they do not establish atomicity or semantic deduplication in live models.
