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
use B13\FutureCacheTags\Cache\FlushTarget;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Locking\Exception\LockAcquireWouldBlockException;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;

/**
 * Flushes the caches of all records that are due, in one go: the cache tags
 * of all due records are merged, so a tag shared by five records is flushed
 * once. Afterwards every record is synced again, which schedules its next
 * due date (the endtime after the starttime passed) or removes it.
 */
final class DueRecordProcessor
{
    public function __construct(
        private readonly ScheduleRepository $scheduleRepository,
        private readonly RecordScheduler $recordScheduler,
        private readonly CacheFlushAdapterInterface $cacheFlushAdapter,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(?int $now = null, bool $dryRun = false): ProcessingResult
    {
        $now ??= time();
        if ($dryRun) {
            $records = $this->scheduleRepository->findDue($now);
            return new ProcessingResult($records, $this->mergeFlushTargets($records));
        }

        $locker = $this->lockFactory->createLocker(
            'future_cache_tags_worker',
            LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK
        );
        try {
            if (!$locker->acquire(LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK)) {
                return new ProcessingResult([], FlushTarget::create(), true);
            }
        } catch (LockAcquireWouldBlockException) {
            return new ProcessingResult([], FlushTarget::create(), true);
        }

        try {
            $records = $this->scheduleRepository->findDue($now);
            $flushTarget = $this->mergeFlushTargets($records);
            $this->cacheFlushAdapter->flush($flushTarget);
            foreach ($records as $record) {
                $this->recordScheduler->sync($record->table, $record->uid, $now, false);
            }
            if ($records !== []) {
                $this->logger->info('Flushed the caches of {count} scheduled records', [
                    'count' => count($records),
                    'tags' => $flushTarget->getTagsByGroup(),
                    'groups' => $flushTarget->getGroups(),
                ]);
            }
            return new ProcessingResult($records, $flushTarget);
        } finally {
            $locker->release();
        }
    }

    /**
     * @param list<ScheduledRecord> $records
     */
    private function mergeFlushTargets(array $records): FlushTarget
    {
        $flushTarget = FlushTarget::create();
        foreach ($records as $record) {
            $flushTarget = $flushTarget->merge($record->flushTarget);
        }
        return $flushTarget;
    }
}
