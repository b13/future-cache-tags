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

use B13\FutureCacheTags\Configuration\ExtensionSettings;
use B13\FutureCacheTags\Messenger\DueDateNotifier;
use B13\FutureCacheTags\Messenger\ProcessDueRecordsMessage;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class DueDateNotifierTest extends UnitTestCase
{
    #[Test]
    public function nothingIsDispatchedWhenMessengerIsDisabled(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        (new DueDateNotifier($this->createSettings(false), $messageBus, new NullLogger()))->notify(time() + 60);
    }

    #[Test]
    public function messageIsDelayedUntilTheDueDate(): void
    {
        $dueAt = time() + 3600;
        $dispatched = [];
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $message, array $stamps) use (&$dispatched): Envelope {
                $dispatched = [$message, $stamps];
                return new Envelope($message, $stamps);
            }
        );

        (new DueDateNotifier($this->createSettings(true), $messageBus, new NullLogger()))->notify($dueAt);

        [$message, $stamps] = $dispatched;
        self::assertInstanceOf(ProcessDueRecordsMessage::class, $message);
        self::assertSame($dueAt, $message->dueAt);
        self::assertInstanceOf(DelayStamp::class, $stamps[0]);
        // The clock may have moved on by a second
        self::assertEqualsWithDelta(3600000, $stamps[0]->getDelay(), 1000);
    }

    #[Test]
    public function failingTransportDoesNotBreakSavingRecords(): void
    {
        $messageBus = self::createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willThrowException(new \RuntimeException('Transport is down', 1764086402));

        (new DueDateNotifier($this->createSettings(true), $messageBus, new NullLogger()))->notify(time() + 60);
        $this->addToAssertionCount(1);
    }

    private function createSettings(bool $useMessenger): ExtensionSettings
    {
        $settings = self::createStub(ExtensionSettings::class);
        $settings->method('isMessengerEnabled')->willReturn($useMessenger);
        return $settings;
    }
}
