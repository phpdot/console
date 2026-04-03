<?php

/**
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Console\Discovery;

use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

final class CommandDiscovery
{
    /**
     * Discover command classes with #[AsCommand] in the given directories.
     *
     * @param list<string> $directories Directories to scan for command classes
     * @return array<string, class-string<SymfonyCommand>> Command name to class map
     */
    public function discover(array $directories): array
    {
        $commandMap = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $filePath = $file->getRealPath();

                if ($filePath === false) {
                    continue;
                }

                $classes = $this->extractClasses($filePath);

                foreach ($classes as $className) {
                    $result = $this->resolveCommand($className);

                    if ($result === null) {
                        continue;
                    }

                    [$name, $class] = $result;
                    $commandMap[$name] = $class;
                }
            }
        }

        ksort($commandMap);

        return $commandMap;
    }

    /**
     * @return list<class-string>
     */
    private function extractClasses(string $filePath): array
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return [];
        }

        $tokens = PhpToken::tokenize($contents);
        $namespace = '';
        $i = 0;
        $count = count($tokens);
        $classes = [];

        while ($i < $count) {
            $token = $tokens[$i];

            if ($token->id === T_NAMESPACE) {
                $i++;

                while ($i < $count && $tokens[$i]->id === T_WHITESPACE) {
                    $i++;
                }

                $parts = [];

                while ($i < $count && $tokens[$i]->text !== ';' && $tokens[$i]->text !== '{') {
                    if ($tokens[$i]->id !== T_WHITESPACE) {
                        $parts[] = $tokens[$i]->text;
                    }

                    $i++;
                }

                $namespace = implode('', $parts);
            }

            if ($token->id === T_CLASS) {
                $j = $i - 1;

                while ($j >= 0 && $tokens[$j]->id === T_WHITESPACE) {
                    $j--;
                }

                if ($j >= 0 && $tokens[$j]->id === T_NEW) {
                    $i++;
                    continue;
                }

                $i++;

                while ($i < $count && $tokens[$i]->id === T_WHITESPACE) {
                    $i++;
                }

                if ($i < $count && $tokens[$i]->id === T_STRING) {
                    /** @var class-string $className */
                    $className = $namespace !== '' ? $namespace . '\\' . $tokens[$i]->text : $tokens[$i]->text;
                    $classes[] = $className;
                }
            }

            if ($token->id === T_INTERFACE || $token->id === T_TRAIT || $token->id === T_ENUM) {
                $i++;
                continue;
            }

            $i++;
        }

        return $classes;
    }

    /**
     * @param class-string $className
     * @return array{string, class-string<SymfonyCommand>}|null
     */
    private function resolveCommand(string $className): ?array
    {
        if (!class_exists($className)) {
            return null;
        }

        $reflection = new ReflectionClass($className);

        if ($reflection->isAbstract()) {
            return null;
        }

        if (!$reflection->isSubclassOf(SymfonyCommand::class)) {
            return null;
        }

        $attributes = $reflection->getAttributes(AsCommand::class);

        if ($attributes === []) {
            return null;
        }

        $attribute = $attributes[0]->newInstance();

        $name = $attribute->name;

        if ($name === '') {
            return null;
        }

        /** @var class-string<SymfonyCommand> $className */
        return [$name, $className];
    }
}
