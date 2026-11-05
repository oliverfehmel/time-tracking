---
name: pr-review
description: Review a pull request, branch or the current working-tree diff with an independent reviewer subagent that starts with fresh context. Use when the user asks for a PR review, a second opinion on a change, or "review before merge".
argument-hint: "[PR number | branch | (empty = current changes)]"
---

# PR review with fresh context

The point of this skill is **objectivity**: the review is done by the `reviewer` subagent (`.claude/agents/reviewer.md`), which does not see this conversation. Do not undermine that by passing along your own reasoning.

## 1. Determine the target

From `$ARGUMENTS`:

- A number (`12`, `#12`) → PR. Check it exists: `gh pr view 12 --json number,title,headRefName,baseRefName,state`.
- A branch name → diff `main...<branch>`.
- Empty → current branch vs. `main` plus uncommitted changes (`git status`, `git diff main...HEAD`, `git diff`). If there is nothing to review, say so and stop.

## 2. Spawn the reviewer

Use the Agent tool with `subagent_type: "reviewer"`. The prompt contains **only**:

- how to get the diff (e.g. "Review PR #12: run `gh pr diff 12` and `gh pr view 12`" or "Review the diff `git diff main...HEAD` plus uncommitted changes from `git diff`"),
- the instruction to follow its checklist and output format.

Do **not** include: why the change was made, what you think is risky, what you already checked, or summaries of earlier discussion. If the user named a specific concern, pass it as one extra sentence ("Also pay special attention to: …").

For a large diff (> ~800 changed lines or clearly separate areas), spawn two or three reviewers in parallel, each scoped to a set of files, in a single message.

## 3. Check the findings

The reviewer can be wrong too. For each finding:

- Open the cited `file:line` and confirm the scenario is real.
- Drop findings that are clearly wrong; mark doubtful ones as "unconfirmed" instead of silently dropping them.
- Do not add findings of your own to the reviewer's list; if you notice something, list it separately as "additional note (main session)".

## 4. Report

Give the user the confirmed findings ranked by severity (file links as `[File.php:123](src/…/File.php:123)`), the reviewer's "not verified" list, and a one-line verdict: *ready to merge*, *merge after fixes*, or *needs rework*.

## 5. Optional follow-ups (only on the user's request)

- **Post to GitHub**: `gh pr review <n> --comment --body-file <file>` (or `--request-changes` / `--approve` only if the user says so). Never approve your own PR.
- **Fix**: apply fixes in the main session, then re-run this skill for a fresh review — not the same subagent.
