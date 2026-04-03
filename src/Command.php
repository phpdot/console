<?php

/**
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Console;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

abstract class Command extends SymfonyCommand
{
    /**
     * Write an info message to output.
     */
    protected function info(OutputInterface $output, string $message): void
    {
        $output->writeln('<info>' . $message . '</info>');
    }

    /**
     * Write an error message to output.
     */
    protected function error(OutputInterface $output, string $message): void
    {
        $output->writeln('<error>' . $message . '</error>');
    }

    /**
     * Write a success message to output with a checkmark prefix.
     */
    protected function success(OutputInterface $output, string $message): void
    {
        $output->writeln('<info>✔ ' . $message . '</info>');
    }

    /**
     * Write a warning message to output with a warning prefix.
     */
    protected function warning(OutputInterface $output, string $message): void
    {
        $output->writeln('<comment>⚠ ' . $message . '</comment>');
    }

    /**
     * Write a comment message to output.
     */
    protected function comment(OutputInterface $output, string $message): void
    {
        $output->writeln('<comment>' . $message . '</comment>');
    }

    /**
     * Ask the user a question.
     */
    protected function ask(InputInterface $input, OutputInterface $output, string $question, ?string $default = null): ?string
    {
        $helper = $this->getQuestionHelper();
        $q = new Question($question . ' ', $default);

        /** @var ?string $answer */
        $answer = $helper->ask($input, $output, $q);

        return $answer;
    }

    /**
     * Ask the user a yes/no confirmation question.
     */
    protected function confirm(InputInterface $input, OutputInterface $output, string $question, bool $default = false): bool
    {
        $helper = $this->getQuestionHelper();
        $q = new ConfirmationQuestion($question . ' ', $default);

        /** @var bool $answer */
        $answer = $helper->ask($input, $output, $q);

        return $answer;
    }

    /**
     * Ask the user to choose from a list of options.
     *
     * @param list<string> $choices
     */
    protected function choice(InputInterface $input, OutputInterface $output, string $question, array $choices, ?string $default = null): string
    {
        $helper = $this->getQuestionHelper();
        $q = new ChoiceQuestion($question, $choices, $default);

        /** @var string $answer */
        $answer = $helper->ask($input, $output, $q);

        return $answer;
    }

    /**
     * Ask the user to choose multiple items from a list of options.
     *
     * @param list<string> $choices
     * @return list<string>
     */
    protected function multiChoice(InputInterface $input, OutputInterface $output, string $question, array $choices): array
    {
        $helper = $this->getQuestionHelper();
        $q = new ChoiceQuestion($question, $choices);
        $q->setMultiselect(true);

        /** @var list<string> $answer */
        $answer = $helper->ask($input, $output, $q);

        return $answer;
    }

    /**
     * Ask the user for secret input (hidden).
     */
    protected function secret(InputInterface $input, OutputInterface $output, string $question): ?string
    {
        $helper = $this->getQuestionHelper();
        $q = new Question($question . ' ');
        $q->setHidden(true);
        $q->setHiddenFallback(false);

        /** @var ?string $answer */
        $answer = $helper->ask($input, $output, $q);

        return $answer;
    }

    /**
     * Ask the user a question with autocomplete suggestions.
     *
     * @param list<string>|callable(string): list<string> $suggestions
     */
    protected function autocomplete(InputInterface $input, OutputInterface $output, string $question, array|callable $suggestions): string
    {
        $helper = $this->getQuestionHelper();
        $q = new Question($question . ' ');

        if (is_callable($suggestions)) {
            $q->setAutocompleterCallback($suggestions);
        } else {
            $q->setAutocompleterValues($suggestions);
        }

        /** @var string $answer */
        $answer = $helper->ask($input, $output, $q);

        return $answer;
    }

    /**
     * Render a table to output.
     *
     * @param list<array<string, scalar|null>> $rows
     * @param list<string> $headers
     */
    protected function table(OutputInterface $output, array $rows, array $headers = []): void
    {
        if ($rows === []) {
            return;
        }

        if ($headers === []) {
            $headers = array_keys($rows[0]);
        }

        $table = new Table($output);
        $table->setHeaders($headers);

        foreach ($rows as $row) {
            $table->addRow(array_values($row));
        }

        $table->render();
    }

    /**
     * Iterate over items with a progress bar.
     *
     * @template T
     * @param iterable<T> $items
     * @param callable(T, int): void $callback
     */
    protected function withProgress(OutputInterface $output, iterable $items, callable $callback): void
    {
        $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output);

        if (is_array($items) || $items instanceof \Countable) {
            $progressBar->setMaxSteps(count($items));
        }

        $progressBar->start();

        $index = 0;

        foreach ($items as $item) {
            $callback($item, $index);
            $progressBar->advance();
            $index++;
        }

        $progressBar->finish();
        $output->writeln('');
    }

    private function getQuestionHelper(): QuestionHelper
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        return $helper;
    }
}
