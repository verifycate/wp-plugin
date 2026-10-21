# Contributing to Verifycate for WordPress

Thanks for helping improve the plugin. This project follows the
[WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
and the [WordPress plugin best practices](https://developer.wordpress.org/plugins/).

## Ways to contribute

- **Add or fix an LMS integration** — the most valuable contribution. New LMS
  adapters are welcomed as pull requests.
- Report bugs and request features using the issue templates.
- Improve documentation and translations (`languages/verifycate.pot`).
- Review pull requests.

## Development setup

The plugin depends on the private `verifycate/php-sdk` package through a
Composer path repository at `../php-sdk`. Keep the checkouts side by side:

```bash
mkdir -p ~/projects/verifycate-os
cd ~/projects/verifycate-os

git clone git@github.com:verifycate/wp-plugin.git
git clone git@github.com:verifycate/php-sdk.git

cd wp-plugin
composer install
npm install
npm run env:start
```

Run the full check suite before opening a pull request:

```bash
composer check
```

`composer check` runs PHPCS (WordPress + PHPCompatibility), PHPStan and PHPUnit.
The unit suite uses Brain Monkey, so it does not boot WordPress. Integration
testing with wp-env requires Docker.

### CI token

CI clones the private SDK before installing dependencies. Add a repository
secret named `VERIFYCATE_SDK_TOKEN` with **Contents: read** on
`verifycate/php-sdk`. Without it the quality and package jobs fail before
Composer runs.

## Coding standards

- PHP follows the WordPress Coding Standards, enforced by PHPCS
  (`composer lint`, auto-fix with `composer lint:fix`).
- Static analysis must pass PHPStan level 6 (`composer analyse`).
- PHP 7.4 compatibility is required. Do not use PHP 8+ only syntax.
- Namespace all PHP as `Verifycate\...` with PSR-4 layout under `src/`.
- Namespaced classes use PSR-4 file names and camelCase members. The PHPCS
  ruleset documents this exception; global functions and hooks must still use
  the `verifycate` prefix.
- All user-facing strings must be translatable with the `verifycate` text
  domain.

## Security requirements

Every pull request must maintain the plugin's security posture:

- Capability checks on privileged actions (`manage_options` for settings).
- Nonces on all forms, AJAX handlers and REST routes.
- Sanitize all input; escape all output.
- Never call `wp_remote_request` or `wp_remote_get` directly. Use the
  `Verifycate\Api\ClientFactory`, which injects the SDK's WordPress transport.
- Never log or render a stored API key in full.
- Uninstall must not delete data unless the administrator opted in on the Setup
  screen.

## Architecture

```text
Integrations\*        normalizes an LMS completion into Issuance\CompletionEvent
Issuance\IssuanceService  matches mappings and enqueues a job
Issuance\Queue        durable jobs, retries and dedupe
Issuance\Worker       calls the SDK and records the result
Api\ClientFactory     builds the SDK client with WordPressTransport
Api\CertificateCatalog   cached certificate list and attribute definitions
Admin\*               setup, mappings, queue and logs
Frontend\Wallet       shortcode and server-rendered block
```

The SDK owns every API path and request shape. The plugin must not hand-write
endpoint URLs or request bodies.

## Adding a new LMS integration

Each LMS is just a different completion event feeding one shared pipeline.

1. Create `src/Integrations/<Lms>.php` extending
   `src/Integrations/AbstractIntegration.php`.
2. Implement `getId()`, `getLabel()`, `isActive()` and
   `getCoursePostType()`.
3. Subscribe to the LMS's completion hook in `register()` and normalize the
   payload into a `CompletionEvent` (user id, course id, course title, optional
   score, optional context).
4. Do **not** call the Verifycate API directly from the hook. Hand the event to
   `Issuance\IssuanceService`, which handles mapping, dedupe, queueing and
   retries.
5. Register the adapter in `src/Integrations/Registry.php`.
6. Add unit tests for event normalization and update the supported-hook table in
   `README.md` and `readme.txt`.

Document the exact hooks and LMS versions you tested against in the pull
request, since hook names can change between LMS releases. If an LMS keeps its
hook names private, use the custom-hook adapter rather than guessing.

## Tests

- `composer test` runs `tests/unit` with Brain Monkey.
- `tests/phpstan-bootstrap.php` and `tests/stubs/wp-cli.php` supply constants and
  WP-CLI symbols to PHPStan; they are not loaded at runtime.
- Add a unit test for pure logic (mapping, attribute rendering, retry policy,
  settings, encryption). Hooks and `$wpdb` queries are better covered by a
  wp-env integration test; document the manual steps if Docker is unavailable.

## Commits and the DCO

This project uses the [Developer Certificate of Origin](https://developercertificate.org/)
instead of a CLA. Sign off every commit:

```bash
git commit -s -m "Add LearnPress integration"
```

The sign-off certifies you have the right to submit the contribution under the
project's GPL-2.0-or-later license.
