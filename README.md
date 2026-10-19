# Verifycate for WordPress

Issue verifiable certificates from your WordPress LMS. The plugin connects
LearnDash, Tutor LMS, LearnPress, LifterLMS, Sensei and more to
[Verifycate](https://verifycate.com), so a certificate is created and issued
automatically when a learner completes a course.

This repository is the open-source client. The Verifycate service itself is a
hosted, paid product; the plugin is a thin client that talks to its public API.

## Status

Early development (`0.1.0`, unreleased). Not yet published on wordpress.org.

## Requirements

- WordPress 6.2+
- PHP 7.4+
- A Verifycate account with an active Pro seat and a workspace API key

## How it works

1. An administrator connects the site with a Verifycate workspace slug and API
   key.
2. They map an LMS course (or an entire course catalogue) to a Verifycate
   certificate.
3. When a learner completes that course, the plugin queues an issuance job and
   creates and issues the certificate through the Verifycate API.

The plugin stores only a workspace API key and per-recipient issue metadata. It
sends the learner's name, email address, course title, completion date and any
mapped certificate attributes to the Verifycate API. See the plugin's
`readme.txt` for the full external-service disclosure.

## Development

### Prerequisites

- PHP 7.4+ and [Composer](https://getcomposer.org/)
- Node.js 20+ and npm
- [Docker](https://www.docker.com/) for the local WordPress environment

### Setup

```bash
composer install          # PHP dependencies and the PSR-4 autoloader
npm install               # JS tooling and @wordpress/env

npm run env:start         # start a local WordPress at http://localhost:8888
```

The default wp-env admin credentials are `admin` / `password`.

### Quality checks

```bash
composer lint             # PHPCS with WordPress + PHPCompatibility standards
composer analyse          # PHPStan (level 6)
composer test             # PHPUnit unit tests
composer check            # all of the above
npm run plugin-zip        # build the distributable plugin zip
```

CI runs the same checks on every pull request, plus the official
[Plugin Check](https://wordpress.org/plugins/plugin-check/) against the built
zip.

### Project structure

```
verifycate.php          Plugin bootstrap (headers, autoloader, requirement checks)
uninstall.php           Cleanup on delete
src/
  Plugin.php            Main plugin class
  Api/                  Verifycate REST API client
  Integrations/         One adapter per LMS + a custom-hook fallback
  Issuance/             Mapping, idempotency, queue, retries
  Admin/                Settings, mappings UI, logs, notices
  Frontend/             Shortcodes, blocks, certificate wallet
  Rest/                 REST endpoints (wallet, mappings)
  Cli/                  WP-CLI commands
  Support/              Shared utilities (secret storage, logging, caching)
tests/                  PHPUnit unit and integration tests
```

## Adding an LMS integration

Integrations are intentionally thin: each adapter subscribes to its LMS's
completion hook, normalizes the event into a shared value object and hands it
to the shared issuance pipeline. See the "Adding a new LMS integration" section
in [CONTRIBUTING.md](CONTRIBUTING.md).

## Contributing

Contributions are welcome — especially new LMS adapters. See
[CONTRIBUTING.md](CONTRIBUTING.md) and the issue templates. All commits must be
signed off (`git commit -s`; see the DCO in CONTRIBUTING.md).

## License

[GPL-2.0-or-later](LICENSE). Bundled dependencies must be GPL-compatible.
