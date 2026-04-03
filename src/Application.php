<?php

/**
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Console;

use PHPdot\Console\Cache\CommandCache;
use PHPdot\Console\Discovery\CommandDiscovery;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class Application
{
    private readonly SymfonyApplication $symfony;

    /** @var array<string, class-string<SymfonyCommand>> */
    private array $commandMap = [];

    /**
     * @param string $name Application name
     * @param string $version Application version
     * @param ContainerInterface|null $container PSR-11 container for resolving commands
     * @param CommandCache|null $cache Cache for discovered command maps
     */
    public function __construct(
        string $name = 'PHPdot',
        string $version = '1.0.0',
        private readonly ?ContainerInterface $container = null,
        private readonly ?CommandCache $cache = null,
    ) {
        $this->symfony = new SymfonyApplication($name, $version);
    }

    /**
     * Discover commands in the given directories.
     *
     * @param list<string> $directories Directories to scan
     * @param bool $forceRescan Ignore cache and rescan
     */
    public function discover(array $directories, bool $forceRescan = false): self
    {
        $discovered = null;

        if (!$forceRescan && $this->cache !== null && $this->cache->has()) {
            $discovered = $this->cache->read();
        }

        if ($discovered === null) {
            $discovery = new CommandDiscovery();
            $discovered = $discovery->discover($directories);

            if ($this->cache !== null) {
                $this->cache->write($discovered);
            }
        }

        /** @var array<string, class-string<SymfonyCommand>> $discovered */
        foreach ($discovered as $name => $class) {
            $this->commandMap[$name] = $class;
        }

        $this->wireCommands();

        return $this;
    }

    /**
     * Register command classes by their class names.
     *
     * @param list<class-string<SymfonyCommand>> $classes
     */
    public function register(array $classes): self
    {
        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(AsCommand::class);

            if ($attributes === []) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();

            $name = $attribute->name;

            if ($name === '') {
                continue;
            }

            $this->commandMap[$name] = $class;
        }

        $this->wireCommands();

        return $this;
    }

    /**
     * Add a command instance directly.
     */
    public function add(SymfonyCommand $command): self
    {
        $this->symfony->add($command);

        return $this;
    }

    /**
     * Run the application.
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        return $this->symfony->run($input, $output);
    }

    /**
     * Call a command programmatically and return its exit code.
     *
     * @param array<string, mixed> $arguments
     */
    public function call(string $commandName, array $arguments = []): int
    {
        $command = $this->symfony->find($commandName);
        $input = new ArrayInput($arguments);
        $output = new BufferedOutput();

        return $command->run($input, $output);
    }

    /**
     * Get the underlying Symfony Application instance.
     */
    public function getSymfonyApplication(): SymfonyApplication
    {
        return $this->symfony;
    }

    private function wireCommands(): void
    {
        if ($this->container !== null) {
            $loader = new ContainerCommandLoader($this->container, $this->commandMap);
            $this->symfony->setCommandLoader($loader);
        } else {
            foreach ($this->commandMap as $class) {
                $this->symfony->add(new $class());
            }
        }
    }
}
