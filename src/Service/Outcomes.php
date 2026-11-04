<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Service;

use Contenir\Audience\Service\Outcome;

use function in_array;

/**
 * What became of a person, from Mailchimp's reply about them.
 *
 * @api
 */
final class Outcomes
{
    /**
     * Errors for an address Mailchimp won't subscribe again through the API: it was forgotten, or
     * unsubscribed or cleaned in a way only the person can undo.
     */
    public const array FORGOTTEN = ['Forgotten Email Not Subscribed', 'Member In Compliance State'];

    /**
     * The outcome of taking someone off the audience: someone not in it is already off it.
     */
    public static function gone(Reply $reply): Outcome
    {
        return 404 === $reply->status ? Outcome::done() : self::of($reply);
    }

    public static function of(Reply $reply): Outcome
    {
        if ($reply->ok()) {
            $id = Json::string($reply->body, 'id');

            return Outcome::done('' === $id ? null : $id);
        }

        if ($reply->retriable()) {
            return Outcome::retry($reply->message(), $reply->retryAfter);
        }

        return in_array($reply->title(), self::FORGOTTEN, strict: true)
            ? Outcome::forgotten($reply->message())
            : Outcome::failed($reply->message());
    }
}
