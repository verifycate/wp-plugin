# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-10-23

### Added

- Connection setup with a single API key: the plugin resolves the workspace,
  checks the key scopes and plan, and enables issuance in one action.
- Certificate catalogue cache and mapping editor with any-course and
  course-specific mappings, searchable course, quiz, lesson and certificate
  comboboxes, attribute templates and a token reference.
- LMS adapters for LearnDash, Tutor LMS, LearnPress, LifterLMS and Sensei, plus
  a documented `verifycate_course_completed` custom hook.
- Durable issuance queue on custom tables with exponential backoff, retry
  classification, stale-lock recovery and a retry action.
- Duplicate suppression and recertification (reissue) policies.
- Admin setup, mappings, queue and activity log screens.
- Certificate wallet shortcode and server-rendered block.
- Single-certificate and public-verification shortcodes.
- WP-CLI commands for status, mappings, direct issuance, queue processing,
  retries and logs.
- PHPUnit coverage for encryption, settings, mappings, completion events,
  attribute templates, retry policy and the integration registry.
- Opt-in uninstall cleanup for settings, mappings, queue history and logs.
- Translation-ready with a bundled `languages/verifycate.pot` template.

### Changed

- The plugin builds against the public `verifycate/php-sdk` package and ships
  the SDK in release zips.
- The Verifycate API client is the SDK's WordPress transport, backed by
  `wp_remote_request`; the plugin no longer hand-writes API paths.

[0.1.0]: https://github.com/verifycate/wp-plugin/releases/tag/v0.1.0
