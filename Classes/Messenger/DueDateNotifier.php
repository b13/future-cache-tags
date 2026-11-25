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

use B13\FutureCacheTags\Configuration\ExtensionSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Dispatches a delayed ProcessDueRecordsMessage per due date, if enabled
 * in the extension configuration.
 */
final class DueDateNotifier
{
    public function __construct(
        private readonly ExtensionSettings $settings,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settings->isMessengerEnabled();
    }

    public function notify(int $dueAt): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $delayInMilliseconds = max(0, $dueAt - time()) * 1000;
        try {
            $this->messageBus->dispatch(new ProcessDueRecordsMessage($dueAt), [new DelayStamp($delayInMilliseconds)]);
        } catch (\Throwable $e) {
            // The CLI worker still finds the record, a failing transport must not break saving records.
            $this->logger->error('Could not dispatch the message for scheduled records due at {dueAt}: {message}', [
                'dueAt' => $dueAt,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
