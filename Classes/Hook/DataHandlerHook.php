<?php

declare(strict_types=1);

namespace B13\FutureCacheTags\Hook;

/*
 * This file is part of TYPO3 CMS-based extension "future_cache_tags" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\FutureCacheTags\Schedule\RecordScheduler;
use B13\FutureCacheTags\Schedule\ScheduledTableRegistry;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Syncs the schedule of every record DataHandler writes, moves, copies,
 * localizes, deletes or restores, once DataHandler is done.
 *
 * Writes in a workspace only touch workspace versions, which are ignored;
 * publishing is handled by RecordPublishedListener.
 */
final class DataHandlerHook
{
    /** @var array<string, array<int, true>> */
    private array $records = [];

    public function __construct(
        private readonly ScheduledTableRegistry $tableRegistry,
        private readonly RecordScheduler $recordScheduler,
    ) {
    }

    public function processDatamap_afterDatabaseOperations(string $status, string $table, string|int $id, array $fieldArray, DataHandler $dataHandler): void
    {
        if (!MathUtility::canBeInterpretedAsInteger($id)) {
            $id = $dataHandler->substNEWwithIDs[$id] ?? 0;
        }
        $this->remember($table, (int)$id);
    }

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $this->syncRememberedRecords();
    }

    public function processCmdmap_postProcess(string $command, string $table, string|int $id, mixed $value, DataHandler $dataHandler): void
    {
        if (in_array($command, ['move', 'delete', 'undelete'], true)) {
            $this->remember($table, (int)$id);
        }
    }

    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
        // Records created by "copy", "localize" and "copyToLanguage", including their children
        foreach ($dataHandler->copyMappingArray_merged as $table => $uids) {
            foreach ($uids as $newUid) {
                $this->remember((string)$table, (int)$newUid);
            }
        }
        $this->syncRememberedRecords();
    }

    private function remember(string $table, int $uid): void
    {
        if ($uid > 0 && $this->tableRegistry->has($table)) {
            $this->records[$table][$uid] = true;
        }
    }

    private function syncRememberedRecords(): void
    {
        $records = $this->records;
        $this->records = [];
        foreach ($records as $table => $uids) {
            foreach (array_keys($uids) as $uid) {
                $this->recordScheduler->sync($table, $uid);
            }
        }
    }
}
