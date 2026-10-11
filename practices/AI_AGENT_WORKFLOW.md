# AI agent workflow

Authoritative for setting a new repository up so agent-driven development follows the
[working agreement](../principles/WORKING_AGREEMENT.md) mechanically, not just by good intentions. The
stack is built for owner-directed, agent-executed work; this document is the scaffolding that keeps an
agent inside the rules.

## Single-source rulebook, dual entry points

There is **one** normative rules document per repository. Both agent entry points _point to_ it and
never restate it, so they cannot drift:

- **`CLAUDE.md`** — the Claude Code entry point: a short pointer to the rules plus the document map.
- **`AGENTS.md`** — the same pointer for other agent tooling, kept in sync with `CLAUDE.md`.

Restating a rule in an entry point is the drift bug this structure exists to prevent. Both files carry
the _pointer_ and the _map_; the rules live once, in the normative document. On this stack that
normative document is the project's development-rules file (modeled on
[development principles](../principles/DEVELOPMENT_PRINCIPLES.md) +
[working agreement](../principles/WORKING_AGREEMENT.md)).

## Branch guards

The branch-first rule ([working agreement](../principles/WORKING_AGREEMENT.md) §1) uses defense in
depth: guard mutating tools at each supported agent boundary, reject commits on `main` in repository
hooks, and protect the remote default branch. A direct-editor hook alone cannot cover shell writes or
another client, while a commit hook cannot prevent working-tree edits and can be bypassed. Document
the residual limits instead of claiming one hook makes every local mutation impossible.

The following is the direct-editor portion for an environment with these named tools. Extend it to
other mutating tools when that environment provides a reliable pre-tool hook.

```jsonc
// .claude/settings.json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Edit|Write|NotebookEdit",
        "hooks": [
          {
            "type": "command",
            "command": "\"$CLAUDE_PROJECT_DIR\"/.claude/hooks/guard-main-branch.sh",
            "statusMessage": "Checking branch rule",
          },
        ],
      },
    ],
  },
}
```

The direct-editor hook denies the edit when on `main`, and stays silent otherwise:

```bash
#!/usr/bin/env bash
# Blocks edits while HEAD is on main.
set -euo pipefail
branch=$(git -C "${CLAUDE_PROJECT_DIR:-.}" symbolic-ref --quiet --short HEAD 2>/dev/null || echo '')
if [[ "$branch" == "main" ]]; then
  cat <<'JSON'
{ "hookSpecificOutput": {
    "hookEventName": "PreToolUse",
    "permissionDecision": "deny",
    "permissionDecisionReason": "Edits on main are not allowed. Create a feat/, fix/, refactor/, docs/, test/, or chore/ branch first. Only the owner merges into main." } }
JSON
fi
```

Treat guards as backstops, not reminders: the agent still branches first by rule. Test each installed
guard independently and verify that read-only inspection remains available.

When an agent runtime can classify shell commands before execution, apply the same branch check to
commands it identifies as mutating. Add a versioned hook installer for `pre-commit`/`commit-msg`
checks that reject commits on `main` and validate subjects on feature branches; local `.git/hooks`
alone are not cloned. Remote branch protection remains the authoritative merge backstop.

## Workspace indexing, context boundaries, and upload bandwidth prevention

Modern AI developer environments—including **Google Antigravity (Gemini)** and **OpenAI Codex (ChatGPT
CLI)**—run background daemons, language servers (`language_server_linux_x64`, extension hosts), and agent
runtimes. These services automatically walk the workspace to generate ASTs, track files, and compute
vector embeddings for semantic code retrieval.

### The ingestion failure mode

If a workspace lacks strict agent-specific ignore rules, background daemons crawl every subdirectory.
Third-party dependency directories (`node_modules/`, `vendor/`, `.venv/`), build outputs (`dist/`, `.vite/`,
`build/`), caches (`.cache/`, `.pytest_cache/`), and large binary media or log files contain tens of
thousands of files and hundreds of megabytes of external code.

When unconstrained, these daemons:
1. **Upload hundreds of megabytes or gigabytes of raw files and embeddings** over HTTPS to remote cloud
   endpoints (such as Google Cloud Code endpoints or OpenAI APIs), consuming massive internet bandwidth on
   metered or restricted connections.
2. **Saturate local CPU and memory** by parsing and indexing minified vendor libraries.
3. **Degrade AI context quality**, polluting semantic retrieval and tool context with irrelevant third-party
   code rather than the project's own domain logic.
4. **Risk secret and data leakage**, inadvertently sending local dumps, logs, or uncommitted environment files
   to remote cloud servers.

Every repository on this stack MUST enforce proactive, defense-in-depth ignore controls across all supported
AI agents.

### Google Antigravity & Gemini daemon controls

Google Antigravity's language server and embedded `ripgrep` search engine natively inspect
**`.antigravityignore`** at the workspace root before any file crawling or embedding calculation begins.

Every repository MUST commit `.antigravityignore` at its root with comprehensive vendor and binary exclusions:

```gitignore
# Dependencies & package managers
**/node_modules/**
/vendor/**
**/.venv/**
**/target/**

# Build artifacts, caches, and bundlers
**/dist/**
**/build/**
**/.vite/**
**/.next/**
**/.turbo/**
**/.cache/**
**/.pytest_cache/**

# Ephemeral runtime data, storage, and logs
/storage/**
/playwright-report/**
/test-results/**
*.log

# Large binary media and package archives
*.png
*.jpg
*.jpeg
*.webp
*.gif
*.woff2
*.zip
*.tar.gz
*.qcow2
*.vsix
*.iso
```

Additionally, Antigravity IDE enforces a semantic embedding ceiling (`antigravity.searchMaxWorkspaceFileCount`,
defaulting to 5,000 files). Its specification notes:
> *"Jetski will attempt to compute embeddings for workspaces up to this many files. This file count ignores .gitignore and binary files."*

To protect local bandwidth and system resources against accidental runaway indexing, declare the file count
ceiling and exclude patterns in committed `.vscode/settings.json` or developer environment settings:

```jsonc
// .vscode/settings.json or User settings.json
{
  "antigravity.searchMaxWorkspaceFileCount": 3200,
  "search.exclude": {
    "**/node_modules": true,
    "**/vendor": true,
    "**/storage": true,
    "**/dist": true,
    "**/*.png": true,
    "**/*.jpg": true,
    "**/*.webp": true
  },
  "files.watcherExclude": {
    "**/.git/objects/**": true,
    "**/.git/subtree-cache/**": true,
    "**/node_modules/**": true,
    "**/vendor/**": true,
    "**/dist/**": true,
    "**/storage/**": true
  }
}
```

### OpenAI Codex & ChatGPT CLI controls

OpenAI Codex CLI and autonomous coding agents operate as a full shell execution environment.
**Crucially, Codex CLI does NOT treat `.gitignore` as a file-reading or security boundary.** Because the
Codex agent runs shell tools (`cat`, `rg`, `find`), an agent can inspect and upload files ignored by git
if they exist on the filesystem.

To prevent Codex from reading and exfiltrating large dependencies, vendor bundles, private keys, or logs,
environments must configure sandbox permission profiles in `~/.codex/config.toml` (available in Codex CLI
version 0.125 and later):

```toml
# ~/.codex/config.toml
default_permissions = "workspace_restricted"

[permissions.workspace_restricted]
description = "Restricted sandbox denying ingestion of vendor bundles and sensitive files"
extends = ":workspace"

[permissions.workspace_restricted.filesystem]
"**/node_modules" = "deny"
"**/vendor" = "deny"
"**/.env" = "deny"
"**/*.log" = "deny"
"**/*.secret" = "deny"
```

Where deep dependencies are required, rely on **containerized toolchains**
([containerized toolchains](CONTAINERIZED_TOOLCHAINS.md)). Storing dependencies inside isolated container
volumes ensures the host-level Codex agent never encounters vendor trees in its local workspace.

### Universal toolchain baseline (`.gitignore` + `.ignore`)

All modern coding agents rely on universal command-line tools—primarily `ripgrep` (`rg`) and `fd`—for file
discovery. These tools automatically respect both `.gitignore` and `.ignore`. Never scaffold or maintain a
repository or script directory without a committed, comprehensive `.gitignore` and `.ignore`.

### The pre-install invariant

**Never run `npm install`, `composer install`, `pip install`, or unpack vendor archives before
`.gitignore` and `.antigravityignore` are present in the workspace.**

If dependency installation occurs first, background daemons detect newly created folders and trigger an
immediate, unconstrained scanning and uploading loop before ignore files can be authored.

## The answer-summary rule

An agent's _answers_ are governed too: any answer carrying findings, comparisons, trade-offs, or a
decision ends with a `## Summary` that stands alone. The full rule is in the
[working agreement](../principles/WORKING_AGREEMENT.md) §4. It exists because the owner reads the
summary to act; burying a decision in prose above it hides the thing they need.

## What the agent decides vs. what the owner decides

- **The agent** designs, implements, verifies, tests, and reports — end to end, across as many
  branches as a request needs.
- **The owner** holds merge authority and product direction. The agent never advances `main`, never
  picks a product direction unprompted, and never edits a sibling project without an explicit ask
  (see [cross-project sync](../reuse/CROSS_PROJECT_SYNC.md)).

## Rules

- One normative rules document; `CLAUDE.md` and `AGENTS.md` point to it and stay in sync.
- Layer tool hooks, versioned commit-hook installation, and remote branch protection; state which
  mutation paths the current environment can and cannot intercept.
- Every repository commits `.antigravityignore` and `.gitignore` before installing any dependencies;
  prevent unconstrained AI daemon uploads (Antigravity language server, Codex context ingestion).
- Configure AI file count ceilings (`antigravity.searchMaxWorkspaceFileCount`) and sandbox filesystem
  denials (`~/.codex/config.toml`) to prevent bandwidth exhaustion and secret leakage.
- Answers with findings or decisions end in a standalone `## Summary`.
- The agent executes; the owner merges and directs.
