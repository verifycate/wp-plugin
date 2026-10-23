=== Verifycate ===
Contributors: verifycate
Tags: certificates, lms, learndash, tutor lms, learnpress
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Issue verifiable certificates from your WordPress LMS with Verifycate.

== Description ==

Verifycate connects your WordPress LMS to the Verifycate credential platform, so
a verifiable certificate is queued and issued automatically when a learner
completes a course.

Supported course plugins:

* LearnDash
* Tutor LMS
* LearnPress
* LifterLMS
* Sensei
* A generic custom hook for course, membership, e-commerce and in-house plugins

Highlights:

* Automatic issuance on course completion, quiz passing or lesson completion,
  with an "any course" option so one certificate can cover a whole catalogue.
* Optional minimum quiz scores, so a certificate is only issued after the learner
  reaches the required result.
* Map learner and course data to certificate attributes with tokens such as
  {user_name}, {course_title}, {completion_date}, {user_meta:key} and
  {course_meta:key}.
* Control repeat completions: issue once per learner, or reissue onto the latest
  published design for recertification.
* A durable issuance queue with exponential backoff, visible failures and a
  retry action, so a temporary API outage does not lose a completion.
* A certificate wallet shortcode and block so learners can open what they have
  earned, plus single-certificate and public-verification shortcodes.
* Search, sorting, pagination and bulk actions for course mappings.
* A dedicated Configure screen for the workspace API key and environment
  settings.
* Translation-ready, with a bundled `languages/verifycate.pot` template.
* A connection test that explains bad keys, missing plans and permission
  problems.
* WP-CLI commands for status, mappings, direct issuance, queue processing,
  retries and logs.

Designing the certificate itself happens in the Verifycate dashboard. This
plugin handles the connection and issuance.

== External services ==

This plugin connects to the Verifycate API to create and issue digital
certificates.

* Service: Verifycate (https://verifycate.com)
* What is sent, and when: after an administrator connects the site with a
  Verifycate workspace API key and configures at least one course mapping, the
  plugin sends the learner's name, email address, the course title, the
  completion date, a score when the LMS provides one, and any mapped certificate
  attributes to the Verifycate API so the certificate can be created, issued and
  delivered. No data is sent before the site is connected and a mapping is
  saved.
* The connection uses the REST API over HTTPS with a workspace API key.
* Terms of Use: https://verifycate.com/terms
* Privacy Policy: https://verifycate.com/privacy

A Verifycate account is required for the plugin to issue certificates.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/verifycate/`, or install it
   through the WordPress plugins screen.
2. Activate the plugin.
3. Go to Verifycate > Get started, paste a workspace API key, and click Connect
   Verifycate. The plugin finds the workspace, checks the key scopes and plan,
   and tests the connection.
4. Create at least one certificate in the Verifycate dashboard, then map a
   course under Verifycate > Mappings and choose the course, quiz-passed or
   lesson-completed trigger.
5. Optional: add `[verifycate_wallet]` to a learner-facing page, or insert the
   Verifycate Wallet block.

== Frequently Asked Questions ==

= Does this plugin require an LMS plugin? =

No. The plugin works with LearnDash, Tutor LMS, LearnPress, LifterLMS and Sensei
when they are present, and you can also issue certificates through the custom
hook. A custom-hook mapping lets you connect other plugins without waiting for a
dedicated integration.

= Do I need a Verifycate account? =

Yes. Certificates are created and hosted by Verifycate. The plugin is the
connection between your WordPress site and your Verifycate workspace.

= Does the plugin design my certificates? =

No. You design certificates in the Verifycate dashboard and map to them here.

= Can I issue a certificate for a quiz or a lesson? =

Yes. Choose the LMS, then select the course-completed, quiz-passed or
lesson-completed trigger and pick a specific course, quiz or lesson — or use one
of the “Any” options to cover a whole catalogue or a group of items. Quiz
triggers can optionally require a minimum score. For an event your LMS does not
expose, use the custom hook and trigger `verifycate_course_completed` from your
own code.

= Does it work without an API key? =

No. Course-completion issuance requires a workspace API key so the plugin can
talk to the Verifycate API.

= What happens if the Verifycate API is temporarily unavailable? =

The completion is written to a database queue. The plugin retries with
exponential backoff, up to five attempts, and shows the last error under
Verifycate > Logs. Failed jobs can be retried after the connection or mapping is
fixed.

= Where is data stored? =

Settings, encrypted API key, mappings, queue jobs and bounded logs are stored in
your WordPress database. Uninstall removes them only when the administrator
enabled data removal on the Get started screen.

= Can learners see their certificates? =

Yes. Add the `[verifycate_wallet]` shortcode or the Verifycate Wallet block to a
page. Logged-in learners see the certificates issued to their account email.
You can also embed a single certificate with `[verifycate_certificate
public_id="..."]` or add a public verification form with `[verifycate_verify]`.

= Can I translate the plugin? =

Yes. The plugin is translation-ready and includes a `languages/verifycate.pot`
template. Use Poedit, Loco Translate, or WP-CLI to create a translation for your
locale, then place the `.po` and `.mo` files in `wp-content/languages/plugins/`
or the plugin's `languages/` directory.

== Screenshots ==

1. Connect your Verifycate workspace and see which LMS plugins are active.
2. Store the workspace API key without exposing it in the admin.
3. Map any course, quiz or lesson to a Verifycate certificate.
4. Add a minimum score for quiz-based completions.
5. Pick certificates directly from the Verifycate catalogue.
6. Search quiz and lesson options grouped by course.
7. Use the same mapping flow for Sensei LMS.
8. Map custom completion hooks when an LMS is not covered.
9. Monitor queued and issued certificates from the logs screen.
10. Give learners a wallet with every certificate they have earned.
11. Let anyone verify a certificate with its public ID.
12. Embed a certificate card anywhere with a shortcode.

== Changelog ==

= 0.1.0 =
* Initial release.
* Connection setup with an API key stored encrypted at rest and a connection
  test with actionable errors.
* Course mappings for LearnDash, Tutor LMS, LearnPress, LifterLMS, Sensei and a
  custom hook, including any-course mappings and attribute templates.
* Course-completed, quiz-passed and lesson-completed triggers, with optional
  minimum quiz scores.
* Durable issuance queue with retries, exponential backoff and a retry action.
* Duplicate suppression and recertification (reissue) policies.
* Certificate wallet shortcode and server-rendered block, plus
  single-certificate and public-verification shortcodes.
* Search, sorting, pagination and bulk actions for course mappings.
* Dedicated Configure screen for the workspace API key and environment settings.
* Translation-ready with a bundled POT file.
* Admin mappings, queue and activity log screens.
* WP-CLI commands for status, mappings, issuance, queue, retries and logs.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
