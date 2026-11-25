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

/**
 * Bridges the cache flushing APIs of the supported TYPO3 versions:
 * DataHandler (v13, v14) and CacheFlusher (v15+).
 */
interface CacheFlushAdapterInterface
{
    /**
     * Resolves what DataHandler would flush when the record is saved,
     * without flushing anything.
     */
    public function resolveRecord(string $table, int $uid): FlushTarget;

    public function flush(FlushTarget $target): void;
}
