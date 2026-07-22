# Development principles

The single normative source for **how** work is done on this stack. Every other document in this
repository is authoritative for one *area* (architecture, a client, a practice) and points back here
for the rules. When something here and something there seem to conflict, this file wins.

These principles are binding on **every** change, however small — a one-line fix obeys them as fully
as a new subsystem.

---

## 0. PRECISE YET CONCISE

The house style, and the lens for every rule below. Say exactly what is true, and no more.

- **Precise:** state the real constraint, the real dependency direction, the real invariant. No
  vagueness that a reader has to guess past, no hand-waving where a fact belongs.
- **Concise:** one fact lives in exactly one place. Link to it; never restate it. Delete a sentence,
  a comment, or a whole document the moment it stops adding information the code or a sibling doc
  does not already carry.
- Applied to **code:** the shortest expression that is still obvious. Not code golf — clarity is the
  target, brevity is what clarity usually costs once the design is right.
- Applied to **docs and comments:** explain the *why* the code cannot say itself. Never narrate what
  the code already states. A comment that repeats its line is deleted, not maintained.
- Applied to **this repository:** describe intent and constraints in prose; drop into a code or
  contract block only where the thing is genuinely tricky or non-obvious. A precise description lets
  the implementing agent write more idiomatic code than a skeleton frozen here ever could.

Everything that follows is PRECISE YET CONCISE made specific.

---

## 1. DRY — never duplicate anything

A second occurrence of anything — behavior, a wire shape, a user-facing string, a documented fact —
is a signal to extract, not to copy.

- **Code:** the second time a behavior appears, it becomes a reusable module that *owns* that
  concern, and both callers depend on it. Platform bootstraps, build configuration, and error
  handling differ only in the values they inject — never in duplicated logic.
- **Contracts:** a wire, storage, or content shape is defined **once** and consumed from that one
  place by every client, the server, and firmware. See [contracts](../architecture/CONTRACTS.md).
- **User-facing strings:** every string outside content lives once in the typed message catalog,
  with each supported locale in parity. No inline literals in components. See [i18n](../practices/I18N.md).
- **Facts:** one fact, one document. This is why the normative rules live only here and every entry
  point *links* rather than restating.

DRY is not "avoid typing twice." It is "there is exactly one place the truth about X can change."

---

## 2. Object-oriented design

Model behavior as small, role-based classes: one responsibility, an intention-revealing name,
composition preferred over inheritance.

- **Depend on abstractions.** Domain services receive their collaborators as constructor parameters
  (ports) and are resolved from the DI container. No module-level singletons, no global lookups, no
  reaching into a service locator from inside a class.
- **Reuse the established patterns rather than inventing parallel ones.** The catalog is fixed and
  documented in [design patterns](DESIGN_PATTERNS.md): ports & adapters for every platform
  capability, a container + tokens for wiring, a state machine for domain flow, a mediator for
  coordinating effects, an observer store for state propagation, repositories for persistence,
  strategies for interchangeable behavior, a semantic model for physical devices.
- **Keep decisions in the domain.** Rules live in the shared domain core; UI components render and
  delegate — they never decide. See [shared domain](../architecture/SHARED_DOMAIN.md).
- **Follow SOLID as consequences, not slogans:** a class has one reason to change; it is open for
  extension through injected strategy, not modification; adapters are substitutable for their port;
  ports are narrow and role-specific rather than fat; high-level policy depends on port
  abstractions, and adapters depend on those same abstractions.

---

## 3. Determinism and testability

Design so that every rule can be proven without a DOM, a network, or real time.

- Anything non-deterministic — randomness, the clock, delayed work, I/O — enters a class through a
  port. Tests inject a fixed source (a fixed dice roll, a manual test clock) and assert exact
  outcomes. See [testing](../practices/TESTING.md).
- A domain unit test constructs the class with test doubles and asserts behavior. If a rule cannot be
  tested that way, the design has a hidden dependency to lift into a port.
- Effects that are *timed* (audio cues, animations, retries) issue their timing through a scheduler
  port, so `delayMs` is asserted against a manual clock instead of waited out.

---

## 4. Strict typing

- Application and tooling code is **strict TypeScript**; firmware is **C++17**. These are the only
  two languages on the stack.
- No `any`, no unchecked casts, no silently widened types at a module boundary. A value that crosses
  a boundary has a named, checked type.
- The shared strict compiler baseline (`tsconfig.base.json`) and lint/format config live once at the
  monorepo root and every workspace extends them. See [monorepo](../components/MONOREPO.md).

---

## 5. One implementation, many hosts

- The web and Android clients render the **same** UI component set. Never fork a screen by platform;
  inject the platform's behavior through a port and keep the screen identical. See
  [shared UI](../components/SHARED_UI.md).
- The domain core is framework- and DOM-free. The server may import it; it must never import the UI.
  The dependency direction is fixed in [architecture overview](../architecture/OVERVIEW.md).
- Physical devices receive **intent** through a semantic contract and render frames locally. Clients
  and firmware never stream animation frames to each other. See [contracts](../architecture/CONTRACTS.md).

---

## 6. Visual performance is a rule, not a polish step

Perpetual visual effects follow a hard budget from the first commit, not a later optimization pass.
Compositor-only animation, respect for reduced motion, and an adaptive lifecycle for any GPU canvas
are acceptance criteria. The full budget and its verification method are in
[UI performance](../practices/UI_PERFORMANCE.md).

---

## 7. Definition of done

A change is done when all of the following are true — see [working agreement](WORKING_AGREEMENT.md)
for the workflow that enforces them:

- It was developed on its own branch, created **before** the first edit.
- The narrowest relevant tests pass while iterating, and the repository-wide check passes for a
  broad change. UI changes additionally pass the browser acceptance flow in every supported locale.
- Any contract it touched was updated in the single source *before* the code that depends on it.
- Any cross-project reuse it introduced or adopted is recorded in the same change. See
  [cross-project sync](../reuse/CROSS_PROJECT_SYNC.md).
- The result — including failures and skipped steps — is reported honestly, and `main` is not
  advanced without the owner's explicit confirmation.

---

## How an agent should use this file

Read it before the first edit of every task. It is short by design. The area documents assume you
have; they describe *where* things go and *how* they are shaped, and rely on this file for *why* and
*whether*.
