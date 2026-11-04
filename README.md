# Mailchimp for Contenir CMS

Keeps a Mailchimp audience in step with a site's sign-ups and CMS records, through
[contenir/contenir-audience](https://github.com/contenir/contenir-audience)'s outbox, and hears
back about unsubscribes, cleaned addresses, complaints and changed addresses through a webhook.

This release holds the service half only: the adapter and the webhook receiver the shared sync
rules call. The add-on itself (settings, listeners, the drain schedule, screens and checks) comes
next.

## How it talks to Mailchimp

- **Connecting:** an API key over HTTP Basic. The key ends with the account's data centre, such
  as `-us21`, which is where requests go.
- **Joining:** one `PUT /lists/{id}/members/{hash}` per person, so a batch cut short is safe to
  send again. `status_if_new` says whether a newcomer joins at once or confirms by email first;
  `status` is sent only for fresh consent, so someone who unsubscribed isn't taken back without
  it. The sign-up time and address from the consent ledger go with it, and the binding's tags are
  added to everyone who joins. Batches are of 100 people, each its own request.
- **Updating:** merge fields only, by `PATCH`, so the status is left alone and no one is added.
- **Unsubscribing and archiving:** `PATCH` to unsubscribed, and `DELETE`, which archives the
  member, which Mailchimp can undo. Someone not in the audience is already off it.
- **Outcomes:** "Forgotten Email Not Subscribed" and "Member In Compliance State" are never
  retried. A 429 or 5xx, or a request that never arrived, is tried again, after `Retry-After`
  when Mailchimp gives one. A 401 or 403 stops the run.
- **Webhook:** registered for unsubscribes, cleaned addresses and changed addresses, from people
  and admins but not from the API, so the add-on's own changes don't echo back. Mailchimp's GET
  check is answered with no events. When the binding's `signing_secret` option holds the secret
  Mailchimp gave at registration, each call must carry a good `X-Mailchimp-Signature` from the
  last five minutes. Either way, each call must be for the binding's audience. A reason of
  `abuse` is read as a complaint.

## Testing

Every request is tested against Mailchimp's documented replies, including refused keys, rate
limits, compliance states and signed webhooks, so the suite never calls the real service.

```bash
composer check
```
