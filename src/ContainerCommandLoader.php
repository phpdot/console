<?php

/**
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Console;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

final class ContainerCommandLoader implements CommandLoaderInterface
{
    /**
     * @param ContainerInterface $container PSR-11 container for resolving commands
     * @param array<string, class-string<SymfonyCommand>> $commandMap Command name to class map
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $commandMap,
    ) {}

    /**
     * Resolve a command by name from the container.
     *
     * @throws CommandNotFoundException If the command is not in the map or the resolved object is not a command
     */
    public function get(string $name): SymfonyCommand
    {
        if (!isset($this->commandMap[$name])) {
            throw new CommandNotFoundException(sprintf('Command "%s" does not exist.', $name));
        }

        $command = $this->container->get($this->commandMap[$name]);

        if (!$command instanceof SymfonyCommand) {
            throw new CommandNotFoundException(sprintf(
                'Command "%s" resolved to "%s" which is not a Symfony Command instance.',
                $name,
                get_debug_type($command),
            ));
        }

        return $command;
    }

    /**
     * Check if a command exists in the map.
     */
    public function has(string $name): bool
    {
        return isset($this->commandMap[$name]);
    }

    /**
     * Get all registered command names.
     *
     * @return list<string>
     */
    public function getNames(): array
    {
        return array_keys($this->commandMap);
    }
}
