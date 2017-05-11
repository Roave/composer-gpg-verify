<?php

declare(strict_types=1);

namespace ComposerGpgVerifyTest;

use Composer\Composer;
use Composer\Config;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use ComposerGpgVerify\Verify;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ComposerGpgVerify\Verify
 */
final class VerifyTest extends TestCase
{
    /**
     * @var Event|\PHPUnit_Framework_MockObject_MockObject
     */
    private $event;

    /**
     * @var Composer|\PHPUnit_Framework_MockObject_MockObject
     */
    private $composer;

    /**
     * @var Config|\PHPUnit_Framework_MockObject_MockObject
     */
    private $config;

    protected function setUp() : void
    {
        parent::setUp();

        $this->event    = $this->createMock(Event::class);
        $this->composer = $this->createMock(Composer::class);
        $this->config   = $this->createMock(Config::class);

        $this->event->expects(self::any())->method('getComposer')->willReturn($this->composer);
        $this->composer->expects(self::any())->method('getConfig')->willReturn($this->config);
    }

    public function testWillDisallowPluginInstantiation() : void
    {
        $this->expectException(\Throwable::class);

        new Verify();
    }

    public function testWillDisallowInstallationOnNonSourceInstall() : void
    {
        $this
            ->config
            ->expects(self::any())
            ->method('get')
            ->with('preferred-install')
            ->willReturn('foo');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected installation "preferred-install" to be "source", found "foo" instead');

        Verify::verify($this->event);
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
