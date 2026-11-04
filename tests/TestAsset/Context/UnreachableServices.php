<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\TestAsset\Context;

use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * A host that says it has every service but can make none of them.
 */
final class UnreachableServices implements ContainerInterface
{
    #[Override]
    public function get(string $id): mixed
    {
        throw new class('refused') extends RuntimeException implements ContainerExceptionInterface {};
    }

    #[Override]
    public function has(string $id): bool
    {
        return true;
    }
}
