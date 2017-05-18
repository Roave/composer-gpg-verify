<?php

declare(strict_types=1);

namespace RoaveTest\ComposerGpgVerify\Exception;

use LogicException;
use PHPUnit\Framework\TestCase;
use Roave\ComposerGpgVerify\Exception\PackagesTrustCheckFailed;
use Roave\ComposerGpgVerify\Package\PackageVerification;

/**
 * @covers \Roave\ComposerGpgVerify\Verify
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
}
