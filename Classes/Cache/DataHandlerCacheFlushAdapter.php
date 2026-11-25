<?php

declare(strict_types=1);

namespace B13\FutureCacheTags\Cache;

/*
 * This file is part of TYPO3 CMS-based extension "future_cache_tags" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * TYPO3 v13 and v14: asks DataHandler what it flushes when the record is saved.
 *
 * DataHandler::prepareCacheFlush() resolves the page tags (including
 * "TCEMAIN.clearCache_*"), "<table>", "<table>_<uid>", "TCEMAIN.clearCacheCmd"
 * and calls the "clearCachePostProc" hooks, but does not flush anything. It is
 * protected, so it is called through a closure bound to a fresh DataHandler.
 *
 * Unlike v15, DataHandler in v13/v14 does not flush "<table>_pid_<pid>",
 * the tag automatic cache tagging of Extbase repositories puts on list
 * pages. It is added here, so list pages are flushed as well.
 */
final class DataHandlerCacheFlushAdapter implements CacheFlushAdapterInterface
{
    public function __construct(
        private readonly CacheManager $cacheManager,
    ) {
    }

    public function resolveRecord(string $table, int $uid): FlushTarget
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->BE_USER = $this->createLiveBackendUser();
        $pageId = $table === 'pages' ? 0 : (int)(BackendUtility::getRecord($table, $uid, 'pid', '', false)['pid'] ?? 0);
        // The Page TSconfig of a page applies to the page itself, the one of the record page to the record
        $pageTsConfigPageId = $table === 'pages' ? $uid : $pageId;
        /** @var array{0: array<string, bool>, 1: list<string>} $result */
        $result = (function (string $table, int $uid, int $pageTsConfigPageId): array {
            /** @var DataHandler $this */
            return $this->prepareCacheFlush($table, $uid, $pageTsConfigPageId);
        })->call($dataHandler, $table, $uid, $pageTsConfigPageId);
        [$tagsToClear, $commands] = $result;

        $tags = array_map(strval(...), array_keys(array_filter($tagsToClear)));
        if ($pageId > 0 && in_array($table . '_' . $uid, $tags, true)) {
            $tags[] = $table . '_pid_' . $pageId;
        }
        $groups = [];
        foreach ($commands as $command) {
            $command = trim((string)$command);
            $lowerCaseCommand = strtolower($command);
            if ($lowerCaseCommand === 'pages' || $lowerCaseCommand === 'all') {
                $groups[] = $lowerCaseCommand;
            } elseif (MathUtility::canBeInterpretedAsInteger($command)) {
                $tags[] = 'pageId_' . (int)$command;
            } elseif (str_starts_with($lowerCaseCommand, 'cachetag:')) {
                $tags[] = substr($command, 9);
            }
        }
        return FlushTarget::create(['pages' => $tags], $groups);
    }

    public function flush(FlushTarget $target): void
    {
        if ($target->hasGroup('all')) {
            $this->cacheManager->flushCaches();
            return;
        }
        foreach ($target->getGroups() as $group) {
            $this->cacheManager->flushCachesInGroup($group);
        }
        foreach ($target->getTagsByGroup() as $group => $tags) {
            if (!$target->hasGroup($group)) {
                $this->cacheManager->flushCachesInGroupByTags($group, $tags);
            }
        }
    }

    /**
     * Scheduled records are always live records, so the cache is resolved
     * like in the live workspace, regardless of the current user.
     */
    private function createLiveBackendUser(): BackendUserAuthentication
    {
        $currentUser = $GLOBALS['BE_USER'] ?? null;
        $backendUser = $currentUser instanceof BackendUserAuthentication ? clone $currentUser : new BackendUserAuthentication();
        $backendUser->workspace = 0;
        return $backendUser;
    }
}
