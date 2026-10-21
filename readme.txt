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

* Automatic issuance on course completion, with an "any course" option so one
  certificate can cover a whole catalogue.
* Map learner and course data to certificate attributes with tokens such as
  {user_name}, {course_title}, {completion_date}, {user_meta:key} and
  {course_meta:key}.
* Control repeat completions: issue once per learner, or reissue onto the latest
  published design for recertification.
* A durable issuance queue with exponential backoff, visible failures and a
  retry action, so a temporary API outage does not lose a completion.
* A certificate wallet shortcode and block so learners can open what they have
  earned.
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
3. Go to Verifycate > Get started and paste your workspace slug or dashboard
   URL plus your API key. The plugin tests the connection when you click
   Connect Verifycate.
4. Create at least one certificate in the Verifycate dashboard, then map a
   course to it under Verifycate > Mappings.
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

= Does it work without an API key? =

No. Course-completion issuance requires a workspace slug and API key so the
plugin can talk to the Verifycate API.

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

== Screenshots ==

1. Connecting a WordPress site to Verifycate.
2. Mapping a course to a certificate.
3. The learner certificate wallet.
4. Queue and issuance logs.

== Changelog ==

= 0.1.0 =
* Initial release.
* Connection setup with an API key stored encrypted at rest and a connection
  test with actionable errors.
* Course mappings for LearnDash, Tutor LMS, LearnPress, LifterLMS, Sensei and a
  custom hook, including any-course mappings and attribute templates.
* Durable issuance queue with retries, exponential backoff and a retry action.
* Duplicate suppression and recertification (reissue) policies.
* Certificate wallet shortcode and server-rendered block.
* Admin mappings, queue and activity log screens.
* WP-CLI commands for status, mappings, issuance, queue, retries and logs.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
