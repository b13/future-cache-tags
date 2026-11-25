<?php

declare(strict_types=1);

namespace B13\FutureCacheTags\Tests\Unit;

/*
 * This file is part of TYPO3 CMS-based extension "future_cache_tags" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\FutureCacheTags\Schedule\ScheduledTable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ScheduledTableTest extends UnitTestCase
{
    private const NOW = 1700000000;

    public static function nextDueDateDataProvider(): \Generator
    {
        yield 'no dates' => [['starttime' => 0, 'endtime' => 0], null];
        yield 'future starttime' => [['starttime' => self::NOW + 10], self::NOW + 10];
        yield 'future endtime' => [['endtime' => self::NOW + 20], self::NOW + 20];
        yield 'both in the future: starttime first' => [['starttime' => self::NOW + 10, 'endtime' => self::NOW + 20], self::NOW + 10];
        yield 'starttime passed, endtime in the future' => [['starttime' => self::NOW - 10, 'endtime' => self::NOW + 20], self::NOW + 20];
        yield 'starttime is now: passed' => [['starttime' => self::NOW], null];
        yield 'both passed' => [['starttime' => self::NOW - 20, 'endtime' => self::NOW - 10], null];
        yield 'hidden with future starttime' => [['hidden' => 1, 'starttime' => self::NOW + 10], null];
        yield 'hidden with future endtime' => [['hidden' => 1, 'endtime' => self::NOW + 10], null];
        yield 'deleted with future starttime' => [['deleted' => 1, 'starttime' => self::NOW + 10], null];
    }

    #[Test]
    #[DataProvider('nextDueDateDataProvider')]
    public function nextDueDateIsTheNextFutureStarttimeOrEndtimeOfAnEnabledRecord(array $record, ?int $expected): void
    {
        self::assertSame($expected, $this->createTable()->getNextDueDate($record, self::NOW));
    }

    #[Test]
    public function tableWithOnlyAnEndtimeUsesTheEndtime(): void
    {
        $table = new ScheduledTable('tx_table', null, 'endtime', null, null, false, null, null);

        self::assertSame(self::NOW + 5, $table->getNextDueDate(['starttime' => self::NOW + 1, 'endtime' => self::NOW + 5], self::NOW));
        self::assertSame(['uid', 'endtime'], $table->getFieldsToSelect());
    }

    #[Test]
    public function workspaceVersionsAreNoLiveRecords(): void
    {
        self::assertTrue($this->createTable()->isLiveRecord(['t3ver_wsid' => 0]));
        self::assertFalse($this->createTable()->isLiveRecord(['t3ver_wsid' => 1]));
    }

    private function createTable(): ScheduledTable
    {
        return new ScheduledTable('tt_content', 'starttime', 'endtime', 'hidden', 'deleted', true, 'sys_language_uid', 'l18n_parent');
    }
}
