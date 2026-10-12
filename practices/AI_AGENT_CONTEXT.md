# AI agent workspace context

Authoritative for development-agent file discovery, context ingestion, and the controls that bound
local indexing work, remote uploads, and sensitive-file access. Controls follow the
[proportionate safeguard rule](../principles/DEVELOPMENT_PRINCIPLES.md#7-trust-boundaries-are-not-mvp-compromises).
Application-owned assistant memory and retrieval belong to the
[AI subsystem](../architecture/AI_SUBSYSTEM.md).

## Configure before ingestion

For each supported development agent, record its version/platform, discovery mechanisms, whether
indexing is local or remote, and which settings enforce each boundary. Verify these facts against
the installed version and official documentation; an agent's shell access does not establish that
it automatically embeds or uploads the whole workspace.

Commit applicable repository configuration and concise setup instructions before installing
dependencies, unpacking large generated trees, or enabling automatic indexing. For an existing
workspace, pause affected indexers while configuring and verifying exclusions; rebuild their indexes
when necessary. New exclusions do not prove previously ingested data has been removed remotely.
Machine-specific permissions belong in documented developer setup or managed policy, with no
credentials or assumptions about another contributor's home paths in committed configuration.

## Separate the controls

| Control | Purpose | Limit |
| --- | --- | --- |
| `.gitignore` | Keep generated or private local files out of ordinary source tracking. | Does not deny filesystem reads or uploads, and does not untrack existing files. |
| Search/index exclusions | Reduce discovery noise and indexing cost. | Cover only the tools and operations that honor them. |
| Filesystem permissions or isolated workspaces | Make protected files unavailable to agent tools. | Must cover direct reads, shell subprocesses, and other configured file-access paths. |
| Network/ingestion controls | Bound transfer volume and destinations. | A command sandbox's network policy does not necessarily govern the agent's model connection, indexer, or connectors. |

Use `.gitignore` for the project's actual generated dependencies, outputs, caches, and private local
data. Add `.ignore` or `.rgignore` when its consumer needs different discovery rules; agent-specific
setup requirements are defined below. Reuse existing exclusions where supported; when several tools
require separate files, derive overlapping patterns from one maintained source.
[Ripgrep's filtering](https://github.com/BurntSushi/ripgrep/blob/master/GUIDE.md#automatic-filtering)
normally honors Git and search ignore files, but flags and explicit paths can bypass filtering.
Validate the commands the agent actually uses.

### The three Git exclusion scopes and boundary rules

Git provides three distinct exclusion scopes with strict precedence:
1. **Shared repository exclusions (`.gitignore`):** Committed project-wide rules governing generated
   dependencies, outputs, caches, and private local layouts.
2. **Repository-specific personal exclusions (`$GIT_COMMON_DIR/info/exclude` or `.git/info/exclude`):**
   Uncommitted rules scoped strictly to that local clone, suitable for machine-specific developer
   overrides or scratch files in that specific repository.
3. **User-wide personal exclusions (`core.excludesFile`, defaulting to `$XDG_CONFIG_HOME/git/ignore`
   or `~/.config/git/ignore`):** Personal defaults applied across every repository on the developer machine.

Exclusion patterns affect untracked path discovery and status; already tracked files remain tracked,
repository rules take precedence, and forced additions remain possible. Maintain strict boundaries:

- **Never blanket-ignore collaborative configuration directories:** Directories such as `.github/`
  and `.vscode/` belong to team coordination.
  - `.github/` contains CI/CD workflows (`.github/workflows/`), issue and PR templates, `dependabot.yml`,
    and `CODEOWNERS`. A blanket `.github` rule in global or personal ignore silences untracked workflows
    and automations from normal status and staging across all repositories.
  - `.vscode/` contains repository-shared editor formatting, linter profiles (`.vscode/settings.json`),
    recommended extensions (`.vscode/extensions.json`), and shared build/debug tasks. Blanket-ignoring
    `.vscode` blinds contributors to team workspace conventions.
  - Exclude machine- or user-specific files by targeted pattern in the repository's uncommitted
    `.git/info/exclude` (e.g. a personal scratch file or machine-dependent debug config), never by
    global directory-level bans.
- **Keep project build outputs in repository-level `.gitignore`:** Generic directory names like `build/`
  or `dist/` must not be placed in global ignore files. Software stacks outside Web/Node (such as C/C++,
  CMake, Gradle, or custom tooling) routinely maintain committed build orchestration scripts, Makefiles,
  or Dockerfiles under a directory named `build/`. Output directories must be ignored in the repository's
  own `.gitignore` matching their concrete generated layout.
- **Permissible global ignore scope:** Limit global ignore files strictly to genuinely personal or
  OS-level artifacts (e.g. `.DS_Store`, `Thumbs.db`), user shell history (`*.zsh_history`), and personal
  tool overrides (`**/.claude/settings.local.json`). Language-toolchain caches and bytecode belong in
  repository `.gitignore`; any user-selected global defaults must be verified not to hide maintained
  test fixtures across unrelated repositories.

### Context discovery vs. model context ingestion

Exclude generated dependency trees (`node_modules/`, `bower_components/`, `vendor/`), build outputs,
caches, dumps, runtime logs, and large archives from routine context discovery according to their actual
paths, including nested workspaces. Keep source, manifests, lockfiles, sanitized configuration examples,
and test fixtures discoverable. Avoid blanket extension or directory bans that hide maintained files:
images and fonts may be [product assets](ASSETS_AND_CONTENT.md), screenshots may be
[UI verification evidence](RENDERED_UI_INTEGRITY.md), and a storage directory may contain tracked
scaffolding. Allow bounded inspection of relevant assets, dependencies, or redacted diagnostics when
the task requires it. Builds still need access to their dependencies; ordinary package-manager
workflows remain governed by [monorepo](../components/MONOREPO.md), while invasive SDKs follow
[containerized toolchains](CONTAINERIZED_TOOLCHAINS.md).

Distinguish index discoverability from bulk model ingestion:
- Manifests (`package.json`, `Cargo.toml`, `pyproject.toml`) and lockfiles (`package-lock.json`,
  `yarn.lock`, `pnpm-lock.yaml`, `Cargo.lock`) must remain discoverable to search tools and package
  managers so dependency trees and version pins can be verified.
- Agents must **never bulk-dump multi-megabyte lockfiles or minified bundles into model context or
  prompt payloads**. Inspect specific dependencies through targeted searches or structured
  extraction, with an explicit total output bound applied before results enter model context. Use a
  maximum-output-byte control, or combine bounded file scope and result count with per-line byte
  limits—for example, `rg --max-count 10 --max-columns 200` against one explicit lockfile.
  Match-count-only limits such as `grep -m` do not bound line length; per-line limits alone do not
  bound total output. Structured queries must also return bounded results and use the evidence source
  required by the task (e.g. `npm ls --package-lock-only <package>` for lockfile pins). Treat omitted
  or truncated output as incomplete evidence and refine the query when necessary.

Protect credentials and private data according to the task's access needs. Keep unrelated sensitive
files outside the agent-accessible environment, or enforce targeted read denials through a verified
runtime boundary. Provide relevant private data through bounded, authorized access, redacting fields
the task does not need. A needed development credential is scoped to the operation that consumes
it; its value must not enter model context or diagnostics. If the runtime cannot enforce the
required boundary, use an isolated environment containing only permitted inputs. Ignore files and
instructions alone are insufficient for that guarantee.

## Tool-specific integration

- **OpenAI Codex:** use the installed version's
  [permission profiles](https://learn.chatgpt.com/docs/permissions). Workspace-relative rules belong
  under `permissions.<name>.filesystem.":workspace_roots"`; `deny` blocks reads and writes. Prefer
  exact sensitive paths when practical; glob expansion can have platform-specific depth and startup
  limits. Verify the active profile and its coverage rather than relying on a configuration file's
  presence. Command-network restrictions do not cover every connector or ingestion path.
- **Editor integrations:** [VS Code search exclusions](https://code.visualstudio.com/docs/editing/codebasics#advanced-search-options)
  govern editor search. Watcher settings govern file watching; neither establishes a remote
  ingestion boundary without documentation and verification for that integration.

### Google Antigravity

**For projects using a version of Antigravity that supports it, commit `.antigravityignore` at the
workspace root as a required setup artifact before dependency installation or agent discovery.**
Populate it with the project's generated dependency, output, cache, and private-data paths using the
policy above. Verify that the installed Antigravity surface consumes it; its presence alone is
insufficient. Do not silently omit it or assume `.gitignore` substitutes for it. If the selected
version does not support this file, document the limitation and verify an equivalent supported
discovery control before enabling the affected feature.

Select controls for the actual CLI or IDE surface using its
[settings documentation](https://antigravity.google/docs/settings). Its
[changelog](https://antigravity.google/docs/changelog) documents `.antigravityignore` handling in
file completion; that alone does not establish exclusion from every indexer or upload path.

For IDE versions that expose `antigravity.searchMaxWorkspaceFileCount`, **start with `3200` files as
the suggested indexing threshold**. Set it in the installed IDE's supported workspace or user
settings scope; commit `.vscode/settings.json` only when that version recognizes the key and scope.
Verify the effective value and how excluded/binary files contribute to the count. Adjust the
threshold to measured workspace needs and resource limits, documenting the reason. If the setting
is unavailable or unrecognized, record that limitation and use a supported indexing limit or
disable unnecessary automatic ingestion; an ignored setting provides no control.

**User-wide agent rules and editor settings as behavioral backstops:**
For workstations running Google Antigravity across multiple projects, configure machine-local
guidance to minimize routine chat bloat without dirtying committed repository files:
- Maintain a single canonical global rule source: either a standalone root rule (`~/.gemini/GEMINI.md`)
  or modular rules in the global customization root (`~/.gemini/config/rules/<name>.md`, which requires
  valid YAML frontmatter). Global rules provide behavioral instructions—reminding the agent to avoid
  injecting `node_modules/`, bulk lockfile text, or build outputs into chat—but are **not** technical
  enforcement boundaries for file access or remote ingestion.
- Differentiate editor search and watcher controls: configure `files.watcherExclude` to reduce watcher
  churn on rapidly regenerating caches, and use `search.exclude` to reduce search noise. Prefer
  repository- or workspace-scoped editor settings over blanket user-wide bans to prevent masking
  maintained fixtures in unrelated projects, and preserve an explicit toggle for targeted searches when
  investigating dependencies.

## Indexing and transfer budgets

For agents with background indexing, set supported file-count/size limits or disable unnecessary
automatic ingestion based on the workspace and connection budget. A file-count ceiling is not a
byte-transfer ceiling. If unwanted transfer continues, pause the responsible feature and inspect
its effective configuration; use network isolation when an enforceable transfer limit is required.

## Verification

Use a disposable fixture with maintained source, a lockfile, a sanitized environment example, a
needed image, nested generated dependencies, an output directory, and harmless canary files in
protected locations. For each supported agent/platform:

- Prove routine discovery omits excluded trees while required source/configuration stays visible.
- For Antigravity, check that the root `.antigravityignore` is tracked and honored by its supported
  discovery operations, or that the documented version limitation has a verified alternative.
  Exercise any configured file-count threshold and confirm the observed behavior.
- Prove protected canaries cannot be read through direct tools, shell commands, or configured
  connectors, including files created after startup where the policy must cover them.
- Verify background indexing boundaries and any configured resource/transfer budget through
  available diagnostics; do not infer upload prevention from an ignore-file check.
- Run the required build/test workflow and bounded asset/evidence inspection under the controls.

Record the version, effective settings, observed results, and uncovered paths; repeat after relevant
agent or configuration changes. Project-owned exclusion generators and boundary runners have
deterministic tests. If a required boundary is unverified, keep the affected ingestion feature
disabled or the protected inputs outside its environment. Task completion uses the
[working agreement's checklist](../principles/WORKING_AGREEMENT.md#3-definition-of-done-single-authoritative-checklist).
