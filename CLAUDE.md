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
- Runtime assistants, provider operations, and live quality comparisons:
  [AI subsystem](architecture/AI_SUBSYSTEM.md), [provider operations](architecture/AI_PROVIDER_OPERATIONS.md),
  and [evaluation](practices/AI_EVALUATION.md)
- Maintained prose context as an alternative: [rolling summaries](architecture/ROLLING_SUMMARIES.md)
- Durable steps, recovery, shared logs/admin, and shared-host triggers:
  [job management](architecture/BACKGROUND_JOBS.md)
- Immutable propositions and source evidence: [FactDB](architecture/FACT_DB.md)
- Client, server, firmware, and monorepo structure: [components](components/MONOREPO.md)
- Testing, rendered UI integrity, i18n, assets, performance, isolated toolchains, and workflow:
  [practices](practices/TESTING.md)
- Development-agent discovery, file-access boundaries, and indexing/upload verification:
  [workspace context controls](practices/AI_AGENT_CONTEXT.md)
- Runtime and supply-chain security: [sessions](security/SESSION_MANAGEMENT.md),
  [untrusted files](security/UNTRUSTED_FILE_HANDLING.md), and [scanning](security/README.md)
- Owner-authorized agent sign-in and UI checks on deployed applications:
  [hosted browser access](security/HOSTED_BROWSER_ACCESS.md)
- Project conformance: [cross-project sync](reuse/CROSS_PROJECT_SYNC.md)
- New-project sequence: [new project guide](NEW_PROJECT_GUIDE.md)

Keep `AGENTS.md` and `CLAUDE.md` identical so both agent entry points expose the same pointer and map
without becoming a second rules source.
