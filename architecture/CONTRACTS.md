# Contracts

Authoritative for how shared shapes are defined and consumed. A **contract** is any shape that crosses
a boundary: a wire message between client and server, a stored record, a content schema, a
device protocol. The rule is [DRY](../principles/DEVELOPMENT_PRINCIPLES.md) §1 at its strictest.

## The single-source rule

**A contract is defined in exactly one place and consumed from there by everyone.** Client, server,
and firmware read the same definition; none of them restates it. When a contract changes, it changes
in its one source **before** any code that depends on it — that ordering is part of the
[definition of done](../principles/WORKING_AGREEMENT.md).

Where the single source lives depends on who shares it:

- **TypeScript-only contracts shared by multiple workspaces** (for example, a wire message between
  web/mobile/server) are types in `@app/shared`, imported by their consumers. A server-persistence
  record used only inside `@app/server` is defined once there; sharing a wire contract does not make
  the physical database schema public to clients.
- **Cross-language contracts** (anything firmware in C++ must also honor) are specified in a `docs/`
  contract file that is the normative source; the TypeScript twin and the firmware implementation
  both conform to that document, and the document — not either implementation — is authoritative.

## Content is a contract too

Product content ships as data validated against a schema, not as code. "One validated definition plus
the assets it names" is a shape: the schema is defined once, the domain validates against it at
construction, and asset paths are **generated from one layout definition** so the on-disk tree, the
build config, and the content never drift. Detail: [assets & content](../practices/ASSETS_AND_CONTENT.md).

## Persistence ownership is part of the contract

For a networked multi-client application, the server owns canonical durable records.
Clients consume bounded, authenticated domain-use-case APIs; they do not receive generic persistence
operations. Browser/native storage is transient cache, session state, or an explicitly retired
migration source—not a competing truth.
Record identity, revision/concurrency semantics, authorization scope, and deletion behavior belong
to the shared contract even when the physical database schema remains private to the server.

This server-oriented blueprint does not define a local-only/offline-authoritative product. Such a
product needs a separate architecture specification before implementation; it cannot use local-only
language as an exception to the authentication, tenant-isolation, or server-authority rules of a
networked composition. “It uses IndexedDB/Preferences” is an adapter choice, not a synchronization
design.

## The semantic device model

The most important cross-language contract, and the one most often gotten wrong. A physical device is
driven by **meaning, not frames.** The wire carries a mode and its parameters; the firmware renders
the actual output locally from that meaning. The client never streams per-frame data, and the server
can hold a faithful in-memory twin of the device by interpreting the same contract.

This is worth stating as an explicit shape because the temptation to stream frames is strong and
wrong. A minimal example of the _meaning_-level contract for a multi-light device:

```jsonc
// Semantic: the client sends intent; the device computes frames.
{ "mode": "off" }
{ "mode": "ambient", "palette": ["#402015", "…"], "intensity": 0.6 } // device animates locally
{ "mode": "select",  "index": 3 }                                    // exactly one unit indicates a choice
```

Two things this buys, both required:

- **Correct timing on-device.** Animation runs at the device's own clock, unaffected by network
  jitter, because the device owns the frames.
- **A testable twin.** The server (and unit tests) interpret the same contract into an in-memory model
  and assert against it, with no hardware present.

_Reference implementation:_ Mystery's six-light box has two semantic modes — exactly one chamber
communicates a rolled result, while all six render scene ambience outside a roll — and the firmware's
allocation-free engine computes each mode's frames from the mode alone. The server mirrors it as an
in-memory device twin. See the project's `docs/DEVICE_API.md` for the concrete wire format; this
document is only the _pattern_.

## Rules

- One definition per contract; consumers import or conform, never restate.
- A contract change lands in its source before its dependents.
- Cross-language contracts are documented in a normative `docs/` file; the document is authoritative
  over any single implementation.
- Devices receive semantic intent and render locally. Never stream frames.
- Validate content against its schema at construction, so a malformed contract fails early.
- Keep canonical durable state server-owned. A local-authoritative product is outside this
  blueprint and requires a separate architecture specification.
