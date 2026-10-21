# Verifycate for WordPress

Issue verifiable certificates from your WordPress LMS. The plugin connects
LearnDash, Tutor LMS, LearnPress, LifterLMS, Sensei and any custom completion
hook to [Verifycate](https://verifycate.com), so a certificate is queued and
issued automatically when a learner completes a course.

This repository is the open-source client. The Verifycate service itself is a
hosted, paid product; the plugin is a thin client that talks to its public API
through the [Verifycate PHP SDK](https://github.com/verifycate/php-sdk).

## Status

`0.1.0`, unreleased. Not yet published on wordpress.org.

## Requirements

- WordPress 6.2+
- PHP 7.4+
- A Verifycate account with an active Pro seat
- A workspace API key with `certificates:read` and `certificates:write`

## What it does

- **Connection** — save the workspace slug and API key, test the connection,
  and surface API problems (bad key, plan, permissions) as actionable notices.
- **Mappings** — map one course, an entire LMS catalogue, or a custom hook to a
  certificate. Specific-course mappings take precedence over “any course”.
- **Attributes** — map learner and course data to certificate attributes with
  tokens such as `{user_name}`, `{course_title}`, `{completion_date}`,
  `{user_meta:key}` and `{course_meta:key}`.
- **Durable queue** — completions are written to a database queue and processed
  by WP-Cron with exponential backoff. Failed jobs are visible and retryable.
- **Repeat completions** — issue once per learner, or reissue the certificate
  onto the latest published design for recertification.
- **Wallet** — `[verifycate_wallet]` shortcode and a server-rendered
  “Verifycate Wallet” block so logged-in learners can open their certificates.
- **WP-CLI** — connection status, mappings, direct issuance, queue processing,
  retries and logs.

Certificate design happens in the Verifycate dashboard. The plugin handles the
connection, mapping and issuance.

## Installation

### Release zip

1. Upload the plugin zip to `/wp-content/plugins/verifycate/`, or install it
   through the WordPress plugins screen.
2. Activate the plugin.
3. Go to **Verifycate → Setup**, enter your workspace slug and API key, then
   click **Test connection**.
4. Create at least one certificate in the Verifycate dashboard.
5. Go to **Verifycate → Mappings** and map a course to that certificate.

The release zip includes the PHP SDK in `vendor/`; no Composer run is needed on
the site.

## Development

The plugin depends on the private `verifycate/php-sdk` package through a
Composer path repository at `../php-sdk`. Keep the two checkouts side by side:

```bash
mkdir -p ~/projects/verifycate-os
cd ~/projects/verifycate-os

git clone git@github.com:verifycate/wp-plugin.git
git clone git@github.com:verifycate/php-sdk.git

cd wp-plugin
composer install          # PHP dependencies, SDK included
npm install               # JS tooling and @wordpress/env

npm run env:start         # local WordPress at http://localhost:8888
```

The default wp-env admin credentials are `admin` / `password`.

### Quality checks

```bash
composer lint             # PHPCS with WordPress + PHPCompatibility standards
composer analyse          # PHPStan level 6
composer test             # PHPUnit unit tests (Brain Monkey)
composer check            # all of the above
composer build            # distributable zip in build/verifycate.zip
npm run plugin-zip        # JS-side zip helper
```

CI runs the same checks on PHP 7.4–8.3, the official
[Plugin Check](https://wordpress.org/plugins/plugin-check/) against the built
plugin, and uploads the zip as an artifact.

### CI and the private SDK

The PHP SDK repository is private, so CI clones it with a read-only deploy key.
The key is stored as the `VERIFYCATE_SDK_DEPLOY_KEY` secret in this repository
and the matching public key is a read-only deploy key on
`verifycate/php-sdk`. The workflow writes the key to a temporary SSH identity and
clones over SSH before `composer install`.

For local development the path repository is enough; no key is needed.

## Supported LMS hooks

Each adapter normalizes the LMS event into the shared issuance pipeline and
never calls the API directly.

| LMS | Hook | Arguments used |
| --- | --- | --- |
| LearnDash | `learndash_course_completed` | `$data['user']`, `$data['course']`, optional `$data['progress']['score']` |
| Tutor LMS | `tutor_course_complete_after` | `$course_id`, `$user_id` |
| LearnPress | `learn-press/user-course-finished` | `$course_id`, `$user_id`, `$result` |
| LifterLMS | `lifterlms_course_completed` | `$student_id`, `$course_id` |
| Sensei | `sensei_user_course_end` | `$user_id`, `$course_id` |
| Custom | `verifycate_course_completed` | `$user_id`, `$course_id`, `$event`, `$context` |

### Custom hook

Use the custom hook for membership, e-commerce or in-house course plugins:

```php
do_action( 'verifycate_course_completed', $user_id, $course_id );

do_action(
	'verifycate_course_completed',
	$user_id,
	$course_id,
	'course_completed',
	array(
		'course_title' => 'Advanced Leadership',
		'score'        => 92,
		'cohort'       => 'Spring 2026',
	)
);
```

Create a mapping with **Custom hook**, enter the event name (default
`course_completed`) and leave the course id at `0` to match any course. The
`$context` array is available to attribute templates as `{context:key}`.

## Mapping behaviour

- **Any course** — one certificate covers every published course in that LMS.
- **Specific course** — overrides an any-course mapping for the same
  certificate.
- **Skip duplicates** — a learner receives the certificate once. Pending jobs
  also suppress a second queue entry.
- **Issue again** — reissues the existing certificate onto the latest published
  design (recertification).
- **Auto-issue** — import the recipient and release the credential immediately.
  Turn it off to queue recipients and release them from the dashboard.
- **Attributes** — one `key = template` per line. Only keys declared on the
  certificate are sent. Unknown tokens are left as-is so typos are visible.

If the certificate is still a draft, recipients queue but cannot be released.
Publishing the certificate in Verifycate is the single action that releases the
queue.

## Wallet

Shortcode:

```text
[verifycate_wallet title="My certificates" empty_text="Nothing here yet." limit="20"]
```

Block: insert **Verifycate Wallet** from the widget category. It is rendered on
the server, so no block build step is needed.

The wallet calls the API with the logged-in learner's email and caches the
result for five minutes. It only shows issues whose certificate is active.

## WP-CLI

```bash
wp verifycate status
wp verifycate mappings
wp verifycate issue <certificate-id> --email=ada@example.com --name="Ada Lovelace"
wp verifycate issue <certificate-id> --email=ada@example.com --attributes='{"course_hours":"40"}'
wp verifycate process --limit=20
wp verifycate retry
wp verifycate logs --level=error --limit=20
```

## Data and privacy

The plugin stores:

- the workspace slug, encrypted API key and API/portal URLs in
  `wp_options` (`verifycate_settings`);
- course-to-certificate mappings in `{prefix}verifycate_mappings`;
- queued issuance jobs and retry state in `{prefix}verifycate_jobs`;
- bounded activity logs in `{prefix}verifycate_logs` (30-day retention).

The API key is encrypted at rest with sodium secretbox when available, falling
back to OpenSSL AES-256-GCM. The key is never rendered in full.

Completions send the learner's name, email address, course title, completion
date, score when available, and mapped certificate attributes to the Verifycate
API. Nothing is sent before the site is connected and a mapping is saved.

Uninstall removes settings, tables, queue history and logs **only** when the
administrator enabled “Delete … when the plugin is uninstalled” on the Setup
screen. The default is to keep data so a reinstall resumes cleanly.

## Project structure

```text
verifycate.php             Plugin bootstrap, constants and requirement checks
uninstall.php              Opt-in data removal
src/
  Plugin.php               Service container and hook wiring
  Install.php              Tables, schema upgrades and cron
  Api/                     Client factory, WordPress transport, catalogue cache
  Integrations/            One adapter per LMS plus a custom hook
  Issuance/                Events, mappings, attributes, queue, worker
  Admin/                   Setup, mappings and logs screens
  Frontend/                Wallet shortcode and block
  Cli/                     WP-CLI commands
  Support/                 Settings, encryption, logging, requirements
assets/                    Admin and wallet CSS/JS
tests/                     PHPUnit unit tests, stubs and fixtures
```

## Contributing

Contributions are welcome — especially new LMS adapters. See
[CONTRIBUTING.md](CONTRIBUTING.md) and the issue templates. All commits must be
signed off (`git commit -s`; see the DCO in CONTRIBUTING.md).

## License

[GPL-2.0-or-later](LICENSE). Bundled dependencies must be GPL-compatible; the
Verifycate PHP SDK is MIT.
