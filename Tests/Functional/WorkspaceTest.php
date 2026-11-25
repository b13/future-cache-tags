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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Workspace versions are not published by time: only live records are
 * scheduled, and publishing syncs the schedule of the live record.
 */
#[Group('workspaces')]
final class WorkspaceTest extends AbstractFunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['workspaces'];

    private int $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/workspace.csv');
        $this->now = time();
    }

    #[Test]
    public function futureStarttimeSetInAWorkspaceIsScheduledWhenItIsPublished(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Live']);
        $this->setWorkspaceId(1);

        $this->actionService->modifyRecord('tt_content', $uid, ['starttime' => $this->now + 3600]);
        self::assertNull($this->getScheduledRecord('tt_content', $uid));
        self::assertNull($this->getScheduledRecord('tt_content', $this->getWorkspaceVersionUid($uid)));

        $this->actionService->publishWorkspace(1);
        self::assertSame($this->now + 3600, $this->getDueDate('tt_content', $uid));
    }

    #[Test]
    public function liveScheduleStaysUntilAWorkspaceChangeIsPublished(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Live', 'starttime' => $this->now + 3600]);
        $this->setWorkspaceId(1);

        // Remove the starttime in the workspace only: live still goes online at the starttime
        $this->actionService->modifyRecord('tt_content', $uid, ['starttime' => 0]);
        self::assertSame($this->now + 3600, $this->getDueDate('tt_content', $uid));

        $this->actionService->publishWorkspace(1);
        self::assertNull($this->getScheduledRecord('tt_content', $uid));
    }

    #[Test]
    public function changedStarttimeIsRescheduledWhenItIsPublished(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Live', 'starttime' => $this->now + 3600]);
        $this->setWorkspaceId(1);

        $this->actionService->modifyRecord('tt_content', $uid, ['starttime' => $this->now + 86400]);
        self::assertSame($this->now + 3600, $this->getDueDate('tt_content', $uid));

        $this->actionService->publishWorkspace(1);
        self::assertSame($this->now + 86400, $this->getDueDate('tt_content', $uid));
    }

    #[Test]
    public function hidingInAWorkspaceRemovesTheScheduleWhenItIsPublished(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Live', 'endtime' => $this->now + 3600]);
        $this->setWorkspaceId(1);

        $this->actionService->modifyRecord('tt_content', $uid, ['hidden' => 1]);
        self::assertNotNull($this->getScheduledRecord('tt_content', $uid));

        $this->actionService->publishWorkspace(1);
        self::assertNull($this->getScheduledRecord('tt_content', $uid));
    }

    #[Test]
    public function newRecordCreatedInAWorkspaceIsScheduledWhenItIsPublished(): void
    {
        $this->setWorkspaceId(1);
        $uid = $this->createRecord('tt_content', 2, ['header' => 'New in workspace', 'starttime' => $this->now + 3600]);
        self::assertNull($this->getScheduledRecord('tt_content', $uid));

        $this->actionService->publishWorkspace(1);
        self::assertSame($this->now + 3600, $this->getDueDate('tt_content', $uid));
    }

    #[Test]
    public function deletionInAWorkspaceRemovesTheScheduleWhenItIsPublished(): void
    {
        $uid = $this->createRecord('tt_content', 2, ['header' => 'Live', 'starttime' => $this->now + 3600]);
        $this->setWorkspaceId(1);

        $this->actionService->deleteRecord('tt_content', $uid);
        self::assertNotNull($this->getScheduledRecord('tt_content', $uid));

        $this->actionService->publishWorkspace(1);
        self::assertNull($this->getScheduledRecord('tt_content', $uid));
    }

    private function getWorkspaceVersionUid(int $liveUid): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder
            ->select('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('t3ver_oid', $liveUid),
                $queryBuilder->expr()->eq('t3ver_wsid', 1),
            )
            ->executeQuery()
            ->fetchOne();
    }
}
