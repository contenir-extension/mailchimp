<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Tests\TestAsset\Operation;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Operation\OperationContextInterface;
use Contenir\Extension\Api\Operation\SectionViewInterface;
use LogicException;
use Override;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * What a host gives an operation of the add-on's: its context, or none, and nothing posted.
 */
final readonly class FakeOperationContext implements OperationContextInterface
{
    public function __construct(
        private ?ExtensionContextInterface $extension,
    ) {}

    #[Override]
    public function allPost(): array
    {
        return [];
    }

    #[Override]
    public function extension(): ?ExtensionContextInterface
    {
        return $this->extension;
    }

    #[Override]
    public function isPost(): bool
    {
        return true;
    }

    #[Override]
    public function logger(): LoggerInterface
    {
        return $this->extension?->logger() ?? throw new LogicException('No add-on context');
    }

    #[Override]
    public function param(string $name, mixed $default = null): mixed
    {
        return $default;
    }

    #[Override]
    public function post(string $name, mixed $default = null): mixed
    {
        return $default;
    }

    #[Override]
    public function section(): SectionViewInterface
    {
        throw new LogicException('The add-on\'s operations don\'t read the section');
    }

    #[Override]
    public function services(): ContainerInterface
    {
        throw new LogicException('The add-on\'s operations don\'t use the host\'s services directly');
    }

    #[Override]
    public function sitePath(): string
    {
        throw new LogicException('The add-on\'s operations don\'t use the site\'s path directly');
    }
}
