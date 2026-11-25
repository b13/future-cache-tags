<?php

declare(strict_types=1);

namespace B13\FutureCacheTags\Configuration;

/*
 * This file is part of TYPO3 CMS-based extension "future_cache_tags" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class ExtensionSettings
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function isMessengerEnabled(): bool
    {
        return (bool)$this->get('useMessenger');
    }

    private function get(string $path): mixed
    {
        try {
            return $this->extensionConfiguration->get('future_cache_tags', $path);
        } catch (\Exception) {
            return null;
        }
    }
}
