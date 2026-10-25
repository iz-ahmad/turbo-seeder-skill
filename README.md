# turbo-seeder-skill

An agent skill that teaches coding agents to use [`iz-ahmad/laravel-turbo-seeder`](https://github.com/iz-ahmad/laravel-turbo-seeder) correctly: choosing between the factory and generator paths, the default and CSV strategies, using `TurboData` helpers, and avoiding the package's documented pitfalls.

## Install

```bash
npx skills add iz-ahmad/turbo-seeder-skill
```

Targets package `^1.0` (verified against v1.0.0).

## What it covers

- Decision flow: `fromFactory()` vs `generate()`, default vs CSV strategy
- Safe workflow: `turbo-seeder:test-connection`, scaffold, dry run, run, verify
- Rules that prevent common failures (helper placement, relationships, option conflicts, production guard)
- Always defers to the installed package README, and asks the user when something is ambiguous

## Layout

```
skills/laravel-turbo-seeder/      installed by the skills CLI
├── SKILL.md
└── references/
    ├── builder-api.md
    ├── turbodata.md
    ├── csv-and-drivers.md
    └── recipes.md
scripts/generate-api.php          maintainer tooling: regenerates API blocks from the package
.github/workflows/drift-check.yml weekly check against the latest package release
```

See [MAINTAINING.md](MAINTAINING.md) for how the references are kept in sync with the package.

## License

MIT
