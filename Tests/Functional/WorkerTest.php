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

use B13\FutureCacheTags\Command\WorkerCommand;
use B13\FutureCacheTags\Schedule\DueRecordProcessor;
use B13\FutureCacheTags\Schedule\ScheduleRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * cache:future:worker
 */
final class WorkerTest extends AbstractFunctionalTestCase
{
    private int $now;
    private int $first;
    private int $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = time();
        $this->first = $this->createRecord('tt_content', 2, ['header' => 'First', 'starttime' => $this->now + 3600]);
        $this->second = $this->createRecord('tt_content', 2, ['header' => 'Second', 'endtime' => $this->now + 7200]);
        // Saving the records flushed the caches, fill them afterwards
        $cache = $this->getPageCache();
        $cache->set('list', 'news list', ['pageId_3', 'tt_content_pid_2']);
        $cache->set('first', 'first record', ['pageId_4', 'tt_content_' . $this->first]);
        $cache->set('second', 'second record', ['pageId_5', 'tt_content_' . $this->second]);
        $cache->set('clearCacheCmd', 'cache tag of TCEMAIN.clearCacheCmd', ['news_list']);
        $cache->set('unrelated', 'unrelated page', ['pageId_99']);
    }

    #[Test]
    public function nothingIsFlushedBeforeTheDueDate(): void
    {
        $result = $this->process($this->now + 3599);

        self::assertSame([], $result->records);
        foreach (['list', 'first', 'second', 'clearCacheCmd', 'unrelated'] as $entry) {
            self::assertTrue($this->getPageCache()->has($entry), $entry);
        }
    }

    #[Test]
    public function cachesOfDueRecordsAreFlushedAndOthersSurvive(): void
    {
        $result = $this->process($this->now + 3600);

        self::assertCount(1, $result->records);
        self::assertFalse($this->getPageCache()->has('list'));
        self::assertFalse($this->getPageCache()->has('first'));
        self::assertFalse($this->getPageCache()->has('clearCacheCmd'));
        self::assertTrue($this->getPageCache()->has('second'));
        self::assertTrue($this->getPageCache()->has('unrelated'));
        self::assertNull($this->getScheduledRecord('tt_content', $this->first));
        self::assertNotNull($this->getScheduledRecord('tt_content', $this->second));
    }

    #[Test]
    public function tagsSharedByDueRecordsAreFlushedOnce(): void
    {
        $result = $this->process($this->now + 7200);

        self::assertCount(2, $result->records);
        $tags = $result->flushTarget->getTags();
        self::assertSame(array_values(array_unique($tags)), $tags);
        self::assertContains('tt_content_pid_2', $tags);
        self::assertContains('tt_content_' . $this->first, $tags);
        self::assertContains('tt_content_' . $this->second, $tags);
        self::assertFalse($this->getPageCache()->has('second'));
        self::assertTrue($this->getPageCache()->has('unrelated'));
        self::assertSame([], $this->get(ScheduleRepository::class)->findAll());
    }

    #[Test]
    public function aWorkerThatRunsLateFlushesEverythingThatIsDue(): void
    {
        $result = $this->process($this->now + 86400);

        self::assertCount(2, $result->records);
        self::assertSame([], $this->get(ScheduleRepository::class)->findAll());
    }

    #[Test]
    public function dryRunFlushesNothingAndKeepsTheSchedule(): void
    {
        $result = $this->process($this->now + 7200, true);

        self::assertCount(2, $result->records);
        self::assertTrue($this->getPageCache()->has('list'));
        self::assertTrue($this->getPageCache()->has('first'));
        self::assertNotNull($this->getScheduledRecord('tt_content', $this->first));
        self::assertNotNull($this->getScheduledRecord('tt_content', $this->second));
    }

    #[Test]
    public function recordChangedWithoutDataHandlerIsRescheduledAfterTheFlush(): void
    {
        // An import moved the starttime without DataHandler, the schedule still has the old date
        $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->update('tt_content', ['starttime' => $this->now + 86400], ['uid' => $this->first]);

        $this->process($this->now + 3600);

        self::assertSame($this->now + 86400, $this->getDueDate('tt_content', $this->first));
    }

    #[Test]
    public function commandReportsTheFlushedRecords(): void
    {
        $this->getConnectionPool()->getConnectionForTable(ScheduleRepository::TABLE)
            ->update(ScheduleRepository::TABLE, ['due_at' => $this->now - 60], ['record_uid' => $this->first]);
        $commandTester = new CommandTester(new WorkerCommand($this->get(DueRecordProcessor::class)));

        $commandTester->execute(['--dry-run' => true]);
        self::assertStringContainsString('Would flush the caches of 1 scheduled records.', $commandTester->getDisplay());
        self::assertStringContainsString('tt_content_pid_2', $commandTester->getDisplay());
        self::assertTrue($this->getPageCache()->has('first'));

        $commandTester->execute([]);
        self::assertStringContainsString('Flushed the caches of 1 scheduled records.', $commandTester->getDisplay());
        self::assertFalse($this->getPageCache()->has('first'));
    }
}
