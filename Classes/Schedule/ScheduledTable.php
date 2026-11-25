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

/**
 * The fields of a TCA table that decide when a record becomes visible
 * or expires.
 */
final class ScheduledTable
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $startTimeField,
        public readonly ?string $endTimeField,
        public readonly ?string $disabledField,
        public readonly ?string $deletedField,
        public readonly bool $isWorkspaceAware,
        public readonly ?string $languageField,
        public readonly ?string $translationParentField,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getFieldsToSelect(): array
    {
        $fields = ['uid', $this->startTimeField, $this->endTimeField, $this->disabledField, $this->deletedField, $this->languageField];
        if ($this->isWorkspaceAware) {
            $fields[] = 't3ver_wsid';
        }
        return array_values(array_unique(array_filter($fields, static fn (?string $field): bool => $field !== null && $field !== '')));
    }

    /**
     * The next point in time after $now at which the record becomes visible
     * or expires, null if there is none or the record stays invisible anyway.
     *
     * A disabled ("hidden") record does not become visible at its starttime,
     * so it needs no flush. Enabling it is a DataHandler write, which flushes
     * the caches and schedules the record again.
     */
    public function getNextDueDate(array $record, int $now): ?int
    {
        if ($this->deletedField !== null && (bool)($record[$this->deletedField] ?? false)) {
            return null;
        }
        if ($this->disabledField !== null && (bool)($record[$this->disabledField] ?? false)) {
            return null;
        }
        $dueDates = [];
        foreach ([$this->startTimeField, $this->endTimeField] as $field) {
            $timestamp = $field !== null ? (int)($record[$field] ?? 0) : 0;
            if ($timestamp > $now) {
                $dueDates[] = $timestamp;
            }
        }
        return $dueDates === [] ? null : min($dueDates);
    }

    public function isLiveRecord(array $record): bool
    {
        return !$this->isWorkspaceAware || (int)($record['t3ver_wsid'] ?? 0) === 0;
    }

    public function isDefaultLanguageRecord(array $record): bool
    {
        return $this->languageField === null || (int)($record[$this->languageField] ?? 0) === 0;
    }
}
