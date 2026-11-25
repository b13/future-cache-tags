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

use B13\FutureCacheTags\Schedule\DueRecordProcessor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessDueRecordsMessageHandler
{
    public function __construct(
        private readonly DueRecordProcessor $dueRecordProcessor,
    ) {
    }

    public function __invoke(ProcessDueRecordsMessage $message): void
    {
        // Use the current time, not the due date of the message: a long-running
        // consumer may deliver it late, and everything due until now is flushed.
        $this->dueRecordProcessor->process();
    }
}
