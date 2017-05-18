<?php

declare(strict_types=1);

namespace RoaveTest\ComposerGpgVerify\Package;

use Composer\Package\PackageInterface;
use PHPUnit\Framework\TestCase;
use Roave\ComposerGpgVerify\Package\UnknownPackageFormat;

/**
 * @covers \Roave\ComposerGpgVerify\Package\UnknownPackageFormat
 */
final class UnknownPackageFormatTest extends TestCase
{
    public function testVerification() : void
    {
        /* @var $package PackageInterface|\PHPUnit_Framework_MockObject_MockObject */
        $package     = $this->createMock(PackageInterface::class);
        $packageName = uniqid('packageName', true);

        $package->expects(self::any())->method('getName')->willReturn($packageName);

        $verification = UnknownPackageFormat::fromNonGitPackage($package);

        self::assertInstanceOf(UnknownPackageFormat::class, $verification);
        self::assertSame($packageName, $verification->packageName());
        self::assertFalse($verification->isVerified(), 'Unknown package types cannot be verified');
        self::assertSame(
            'Package "'
            . $packageName
            . '" is in a format that Roave\ComposerGpgVerify cannot verify:'
            . ' try forcing it to be downloaded as GIT repository',
            $verification->printReason()
        );
    }
}
