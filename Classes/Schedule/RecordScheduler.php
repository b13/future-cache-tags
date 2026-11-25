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

use B13\FutureCacheTags\Cache\CacheFlushAdapterInterface;
use B13\FutureCacheTags\Messenger\DueDateNotifier;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Keeps the schedule entry of a record in sync with the record: schedules it
 * when it has a future starttime / endtime, removes it otherwise.
 *
 * Only live records are scheduled: workspace versions are not published
 * by time, they are synced when they are published.
 */
final class RecordScheduler
{
    public function __construct(
        private readonly ScheduledTableRegistry $tableRegistry,
        private readonly ScheduleRepository $scheduleRepository,
        private readonly CacheFlushAdapterInterface $cacheFlushAdapter,
        private readonly ConnectionPool $connectionPool,
        private readonly DueDateNotifier $dueDateNotifier,
    ) {
    }

    /**
     * @param bool $includeTranslations Also sync the translations of a default language record,
     *                                  their starttime / endtime may be synced from it ("l10n_mode=exclude").
     * @param bool $notify Dispatch a delayed message for a new due date, see DueDateNotifier
     * @return int|null The due date the record is scheduled for, null if it is not scheduled
     */
    public function sync(string $table, int $uid, ?int $now = null, bool $includeTranslations = true, bool $notify = true): ?int
    {
        $now ??= time();
        $scheduledTable = $this->tableRegistry->get($table);
        if ($scheduledTable === null || $uid <= 0) {
            return null;
        }
        $record = $this->fetchRecord($scheduledTable, $uid);
        if ($record !== null && !$scheduledTable->isLiveRecord($record)) {
            // A workspace version, the live record and its schedule stay untouched.
            return null;
        }

        $dueAt = $record !== null ? $scheduledTable->getNextDueDate($record, $now) : null;
        if ($dueAt === null) {
            $this->scheduleRepository->remove($table, $uid);
        } else {
            $flushTarget = $this->cacheFlushAdapter->resolveRecord($table, $uid);
            $previousDueAt = $this->scheduleRepository->save(new ScheduledRecord($table, $uid, $dueAt, $flushTarget), $now);
            if ($notify && $previousDueAt !== $dueAt) {
                $this->dueDateNotifier->notify($dueAt);
            }
        }

        if ($includeTranslations && $record !== null && $scheduledTable->isDefaultLanguageRecord($record)) {
            foreach ($this->fetchTranslationUids($scheduledTable, $uid) as $translationUid) {
                $this->sync($table, $translationUid, $now, false, $notify);
            }
        }
        return $dueAt;
    }

    private function fetchRecord(ScheduledTable $table, int $uid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table->name);
        $queryBuilder->getRestrictions()->removeAll();
        $record = $queryBuilder
            ->select(...$table->getFieldsToSelect())
            ->from($table->name)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return is_array($record) ? $record : null;
    }

    /**
     * @return list<int>
     */
    private function fetchTranslationUids(ScheduledTable $table, int $uid): array
    {
        if ($table->translationParentField === null || $table->languageField === null) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table->name);
        $queryBuilder->getRestrictions()->removeAll();
        $constraints = [
            $queryBuilder->expr()->eq($table->translationParentField, $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            $queryBuilder->expr()->gt($table->languageField, $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
        ];
        if ($table->isWorkspaceAware) {
            $constraints[] = $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT));
        }
        return array_map(intval(...), $queryBuilder
            ->select('uid')
            ->from($table->name)
            ->where(...$constraints)
            ->executeQuery()
            ->fetchFirstColumn());
    }
}
