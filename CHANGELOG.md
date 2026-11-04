# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-11-04

### Added

- The Mailchimp service adapter: connection check, audiences and merge fields; adding or updating
  each person with `status_if_new`, resubscribing only on fresh consent, with tags; merge-field
  updates, unsubscribes and archiving.
- The webhook receiver: registers for unsubscribes, cleaned and changed addresses, answers
  Mailchimp's check, verifies the HMAC signature when the secret is held, and checks each call is
  for the binding's audience.
- The add-on: a settings section with the API key and the webhook's secret, and Test connection, Sync all now and
  Re-register webhooks; the sync-record and remove-record listeners; the drain schedule; the events webhook, which
  checks each call's signature against the secret Mailchimp gave as the webhook was registered; the library's tables
  under `ext_mailchimp_`; the Sync and Bindings screens; and checks for the setup, the connection, the outbox and the
  webhooks.
