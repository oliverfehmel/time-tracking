---
name: release
description: Cut a new release of the time-tracking app — choose the version, finalize CHANGELOG.md in a release PR, tag the release commit after the merge, and publish the GitHub release. Use only when the user explicitly asks to release.
argument-hint: "[version, e.g. 1.2.1 | patch | minor | major]"
disable-model-invocation: true
---

# Release

Versioning is SemVer, tags have **no `v` prefix** (`1.1.3`, `1.2.0`, `1.3.0`) and are lightweight tags on the release commit. Precedent: PR "Release 1.3.0" (commit `Release 1.3.0`), GitHub release "REST API with personal API tokens and Swagger UI".

`main` is **protected**: changes only arrive through pull requests (a direct push fails with `GH013: Repository rule violations … Changes must be made through a pull request`). The release commit therefore goes through a release PR, and the tag is pushed only once that commit is part of `main`.

## 1. Preconditions

- All feature PRs for this release are merged; local `main` is up to date with `origin/main` and the working tree is clean.
- CI on `main` is green: `gh run list --branch main --limit 3`.
- `php bin/phpunit` green locally.
- `CHANGELOG.md` has a `## [Unreleased]` section with content. If it is empty, stop and tell the user.

## 2. Choose the version

- Last version: `git tag --sort=-v:refname | head -1`.
- Look at what was merged since: `git log <last-tag>..origin/main --oneline`.
- If `$ARGUMENTS` is not an explicit version, propose one: **patch** for fixes only, **minor** for new features / dependency updates without breaking setup changes, **major** for breaking changes (e.g. required PHP/DB changes, removed features, manual migration steps). Confirm with the user.

## 3. Finalize the changelog on a release branch

- `git fetch origin && git switch -c chore/release-x.y.z origin/main`
- Rename `## [Unreleased]` to `## [x.y.z] - YYYY-MM-DD` (today's date).
- Check the section against the merged commits: every user-visible change has an entry; groups are in the order **Added, Changed, Removed, Fixed**; nothing internal-only is listed (refactorings, test setup, CI, tooling). Migrations need no note — `deploy.sh` runs them.
- Do not add a new empty `[Unreleased]` section — the next feature creates it.
- `git add CHANGELOG.md && git commit -m "Release x.y.z"` (body: what was finalized, plus the attribution trailer if required). Note the SHA of this commit — it is what gets tagged.

## 4. Release PR (with the user's OK)

Pushing a branch and opening a PR is outward-facing — confirm first.

```bash
git push -u origin chore/release-x.y.z
gh pr create --base main --title "Release x.y.z" --body-file <file>
```

PR body: the finalized changelog section, plus "After the merge: tag `x.y.z` on the release commit and publish the GitHub release". Keep the PR to the release commit (and, if needed, tooling changes that belong to the release process).

The user merges the PR (or tells you to: `gh pr merge <n> --merge --delete-branch`). Use a **merge commit**, not squash or rebase — otherwise the release commit gets a new SHA and the tag would point to a commit that is not on `main`.

## 5. Tag (after the merge)

```bash
git fetch origin
git merge-base --is-ancestor <release-sha> origin/main   # must succeed
git tag x.y.z <release-sha>
git push origin x.y.z
```

Never push the tag before the release commit is on `main`. Pushing a tag is outward-facing — it is covered by the user's OK for the release; if in doubt, ask.

## 6. GitHub release

- Title: a short summary of the 2–4 headline changes (e.g. "Half days, Symfony 8.1 and Turbo 8").
- Body: a condensed version of the changelog section, starting with `## What's Changed`, then `### Added` / `### Changed` / `### Removed` / `### Fixed` with one short bullet per item (nested bullets for details), ending with a `**Full Changelog**: https://github.com/oliverfehmel/time-tracking/compare/<previous>...x.y.z` link. Add an `### Upgrade notes` section if admins need to do something (migrations and `asset-map:compile` are always done by `deploy.sh`; mention only extra steps such as new env variables, PHP version, web server changes).
- Write the body to a scratch file and show it to the user, then:
  `gh release create x.y.z --title "<title>" --notes-file <file> --verify-tag`

## 7. Afterwards

- Report the release URL.
- Update local `main`: `git switch main && git pull --ff-only`.
- Deployment is done by the user via `deploy.sh` on the server — do not run it.
