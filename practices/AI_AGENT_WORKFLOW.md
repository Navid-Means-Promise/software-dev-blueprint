# AI agent workflow

Authoritative for setting a new repository up so agent-driven development follows the
[working agreement](../principles/WORKING_AGREEMENT.md) mechanically, not just by good intentions. The
stack is built for owner-directed, agent-executed work; this document is the scaffolding that keeps an
agent inside the rules.

## Single-source rulebook, dual entry points

There is **one** normative rules document per repository. Both agent entry points *point to* it and
never restate it, so they cannot drift:

- **`CLAUDE.md`** — the Claude Code entry point: a short pointer to the rules plus the document map.
- **`AGENTS.md`** — the same pointer for other agent tooling, kept in sync with `CLAUDE.md`.

Restating a rule in an entry point is the drift bug this structure exists to prevent. Both files carry
the *pointer* and the *map*; the rules live once, in the normative document. On this stack that
normative document is the project's development-rules file (modeled on
[development principles](../principles/DEVELOPMENT_PRINCIPLES.md) +
[working agreement](../principles/WORKING_AGREEMENT.md)).

## The branch-guard hook

The branch-first rule ([working agreement](../principles/WORKING_AGREEMENT.md) §1) is enforced by a
`PreToolUse` hook that **denies edits while `HEAD` is on `main`**. This is the one piece of the
workflow worth showing exactly, because it is what makes the rule survive an agent forgetting it.

Settings register the hook against the edit tools:

```jsonc
// .claude/settings.json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Edit|Write|NotebookEdit",
        "hooks": [
          { "type": "command",
            "command": "\"$CLAUDE_PROJECT_DIR\"/.claude/hooks/guard-main-branch.sh",
            "statusMessage": "Checking branch rule" }
        ]
      }
    ]
  }
}
```

The hook denies the edit when on `main`, and stays silent otherwise:

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

Treat the hook as a backstop, not a reminder: the agent still branches first by rule; the hook only
catches the lapse.

## The answer-summary rule

An agent's *answers* are governed too: any answer carrying findings, comparisons, trade-offs, or a
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
- The `PreToolUse` branch-guard hook is installed; branch-first is still a rule, not just the hook.
- Answers with findings or decisions end in a standalone `## Summary`.
- The agent executes; the owner merges and directs.
