# Security Policy

## Reporting a vulnerability

Please **do not** open a public issue for security vulnerabilities.

Report privately to **security@verifycate.com**. Include:

- A description of the issue and its impact.
- Steps to reproduce, or a proof of concept.
- Affected version(s) and environment (WordPress, PHP, LMS plugin versions).

We aim to acknowledge reports within 3 business days and to ship a fix for
confirmed, high-impact issues as quickly as the wordpress.org release
cooldown allows. We will credit reporters in the changelog unless asked not to.

## Scope

This repository is the open-source WordPress client for Verifycate. It stores a
workspace API key and sends learner data to the Verifycate API. Reports about
the plugin's handling of credentials, learner data, capabilities, nonces,
input sanitization and output escaping are in scope.

The Verifycate service itself is out of scope for this repository; report
service issues through your normal support channel.
