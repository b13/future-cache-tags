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

use B13\FutureCacheTags\Schedule\ScheduleRebuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Configuration\Features;

#[AsCommand(
    name: 'cache:future:rebuild',
    description: 'Rebuilds the schedule from all records with a future starttime or endtime.',
)]
final class RebuildCommand extends Command
{
    public function __construct(
        private readonly ScheduleRebuilder $scheduleRebuilder,
        private readonly Features $features,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('table', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only rebuild these tables');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = [];
        try {
            $counts = $this->scheduleRebuilder->rebuild(
                array_values((array)$input->getOption('table')),
                null,
                static function (string $table, int $count) use ($io, &$rows): void {
                    $rows[] = [$table, $count];
                    if ($io->isVerbose()) {
                        $io->writeln(sprintf('%s: %d', $table, $count));
                    }
                }
            );
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::INVALID;
        }
        $io->table(['Table', 'Scheduled records'], $rows);
        $io->success(sprintf('Scheduled %d records.', array_sum($counts)));
        if (!$this->features->isFeatureEnabled('frontend.cache.autoTagging')) {
            $io->note(
                'Automatic cache tagging ($GLOBALS[\'TYPO3_CONF_VARS\'][\'SYS\'][\'features\'][\'frontend.cache.autoTagging\']) is disabled. '
                . 'Without it, pages listing records of other storage folders (e.g. Extbase plugins) carry no tag of these records, '
                . 'and only the page of a record, its "<table>_<uid>" tag and TCEMAIN.clearCacheCmd are flushed.'
            );
        }
        return Command::SUCCESS;
    }
}
