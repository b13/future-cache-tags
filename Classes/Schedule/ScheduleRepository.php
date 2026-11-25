<?php

declare(strict_types=1);

namespace B13\FutureCacheTags\Schedule;

/*
 * This file is part of TYPO3 CMS-based extension "future_cache_tags" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use B13\FutureCacheTags\Cache\FlushTarget;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * The schedule ("registry"): one row per record with a future starttime or
 * endtime, the next due date and the cache tags to flush then.
 */
final class ScheduleRepository
{
    public const TABLE = 'tx_futurecachetags_schedule';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    public function find(string $table, int $uid): ?ScheduledRecord
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('record_uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative();
        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @return list<ScheduledRecord> ordered by due date
     */
    public function findDue(int $now): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $result = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->lte('due_at', $queryBuilder->createNamedParameter($now, Connection::PARAM_INT)))
            ->orderBy('due_at')
            ->addOrderBy('uid')
            ->executeQuery();
        $records = [];
        while ($row = $result->fetchAssociative()) {
            $records[] = $this->map($row);
        }
        return $records;
    }

    /**
     * @return list<ScheduledRecord> ordered by due date
     */
    public function findAll(): array
    {
        $result = $this->connectionPool->getQueryBuilderForTable(self::TABLE)
            ->select('*')
            ->from(self::TABLE)
            ->orderBy('due_at')
            ->addOrderBy('uid')
            ->executeQuery();
        $records = [];
        while ($row = $result->fetchAssociative()) {
            $records[] = $this->map($row);
        }
        return $records;
    }

    public function findNextDueDate(): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $dueAt = $queryBuilder
            ->selectLiteral('MIN(' . $queryBuilder->quoteIdentifier('due_at') . ')')
            ->from(self::TABLE)
            ->executeQuery()
            ->fetchOne();
        return $dueAt === null || $dueAt === false ? null : (int)$dueAt;
    }

    /**
     * @return int|null The previous due date of the record, null if it was not scheduled
     */
    public function save(ScheduledRecord $record, int $now): ?int
    {
        $previous = $this->find($record->table, $record->uid);
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $data = [
            'due_at' => $record->dueAt,
            'tags' => json_encode($record->flushTarget->getTagsByGroup(), JSON_THROW_ON_ERROR),
            'cache_groups' => implode(',', $record->flushTarget->getGroups()),
            'updated_at' => $now,
        ];
        if ($previous === null) {
            $connection->insert(self::TABLE, ['tablename' => $record->table, 'record_uid' => $record->uid, ...$data]);
            return null;
        }
        $connection->update(self::TABLE, $data, ['tablename' => $record->table, 'record_uid' => $record->uid]);
        return $previous->dueAt;
    }

    public function remove(string $table, int $uid): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)
            ->delete(self::TABLE, ['tablename' => $table, 'record_uid' => $uid]);
    }

    public function removeAllOfTable(string $table): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)
            ->delete(self::TABLE, ['tablename' => $table]);
    }

    public function removeAll(): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)->truncate(self::TABLE);
    }

    private function map(array $row): ScheduledRecord
    {
        $tags = json_decode((string)($row['tags'] ?? ''), true);
        return new ScheduledRecord(
            (string)$row['tablename'],
            (int)$row['record_uid'],
            (int)$row['due_at'],
            FlushTarget::create(
                is_array($tags) ? $tags : [],
                explode(',', (string)($row['cache_groups'] ?? ''))
            ),
        );
    }
}
