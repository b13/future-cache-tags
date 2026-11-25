<?php

declare(strict_types=1);

namespace B13\FutureCacheTags\Messenger;

/*
 * This file is part of TYPO3 CMS-based extension "future_cache_tags" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

/**
 * Asks the worker to flush the caches of all records that are due.
 * Dispatched with a DelayStamp, so a transport with delay support
 * delivers it at $dueAt. Handling it more often than needed does no harm.
 */
final class ProcessDueRecordsMessage
{
    public function __construct(
        public readonly int $dueAt,
    ) {
    }
}
