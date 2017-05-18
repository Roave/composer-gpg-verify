<?php

declare(strict_types=1);

namespace RoaveTest\ComposerGpgVerify\Package;

use Composer\Package\PackageInterface;
use PHPUnit\Framework\TestCase;
use Roave\ComposerGpgVerify\Package\Git\GitSignatureCheck;
use Roave\ComposerGpgVerify\Package\GitPackage;
use Roave\ComposerGpgVerify\Package\UnknownPackageFormat;

/**
 * @covers \Roave\ComposerGpgVerify\Package\GitPackage
 */
final class GitPackageTest extends TestCase
{
    /**
     * @var PackageInterface
     */
    private $package;

    /**
     * @var string
     */
    private $packageName;

    protected function setUp() : void
    {
        parent::setUp();

        $this->packageName = uniqid('packageName', true);

        /* @var $package PackageInterface|\PHPUnit_Framework_MockObject_MockObject */
        $package = $this->createMock(PackageInterface::class);

        $package->expects(self::any())->method('getName')->willReturn($this->packageName);

        $this->package = $package;
    }

    public function testWillReportSuccessfulVerification() : void
    {
        $verification = GitPackage::fromPackageAndSignatureChecks(
            $this->package,
            $this->makeSignatureCheck(true, 'yadda')
        );

        self::assertInstanceOf(GitPackage::class, $verification);
        self::assertSame($this->packageName, $verification->packageName());
        self::assertTrue($verification->isVerified());
        self::assertSame(
            <<<REASON
The following GIT GPG signature checks passed for package "{$this->packageName}":

yadda
REASON
            ,
            $verification->printReason()
        );
    }

    private function makeSignatureCheck(bool $passed, string $reasoning) : GitSignatureCheck
    {
        /* @var $verification GitSignatureCheck|\PHPUnit_Framework_MockObject_MockObject */
        $verification = $this->createMock(GitSignatureCheck::class);

        $verification->expects(self::any())->method('asHumanReadableString')->willReturn($reasoning);
        $verification->expects(self::any())->method('canBeTrusted')->willReturn($passed);

        return $verification;
    }
}
