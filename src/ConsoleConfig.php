<?php

/**
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Console;

use PHPdot\Container\Attribute\Config;

#[Config('console')]
final readonly class ConsoleConfig
{
    /**
     * @param string $name Application name
     * @param string $version Application version
     * @param string $cachePath Path to command cache file (empty = no cache)
     */
    public function __construct(
        public string $name = 'PHPdot',
        public string $version = '1.0.0',
        public string $cachePath = '',
    ) {}
}
