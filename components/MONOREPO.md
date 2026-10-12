# Monorepo

Authoritative for the repository layout, workspace wiring, and shared toolchain. The module _roles_
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
  containers/             isolated definitions for invasive or platform-specific toolchains
  firmware/               ESP-IDF / C++17 (outside the npm workspace)
  e2e/                    @app/e2e — Playwright acceptance
  tooling/                shared build config + content generators
  docs/                   contracts, architecture notes, roadmap, blueprint-sync ledger
```

`workspaces` covers `packages/*`, `apps/*`, and `e2e`. Firmware is a separate C++/CMake build and is
not an npm workspace.

## The shared toolchain (defined once)

Every one of these is a single root file that workspaces extend — never a per-workspace copy. That is
DRY applied to configuration.

- **`tsconfig.base.json`** — strict TypeScript baseline: `strict`, `noUnusedLocals`,
  `noUnusedParameters`, `noImplicitReturns`, `noImplicitOverride`, `noFallthroughCasesInSwitch`,
  `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`, `isolatedModules`,
  `verbatimModuleSyntax`, `noUncheckedSideEffectImports`, `forceConsistentCasingInFileNames`, a modern
  `ES` target and `bundler` resolution. Each workspace's `tsconfig.json` extends it and adds only what
  it must.
- **ESLint + Prettier** — one config each at the root. No `any`, no unchecked casts (principles §4).
- **Runtime and package-manager baseline** — version one LTS Node selector (for example `.nvmrc` or
  `.node-version`), declare the compatible runtime range and exact package-manager release in the
  root manifest, and use the lockfile's frozen install mode in automation.
- **Shared bundler-config factory** — a `tooling/` function returns the base client bundler config
  (build target, output layout, chunk budget); each client calls it and overrides only its
  differences. This keeps build behavior identical across clients and is a prime candidate to propose
  upstream to the blueprint.
- **Build/chunk budgets** — a single declared budget threshold that the bundler warns against. A
  deliberately split large chunk (e.g. a 3D engine shared by two renderers) has its budget set
  consciously as a review guardrail, not silenced. See [UI performance](../practices/UI_PERFORMANCE.md).
- **Isolated specialized toolchains** — committed OCI-compatible definitions and root runners expose
  stable npm verbs for firmware or another invasive SDK. The host keeps only the selected container
  engine; compilers and SDK packages stay inside images or declared caches. See
  [containerized toolchains](../practices/CONTAINERIZED_TOOLCHAINS.md).

## Root scripts

Root `package.json` exposes the repo-wide verbs; per-workspace scripts do the actual work via
`--workspace`. The important composite is a single **`check`** that runs, in order: typecheck, unit
tests, server tests, lint, and builds. `check` passing is the bar for a broad change in the
[definition of done](../principles/WORKING_AGREEMENT.md).

Firmware exposes `firmware:build:<target>` and `firmware:build:all` through the root even though it is
not an npm workspace. Keep long cross-compilation out of an ordinary UI-only inner loop when needed,
but make the all-target container build a required CI/release gate and a required local check for
firmware, container, lockfile, or toolchain changes.

The mobile workspace similarly exposes the isolated Android package build at the root. Capacitor's
web build remains part of the ordinary workspace check; native APK/AAB compilation is an additional
gate for Android shell, bridge, Gradle, SDK, permission, or container changes.

```jsonc
// root package.json — illustrative
"scripts": {
  "check":       "npm run typecheck && npm run test:unit && npm run test:server && npm run lint && npm run build",
  "typecheck":   "npm run typecheck --workspaces --if-present",
  "dev:web":     "npm run dev -w @app/web",
  "dev:server":  "npm run dev -w @app/server"
}
```

## Development server execution and LAN reachability

Application web/API development runners (`dev`, `dev:web`, `dev:server`, or an equivalent `bin/dev`)
bind to all IPv4 interfaces (`0.0.0.0`) by default for local area network (LAN) device testing. One
shared development configuration owns the default and a documented operator override for
loopback-only access; workspace scripts and the shared bundler-config factory consume it.

Container runners publish the required application ports on a LAN-reachable host address. In
loopback-only mode, restrict the host port publication while keeping the listener reachable inside
the container. Preserve the [container isolation rules](../practices/CONTAINERIZED_TOOLCHAINS.md#host-isolation).
Binding alone does not establish reachability: host firewalls, VM/WSL forwarding, routing, and Wi-Fi
client isolation may require explicit operator configuration. Scope inbound access to the intended
test networks when the host also has publicly reachable interfaces. Report the host's usable LAN
address or hostname and actual port in startup output; devices connect to that address.

The [web client](WEB_CLIENT.md#development-server-and-lan-reachability) owns browser origin, proxy,
and HTTPS configuration. Every listener retains the
[development trust boundary](../principles/DEVELOPMENT_PRINCIPLES.md#7-trust-boundaries-are-not-mvp-compromises).

### Verification

In implementing projects, test the default and operator override through the public runners,
including container port publication when used. From a second LAN device, verify page loading,
authenticated API access, and hot reload where supported; verify loopback-only mode still works
locally and rejects remote connections. Report network prerequisites or an unperformed device check
explicitly.

## Package naming

Pick one scope per project (`@app` is the placeholder) and name packages by role:
`@app/shared`, `@app/ui`, `@app/web`, `@app/mobile`, `@app/server`, `@app/e2e`. The role, not the
product, names the package — so the layout reads the same across every project on the stack.

## Rules

- One toolchain config per concern, at the root; workspaces extend, never copy.
- Dependency direction (overview) is a build-time invariant: `@app/shared` depends on no other
  workspace.
- Firmware stays outside the npm workspace with its own CMake build.
- Invasive and platform-sensitive tools follow the host-safe container practice; root npm scripts
  remain the public interface.
- `check` is the single composite gate; keep it honest — a skipped step is a failed check.

## Standards basis

- [Docker port publishing](https://docs.docker.com/engine/network/port-publishing/) — application
  listeners and host port mappings are separate network boundaries
