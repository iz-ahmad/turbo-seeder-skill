# Maintaining the skill

The package is the source of truth. This repo only summarises it.

## How drift is handled

1. **Installed docs win.** `SKILL.md` tells agents to read the installed package README first and to trust it over `references/`.
2. **Version stamp.** `SKILL.md`, `README.md` and each reference file state the package version they were verified against. Agents compare it with `composer show` and say so when the installed version is newer.
3. **Generated signatures.** `scripts/generate-api.php` rewrites the marked blocks (builder methods, TurboData methods, config keys, artisan commands) from the package source. These blocks are not shipped logic; they live inside the reference files and must not be edited by hand.
4. **Drift check.** `.github/workflows/drift-check.yml` runs weekly (and on demand). It checks out the latest package release, runs the generator in `--check` mode, and compares the latest release tag with every version stamp in `skills/` and `README.md`. If the signatures differ, a stamp is stale, or the generator fails, it opens one `drift` issue (an already-open `drift` issue suppresses new ones). It fails without opening an issue if the package has no published GitHub release.
5. **Process hook.** The package's `CONTRIBUTING.md` and PR template ask contributors to update this repo when public API or behaviour changes. Behaviour changes that no signature shows (exceptions, guards, defaults) are only caught this way or by reading release notes.

GitHub disables scheduled workflows after 60 days without repository activity; re-enable the workflow from the Actions tab if that happens.

## When the package releases

```bash
git clone --branch <tag> https://github.com/iz-ahmad/laravel-turbo-seeder /tmp/package
php scripts/generate-api.php --package=/tmp/package   # use PHP 8.3 to match CI
```

Exit codes: 0 in sync, 1 out of sync (`--check`), 2 tool error. Methods the package README does not document are filtered out by `UNDOCUMENTED_METHODS` in the script; update that list when the README starts documenting one.

Then:

1. Review the package release notes and tests for behaviour changes the signatures do not show.
2. Update the hand-written parts of the affected reference files and `SKILL.md`.
3. Bump the verified version in `SKILL.md`, `README.md` and each reference file.
4. Close the `drift` issue.

## Rules for reference files

- Document only behaviour confirmed in package code, tests or README.
- Where the README and code disagree, document the code and flag the discrepancy in the PR.
- Keep `SKILL.md` under 100 lines; push detail into `references/`.
- `scripts/` and `.github/` are maintainer tooling and are not part of the installed skill.

## Not automated yet

An instant trigger from the package's release workflow (`repository_dispatch`) would need a token with access to both repos. The weekly check is used instead.
