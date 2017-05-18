<?php

declare(strict_types=1);

namespace RoaveTest\ComposerGpgVerify\Exception;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Roave\ComposerGpgVerify\Exception\PreferredInstallIsNotSource;

/**
 * @covers \Roave\ComposerGpgVerify\Exception\PreferredInstallIsNotSource
 */
final class PreferredInstallIsNotSourceTest extends TestCase
{
    public function testFromStringPreferredInstall() : void
    {
        $exception = PreferredInstallIsNotSource::fromPreferredInstall('foo');

        self::assertInstanceOf(PreferredInstallIsNotSource::class, $exception);
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame(
            <<<'EXPECTED'
The detected preferred install required for the git verification to work correctly is "source", but your composer.json configuration reported "foo".
Please edit your composer.json to enforce "source" installation as described at https://getcomposer.org/doc/06-config.md#preferred-install
EXPECTED
            ,
            $exception->getMessage()
        );
    }
    public function testFromArrayPreferredInstall() : void
    {
        $exception = PreferredInstallIsNotSource::fromPreferredInstall(['foo' => 'bar', 'baz' => 'tab']);

        self::assertInstanceOf(PreferredInstallIsNotSource::class, $exception);
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame(
            <<<'EXPECTED'
The detected preferred install required for the git verification to work correctly is "source", but your composer.json configuration reported {"foo":"bar","baz":"tab"}.
Please edit your composer.json to enforce "source" installation as described at https://getcomposer.org/doc/06-config.md#preferred-install
EXPECTED
            ,
            $exception->getMessage()
        );
    }
}
