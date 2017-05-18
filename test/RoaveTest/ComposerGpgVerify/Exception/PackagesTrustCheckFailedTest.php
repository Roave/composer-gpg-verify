<?php

declare(strict_types=1);

namespace RoaveTest\ComposerGpgVerify\Exception;

use LogicException;
use PHPUnit\Framework\TestCase;
use Roave\ComposerGpgVerify\Exception\PackagesTrustCheckFailed;
use Roave\ComposerGpgVerify\Package\PackageVerification;

/**
 * @covers \Roave\ComposerGpgVerify\Exception\PackagesTrustCheckFailed
 */
final class PackagesTrustCheckFailedTest extends TestCase
{
    public function testFromFailedPackageVerificationsWithSinglePackage() : void
    {
        /* @var $verification PackageVerification|\PHPUnit_Framework_MockObject_MockObject */
        $verification = $this->createMock(PackageVerification::class);

        $verification->expects(self::any())->method('printReason')->willReturn('foo');

        $exception = PackagesTrustCheckFailed::fromFailedPackageVerifications($verification);

        self::assertInstanceOf(PackagesTrustCheckFailed::class, $exception);
        self::assertInstanceOf(LogicException::class, $exception);
        self::assertSame(
            <<<'EXPECTED'
The following packages need to be signed and verified, or added to exclusions: 
foo
EXPECTED
            ,
            $exception->getMessage()
        );
    }

    public function testFromFailedPackageVerificationsWithMultiplePackages() : void
    {
        /* @var $verification1 PackageVerification|\PHPUnit_Framework_MockObject_MockObject */
        $verification1 = $this->createMock(PackageVerification::class);
        /* @var $verification2 PackageVerification|\PHPUnit_Framework_MockObject_MockObject */
        $verification2 = $this->createMock(PackageVerification::class);
        /* @var $verification3 PackageVerification|\PHPUnit_Framework_MockObject_MockObject */
        $verification3 = $this->createMock(PackageVerification::class);

        $verification1->expects(self::any())->method('printReason')->willReturn('foo');
        $verification2->expects(self::any())->method('printReason')->willReturn('bar');
        $verification3->expects(self::any())->method('printReason')->willReturn('baz');

        $exception = PackagesTrustCheckFailed::fromFailedPackageVerifications(
            $verification1,
            $verification2,
            $verification3
        );

        self::assertInstanceOf(PackagesTrustCheckFailed::class, $exception);
        self::assertInstanceOf(LogicException::class, $exception);
        self::assertSame(
            <<<'EXPECTED'
The following packages need to be signed and verified, or added to exclusions: 
foo

bar

baz
EXPECTED
            ,
            $exception->getMessage()
        );
    }
}
