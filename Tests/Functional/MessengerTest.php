<?php

declare(strict_types=1);

namespace B13\FutureCacheTags\Tests\Functional;

/*
 * This file is part of TYPO3 CMS-based extension "future_cache_tags" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\FutureCacheTags\Messenger\ProcessDueRecordsMessage;
use B13\FutureCacheTags\Schedule\ScheduleRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function handlingTheMessageFlushesDueRecords(): void
    {
        $now = time();
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Scheduled', 'starttime' => $now + 3600]);
        // Let the time pass
        $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->update('tt_content', ['starttime' => $now - 1], ['uid' => $uid]);
        $this->getConnectionPool()->getConnectionForTable(ScheduleRepository::TABLE)
            ->update(ScheduleRepository::TABLE, ['due_at' => $now - 1], ['record_uid' => $uid]);
        $this->getPageCache()->set('detail', 'detail', ['tt_content_' . $uid]);

        // No transport is configured in tests, the default routing handles the message synchronously
        $this->get(MessageBusInterface::class)->dispatch(new ProcessDueRecordsMessage($now - 1));

        self::assertFalse($this->getPageCache()->has('detail'));
        self::assertNull($this->getScheduledRecord('tt_content', $uid));
    }
}
