---
name: release
description: Cut a new release of the time-tracking app — choose the version, finalize CHANGELOG.md, create the release commit and tag, and publish the GitHub release. Use only when the user explicitly asks to release.
argument-hint: "[version, e.g. 1.2.1 | patch | minor | major]"
disable-model-invocation: true
---

# Release

Versioning is SemVer, tags have **no `v` prefix** (`1.1.3`, `1.2.0`) and are lightweight tags on the release commit on `main`. Precedent: commit `4b42167 Release 1.2.0`, GitHub release "Half days, Symfony 8.1 and Turbo 8".

## 1. Preconditions

- On `main`, up to date with `origin/main`, clean working tree. All feature PRs for this release are merged.
- CI on `main` is green: `gh run list --branch main --limit 3`.
- `php bin/phpunit` green locally.
- `CHANGELOG.md` has a `## [Unreleased]` section with content. If it is empty, stop and tell the user.

## 2. Choose the version

- Last version: `git tag --sort=-v:refname | head -1`.
- Look at what was merged since: `git log <last-tag>..HEAD --oneline`.
- If `$ARGUMENTS` is not an explicit version, propose one: **patch** for fixes only, **minor** for new features / dependency updates without breaking setup changes, **major** for breaking changes (e.g. required PHP/DB changes, removed features, manual migration steps). Confirm with the user.

## 3. Finalize the changelog

- Rename `## [Unreleased]` to `## [x.y.z] - YYYY-MM-DD` (today's date).
- Check the section against the merged commits: every user-visible change has an entry; groups are in the order **Added, Changed, Removed, Fixed**; nothing internal-only is listed.
- Do not add a new empty `[Unreleased]` section — the next feature creates it.

## 4. Release commit and tag (with the user's OK)

```bash
git add CHANGELOG.md
git commit -m "Release x.y.z"   # body: what was finalized, plus the attribution trailer if required
git tag x.y.z
git push origin main
git push origin x.y.z
```

Pushing to `main` and pushing tags is outward-facing — confirm before pushing.

## 5. GitHub release

- Title: a short summary of the 2–4 headline changes (e.g. "Half days, Symfony 8.1 and Turbo 8").
- Body: a condensed version of the changelog section, starting with `## What's Changed`, then `### Added` / `### Changed` / `### Removed` / `### Fixed` with one short bullet per item (nested bullets for details). Add an `### Upgrade notes` section if admins need to do something (run migrations is always done by `deploy.sh`; mention only extra steps such as new env variables, PHP version, web server changes).
- Write the body to a scratch file and show it to the user, then:
  `gh release create x.y.z --title "<title>" --notes-file <file> --verify-tag`

## 6. Afterwards

- Report the release URL.
- Deployment is done by the user via `deploy.sh` on the server — do not run it.
