<?php

declare(strict_types=1);

namespace RoaveTest\ComposerGpgVerify\Package\Git;

use Composer\Package\PackageInterface;
use PHPUnit\Framework\TestCase;
use Roave\ComposerGpgVerify\Package\Git\GitSignatureCheck;

/**
 * @covers \Roave\ComposerGpgVerify\Package\Git\GitSignatureCheck
 */
final class GitSignatureCheckTest extends TestCase
{
    /**
     * @dataProvider commitDataProvider
     */
    public function testFromGitCommitCheck(
        PackageInterface $package,
        string $command,
        int $exitCode,
        string $commandOutput,
        bool $expectedTrust,
        string $expectedHumanReadableStringFormat
    ) : void {
        $check = GitSignatureCheck::fromGitCommitCheck($package, $command, $exitCode, $commandOutput);

        self::assertSame($expectedTrust, $check->canBeTrusted());
        self::assertStringMatchesFormat($expectedHumanReadableStringFormat, $check->asHumanReadableString());
    }

    public function commitDataProvider() : array
    {
        $packageName = uniqid('packageName', true);
        $package = $this->createMock(PackageInterface::class);

        $package->expects(self::any())->method('getName')->willReturn($packageName);

        return [
            'empty' => [
                $package,
                '',
                0,
                '',
                false,
                '',
            ],
        ];
    }
}
