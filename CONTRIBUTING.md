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

```bash
composer install
npm install
npm run env:start
```

Run the full check suite before opening a pull request:

```bash
composer check
```

## Coding standards

- PHP follows the WordPress Coding Standards, enforced by PHPCS
  (`composer lint`, auto-fix with `composer lint:fix`).
- Static analysis must pass PHPStan level 6 (`composer analyse`).
- PHP 7.4 compatibility is required. Do not use PHP 8+ only syntax.
- Namespace all PHP as `Verifycate\...` with PSR-4 layout under `src/`.
- All user-facing strings must be translatable with the `verifycate` text domain.

## Security requirements

Every pull request must maintain the plugin's security posture:

- Capability checks on privileged actions (`manage_options` for settings).
- Nonces on all forms, AJAX handlers and REST routes.
- Sanitize all input; escape all output.
- Use the WordPress HTTP API (`wp_remote_request`), never raw `curl`.
- Never log or render a stored API key in full.

## Adding a new LMS integration

Each LMS is just a different completion event feeding one shared pipeline.

1. Create `src/Integrations/<Lms>.php` extending
   `src/Integrations/AbstractLms.php`.
2. Implement `is_active()` (detect the LMS), `label()`, `list_courses()` and
   `completion_triggers()`.
3. Subscribe to the LMS's completion hook(s) in `register()` and normalize the
   payload into a `CompletionEvent` (user id, email, name, course id, course
   title, completion timestamp, optional score).
4. Do **not** call the Verifycate API directly from the hook. Hand the event to
   the shared `Issuance\Issuer`, which handles queueing, idempotency, mapping
   and retries.
5. Register the adapter in `src/Integrations/Loader.php`.
6. Add unit tests for event normalization and an integration test that asserts a
   completion issues exactly once.

Document the exact hooks and the LMS versions you tested against in the pull
request, since hook names can change between LMS releases. If an LMS keeps its
hook names private, prefer the generic custom-hook adapter rather than guessing.

## Commits and the DCO

This project uses the [Developer Certificate of Origin](https://developercertificate.org/)
instead of a CLA. Sign off every commit:

```bash
git commit -s -m "Add LearnPress integration"
```

The sign-off certifies you have the right to submit the contribution under the
project's GPL-2.0-or-later license.

## Pull requests

- Keep changes focused and describe the motivation and testing.
- Ensure `composer check` passes and CI is green.
- Update `CHANGELOG.md` under `[Unreleased]`.
- Maintainers review within a few business days.
