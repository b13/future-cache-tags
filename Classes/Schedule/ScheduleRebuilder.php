<?php

declare(strict_types=1);

namespace B13\FutureCacheTags\Schedule;

/*
 * This file is part of TYPO3 CMS-based extension "future_cache_tags" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\FutureCacheTags\Messenger\DueDateNotifier;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Builds the schedule from scratch: finds all live, enabled, not deleted
 * records with a future starttime or endtime in every TCA table that has
 * these fields, without DataHandler and without the frontend restrictions,
 * and resolves their cache tags.
 *
 * Needed after installing the extension, and for records written without
 * DataHandler (imports, migrations, direct SQL).
 */
final class ScheduleRebuilder
{
    public function __construct(
        private readonly ScheduledTableRegistry $tableRegistry,
        private readonly ScheduleRepository $scheduleRepository,
        private readonly RecordScheduler $recordScheduler,
        private readonly ConnectionPool $connectionPool,
        private readonly DueDateNotifier $dueDateNotifier,
    ) {
    }

    /**
     * @param list<string> $tables Only rebuild these tables, all scheduled tables if empty
     * @param (callable(string $table, int $count): void)|null $onTableDone
     * @return array<string, int> table => number of scheduled records
     */
    public function rebuild(array $tables = [], ?int $now = null, ?callable $onTableDone = null): array
    {
        $now ??= time();
        $scheduledTables = $this->tableRegistry->getAll();
        if ($tables === []) {
            $this->scheduleRepository->removeAll();
        } else {
            $unknownTables = array_diff($tables, array_keys($scheduledTables));
            if ($unknownTables !== []) {
                throw new \InvalidArgumentException(
                    'These tables have no starttime / endtime: ' . implode(', ', $unknownTables),
                    1764086401
                );
            }
            $scheduledTables = array_intersect_key($scheduledTables, array_flip($tables));
            foreach ($scheduledTables as $table) {
                $this->scheduleRepository->removeAllOfTable($table->name);
            }
        }

        $counts = [];
        $dueDates = [];
        foreach ($scheduledTables as $table) {
            $counts[$table->name] = 0;
            foreach ($this->findScheduledUids($table, $now) as $uid) {
                $dueAt = $this->recordScheduler->sync($table->name, $uid, $now, false, false);
                if ($dueAt !== null) {
                    $counts[$table->name]++;
                    $dueDates[$dueAt] = true;
                }
            }
            if ($onTableDone !== null) {
                $onTableDone($table->name, $counts[$table->name]);
            }
        }
        // One message per due date, not per record
        foreach (array_keys($dueDates) as $dueAt) {
            $this->dueDateNotifier->notify($dueAt);
        }
        return $counts;
    }

    /**
     * @return iterable<int>
     */
    private function findScheduledUids(ScheduledTable $table, int $now): iterable
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table->name);
        $queryBuilder->getRestrictions()->removeAll();
        $future = [];
        foreach ([$table->startTimeField, $table->endTimeField] as $field) {
            if ($field !== null) {
                $future[] = $queryBuilder->expr()->gt($field, $queryBuilder->createNamedParameter($now, Connection::PARAM_INT));
            }
        }
        $constraints = [$queryBuilder->expr()->or(...$future)];
        if ($table->deletedField !== null) {
            $constraints[] = $queryBuilder->expr()->eq($table->deletedField, $queryBuilder->createNamedParameter(0, Connection::PARAM_INT));
        }
        if ($table->disabledField !== null) {
            $constraints[] = $queryBuilder->expr()->eq($table->disabledField, $queryBuilder->createNamedParameter(0, Connection::PARAM_INT));
        }
        if ($table->isWorkspaceAware) {
            $constraints[] = $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT));
        }
        $result = $queryBuilder
            ->select('uid')
            ->from($table->name)
            ->where(...$constraints)
            ->orderBy('uid')
            ->executeQuery();
        while (($uid = $result->fetchOne()) !== false) {
            yield (int)$uid;
        }
    }
}
