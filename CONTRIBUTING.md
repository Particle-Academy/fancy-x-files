# Contributing to particle-academy/fancy-x-files

Notes for anyone, human or agent, working ON this package's code. How to USE the
package is in [AGENTS.md](./AGENTS.md); what it is, is in [README.md](./README.md).
`CLAUDE.md` is a symlink to this file, so Claude Code reads these notes when it
works in this repo.

## Layout

- `src/Contracts/WellKnownFile.php` — the contract every file implements
  (`path()`, `contentType()`, `render()`, `validate()`).
- `src/Files/` — the builders: `RobotsTxt`, `SecurityTxt`, `LlmsTxt`,
  `HumansTxt`, `Sitemap`, `AgentsTxt`.
- `src/Registry.php` — the single source of truth the files are collected in.
- `src/Robots/RobotsPolicy.php` — the default-open robots evaluator.
- `src/Laravel/` — the optional, auto-discovered adapter: service provider,
  the serving route handler, `HonorsRobots`, and the `x-files:check` command.
- `config/` — the publishable Laravel config.
- `tests/Unit`, `tests/Feature` — Pest.

The core has **zero runtime dependencies** (`require` is `php ^8.4` only).

## Testing and formatting

```bash
composer test    # pest
composer lint    # pint --test
```

## Changelog

Update `CHANGELOG.md` in the same commit as the change.
