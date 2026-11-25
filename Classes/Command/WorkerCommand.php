<?php

declare(strict_types=1);

namespace B13\FutureCacheTags\Command;

/*
 * This file is part of TYPO3 CMS-based extension "future_cache_tags" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\FutureCacheTags\Schedule\DueRecordProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cache:future:worker',
    description: 'Flushes the caches of records whose starttime or endtime has passed. Run it every minute.',
)]
final class WorkerCommand extends Command
{
    public function __construct(
        private readonly DueRecordProcessor $dueRecordProcessor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the due records and cache tags, flush nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');
        $result = $this->dueRecordProcessor->process(null, $dryRun);
        if ($result->locked) {
            $io->note('Another worker is running, skipped.');
            return Command::SUCCESS;
        }
        if ($result->records === []) {
            if ($io->isVerbose()) {
                $io->writeln('No scheduled records are due.');
            }
            return Command::SUCCESS;
        }
        if ($dryRun || $io->isVerbose()) {
            $io->table(
                ['Table', 'UID', 'Due at'],
                array_map(static fn ($record): array => [$record->table, $record->uid, date('Y-m-d H:i:s', $record->dueAt)], $result->records)
            );
            foreach ($result->flushTarget->getTagsByGroup() as $group => $tags) {
                $io->writeln(sprintf('Cache tags (%s): %s', $group, implode(', ', $tags)));
            }
            if ($result->flushTarget->getGroups() !== []) {
                $io->writeln('Cache groups: ' . implode(', ', $result->flushTarget->getGroups()));
            }
        }
        $io->writeln(sprintf(
            $dryRun ? 'Would flush the caches of %d scheduled records.' : 'Flushed the caches of %d scheduled records.',
            count($result->records)
        ));
        return Command::SUCCESS;
    }
}
