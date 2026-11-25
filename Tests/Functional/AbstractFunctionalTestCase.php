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

use B13\FutureCacheTags\Schedule\DueRecordProcessor;
use B13\FutureCacheTags\Schedule\ProcessingResult;
use B13\FutureCacheTags\Schedule\ScheduledRecord;
use B13\FutureCacheTags\Schedule\ScheduleRepository;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\ActionService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['b13/future-cache-tags'];

    // The testing framework uses a NullBackend for the page cache
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'pages' => [
                        'backend' => Typo3DatabaseBackend::class,
                    ],
                ],
            ],
        ],
    ];

    protected ActionService $actionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/base.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $this->actionService = new ActionService();
    }

    protected function setWorkspaceId(int $workspaceId): void
    {
        $GLOBALS['BE_USER']->workspace = $workspaceId;
        $this->get(Context::class)->setAspect('workspace', new WorkspaceAspect($workspaceId));
    }

    /**
     * @return int uid of the new record
     */
    protected function createRecord(string $table, int $pageId, array $data): int
    {
        $newIds = $this->actionService->createNewRecord($table, $pageId, $data);
        return (int)$newIds[$table][0];
    }

    protected function getScheduledRecord(string $table, int $uid): ?ScheduledRecord
    {
        return $this->get(ScheduleRepository::class)->find($table, $uid);
    }

    protected function getDueDate(string $table, int $uid): ?int
    {
        return $this->getScheduledRecord($table, $uid)?->dueAt;
    }

    /**
     * @return list<string> The cache tags of the "pages" group, empty if the record is not scheduled
     */
    protected function getScheduledTags(string $table, int $uid): array
    {
        return $this->getScheduledRecord($table, $uid)?->flushTarget->getTags() ?? [];
    }

    protected function process(int $now, bool $dryRun = false): ProcessingResult
    {
        return $this->get(DueRecordProcessor::class)->process($now, $dryRun);
    }

    protected function getPageCache(): FrontendInterface
    {
        return $this->get(CacheManager::class)->getCache('pages');
    }
}
