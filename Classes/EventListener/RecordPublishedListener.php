<?php

declare(strict_types=1);

namespace B13\FutureCacheTags\EventListener;

/*
 * This file is part of TYPO3 CMS-based extension "future_cache_tags" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\FutureCacheTags\Schedule\RecordScheduler;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Workspaces\Event\AfterRecordPublishedEvent;

/**
 * Publishing a workspace version writes the live record, which may now have
 * a different (or no longer a) future starttime / endtime.
 */
final class RecordPublishedListener
{
    public function __construct(
        private readonly RecordScheduler $recordScheduler,
    ) {
    }

    #[AsEventListener('future-cache-tags/record-published', event: AfterRecordPublishedEvent::class)]
    public function __invoke(AfterRecordPublishedEvent $event): void
    {
        $this->recordScheduler->sync($event->getTable(), $event->getRecordId());
    }
}
