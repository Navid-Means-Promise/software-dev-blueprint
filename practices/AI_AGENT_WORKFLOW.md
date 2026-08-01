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

The branch-first rule ([working agreement](../principles/WORKING_AGREEMENT.md) §1) requires guards for
every mutation path available to the agent: direct edit/write tools, shell commands that can write,
and Git commits. A direct-editor hook alone is not complete enforcement because a shell or another
tool could still change files. Install tool-specific guards at the agent boundary and a repository
commit guard as defense in depth; both reject mutation while `HEAD` is `main`.

The following is the minimum direct-editor portion for an environment with these named tools. It
must be extended for every other mutating tool that environment exposes:

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
branch=$(git -C "${CLAUDE_PROJECT_DIR:-.}" rev-parse --abbrev-ref HEAD 2>/dev/null || echo '')
if [[ "$branch" == "main" ]]; then
  cat <<'JSON'
{ "hookSpecificOutput": {
    "hookEventName": "PreToolUse",
    "permissionDecision": "deny",
    "permissionDecisionReason": "Edits on main are not allowed. Create a feat/, fix/, refactor/, docs/, test/, or chore/ branch first. Only the owner merges into main." } }
JSON
fi
```

Treat guards as backstops, not reminders: the agent still branches first by rule. Test the installed
guard set by proving that each mutating path and a commit are rejected on `main` while read-only
inspection remains available.

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
- Guards cover every available mutation path and commits; branch-first is still a rule, not just a
  tool hook.
- Answers with findings or decisions end in a standalone `## Summary`.
- The agent executes; the owner merges and directs.
