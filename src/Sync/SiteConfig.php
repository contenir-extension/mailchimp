<?php

declare(strict_types=1);

namespace Contenir\Extension\Mailchimp\Sync;

use Contenir\Extension\Api\Handle;

use function array_replace_recursive;
use function is_array;
use function is_file;
use function is_string;
use function sprintf;

/**
 * The add-on's own config file, as plan section 11 places it: the site's
 * config/autoload/extensions/<handle>.global.php, with .local.php laid over it, each returning
 * ['extensions' => ['<handle>' => [...]]]. It holds the pepper the email hashes are made with,
 * which belongs in .local.php, and the bindings.
 *
 * @api
 */
final readonly class SiteConfig
{
    /**
     * @param array<array-key, mixed> $bindings Each binding's config, by its name
     */
    public function __construct(
        public ?string $pepper,
        public array $bindings,
    ) {}

    /**
     * The config in the site at $sitePath, or nothing when it has no file.
     *
     * @throws NotReady When a file doesn't return a list, or its bindings aren't one.
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; the pepper is checked as text and the bindings as a list.
     */
    public static function read(string $sitePath, Handle $handle): self
    {
        $config = [];
        foreach (['global', 'local'] as $kind) {
            $config = array_replace_recursive($config, self::file(
                sprintf('%s/config/autoload/extensions/%s.%s.php', $sitePath, $handle->value, $kind),
                $handle,
            ));
        }

        $pepper   = $config['pepper'] ?? null;
        $bindings = $config['bindings'] ?? [];
        if (! is_array($bindings)) {
            throw NotReady::because('The bindings in the add-on\'s config file must be a list.');
        }

        return new self(is_string($pepper) && '' !== $pepper ? $pepper : null, $bindings);
    }

    /**
     * The add-on's part of one file, or nothing when there's no such file.
     *
     * @return array<array-key, mixed>
     *
     * @throws NotReady
     *
     * @mago-expect analysis:mixed-assignment A config file returns whatever it returns; it is checked as a list.
     */
    private static function file(string $path, Handle $handle): array
    {
        if (! is_file($path)) {
            return [];
        }

        $config = require $path;
        $own    = is_array($config) ? $config['extensions'][$handle->value] ?? [] : null;
        if (! is_array($own)) {
            throw NotReady::because(sprintf(
                '%s must return [\'extensions\' => [\'%s\' => [...]]].',
                $path,
                $handle->value,
            ));
        }

        return $own;
    }
}
