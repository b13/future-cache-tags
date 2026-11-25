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

use B13\FutureCacheTags\Command\RebuildCommand;
use B13\FutureCacheTags\Schedule\ScheduleRebuilder;
use B13\FutureCacheTags\Schedule\ScheduleRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Configuration\Features;

/**
 * cache:future:rebuild
 */
final class RebuildTest extends AbstractFunctionalTestCase
{
    private const NOW = 1700000000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/rebuild.csv');
    }

    #[Test]
    public function liveEnabledRecordsWithFutureStarttimeOrEndtimeAreScheduled(): void
    {
        $counts = $this->get(ScheduleRebuilder::class)->rebuild([], self::NOW);

        self::assertSame(1, $counts['pages']);
        self::assertSame(4, $counts['tt_content']);
        self::assertSame(
            [
                'pages:10' => 1700003600,
                'tt_content:100' => 1700003600,
                'tt_content:101' => 1700007200,
                'tt_content:102' => 1700010800,
                // A translation with its own starttime
                'tt_content:107' => 1700014400,
            ],
            $this->getSchedule()
        );
        self::assertContains('tt_content_pid_2', $this->getScheduledTags('tt_content', 100));
        self::assertContains('news_list', $this->getScheduledTags('tt_content', 100));
    }

    #[Test]
    public function rebuildReplacesStaleEntries(): void
    {
        $this->getConnectionPool()->getConnectionForTable(ScheduleRepository::TABLE)->insert(ScheduleRepository::TABLE, [
            'tablename' => 'tt_content',
            'record_uid' => 103,
            'due_at' => self::NOW + 60,
            'tags' => '{"pages":["tt_content_103"]}',
        ]);

        $this->get(ScheduleRebuilder::class)->rebuild([], self::NOW);

        self::assertNull($this->getScheduledRecord('tt_content', 103));
    }

    #[Test]
    public function rebuildOfOneTableKeepsTheEntriesOfOtherTables(): void
    {
        $rebuilder = $this->get(ScheduleRebuilder::class);
        $rebuilder->rebuild([], self::NOW);
        $this->getConnectionPool()->getConnectionForTable('tt_content')->update('tt_content', ['hidden' => 1], ['uid' => 100]);

        $counts = $rebuilder->rebuild(['tt_content'], self::NOW);

        self::assertSame(['tt_content' => 3], $counts);
        self::assertArrayHasKey('pages:10', $this->getSchedule());
        self::assertArrayNotHasKey('tt_content:100', $this->getSchedule());
    }

    #[Test]
    public function rebuildOfATableWithoutStarttimeOrEndtimeFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1764086401);
        $this->get(ScheduleRebuilder::class)->rebuild(['sys_category_that_does_not_exist'], self::NOW);
    }

    #[Test]
    public function rebuiltScheduleIsProcessedLikeOneWrittenByDataHandler(): void
    {
        $this->get(ScheduleRebuilder::class)->rebuild([], self::NOW);
        $this->getPageCache()->set('list', 'list', ['tt_content_pid_2']);

        $result = $this->process(1700007200);

        self::assertCount(3, $result->records);
        self::assertFalse($this->getPageCache()->has('list'));
        self::assertSame(
            [
                'tt_content:102' => 1700010800,
                'tt_content:107' => 1700014400,
            ],
            $this->getSchedule()
        );
    }

    #[Test]
    public function commandRebuildsTheScheduleAndHintsAtAutomaticCacheTagging(): void
    {
        // The command uses the current time
        $this->getConnectionPool()->getConnectionForTable('pages')->update('pages', ['starttime' => time() + 3600], ['uid' => 10]);
        $commandTester = new CommandTester(new RebuildCommand($this->get(ScheduleRebuilder::class), $this->get(Features::class)));

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['frontend.cache.autoTagging'] = false;
        $commandTester->execute(['--table' => ['pages']]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('Scheduled 1 records.', $commandTester->getDisplay());
        self::assertStringContainsString('[NOTE] Automatic cache tagging', $commandTester->getDisplay());

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['frontend.cache.autoTagging'] = true;
        $commandTester->execute(['--table' => ['pages']]);
        self::assertStringNotContainsString('[NOTE] Automatic cache tagging', $commandTester->getDisplay());

        $commandTester->execute(['--table' => ['sys_language']]);
        self::assertSame(2, $commandTester->getStatusCode());
    }

    /**
     * @return array<string, int>
     */
    private function getSchedule(): array
    {
        $schedule = [];
        foreach ($this->get(ScheduleRepository::class)->findAll() as $record) {
            $schedule[$record->table . ':' . $record->uid] = $record->dueAt;
        }
        ksort($schedule);
        return $schedule;
    }
}
