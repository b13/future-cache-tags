<?php

declare(strict_types=1);

namespace B13\FutureCacheTags\Cache;

/*
 * This file is part of TYPO3 CMS-based extension "future_cache_tags" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\CMS\Core\Cache\Flush\CacheFlushDemand;
use TYPO3\CMS\Core\Cache\Flush\CacheFlusher;
use TYPO3\CMS\Core\Cache\Flush\CacheFlushingContext;

/**
 * TYPO3 v15+: CacheFlusher resolves a record like DataHandler does,
 * including "TCEMAIN.clearCacheCmd", the storage page tag "<table>_pid_<pid>"
 * and listeners of BeforeRecordRelatedCachesAreFlushedEvent.
 */
final class CoreCacheFlusherAdapter implements CacheFlushAdapterInterface
{
    public function __construct(
        private readonly CacheFlusher $cacheFlusher,
    ) {
    }

    public function resolveRecord(string $table, int $uid): FlushTarget
    {
        $plan = $this->cacheFlusher->resolve(CacheFlushDemand::forRecord($table, $uid), new CacheFlushingContext());
        return FlushTarget::create($plan->getTagsByGroup(), $plan->getGroups());
    }

    public function flush(FlushTarget $target): void
    {
        if ($target->isEmpty()) {
            return;
        }
        $demand = CacheFlushDemand::forGroups(...$target->getGroups());
        foreach ($target->getTagsByGroup() as $group => $tags) {
            $demand = $demand->withTagsInGroup($group, ...$tags);
        }
        $this->cacheFlusher->flush($demand, new CacheFlushingContext());
    }
}
