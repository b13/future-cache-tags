<?php

declare(strict_types=1);

use B13\FutureCacheTags\Cache\CacheFlushAdapterInterface;
use B13\FutureCacheTags\Cache\CoreCacheFlusherAdapter;
use B13\FutureCacheTags\Cache\DataHandlerCacheFlushAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\Cache\Flush\CacheFlusher;

return static function (ContainerConfigurator $container, ContainerBuilder $containerBuilder): void {
    // TYPO3 v15 replaced the cache clearing of DataHandler by CacheFlusher.
    // The adapter for the other version is never registered, so its classes are never reflected.
    $adapter = class_exists(CacheFlusher::class) ? CoreCacheFlusherAdapter::class : DataHandlerCacheFlushAdapter::class;
    $containerBuilder->register($adapter, $adapter)->setAutowired(true);
    $containerBuilder->setAlias(CacheFlushAdapterInterface::class, $adapter);
};
