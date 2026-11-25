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

use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * All TCA tables with a starttime and / or endtime, read from the Schema API.
 */
final class ScheduledTableRegistry
{
    /** @var array<string, ScheduledTable>|null */
    private ?array $tables = null;

    public function __construct(
        private readonly TcaSchemaFactory $tcaSchemaFactory,
    ) {
    }

    /**
     * @return array<string, ScheduledTable>
     */
    public function getAll(): array
    {
        if ($this->tables === null) {
            $this->tables = [];
            foreach ($this->tcaSchemaFactory->all() as $schema) {
                if ($schema->hasCapability(TcaSchemaCapability::RestrictionStartTime)
                    || $schema->hasCapability(TcaSchemaCapability::RestrictionEndTime)
                ) {
                    $this->tables[$schema->getName()] = $this->createScheduledTable($schema);
                }
            }
            ksort($this->tables);
        }
        return $this->tables;
    }

    public function get(string $table): ?ScheduledTable
    {
        return $this->getAll()[$table] ?? null;
    }

    public function has(string $table): bool
    {
        return $this->get($table) !== null;
    }

    private function createScheduledTable(TcaSchema $schema): ScheduledTable
    {
        $languageField = null;
        $translationParentField = null;
        if ($schema->isLanguageAware()) {
            $languageCapability = $schema->getCapability(TcaSchemaCapability::Language);
            $languageField = $languageCapability->getLanguageField()->getName();
            $translationParentField = $languageCapability->getTranslationOriginPointerField()->getName();
        }
        return new ScheduledTable(
            name: $schema->getName(),
            startTimeField: $this->getFieldName($schema, TcaSchemaCapability::RestrictionStartTime),
            endTimeField: $this->getFieldName($schema, TcaSchemaCapability::RestrictionEndTime),
            disabledField: $this->getFieldName($schema, TcaSchemaCapability::RestrictionDisabledField),
            deletedField: $this->getFieldName($schema, TcaSchemaCapability::SoftDelete),
            isWorkspaceAware: $schema->isWorkspaceAware(),
            languageField: $languageField,
            translationParentField: $translationParentField,
        );
    }

    private function getFieldName(TcaSchema $schema, TcaSchemaCapability $capability): ?string
    {
        if (!$schema->hasCapability($capability)) {
            return null;
        }
        $fieldName = $schema->getCapability($capability)->getFieldName();
        return $fieldName !== '' ? $fieldName : null;
    }
}
