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

/**
 * The caches to flush when a scheduled record goes live or expires:
 * cache tags per cache group, and complete cache groups ("pages", "all")
 * as requested by "TCEMAIN.clearCacheCmd".
 */
final class FlushTarget
{
    /**
     * @param array<string, list<string>> $tags cache group => tags
     * @param list<string> $groups
     */
    private function __construct(
        private readonly array $tags,
        private readonly array $groups,
    ) {
    }

    /**
     * @param array<string, iterable<string>> $tags cache group => tags
     * @param iterable<string> $groups
     */
    public static function create(array $tags = [], iterable $groups = []): self
    {
        $normalizedTags = [];
        foreach ($tags as $group => $groupTags) {
            $groupTags = array_filter(array_map(strval(...), [...$groupTags]), static fn (string $tag): bool => $tag !== '');
            if ($groupTags === []) {
                continue;
            }
            $groupTags = array_values(array_unique($groupTags));
            sort($groupTags);
            $normalizedTags[(string)$group] = $groupTags;
        }
        ksort($normalizedTags);
        $normalizedGroups = array_values(array_unique(array_filter(array_map(strval(...), [...$groups]), static fn (string $group): bool => $group !== '')));
        sort($normalizedGroups);
        return new self($normalizedTags, $normalizedGroups);
    }

    public static function forTags(string ...$tags): self
    {
        return self::create(['pages' => $tags]);
    }

    public function merge(self $other): self
    {
        $tags = $this->tags;
        foreach ($other->tags as $group => $groupTags) {
            $tags[$group] = [...($tags[$group] ?? []), ...$groupTags];
        }
        return self::create($tags, [...$this->groups, ...$other->groups]);
    }

    /**
     * @return array<string, list<string>> cache group => tags
     */
    public function getTagsByGroup(): array
    {
        return $this->tags;
    }

    /**
     * @return list<string>
     */
    public function getTags(string $group = 'pages'): array
    {
        return $this->tags[$group] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function hasGroup(string $group): bool
    {
        return in_array($group, $this->groups, true);
    }

    public function isEmpty(): bool
    {
        return $this->tags === [] && $this->groups === [];
    }
}
