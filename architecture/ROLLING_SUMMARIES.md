# Rolling summaries

Optional reusable pattern for maintaining compressed conversational context. A rolling summary
updates a detailed memory from its previous revision plus changed source material. A shorter UI
brief may be derived from that detailed memory. This is an alternative to direct source replay or
[FactDB](FACT_DB.md), not a required layer of either. The host chooses the pattern for its workload
and qualifies the complete pipeline under [AI evaluation](../practices/AI_EVALUATION.md).

## Choosing the pattern

Rolling summaries can suit append-dominant conversations, handover notes, and bounded working
context where a reviewed compression policy is acceptable. They reduce repeated processing of
unchanged messages. They do not make a lossy summary equivalent to its original sources, eliminate
all reprocessing, or guarantee recovery of facts omitted in an earlier revision.

Prefer explicit evidence relationships or FactDB when immutable atomic propositions, corroborating
sources, conflicting attributions, withdrawal, and frequent historical edits are core requirements.
A bounded full-source rebuild may be simpler for small histories. Segmented summaries, checkpoints,
and retrieval are optional alternatives, not automatic requirements of rolling summaries. Record
the chosen completeness and edit-recovery policy before comparing quality or cost.

## Ownership and module boundaries

Keep raw messages and document metadata in their existing authoritative stores. Treat maintained
summary text, UI briefs, and search projections as derived data. A summary must never become fresh
user authorization, verified truth, or a second owner of application facts. Do not duplicate
canonical knowledge in current assistant memory and a summary repository.

The reusable core owns revisioned summary snapshots, source coverage, update admission, validation,
and publication. Inject ports for source loading, summary storage, model generation, clocks and
identifiers. Hosts supply access scope, source mapping, document context, retention, UI projection,
and qualification scenarios. Keep provider clients stateless. Do not embed legal fields, account
credentials, web routes, or a provider SDK in the core.

A host may pass dynamic document properties alongside generic metadata such as creation date and
participant identity. Keep the format extensible and separate trusted host metadata from quoted
message content. A fixed domain checklist is not required; strict envelopes, references, revisions,
and budgets still are. Flexible text does not justify silently losing numbers, uncertainty,
negation, speaker attribution, corrections, or the distinction between proposed and completed work.

## Durable state and update envelope

Persist a scoped summary revision with its detailed text, coverage manifest, source/context
revision, generation-contract identity, and update time. Coverage identifies the exact source IDs
and versions used; a timestamp alone is insufficient when edits share a timestamp or clocks differ.
Record any processing timestamp as observational metadata alongside revision identity. Store a UI
brief with the exact detailed-summary revision it derives from.

Each update loads the previous accepted summary and the authorized changes since its coverage:
new messages, edits, withdrawals, and changed document context. An update envelope identifies the
source's original chronological position, current version, previous used version, and kind of
change. New messages and edits are not interchangeable. Include complete message text within the
chosen budget, rather than chopping individual messages into invalid or misleading fragments.

For example, a summary records “the client reports rent of 10 million; a later correction says
12 million.” If the earlier message changes to 11 million, its update is explicitly an old-message
edit, not a new latest assertion. Supply its previous used text, replacement text, and the later
correction when the system can establish that relationship. The current summary should retain the
later 12-million correction without treating the edited earlier message as the latest event.

## Historical edits and recovery

A host may keep summary snapshots after accepted batches. An edit or deletion selects the latest
snapshot whose entire covered source prefix is still current and lies before the earliest changed
message, then replays every current message after that boundary in original order. Discard snapshots
containing changed/deleted inputs; later snapshots cannot be reused just because their final message
is unchanged. A missing or incompatible snapshot starts a rebuild from the beginning. Changed
document context invalidates snapshots that used the previous context. Source IDs/revisions and a
context/contract identity establish validity; old message bodies are not required by this algorithm.
Snapshots do not restore information omitted from their own compressed prefix.

Hosts exposing FactDB and rolling summaries concurrently select them through a strategy port,
with independent persisted state and source checkpoints. A common displayed summary is a derived
projection of the selected strategy only. Switching queues catch-up for the target and must not
let an in-flight nonselected strategy overwrite that display. Job lifecycle, saved execution steps,
common logging, and shared-host triggers follow [Job Management](BACKGROUND_JOBS.md).

A source's processing timestamp or revision tells the system **what changed**, not which later
messages reinterpret it. A rolling summary alone cannot reliably identify every relevant later
correction. State how the host obtains the required context:

- Recorded source dependencies or provenance can identify affected material directly.
- A bounded broader replay can include all later sources or rebuild the complete available history.
- Qualified lexical/semantic retrieval can find candidate context, but a missed correction remains
  possible; retrieval is not proof of complete coverage.

An update must not claim complete repair when required context cannot be supplied. Mark the derived
summary stale/pending and use the declared rebuild or review path. Withdrawals require the same
care: removing one source does not remove an independently supported fact, while unsupported
claims must not survive merely because old summary prose contains them. Dynamic metadata changes
also invalidate interpretations that depend on the changed context.

Retain source history or the exact previously used source content where necessary for edit repair,
subject to the host's retention/deletion policy. If that history is unavailable, rebuild from
current authorized sources rather than inventing a previous version. Erasure invalidates derived
copies and must prevent an old worker from restoring deleted context.

## Generation and publication

Budget the previous summary, complete changed-source envelope, required supporting context,
instructions, framing, and output reserve together. A larger detailed summary can reduce omissions
but is still bounded and lossy. Define rejection, additional batches, or rebuild when required
context exceeds the budget. Choosing only the oldest or newest messages must not masquerade as
complete history coverage. See [provider operations](AI_PROVIDER_OPERATIONS.md) for authoritative
prompt limits, retry/deadline accounting, and content-free diagnostics.

Ask the model for minimal necessary revisions and explicit preservation of exact values and
attribution. Validate the response envelope, bounds, references, and coverage mechanically. These
checks cannot prove natural-language correctness; semantic qualification remains necessary. A
failed/rejected attempt must not advance coverage or overwrite accepted text.

A host may strengthen incremental updates with an exact edit plan: bounded, unique, nonoverlapping
old-text/replacement pairs plus appended prose. Apply the pairs against the same accepted revision;
reject missing or ambiguous targets before advancing coverage. The detailed summary can rephrase
source messages and add speaker attribution. For example, changing ten to twelve million must retain
an existing monthly period unless the new source explicitly changes that period too.

Mechanically check recognizable value/context groups (amount, scale/currency and time period),
not the presence of isolated numbers or a qualifier somewhere else in the summary. Define the
supported lexical/format scope, retain exact dates where required, and reject ungrounded new values.
These rules do not establish complete semantic coverage, correct attribution or arbitrary-language
truth. Conservative rejection is visible work needing repair, never an accepted inaccurate update.

Canonical metadata also needs faithful attribution. An assigned participant is not necessarily the
person who created, registered or approved an item; a creation timestamp does not identify that
actor. Do not invent performed actions from those associations. Preserve supplied proper names as
literal identities, rather than interchangeable descriptions. Exact brief copying can propagate an
unsupported assertion already present in detailed memory, so review accepted intermediate prose.

Align prompts and value guards on historical retention. A guard that requires every value from a
batch can reject a faithful current-state summary that keeps only an explicit later correction.
If that policy is intentional, require superseded values to remain clearly historical in detailed
memory; otherwise define a separately qualified way to establish supersession. Number presence
alone cannot determine which claim replaced another. Measure these false rejections separately
from accepted inaccuracies, without replacing failed trials with successful reruns.

A brief projection may select referenced complete sentences from the accepted detailed summary and
render their exact text in code. References identify this attributed derived text, not raw user
messages that omit their speakers. Validate references, duplicates, length and protected value/context
coverage before publication. Exact copying prevents rewriting a selected sentence; it cannot prove
that the selection includes every material sentence or necessary surrounding context. Qualify a
rewritten projection and a sentence-selection projection as different strategies/configurations.

Do model work outside database write locks. Publish detailed text and its coverage atomically only
if source/context and prior-summary revisions still match. Use compare-and-swap and trusted
idempotency identities to reject stale/concurrent results and avoid duplicate updates. Bounded
retries may reuse the same input only while its revision checks remain valid. Never replay an
already committed application effect to recover a summary.

Derive the UI brief from the accepted detailed revision under a separately qualified projection
contract. If brief generation fails, preserve detailed memory and leave the brief pending or
explicitly retain a labeled older revision according to product policy. Do not report the new UI
brief as current merely because detailed memory was updated successfully.

## Verification and operational use

Use deterministic source/model/clock doubles to prove scope isolation, complete coverage accounting,
new-message versus old-edit handling, metadata invalidation, withdrawal, CAS rejection, idempotency,
restart recovery, bounded retries, all-attempt usage reporting, and atomic publication. Include
failure between detailed-memory commit and brief publication, and erasure during in-flight work.

Live acceptance covers long append sequences, repeated compression drift, corrections of earlier
claims, old edits preceding later corrections, conflicting speakers, exact values, uncertainty,
source withdrawal with independent support, dynamic properties, and injected instructions inside
source text. Review intermediate detailed summaries as well as final UI briefs; an incorrect
accepted memory is a failure even when a later brief happens to look correct. Report recovered
transport failures separately from semantic failures under the shared evaluation rules.

Provide a bounded rebuild/audit mechanism from authoritative sources and define when it is used
(for example a detected inconsistency, retention change, contract upgrade, or product-specific drift
threshold). Qualify rebuild and incremental paths independently. Keep fingerprints and evidence for
the selected model, prompt, edit-context policy, compression limits, brief projection, and retry
bounds. A rolling-summary choice in one application does not require replacing another
application's qualified FactDB pipeline.
