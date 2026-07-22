# Monorepo

Authoritative for the repository layout, workspace wiring, and shared toolchain. The module *roles*
are in [architecture overview](../architecture/OVERVIEW.md); this document is how they are assembled
into one repository.

## Why a monorepo

The stack's central constraint is a shared domain and a shared UI consumed by several hosts. A single
npm-workspaces monorepo is what makes that sharing a plain local dependency rather than a publishing
pipeline: `apps/web` and `apps/mobile` both depend on `@app/ui`, which depends on `@app/shared`, and
a change to the core is visible to every consumer immediately.

## Layout

```text
<project>/
  package.json            workspaces + root scripts (check, dev:*, build:*)
  tsconfig.base.json      the one strict TS baseline every workspace extends
  eslint.config.*         one lint config for the repo
  .prettierrc.json        one format config for the repo
  packages/
    shared/               @app/shared — pure domain core
    ui/                   @app/ui — shared components + bootstrap
  apps/
    web/                  @app/web
    mobile/               @app/mobile
    server/               @app/server
  firmware/               ESP-IDF / C++17 (outside the npm workspace)
  e2e/                    @app/e2e — Playwright acceptance
  tooling/                shared build config + content generators
  docs/                   contracts, architecture notes, roadmap, cross-project ledger
```

`workspaces` covers `packages/*`, `apps/*`, and `e2e`. Firmware is a separate C++/CMake build and is
not an npm workspace.

## The shared toolchain (defined once)

Every one of these is a single root file that workspaces extend — never a per-workspace copy. That is
DRY applied to configuration.

- **`tsconfig.base.json`** — strict TypeScript baseline: `strict`, `noUnusedLocals`,
  `noUnusedParameters`, `noImplicitReturns`, `noImplicitOverride`, `noFallthroughCasesInSwitch`,
  `isolatedModules`, `forceConsistentCasingInFileNames`, a modern `ES` target and `bundler`
  resolution. Each workspace's `tsconfig.json` extends it and adds only what it must.
- **ESLint + Prettier** — one config each at the root. No `any`, no unchecked casts (principles §4).
- **Shared bundler-config factory** — a `tooling/` function returns the base client bundler config
  (build target, output layout, chunk budget); each client calls it and overrides only its
  differences. This keeps build behavior identical across clients and is a prime backport candidate
  between projects.
- **Build/chunk budgets** — a single declared budget threshold that the bundler warns against. A
  deliberately split large chunk (e.g. a 3D engine shared by two renderers) has its budget set
  consciously as a review guardrail, not silenced. See [UI performance](../practices/UI_PERFORMANCE.md).

## Root scripts

Root `package.json` exposes the repo-wide verbs; per-workspace scripts do the actual work via
`--workspace`. The important composite is a single **`check`** that runs, in order: typecheck, unit
tests, server tests, lint, and builds. `check` passing is the bar for a broad change in the
[definition of done](../principles/WORKING_AGREEMENT.md).

```jsonc
// root package.json — illustrative
"scripts": {
  "check":       "npm run typecheck && npm run test:unit && npm run test:server && npm run lint && npm run build",
  "typecheck":   "npm run typecheck --workspaces --if-present",
  "dev:web":     "npm run dev -w @app/web",
  "dev:server":  "npm run dev -w @app/server"
}
```

## Package naming

Pick one scope per project (`@app` is the placeholder) and name packages by role:
`@app/shared`, `@app/ui`, `@app/web`, `@app/mobile`, `@app/server`, `@app/e2e`. The role, not the
product, names the package — so the layout reads the same across every project on the stack.

## Rules

- One toolchain config per concern, at the root; workspaces extend, never copy.
- Dependency direction (overview) is a build-time invariant: `@app/shared` depends on no other
  workspace.
- Firmware stays outside the npm workspace with its own CMake build.
- `check` is the single composite gate; keep it honest — a skipped step is a failed check.
