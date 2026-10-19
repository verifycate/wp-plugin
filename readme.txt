=== Verifycate ===
Contributors: verifycate
Tags: certificates, lms, learndash, tutor lms, learnpress, lifterlms, digital certificates, course certificate, verification, online courses
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Issue verifiable certificates from your WordPress LMS with Verifycate.

== Description ==

Verifycate connects your WordPress LMS to the Verifycate credential platform, so
a verifiable certificate is created and issued automatically when a learner
completes a course.

Supported course plugins:

* LearnDash
* Tutor LMS
* LearnPress
* LifterLMS
* Sensei
* A generic custom-hook mapping for other course, membership and e-commerce
  plugins

Highlights:

* Automatic issuance on course, lesson or quiz completion, with an "any course"
  option so one certificate can cover a whole catalogue.
* Map LMS data (learner name, email, course title, completion date, score and
  custom fields) to certificate attributes.
* Control repeat completions: suppress duplicates or issue a fresh certificate
  for recertification.
* Every certificate has a public verification page and signed Open Badge data.
* A certificate wallet shortcode and block so learners can view and download
  what they have earned.

Designing the certificate itself happens in the Verifycate dashboard. This
plugin handles the connection and issuance.

== External services ==

This plugin connects to the Verifycate API to create and issue digital
certificates.

* Service: Verifycate (https://verifycate.com)
* What is sent, and when: after an administrator connects the site with a
  Verifycate workspace API key and configures at least one course mapping, the
  plugin sends the learner's name, email address, the course title, the
  completion date, and any mapped certificate attributes to the Verifycate API
  so the certificate can be created, issued and delivered. No data is sent
  before the site is connected and a mapping is saved.
* The connection uses the REST API over HTTPS with a workspace API key.
* Terms of Use: https://verifycate.com/terms
* Privacy Policy: https://verifycate.com/privacy

A Verifycate account is required for the plugin to issue certificates.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/verifycate/`, or install it
   through the WordPress plugins screen.
2. Activate the plugin.
3. Go to Settings > Verifycate and enter your workspace slug and API key.
4. Create at least one certificate in the Verifycate dashboard, then map a
   course to it under Verifycate > Mappings.

== Frequently Asked Questions ==

= Does this plugin require an LMS plugin? =

No. The plugin works with LearnDash, Tutor LMS, LearnPress, LifterLMS and Sensei
when they are present, and you can also issue certificates manually to any user.
A custom-hook mapping lets you connect other plugins without waiting for a
dedicated integration.

= Do I need a Verifycate account? =

Yes. Certificates are created and hosted by Verifycate. The plugin is the
connection between your WordPress site and your Verifycate workspace.

= Does the plugin design my certificates? =

No. You design certificates in the Verifycate dashboard and map to them here.

= Does it work without an API key? =

No. Course-completion issuance requires a workspace slug and API key so the
plugin can talk to the Verifycate API.

== Screenshots ==

1. Connecting a WordPress site to Verifycate.
2. Mapping a course to a certificate.

== Changelog ==

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
