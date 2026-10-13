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

Treat guards as backstops, not reminders. Before editing, select or create the task branch using the
[task-continuity and phase-boundary rules](../principles/WORKING_AGREEMENT.md#1-branching-and-merge-authority).
Test each installed guard independently and verify that read-only inspection remains available.
Guards check the current branch; they do not determine whether feedback starts a separate task.

When an agent runtime can classify shell commands before execution, apply the same branch check to
commands it identifies as mutating. Add a versioned hook installer for `pre-commit`/`commit-msg`
checks that reject commits on `main` and validate subjects on feature branches; local `.git/hooks`
alone are not cloned. Remote branch protection remains the authoritative merge backstop.

## Workspace context controls

Agent setup also includes [workspace context controls](AI_AGENT_CONTEXT.md), which own discovery
exclusions, file-access boundaries, and indexing/upload verification for the project's supported
development agents, including [Antigravity's `.antigravityignore` setup](AI_AGENT_CONTEXT.md#google-antigravity).

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
- Answers with findings or decisions end in a standalone `## Summary`.
- The agent executes; the owner merges and directs.
