# Payments and wallets

Authoritative for payment-provider integration, payment receipts and stored-value accounting.
It supplements [authorization](AUTHORIZATION.md), [ports and adapters](PORTS_AND_ADAPTERS.md)
and [contracts](CONTRACTS.md). A provider's documented protocol remains an adapter concern;
product prices, wallet rules and fulfillment remain application/domain concerns.

## Boundary and provider selection

Use a narrow payment-provider port with typed initiation and authoritative settlement outcomes.
Concrete adapters own authentication, signatures, unit conversion, approved endpoints, response
validation and provider-specific inquiry/verification semantics. Inject transport, clock, identifiers,
repository and provider registry. Production never selects a fake bank or local success fallback.

Administrative selection affects new attempts. Persist the selected provider and stable merchant/account
identity on each attempt; verification and reconciliation use that identity even after a default change.
A missing or mismatched historical account leaves an explicit unresolved outcome. Retain historical
credential references through pending-payment recovery; never silently use a replacement merchant.
Keep keys server-side, protect configuration changes with a dedicated permission, CSRF/origin checks,
optimistic revision control and an actor/time audit trail. Separate pausing new payments from resolving
existing ones. Do not expose secrets in configuration screens, browser bundles, queryable history or logs.

## Money and intent

Represent canonical money as checked integer minor units plus an explicit currency. Normalize accepted
localized input before validation; conversions between display and provider units are explicit and exact.
Do not use binary floating-point arithmetic for balances, prices, fees or authorization decisions.
An adapter may decode a vendor's integral JSON floating-point field only after finite/range/integrality
checks; this does not permit floating-point domain arithmetic.

The server establishes owner/tenant, purpose, amount, currency, provider and account before initiating
payment. Package/order amounts come from server-owned price snapshots. A wallet top-up may accept a
user-selected amount within server/provider limits. Keep principal value, any payer fee, total charged,
and the credited/fulfilled value distinguishable. A gateway fee does not silently buy wallet credit.

Persist an intent/attempt before network I/O. Bind an idempotency key to the authenticated owner and
exact financial intent; an identical replay returns the existing attempt, while a different amount or
purpose conflicts. Use globally unique application order IDs even when the provider does not enforce
uniqueness. Provider references are unique within provider/account scope. Client-supplied ownership,
time, currency conversions, callback origins and provider URLs are never authoritative.

## State and evidence

Use explicit states for initiation, awaiting payment, uncertain creation/verification, terminal success
and definitive failure/cancellation. Unknown outcomes stay unknown. A callback, browser redirect, HTTP
200, supplied tracking number or provider request-acceptance response is insufficient to grant value.

Before accepting success, validate authoritative server-to-server evidence against the persisted
merchant, provider reference, application order identity where the protocol supplies it, currency and
expected amount semantics. When a protocol only echoes reference/amount on verification, bind the
verified reference to its previously checked initiation receipt. Record the confirmation source and
safe support identifiers. Unknown/malformed response states never become success or definitive failure.

Verify or inquire using the original amount and account, not callback amounts or the current default.
Some providers consume verification on success. An already-verified code alone is insufficient: recover
through authoritative inquiry with the same identity and amount checks. Treat a timeout or process
interruption as possible remote completion. Do not automatically recreate a payment, reroute to another
provider, or repeat an unsafe effect to make an ambiguous attempt disappear. Reuse a stable provider
idempotency identity only when the provider explicitly guarantees its semantics.

## Callbacks and browser sessions

Document each public callback as a bounded external entry point. Verify the provider's authenticated
notification mechanism when available. For browser-return protocols without signed notifications,
use an unguessable transaction-bound capability, compare it safely, validate the original order/reference,
and still require server-to-server confirmation. Capability possession grants no wallet read authority
and cannot redirect credit to another owner. Use fixed registered callback origins and approved checkout
origins; do not derive them from an untrusted Host header or arbitrary redirect input.

Exempt only the external callback from ordinary browser CSRF enforcement. Top-ups, retries,
configuration and administrative controls keep normal authentication and CSRF/origin checks. A callback
works without a current browser session; displaying history and receipts remains authenticated and
owner scoped. Select the provider's return method deliberately with browser SameSite behavior in mind.
Redact callback query secrets from access/error logs, emit no-store/no-referrer responses, and redirect
to a clean authenticated result/history URL without capability or card data. Do not collect card
credentials on the merchant application. Protocol-required card evidence is transient or separately
protected, minimized and bounded; it never becomes ordinary transaction history.

## Local commit and accounting

Keep network calls outside database transactions. Admit verification using bounded concurrency control
and a persisted operation claim; a finite lease is longer than the maximum bounded provider operation.
Reject stale claim publication after takeover. Provider idempotency/inquiry handles remote duplication;
a local claim alone is not an exactly-once external-effect guarantee. Limit creation and callback/retry
work per principal/attempt, with an explicit recovery path after exhausted allowances.

Commit confirmed payment status, immutable confirmation/journal and the corresponding value grant in
one local transaction. Enforce uniqueness at the database boundary so racing callbacks, browser retries,
fresh processes and administrative reconciliation converge on one grant. Prove rollback at every write
boundary, including remote success followed by local commit failure. A different database or external
fulfillment requires a durable outbox/recovery protocol, not a success flag followed by a best-effort call;
background execution follows [Job Management](BACKGROUND_JOBS.md).

Stored value uses an append-only balanced journal with explicit currency, amount, owner, source payment
and economic accounts. A single immutable transfer record may encode equal debit/credit legs when the
supported operation is a two-account transfer. Derive balance from canonical postings; a cached balance
is updated transactionally and reconciled against them. Never edit a historical posting or erase history
to repair money. Refunds, reversals and adjustments are authorized, uniquely correlated compensating
operations with their own reconciliation; purchasing, spending and withdrawing require separate
server-owned use cases. Do not imply that a top-up implementation already supports those operations.

## Recovery, history and operations

Expose a durable history to each payer, including failed, cancelled and uncertain attempts. Scope the
query itself to the authenticated owner/tenant; even an administrator's personal-wallet endpoint remains
owner scoped. Provide bounded pagination, amount/currency/fee semantics, current status, timestamps and
support references. Administrative cross-owner inspection and reconciliation require explicit permission.
Recovery uses the same settlement and accounting path as the callback, never a separate manual-success
shortcut. Retain financial records independently of mutable profile data; define account deletion,
restricted access, backup/restore and retention policy for the product's jurisdiction and obligations.

Document provider confirmation deadlines, reversal/expiry behavior, missing-callback behavior,
reconciliation triggers, bounded attempt/time budgets, stale claims, lost initiation responses and
required support escalation. If inquiry cannot recover a pending attempt's verification inputs, say so;
never present the unresolved attempt as failed or promise an automatic refund without authoritative
provider evidence. Do not claim provider settlement or chargeback/reversal handling from a payment
acceptance receipt alone. Operators need a documented path for every unresolved state, including
restoring historical account configuration and obtaining provider evidence.

## Required verification and adoption

Select gates under [testing](../practices/TESTING.md#change-specific-verification-gates) and execute them
at [pre-merge verification](../principles/WORKING_AGREEMENT.md#pre-merge-verification). Deterministic
coverage includes:

- canonical/localized money boundaries, exact signatures and request mapping, protocol amounts/fees,
  malformed responses, timeouts, repeated verification, explicit failure and unknown status;
- unauthenticated/inactive/forged-owner rejection, cross-owner isolation, admin permission, revocation,
  CSRF/origin defenses and callback capability/order/reference tampering;
- identical/conflicting retries, repeated/concurrent callbacks, stale claims, real separate-process races,
  database rollback, restart recovery and remote-success/local-failure reconciliation;
- provider/default/account changes, missing credentials, creation/retry limits and exhaustion recovery;
- immutable balanced postings, exactly one local grant, preserved history, migration/idempotency,
  foreign-key integrity and restoration of prior records;
- populated and empty wallet/history/admin pages, uncertain/failure/success states, localized inputs and
  catalog errors, all supported locales/directions, mobile containment and accessible controls.

Recording/fake transports prove contracts without contacting a live gateway. Live checks are separately
named, explicitly authorized, target/amount bounded and governed by
[live provider checks](../practices/TESTING.md#live-provider-smoke-checks). Report absent live evidence
as unverified; passing deterministic tests does not qualify production credentials or merchant settings.

Projects adopt this optional subsystem only when payments are in scope. Their local contract records
provider/API version or documentation access date, exact currency/unit mapping, fee and receipt semantics,
registered callback, configuration, schema, limits, recovery and operational exclusions. Keep vendor
constants and product policy there rather than making one vendor's restrictions universal stack rules.

## Sources

- [OWASP payment gateway integration](https://cheatsheetseries.owasp.org/cheatsheets/Third_Party_Payment_Gateway_Integration_Cheat_Sheet.html)
- [OWASP transaction authorization](https://cheatsheetseries.owasp.org/cheatsheets/Transaction_Authorization_Cheat_Sheet.html)
