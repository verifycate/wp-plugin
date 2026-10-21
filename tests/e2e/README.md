# End-to-end harness

This directory holds a local WordPress harness for the issuance pipeline. It is
excluded from the plugin zip.

## What it covers

- Connection settings, certificate catalogue and connection test.
- A custom-hook completion.
- The documented completion hook for Tutor LMS, LearnPress, LifterLMS and
  Sensei (and a real Sensei completion through `Sensei_Utils`).
- Duplicate suppression, recertification/reissue, and API-outage retry.
- The admin Setup, Mappings and Logs screens, including attribute AJAX.
- The wallet shortcode on a real frontend page.

LearnDash is premium and cannot be installed from wordpress.org, so its adapter
is covered by the hook contract in `src/Integrations/LearnDash.php` and the unit
suite, not by this local harness.

## Prerequisites

- Docker running.
- `npm install` and `npx wp-env start`.
- The four free LMS plugins:

```bash
for plugin in tutor learnpress lifterlms sensei-lms; do
  npx wp-env run cli wp plugin install "$plugin" --activate
done
```

wp-env uses PHP 8.3 in `.wp-env.json` because the old PHP 7.4 wp-env image no
longer builds from its Debian package URLs. CI still runs the plugin on PHP 7.4
through 8.4.

## Run

Start the mock Verifycate API on the host:

```bash
node tests/e2e/mock-api.js
```

It listens on `http://0.0.0.0:4567` with workspace `acme` and API key
`vc_test_key`. The WordPress container reaches it at
`http://host.docker.internal:4567`.

Configure the plugin and seed the learner, courses and mappings:

```bash
npx wp-env run cli wp eval-file wp-content/plugins/wp-plugin/tests/e2e/configure.php
npx wp-env run cli wp verifycate status
npx wp-env run cli wp eval-file wp-content/plugins/wp-plugin/tests/e2e/setup.php
npx wp-env run cli wp verifycate mappings
```

`configure.php` stores only the API key. `wp verifycate status` resolves the
workspace through `GET /api/v1/workspaces/current` and prints the detected slug,
scopes and plan state.

Fire the custom hook and process the queue:

```bash
npx wp-env run cli wp eval 'do_action("verifycate_course_completed", 2, 45, "course_completed", array("course_title" => "Custom Leadership 101"));'
npx wp-env run cli wp verifycate process
npx wp-env run cli wp verifycate logs
```

Fire the LMS hooks and process them:

```bash
npx wp-env run cli wp eval-file wp-content/plugins/wp-plugin/tests/e2e/fire-lms.php
npx wp-env run cli wp verifycate process
```

Render the wallet as the seeded learner:

```bash
npx wp-env run cli wp transient delete --all
npx wp-env run cli wp eval 'wp_set_current_user(2); echo do_shortcode("[verifycate_wallet]");'
```

The local site is at `http://localhost:8888` (`admin` / `password`, and the
seeded learner `ada` / `password`).

## Expected result

- `wp verifycate status` shows the queue draining to `done`.
- The mock API log shows one `POST .../issues` and one
  `POST .../issues/{id}/issue` per completion.
- `wp verifycate logs` shows `Certificate issued from a queued completion.`
- The wallet lists the custom certificate and each LMS certificate.
- With the mock stopped, the next job stays `pending` with a cURL error and a
  future `available_at`; restarting the mock and processing again completes it.

## Reset

The mock API keeps state in memory; restart it to clear issues. WordPress state
is reset with:

```bash
npx wp-env destroy
npx wp-env start
```
