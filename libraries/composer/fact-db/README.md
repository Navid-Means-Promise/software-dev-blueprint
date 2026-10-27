# FactDB PHP package

Portable implementation of the [blueprint FactDB contract](../../../architecture/FACT_DB.md).
The package imports no application classes or model SDKs. Its Composer namespace is `FactDb\`;
another PHP application can install this directory as a Composer library. TypeScript consumers
implement the language-neutral contract or use a separately authorized service adapter; there is no
cross-language runtime dependency in this package.

## Composition

Construct the service for a tenant and knowledge scope already authorized by the host. No HTTP route,
global repository, default identity, or network provider is installed. This example is trusted
server composition; it is not a client-controlled storage API.

```php
use FactDb\Adapter\{RandomIdentifiers, SystemClock};
use FactDb\FactDb;
use FactDb\Model\{ClaimDraft, PropositionContent, PropositionDraft, ReconciliationPlan, Reference, Scope, SourceReference, SourceVersion};
use FactDb\Persistence\{SqliteRepository, SqliteSchema};

$pdo = new PDO($authorizedDatabaseDsn, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');

// Run once through the host's explicit migration operation, outside request construction.
SqliteSchema::install($pdo);

$db = new FactDb(
    new Scope($authorizedTenantId, $authorizedKnowledgeId),
    new SqliteRepository($pdo),
    new SystemClock(),
    new RandomIdentifiers()
);
$source = new SourceVersion(new SourceReference('message:1', 1), 1, 'client:42',
    'Monthly rent is 12 million toman.', '2026-10-18T10:00:00Z');
$snapshot = $db->register($source, $db->snapshot()->revision);

// This plan represents a human-reviewed or functionally qualified semantic decision.
$snapshot = $db->apply(new ReconciliationPlan(
    'operation:1', $snapshot->revision, $source->reference, 'review:1',
    [new PropositionDraft('rent', new PropositionContent('Monthly rent', 'is', '12 million toman'))],
    [new ClaimDraft('assertion', Reference::proposed('rent'))]
));
$summaryInput = $snapshot->forSummary();
```

`SqliteRepository` verifies schema and foreign-key configuration; it never creates tables. It owns
its short read/write transactions, so do not invoke it inside another PDO transaction. Use explicit
workflow coordination when a host transaction changes original messages. Registration must converge
before knowledge is considered fresh; a failed host-to-FactDB synchronization cannot be presented
as complete summary coverage.

## Structured content

`PropositionContent` owns subject, predicate, nullable text object, and `Qualifier` type/value pairs.
All values are plain text; conditions remain attached qualifiers, never separate condition facts.
Stated reasons may be qualifiers without another claim or reason reference. Negation stays in the
predicate. Qualifier order is canonicalized for identity; semantic paraphrases still require review.
The derived `Proposition::statement` display is not serialized as independently mutable content.
Source-owned claims supply attribution; the model cannot select an author or persistent ID.

## Reviewed reconciliation

`register()` adds an immutable source version. Edits advance its version by one while preserving
original position/time. `withdraw()` targets an exact source version. `snapshot()->pendingSources`
identifies current sources whose own analysis or recorded dependencies need reconsideration.
`apply()` publishes a checked plan against its expected knowledge revision. `reconcile()` invokes an
injected `SourceAnalyzer` outside the database transaction, then checks and applies its plan.

Use `Reference::existing()` for a semantically equivalent stored proposition. Exact structured-content matches
are also reused mechanically. Semantic atomicity and paraphrase matching are obligations of the
reviewed analyzer, not guarantees made by an exact identity comparison. The provider-neutral
`AnalysisPrompt`/`AnalysisRequest` contract builds bounded requests and strictly decodes batches; the host supplies a qualified transport and review policy. No SDK, embedding
model, network client, or permissive accept-all reviewer is bundled. Provider calls require the host's
normal qualification, privacy, deadlines, accounting, and spend controls.

Claim contexts reference source versions; dependencies and corrections reference claims, whose
attribution comes from their primary sources. Supersession is limited to a later source by the same
actor. Other actors' conflicting assertions remain disputed. An empty plan records a reviewed
question or other source with no propositions. `reviewId` supplies traceability; setting an arbitrary
identifier does not qualify an analyzer.

An edit can make other analyses pending. Reconcile those sources using their current original
content and evidence; do not change their chronological positions. Pending work is derived rather
than maintained as an independent queue. `forSummary()` rejects incomplete snapshots and returns
typed facts with source attribution and chronology, without original source text. The host derives
application summaries from this input.

`synchronize(sources, withdrawals, expectedRevision)` preflights a complete source handoff and
commits one revision. `validateBatch(ReconciliationBatch)` preflights without writing.
`applyBatch(ReconciliationBatch)` preflights chronologically ordered `SourceAnalysisDraft` entries
and commits them in one CAS. Later analyses may reference earlier draft keys; declarations are unique.
forward claim references are rejected. Exact retries converge on one batch receipt. No valid prefix
is committed if another source is invalid. At most 100 sources/propositions per batch are admitted.
The provider-neutral analysis contract requires every requested source, including empty analyses;
its complete request budget includes instructions and host context. Analysis requests select at most
eight sources to reserve response capacity; the core still accepts checked batches of up to 100.
The AI wire separates selected sources from context-only sources. Existing proposition/claim IDs
use collision-checked, request-local aliases; source IDs and new draft keys remain exact strings.
The decoder resolves aliases into canonical typed references against the same scoped snapshot.
Unknown aliases, replaced claims and new keys colliding with supplied identities remain invalid.
Complete current propositions and claims are never silently shortened to make space.

## Upgrading v1

Explicitly install package schema migration 002 and invalidate host summary caches/checkpoints.
Legacy record JSON and hashes remain unchanged. Their claims become ineligible, making source
analyses pending for fresh extraction; old prose IDs cannot receive new claims. They are excluded
from the reusable analyzer catalog. Do not convert prose into invented structured content. Newly
accepted structured knowledge and old provenance coexist; only current structured claims feed
`forSummary()`.

## Storage and capacity

The SQLite adapter implements a bounded aggregate, loading a consistent scope graph and committing
its changes with compare-and-swap. Immutable record payloads own meaning; evidence/link tables are
transactionally derived relational indexes with scoped foreign keys. The adapter never silently
truncates a scope. Its default `Limits` are 1,000 sources, 5,000 retained source versions, 2,000
propositions, 5,000 claims, 5,000 operation receipts, and 4 MiB of retained source text. A plan has at
most 100 drafts of each type and each claim has at most 20 references per relationship kind.
Inject lower limits where appropriate. Larger workloads need an indexed/partitioned implementation
of the repository port; increasing these limits does not change the aggregate's read/write cost.

A host may inject `commitGuard` into `SqliteRepository`; it runs inside each durable transaction
before commit. A rejected/cancelled host attempt rolls back that transaction without adding a
dependency on the host job module. The guard must use the same transactional source/ownership view.

Operation receipts make identical retries converge and reject changed reuse. Source registration
and withdrawal have content/version-based retry behavior. `erase()` removes knowledge and provenance
while retaining a non-content scope tombstone, preventing delayed work from recreating the scope.
The host owns protected database placement, backups, encryption, source retention, and authorized
erasure. Never log source/proposition content through generic exception handlers.

## Verification

The portable contract tests use only this package, PDO SQLite, injected time/IDs, and reviewed
fixtures. Run `./bin/test` from this package at the pre-merge gate; it verifies a mirrored Composer
install in an isolated container before running the contracts. No test contacts a model.

The host connects this contract to its background worker, provider accounting, source
synchronization and summary projections. Model qualification and scheduling remain host-owned.
