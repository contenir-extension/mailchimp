<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\Unit\Sync;

use Contenir\Audience\Consent;
use Contenir\Audience\ConsentSource;
use Contenir\Audience\Email;
use Contenir\Audience\Service\RemoteEvent;
use Contenir\Audience\Service\RemoteEventKind;
use Contenir\Audience\Suppression\SuppressionReason;
use Contenir\Audience\Testing\InMemoryService;
use Contenir\Extension\Mailchimp\Tests\TestAsset\Sync\SyncKit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SyncTest extends TestCase
{
    #[Test]
    public function appliesWhatTheWebhookHearsToItsStores(): void
    {
        $kit   = new SyncKit();
        $email = Email::fromString('ada@example.com');

        $kit->sync->remoteEvents()->apply($kit->sync->bindings->get('newsletter'), [
            new RemoteEvent(RemoteEventKind::Complaint, $email, $kit->clock->now()),
        ]);

        static::assertSame(
            SuppressionReason::Complaint,
            $kit->stores->suppression->reason('newsletter', $kit->stores->hasher->email($email)),
        );
    }

    #[Test]
    public function putsChangesInItsOutbox(): void
    {
        $kit = new SyncKit();

        $kit->sync->audience()->update('newsletter', ['email' => 'ada@example.com', 'name' => 'Ada']);

        static::assertSame(['Name' => 'Ada'], $kit->stores->outbox->rows[1]->values);
    }

    #[Test]
    public function sendsItsOutboxWithItsClockAndLog(): void
    {
        $kit = new SyncKit();
        $kit->sync->audience()->subscribe(
            'newsletter',
            ['email' => 'ada@example.com'],
            new Consent('Signing up to the newsletter', ConsentSource::Form, $kit->clock->now()),
        );
        $service         = new InMemoryService();
        $service->refuse = 'Bad key';

        $kit->sync->worker()->drain($service);

        static::assertSame('Sending paused: {reason}', $kit->logger->entries()[0]['message'] ?? null);
    }
}
