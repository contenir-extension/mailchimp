<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\TestAsset\Http;

use LogicException;
use Override;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function array_shift;

/**
 * A PSR-18 client that answers from a queue, in order, and keeps every
 * request it was sent. Nothing reaches the network.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|ClientExceptionInterface> */
    private array $answers;

    public function __construct(ResponseInterface|ClientExceptionInterface ...$answers)
    {
        $this->answers = $answers;
    }

    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $answer           = array_shift($this->answers) ?? throw new LogicException('No answer is queued');
        if ($answer instanceof ClientExceptionInterface) {
            throw $answer;
        }

        return $answer;
    }
}
