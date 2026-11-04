# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- The Mailchimp service adapter: connection check, audiences and merge fields; adding or updating
  each person with `status_if_new`, resubscribing only on fresh consent, with tags; merge-field
  updates, unsubscribes and archiving.
- The webhook receiver: registers for unsubscribes, cleaned and changed addresses, answers
  Mailchimp's check, verifies the HMAC signature when the secret is held, and checks each call is
  for the binding's audience.
