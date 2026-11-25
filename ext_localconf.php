<?php

declare(strict_types=1);

use B13\FutureCacheTags\Hook\DataHandlerHook;
use B13\FutureCacheTags\Messenger\ProcessDueRecordsMessage;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['future_cache_tags'] = DataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['future_cache_tags'] = DataHandlerHook::class;

$futureCacheTagsConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['future_cache_tags'] ?? [];
if ((bool)($futureCacheTagsConfiguration['useMessenger'] ?? false)) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][ProcessDueRecordsMessage::class]
        ??= (string)(($futureCacheTagsConfiguration['messengerTransport'] ?? '') ?: 'doctrine');
}
unset($futureCacheTagsConfiguration);
