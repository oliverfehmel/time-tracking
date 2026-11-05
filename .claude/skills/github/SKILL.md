---
name: github
description: GitHub conventions for this repo (oliverfehmel/time-tracking) — branches, commits, pull requests, CI checks and issues via the gh CLI. Use whenever creating a branch, committing, opening or updating a PR, checking CI or working with issues.
---

# GitHub workflow

Repository: `oliverfehmel/time-tracking`, default branch `main`. Use the `gh` CLI for everything GitHub-related.

## Branches

- Work never happens directly on `main`. `main` is protected: every change, including the release commit, arrives through a pull request (a direct push fails with `GH013 … Changes must be made through a pull request`; see the `release` skill).
- Tracking: a branch created from `origin/main` tracks `origin/main` — push it explicitly the first time with `git push -u origin <branch>`, never with a bare `git push`.
- Naming: `feature/<kebab-name>` for features, `fix/<kebab-name>` for bug fixes, `chore/<kebab-name>` for dependency updates, refactoring, tooling, docs.
  Examples from history: `feature/half-days`, `chore/symfony-8.1-dependency-update`.
- Branch off an up-to-date `main` (`git fetch origin && git switch -c feature/x origin/main`).

## Commits

- English, imperative, sentence case, no prefix and no trailing period: `Add half-day data model and fractional day calculation`, `Fix Symfony 8.1 and Doctrine ORM 3.7 deprecations`.
- Subject ≤ ~72 characters; a body explains *why* when it is not obvious.
- One logical step per commit (data model → logic → UI → docs), so the PR can be read commit by commit.
- End with the attribution trailer given in the session's system reminder (if any).
- Commit or push only when the user asks for it.

## Pull requests

Create with `gh pr create --base main --title "<title>" --body-file <file>` (write the body to a scratch file to avoid quoting issues). Title: same style as commit subjects.

Body structure (as in the half-days PR):

```markdown
## What
User-visible behaviour, as bullets.

## Why
The motivation in one or two sentences.

## How
Key implementation decisions, new classes/services, formulas.

## Reviewer notes
Migrations, behaviour changes, intentional omissions, known drift.

## Testing
Number of tests (new/total) and what they cover, manual checks done —
and explicitly what was *not* checked (e.g. "not checked visually in the browser").
```

End with the attribution line from the system reminder (if any).

Before opening the PR, make sure:
- `php bin/phpunit` is green locally (or say why it could not run),
- `CHANGELOG.md` has an entry for user-visible changes,
- `CLAUDE.md` / `README.md` are updated if the domain model, services or setup changed.

## CI

- The only workflow is `.github/workflows/tests.yml`: PHP **8.4**, MariaDB 11.8, `composer install`, `php bin/phpunit`. Local PHP is 8.5 and production runs 8.5 — code and `composer.lock` must still work on 8.4.
- Check status with `gh pr checks <n>`; read failures with `gh run view <run-id> --log-failed`.

## Merging

- Only when the user asks. Use a merge commit: `gh pr merge <n> --merge --delete-branch`.
- Never enable auto-merge or force-push to `main` unless explicitly told to.

## Issues

- `gh issue list`, `gh issue view <n>`. Reference issues in PR bodies with `Closes #<n>` when the PR resolves them.
- Create issues only on request; include steps to reproduce for bugs.
