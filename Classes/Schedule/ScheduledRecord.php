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

use B13\FutureCacheTags\Cache\FlushTarget;

/**
 * An entry of the schedule: the caches to flush for a record at its due date.
 */
final class ScheduledRecord
{
    public function __construct(
        public readonly string $table,
        public readonly int $uid,
        public readonly int $dueAt,
        public readonly FlushTarget $flushTarget,
    ) {
    }
}
