# Agent instructions

This repository is the documentation-only specification for the application stack. It contains no
runnable product code.

Before changing this blueprint or using it to guide a project, read the normative
[development principles](principles/DEVELOPMENT_PRINCIPLES.md) and
[working agreement](principles/WORKING_AGREEMENT.md), then consult the authoritative area document.
Do not restate those rules here.

## Document map

- Pattern vocabulary: [design patterns](principles/DESIGN_PATTERNS.md)
- Module boundaries and domain architecture: [architecture](architecture/OVERVIEW.md)
- Client, server, firmware, and monorepo structure: [components](components/MONOREPO.md)
- Testing, rendered UI integrity, i18n, assets, performance, isolated toolchains, and workflow:
  [practices](practices/TESTING.md)
- Runtime and supply-chain security: [sessions](security/SESSION_MANAGEMENT.md),
  [untrusted files](security/UNTRUSTED_FILE_HANDLING.md), and [scanning](security/README.md)
- Project conformance: [cross-project sync](reuse/CROSS_PROJECT_SYNC.md)
- New-project sequence: [new project guide](NEW_PROJECT_GUIDE.md)

Keep `AGENTS.md` and `CLAUDE.md` identical so both agent entry points expose the same pointer and map
without becoming a second rules source.
