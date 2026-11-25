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

use B13\FutureCacheTags\Cache\FlushTarget;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class FlushTargetTest extends UnitTestCase
{
    #[Test]
    public function mergeRemovesDuplicateTagsAndGroups(): void
    {
        $first = FlushTarget::create(['pages' => ['tt_content', 'pageId_1', 'tt_content_pid_2']], ['pages']);
        $second = FlushTarget::create(['pages' => ['tt_content', 'tt_content_pid_2', 'pageId_3'], 'other' => ['x']], ['pages', 'all']);

        $merged = $first->merge($second);

        self::assertSame(['pageId_1', 'pageId_3', 'tt_content', 'tt_content_pid_2'], $merged->getTags());
        self::assertSame(['x'], $merged->getTags('other'));
        self::assertSame(['all', 'pages'], $merged->getGroups());
    }

    #[Test]
    public function emptyTagsAndGroupsAreIgnored(): void
    {
        $target = FlushTarget::create(['pages' => ['', 'a'], 'empty' => ['']], ['', 'pages']);

        self::assertSame(['pages' => ['a']], $target->getTagsByGroup());
        self::assertSame(['pages'], $target->getGroups());
        self::assertTrue(FlushTarget::create(['pages' => []], [''])->isEmpty());
    }
}
