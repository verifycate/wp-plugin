# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Connection setup with a single API key: the plugin resolves the workspace,
  checks the key scopes and plan, and enables issuance in one action.
- Certificate catalogue cache and mapping editor with any-course and
  course-specific mappings, searchable course and certificate comboboxes,
  attribute templates and a token reference.
- LMS adapters for LearnDash, Tutor LMS, LearnPress, LifterLMS and Sensei, plus
  a documented `verifycate_course_completed` custom hook.
- Durable issuance queue on custom tables with exponential backoff, retry
  classification, stale-lock recovery and a retry action.
- Duplicate suppression and recertification (reissue) policies.
- Admin setup, mappings, queue and activity log screens.
- Certificate wallet shortcode and server-rendered block.
- WP-CLI commands for status, mappings, direct issuance, queue processing,
  retries and logs.
- PHPUnit coverage for encryption, settings, mappings, completion events,
  attribute templates, retry policy and the integration registry.
- Opt-in uninstall cleanup for settings, mappings, queue history and logs.

### Changed

- The plugin now consumes the `verifycate/php-sdk` package through a Composer
  path repository during development and ships the SDK in release zips.
- The Verifycate API client is the SDK's WordPress transport, backed by
  `wp_remote_request`; the plugin no longer hand-writes API paths.

## [0.1.0] - 2026-10-21

### Added

- Initial plugin skeleton: bootstrap, PSR-4 autoloader with Composer fallback,
  requirement checks, and text domain loading.
- Tooling: PHPCS (WordPress + PHPCompatibility), PHPStan, PHPUnit, wp-env, and
  GitHub Actions CI.
- Project scaffolding: license, contribution guide, code of conduct, security
  policy, issue and pull request templates.

[Unreleased]: https://github.com/verifycate/wp-plugin/commits/main
[0.1.0]: https://github.com/verifycate/wp-plugin/releases/tag/v0.1.0
