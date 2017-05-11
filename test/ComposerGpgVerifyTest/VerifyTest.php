<?php

declare(strict_types=1);

namespace ComposerGpgVerifyTest;

use Composer\Script\ScriptEvents;
use ComposerGpgVerify\Verify;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ComposerGpgVerify\Verify
 */
final class VerifyTest extends TestCase
{
    public function testWillDisallowPluginInstantiation() : void
    {
        $this->expectException(\Throwable::class);

        new Verify();
    }

    public function testWillRetrieveSubscribedEvents() : void
    {
        $events = Verify::getSubscribedEvents();

        self::assertNotEmpty($events);

        $availableEvents = (new \ReflectionClass(ScriptEvents::class))->getConstants();

        foreach ($events as $eventName => $callback) {
            self::assertContains($eventName, $availableEvents);
            self::assertInternalType('string', $callback);
            self::assertInternalType('callable', [Verify::class, $callback]);
        }
    }
}
