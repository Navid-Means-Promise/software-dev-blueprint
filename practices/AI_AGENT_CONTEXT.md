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

Exclude generated dependency trees, build outputs, caches, dumps, runtime logs, and large archives
from routine context discovery according to their actual paths, including nested workspaces. Keep
source, manifests, lockfiles, sanitized configuration examples, and test fixtures discoverable.
Avoid blanket extension or directory bans that hide maintained files: images and fonts may be
[product assets](ASSETS_AND_CONTENT.md), screenshots may be
[UI verification evidence](RENDERED_UI_INTEGRITY.md), and a storage directory may contain tracked
scaffolding. Allow bounded inspection of relevant assets, dependencies, or redacted diagnostics when
the task requires it. Builds still need access to their dependencies; ordinary package-manager
workflows remain governed by [monorepo](../components/MONOREPO.md), while invasive SDKs follow
[containerized toolchains](CONTAINERIZED_TOOLCHAINS.md).

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
