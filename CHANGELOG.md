# Changelog

Notable changes to `particle-academy/fancy-x-files`.

**BREAKING** marks anything that can stop working on upgrade. This package is
pre-1.0, so breaking changes land in MINOR releases — read those entries before
upgrading.

> Entries below **1.0** were reconstructed from git history when this file was
> introduced, so they summarise commit subjects rather than consumer impact.
> Everything from the next release onward is written by hand, in the same commit
> as the change.

---

## [Unreleased]

## 0.2.0 — 2026-08-07

### Fixed

- **The package could not publish to Packagist at all.** `composer.json` carried
  two long-standing errors that `composer validate` flags as publish errors:

  - a hardcoded `"version": "0.1.1"`. Packagist uses that field in preference to
    the git tag, so every release published as whatever the field said rather
    than what was tagged — which is why the registry listed `0.1.1` / `0.1.0`
    while the repo's tags were `v0.1.1` / `v0.1.0`, and why tagging a new
    version changed nothing.
  - a stray top-level `"repository"` key. The schema defines `repositories`
    (plural, an array); an unknown top-level property is rejected outright.

  Both are removed. The version now comes from the tag, which is what every
  other package in the kit already does.

  **What you must do:** nothing. This only affects whether releases reach the
  registry.

### Changed

- **BREAKING — PHP 8.2 is no longer supported.** `require.php` moves from `^8.2` to `^8.4`.

  **What you must do:** on PHP 8.4 or newer, nothing. On 8.2, either upgrade PHP first or stay on the previous release — it keeps working and is unaffected by this.

- CI now tests PHP 8.4 only, instead of a matrix spanning versions this package no longer claims to support. A matrix that tests what the manifest forbids is worse than none — it reports green for a combination nobody can install.

### Why

These are the kit 0.5 platform floors. The suite was split across PHP 8.2 and 8.3 with the framework spanning 11–13, so no package could rely on anything newer than its weakest sibling. Every PHP package in the kit takes the same floors at once, so a consumer never has to resolve a mix.

Pre-1.0, so this lands in a MINOR. **No API changed, nothing was removed, nothing was renamed** — only what the package requires.


## 0.1.1 — 2026-06-17

### Fixed

- **laravel:** make well-known routes cacheable for route:cache deploy

## 0.1.0 — 2026-06-17

### Added

- fancy-x-files PHP core + Laravel adapter (v0.1.0)
