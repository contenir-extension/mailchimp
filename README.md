# Mailchimp for Contenir CMS

[![Continuous Integration](https://github.com/contenir-extension/mailchimp/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir-extension/mailchimp/actions/workflows/continuous-integration.yml)

Keeps a Mailchimp account's audiences in step with a site's sign-ups and CMS records, through
[contenir/contenir-audience](https://github.com/contenir/contenir-audience)'s outbox, and hears back about unsubscribes,
cleaned addresses and changed addresses through a signed webhook. A sign-up or a save only writes a row in the site's
database; the CMS's job runner sends it within the minute, so a slow or failing Mailchimp never slows a page or a save.

## Setting it up

1. Enable the add-on. Its tables are created at the next sign-in, in the site's database, under `ext_mailchimp_`.
2. Write the add-on's config file. `config/autoload/extensions/mailchimp.global.php` holds the bindings, and
   `mailchimp.local.php`, never committed, the pepper the email hashes are made with. The update check offers a
   fresh pepper to copy. A Mezzio site that signs people up through
   [contenir-audience-mezzio](https://github.com/contenir/contenir-audience-mezzio) reads the same two files and must
   use the same pepper.

   ```php
   return ['extensions' => ['mailchimp' => ['bindings' => [
       'newsletter' => [
           'list'    => 'a1b2c3…',
           'fields'  => ['FNAME' => ['from' => 'name', 'transforms' => ['trim', 'first-name']]],
           'consent' => 'Signing up to the newsletter',
           'tags'    => ['website'],
       ],
       'members' => [
           'list'    => 'd4e5f6…',
           'source'  => 'library.members.member',
           'consent' => 'Members who tick Newsletter',
           'options' => ['consent_field' => 'newsletter'],
       ],
   ]]]];
   ```

3. In Site Settings > Integration > Mailchimp, enter the API key, save, and press Test connection. The key ends with
   the account's data centre, such as `-us21`.
4. For a section binding, bind the listeners in the section's `admin.xml`:

   ```xml
   <events>
       <listen extension="mailchimp" listener="sync-record" event="RecordPostSave"/>
       <listen extension="mailchimp" listener="remove-record" event="RecordPostDelete"/>
   </events>
   ```

5. Press Re-register webhooks. It makes the webhook's secret the first time, registers each binding's audience at
   `/api/extension/mailchimp/events` on the site's own address (`site.url`), and keeps the secret Mailchimp signs each
   webhook's calls with, which Mailchimp gives only then. The site's web server must send `/api/extension/` to the
   CMS's `admin.php`, past any Basic Auth, and answer Mailchimp's GET check there.

## What it adds

| Extension point | What |
|---|---|
| Settings section | Integration > Mailchimp: the API key (masked, in local settings) and the webhook's secret (masked). Buttons: Test connection, Sync all now, Re-register webhooks. |
| Listeners | `sync-record` on `RecordPostSave` and `remove-record` on `RecordPostDelete`, run as the record is saved, since writing a row is quick. |
| Schedule | `drain`, every minute: sends what's due for up to 40 seconds, then deletes sent messages after 7 days and failed ones after 30. |
| Webhook | `events`, at `/api/extension/mailchimp/events?binding=<name>&key=<secret>`. A call without the secret is refused before anything runs. A call for another audience is refused, and so is one whose signature doesn't match the secret Mailchimp gave when the webhook was registered (passed to the receiver as the binding option `signing_secret`). |
| Migrations | The library's outbox, sync state, suppression, consent and webhook tables, under `ext_mailchimp_`, on the site's database. |
| Screens | Sync, at `/admin/extension/mailchimp/sync`: the counts, and the dead, failed and waiting messages with their last error, to retry or remove. Bindings, at `/admin/extension/mailchimp/bindings`: read-only, exactly what each binding sends. |
| Checks | Setup (config file and pepper), connection (with a way to the settings when the key is refused), the outbox (failed and dead messages) and the webhooks (not registered, or deleted at Mailchimp). |

### What a saved record does

A section binding names the record's consent checkbox with the option `consent_field`; without one, being in the
section is the consent.

| The save | Queues |
|---|---|
| A new record, or the checkbox just ticked | a subscribe, with the binding's `consent` wording as fresh consent from the record |
| Any other save while ticked | an update, which reaches only someone already on the list |
| The checkbox just unticked | a removal, as `on_delete` says |
| A changed address | a removal of the old one, and the new one as fresh consent while ticked |
| A deleted record | a removal, as `on_delete` says: `archive` by default (MCH-1), `unsubscribe` keeps them unsubscribed, `keep` leaves them |

Someone who unsubscribed, bounced or complained is never subscribed again without fresh consent, or at all after a
complaint or deletion.

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
