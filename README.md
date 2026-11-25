![CI](https://github.com/b13/future-cache-tags/actions/workflows/ci.yml/badge.svg)

# EXT:future_cache_tags - Flush page caches when scheduled records go live

## Introduction

A news record with a starttime of today 16:00 is shown on a news list whose page cache lives
for a day. At 16:00 nothing happens: the record only shows up when someone flushes the cache.
TYPO3 lowers the cache lifetime for records a page *did* render, but a query never tells which
records it did *not* return because their starttime is still in the future
([Forge #107564](https://forge.typo3.org/issues/107564)).

This extension remembers every record with a future `starttime` or `endtime`, together with
the cache tags TYPO3 would flush if an editor saved the record at that moment, and flushes
them when the time has come. It works for pages, content elements, news, events and every
other TCA table with `starttime` / `endtime`, and it follows workspace publishing.

## How it works

```
 DataHandler save / move / copy / delete        cache:future:rebuild
 workspace publish (AfterRecordPublishedEvent)  (all TCA tables with starttime / endtime)
                    │                                        │
                    └──────────────┬─────────────────────────┘
                                   ▼
          record live, enabled, starttime or endtime in the future?
                     │ yes                         │ no
                     ▼                             ▼
   resolve the cache tags DataHandler flushes   remove from schedule
   for the record (dry run), store them with
   the next due date in tx_futurecachetags_schedule
                                   │
           ┌───────────────────────┴───────────────────────┐
           ▼                                               ▼
  cache:future:worker (cron / Scheduler,      delayed Messenger message (optional),
  every minute)                               delivered at the due date
           └───────────────────────┬───────────────────────┘
                                   ▼
    all due records → tags merged → flushed once → every record synced again
    (a record with starttime and endtime is rescheduled for its endtime)
```

### What gets flushed

The same as when an editor saves the record, resolved without flushing anything:

| Tag                             | Covers                                                                          |
|---------------------------------|---------------------------------------------------------------------------------|
| `pageId_<pid>`                  | the page the record is on (for pages: the page, its siblings and its parent)    |
| `<table>_<uid>`                 | pages showing the record itself (detail views, automatic cache tagging)         |
| `<table>`                       | pages tagged with the whole table                                               |
| `<table>_pid_<pid>`             | lists of the storage folder (Extbase repositories with automatic cache tagging) |
| `TCEMAIN.clearCacheCmd`         | page ids, `cacheTag:<tag>`, `pages`, `all` of the record's page                 |
| `TCEMAIN.clearCache_*`          | `clearCache_pageGrandParent`, `clearCache_pageSiblingChildren`, `clearCache_disable` |

On TYPO3 v13 and v14 the tags come from `DataHandler::prepareCacheFlush()`, including the
`clearCachePostProc` hooks of other extensions. On v15 they come from `CacheFlusher::resolve()`,
including listeners of `BeforeRecordRelatedCachesAreFlushedEvent`. DataHandler in v13 and v14
does not flush `<table>_pid_<pid>` itself; this extension adds it, so list pages tagged by
Extbase are flushed on every version.

**Recommendation:** enable automatic cache tagging so that list pages carry the tags of the
records they list, even of records that are not visible yet:

```php
$GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['frontend.cache.autoTagging'] = true;
```

Without it only the record's own page, `<table>_<uid>` tags set by plugins and
`TCEMAIN.clearCacheCmd` are flushed. `TCEMAIN.clearCacheCmd = <list page id>` on the storage
folder is the classic way to get the same result.

### When a record is scheduled

| Record                                              | Schedule                                                     |
|-----------------------------------------------------|--------------------------------------------------------------|
| `starttime` in the future                           | due at `starttime`                                           |
| `endtime` in the future                             | due at `endtime`                                             |
| both in the future                                  | due at `starttime`, then rescheduled for `endtime`           |
| `starttime` / `endtime` changed                     | rescheduled; removed if both are in the past or empty        |
| hidden                                              | removed: it does not become visible at its starttime         |
| shown again                                         | scheduled again                                              |
| deleted / restored                                  | removed / scheduled again                                    |
| moved                                               | rescheduled with the tags of the new page                    |
| copied, localized                                   | the new record is scheduled (unless hidden at copy)          |
| translation                                         | scheduled on its own; synced when its default language record is saved (`l10n_mode=exclude` copies the starttime) |
| changed in a workspace                              | the live schedule stays as is: workspace versions are not published by time |
| published from a workspace                          | the live record is rescheduled or removed                    |
| written without DataHandler (import, SQL)           | found by `cache:future:rebuild`; a stale entry is flushed once and corrected by the worker |

## Installation & Requirements

The extension supports TYPO3 v13.4, v14.3 and v15 with PHP 8.2+ (PHP 8.5+ for TYPO3 v15).
EXT:workspaces is optional.

Use `composer req b13/future-cache-tags` or install it via TYPO3's Extension Manager from the
[TYPO3 Extension Repository](https://extensions.typo3.org/extension/future_cache_tags/) using the extension key
`future_cache_tags`, then set up the database table and fill the schedule:

```bash
vendor/bin/typo3 extension:setup
vendor/bin/typo3 cache:future:rebuild
```

`cache:future:rebuild` fills the schedule with the records that exist already. Run it once
after installing, and after imports that bypass DataHandler.

## Running the worker

Choose one, or both.

### Cron or Scheduler (default)

```cron
* * * * * /path/to/vendor/bin/typo3 cache:future:worker
```

Or add a "Execute console commands" task for `cache:future:worker` in the Scheduler with a
frequency of one minute. Caches are flushed at most a minute late. Overlapping runs are
prevented by a lock.

### Symfony Messenger

Enable `useMessenger` in the extension configuration (Admin Tools > Settings > Extension
Configuration > future_cache_tags). Every new due date dispatches a `ProcessDueRecordsMessage`
with a `DelayStamp`, routed to the transport set in `messengerTransport` (`doctrine` by default),
so it is delivered on time:

```bash
vendor/bin/typo3 messenger:consume doctrine
```

The transport must support delayed messages (doctrine, amqp, redis). The message only says
"process what is due now", so duplicate or late messages do no harm, and the cron worker can
run alongside as a safety net.

## Commands

| Command                                   | Description                                                                 |
|-------------------------------------------|-----------------------------------------------------------------------------|
| `cache:future:worker`                     | Flushes the caches of all due records. `--dry-run` lists records and tags; `-v` lists them while flushing. |
| `cache:future:rebuild`                    | Rebuilds the schedule from all tables. `--table=tx_news_domain_model_news` (repeatable) rebuilds single tables. |

## Development

All test suites run in containers (docker or podman) via `Build/Scripts/runTests.sh`:

```bash
# Install TYPO3 v13, v14 (default) or v15
Build/Scripts/runTests.sh -t 13 -s composerInstall

Build/Scripts/runTests.sh -s unit
Build/Scripts/runTests.sh -s functional            # mariadb
Build/Scripts/runTests.sh -s functional -d sqlite
Build/Scripts/runTests.sh -t 13 -s phpstan
Build/Scripts/runTests.sh -s cgl
```

The functional tests describe the behaviour of the table above: `Tests/Functional/DataHandlerTest.php`
(starttime, endtime, hidden, delete, move, copy, translations), `WorkerTest.php`, `RebuildTest.php`,
`WorkspaceTest.php` and `MessengerTest.php`. CI runs them on TYPO3 v13.4, v14.3 and v15 with
mariadb, postgres and sqlite, and once without EXT:workspaces.

## License

The extension is licensed under GPL v2+, same as the TYPO3 Core. For details see the LICENSE file in this repository.

## Open Issues

If you find an issue, feel free to create an issue on GitHub or a pull request.

### Credits

This extension was created by [Benni Mack](https://github.com/bmack) in 2026 for [b13 GmbH](https://b13.com),
to solve [Forge #107564](https://forge.typo3.org/issues/107564). The idea of flushing caches in the future was
inspired by [inspiredminds/contao-future-cache-invalidation](https://github.com/inspiredminds/contao-future-cache-invalidation),
which solves the same problem for Contao with delayed Messenger messages.

[Find more TYPO3 extensions we have developed](https://b13.com/useful-typo3-extensions-from-b13-to-you) that help us deliver value in client projects. As part of the way we work, we focus on testing and best practices to ensure long-term performance, reliability, and results in all our code.
