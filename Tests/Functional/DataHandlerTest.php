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

use PHPUnit\Framework\Attributes\Test;

/**
 * How the schedule follows changes made with DataHandler.
 */
final class DataHandlerTest extends AbstractFunctionalTestCase
{
    private int $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = time();
    }

    #[Test]
    public function recordWithFutureStarttimeIsScheduledWithTheCacheTagsDataHandlerFlushes(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Scheduled', 'starttime' => $this->now + 3600]);

        $scheduledRecord = $this->getScheduledRecord('tt_content', $uid);
        self::assertNotNull($scheduledRecord);
        self::assertSame($this->now + 3600, $scheduledRecord->dueAt);
        $tags = $scheduledRecord->flushTarget->getTags();
        // The page of the record, the record and its table
        self::assertContains('pageId_2', $tags);
        self::assertContains('tt_content', $tags);
        self::assertContains('tt_content_' . $uid, $tags);
        // Lists of the storage page (automatic cache tagging of Extbase repositories)
        self::assertContains('tt_content_pid_2', $tags);
        // TCEMAIN.clearCacheCmd = 3, cacheTag:news_list of page 2
        self::assertContains('pageId_3', $tags);
        self::assertContains('news_list', $tags);
        self::assertSame([], $scheduledRecord->flushTarget->getGroups());
    }

    #[Test]
    public function recordWithFutureEndtimeIsScheduledAtItsEndtime(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Expires', 'endtime' => $this->now + 7200]);

        self::assertSame($this->now + 7200, $this->getDueDate('tt_content', $uid));
    }

    #[Test]
    public function recordWithoutFutureStarttimeOrEndtimeIsNotScheduled(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Visible', 'starttime' => $this->now - 3600, 'endtime' => $this->now - 60]);

        self::assertNull($this->getScheduledRecord('tt_content', $uid));
    }

    #[Test]
    public function recordWithStarttimeAndEndtimeIsScheduledForBothOneAfterTheOther(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Both', 'starttime' => $this->now + 3600, 'endtime' => $this->now + 7200]);
        self::assertSame($this->now + 3600, $this->getDueDate('tt_content', $uid));

        $this->process($this->now + 3600);
        self::assertSame($this->now + 7200, $this->getDueDate('tt_content', $uid));

        $this->process($this->now + 7200);
        self::assertNull($this->getScheduledRecord('tt_content', $uid));
    }

    #[Test]
    public function changingTheStarttimeToAnotherFutureDateReschedulesTheRecord(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Scheduled', 'starttime' => $this->now + 3600]);

        $this->actionService->modifyRecord('tt_content', $uid, ['starttime' => $this->now + 86400]);

        self::assertSame($this->now + 86400, $this->getDueDate('tt_content', $uid));
    }

    #[Test]
    public function changingTheStarttimeToThePastRemovesTheRecordFromTheSchedule(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Scheduled', 'starttime' => $this->now + 3600]);

        $this->actionService->modifyRecord('tt_content', $uid, ['starttime' => $this->now - 60]);

        self::assertNull($this->getScheduledRecord('tt_content', $uid));
    }

    #[Test]
    public function removingTheEndtimeRemovesTheRecordFromTheSchedule(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Expires', 'endtime' => $this->now + 3600]);

        $this->actionService->modifyRecord('tt_content', $uid, ['endtime' => 0]);

        self::assertNull($this->getScheduledRecord('tt_content', $uid));
    }

    #[Test]
    public function addingAFutureStarttimeToAVisibleRecordSchedulesIt(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Visible']);
        self::assertNull($this->getScheduledRecord('tt_content', $uid));

        $this->actionService->modifyRecord('tt_content', $uid, ['starttime' => $this->now + 3600]);

        self::assertSame($this->now + 3600, $this->getDueDate('tt_content', $uid));
    }

    #[Test]
    public function hidingAScheduledRecordRemovesItAndShowingItSchedulesItAgain(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Scheduled', 'starttime' => $this->now + 3600]);

        // A hidden record does not become visible at its starttime, nothing to flush then
        $this->actionService->modifyRecord('tt_content', $uid, ['hidden' => 1]);
        self::assertNull($this->getScheduledRecord('tt_content', $uid));

        $this->actionService->modifyRecord('tt_content', $uid, ['hidden' => 0]);
        self::assertSame($this->now + 3600, $this->getDueDate('tt_content', $uid));
    }

    #[Test]
    public function hiddenRecordWithFutureStarttimeIsNotScheduled(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Hidden', 'hidden' => 1, 'starttime' => $this->now + 3600]);

        self::assertNull($this->getScheduledRecord('tt_content', $uid));
    }

    #[Test]
    public function deletingAScheduledRecordRemovesItAndRestoringItSchedulesItAgain(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Scheduled', 'starttime' => $this->now + 3600]);

        $this->actionService->deleteRecord('tt_content', $uid);
        self::assertNull($this->getScheduledRecord('tt_content', $uid));

        $this->actionService->invoke([], ['tt_content' => [$uid => ['undelete' => 1]]]);
        self::assertSame($this->now + 3600, $this->getDueDate('tt_content', $uid));
    }

    #[Test]
    public function movingAScheduledRecordUpdatesItsCacheTags(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Scheduled', 'starttime' => $this->now + 3600]);

        $this->actionService->moveRecord('tt_content', $uid, 4);

        $tags = $this->getScheduledTags('tt_content', $uid);
        self::assertContains('pageId_4', $tags);
        self::assertContains('tt_content_pid_4', $tags);
        self::assertNotContains('tt_content_pid_2', $tags);
    }

    #[Test]
    public function copyOfAScheduledRecordIsScheduledOnceItIsShown(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Scheduled', 'starttime' => $this->now + 3600]);

        $newIds = $this->actionService->copyRecord('tt_content', $uid, 4);
        $copyUid = (int)$newIds['tt_content'][$uid];
        // tt_content is hidden at copy ("hideAtCopy")
        self::assertNull($this->getScheduledRecord('tt_content', $copyUid));

        $this->actionService->modifyRecord('tt_content', $copyUid, ['hidden' => 0]);
        self::assertSame($this->now + 3600, $this->getDueDate('tt_content', $copyUid));
        self::assertContains('pageId_4', $this->getScheduledTags('tt_content', $copyUid));
    }

    #[Test]
    public function pageWithFutureStarttimeIsScheduledWithItsParentPage(): void
    {
        $uid = $this->createRecord('pages', 1, ['title' => 'Future page', 'hidden' => 0, 'starttime' => $this->now + 3600]);

        self::assertSame($this->now + 3600, $this->getDueDate('pages', $uid));
        $tags = $this->getScheduledTags('pages', $uid);
        // The page itself, its siblings and its parent page (menus)
        self::assertContains('pageId_' . $uid, $tags);
        self::assertContains('pageId_1', $tags);
        self::assertContains('pageId_4', $tags);
        self::assertContains('pages_' . $uid, $tags);
    }

    #[Test]
    public function starttimeSyncedToTranslationsSchedulesThemAsWell(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->insert('tt_content', ['uid' => 200, 'pid' => 2, 'header' => 'Default', 'sys_language_uid' => 0]);
        $connection->insert('tt_content', ['uid' => 201, 'pid' => 2, 'header' => 'Translation', 'sys_language_uid' => 1, 'l18n_parent' => 200]);

        // "l10n_mode=exclude": DataHandler copies the starttime to the translation
        $this->actionService->modifyRecord('tt_content', 200, ['starttime' => $this->now + 3600]);

        self::assertSame($this->now + 3600, $this->getDueDate('tt_content', 200));
        self::assertSame($this->now + 3600, $this->getDueDate('tt_content', 201));
        self::assertContains('tt_content_201', $this->getScheduledTags('tt_content', 201));
    }
}
