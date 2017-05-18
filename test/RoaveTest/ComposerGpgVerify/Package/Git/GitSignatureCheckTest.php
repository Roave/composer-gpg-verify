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
                <<<'READABLE'
[NOT SIGNED] [NOT VERIFIED]    
Command: 
Exit code: 0
Output: 
READABLE
            ],
            'not signed' => [
                $package,
                'git verify-commit --verbose HEAD',
                1,
                '',
                false,
                <<<'READABLE'
[NOT SIGNED] [NOT VERIFIED]    
Command: git verify-commit --verbose HEAD
Exit code: 1
Output: 
READABLE
            ],
            'signed, no key' => [
                $package,
                'git verify-commit --verbose HEAD',
                1,
                <<<'OUTPUT'
tree 4b825dc642cb6eb9a060e54bf8d69288fbee4904
author Mr. Magoo <magoo@example.com> 1495040303 +0200
committer Mr. Magoo <magoo@example.com> 1495040303 +0200

signed commit
gpg: Signature made Mi 17 Mai 2017 18:58:23 CEST
gpg:                using RSA key ECFE352F73409A6E
gpg: Can't check signature: No public key
OUTPUT
                ,
                false,
                <<<'READABLE'
[SIGNED] [NOT VERIFIED] Commit #4b825dc642cb6eb9a060e54bf8d69288fbee4904   (Key ECFE352F73409A6E)
Command: git verify-commit --verbose HEAD
Exit code: 1
Output: tree 4b825dc642cb6eb9a060e54bf8d69288fbee4904
author Mr. Magoo <magoo@example.com> 1495040303 +0200
committer Mr. Magoo <magoo@example.com> 1495040303 +0200

signed commit
gpg: Signature made Mi 17 Mai 2017 18:58:23 CEST
gpg:                using RSA key ECFE352F73409A6E
gpg: Can't check signature: No public key
READABLE
            ],
            'signed, key not trusted' => [
                $package,
                'git verify-commit --verbose HEAD',
                0,
                <<<'OUTPUT'
tree 4b825dc642cb6eb9a060e54bf8d69288fbee4904
author Mr. Magoo <magoo@example.com> 1495041438 +0200
committer Mr. Magoo <magoo@example.com> 1495041438 +0200

signed commit
gpg: Signature made Mi 17 Mai 2017 19:17:18 CEST
gpg:                using RSA key 3CD2E574BC4207C7
gpg: Good signature from "Mr. Magoo <magoo@example.com>" [unknown]
gpg: WARNING: This key is not certified with a trusted signature!
gpg:          There is no indication that the signature belongs to the owner.
Primary key fingerprint: AA0E 63DC BC06 F864 F53E  F630 3CD2 E574 BC42 07C7
OUTPUT
                ,
                false,
                <<<'READABLE'
[SIGNED] [NOT VERIFIED] Commit #4b825dc642cb6eb9a060e54bf8d69288fbee4904  By "Mr. Magoo <magoo@example.com>" (Key 3CD2E574BC4207C7)
Command: git verify-commit --verbose HEAD
Exit code: 0
Output: tree 4b825dc642cb6eb9a060e54bf8d69288fbee4904
author Mr. Magoo <magoo@example.com> 1495041438 +0200
committer Mr. Magoo <magoo@example.com> 1495041438 +0200

signed commit
gpg: Signature made Mi 17 Mai 2017 19:17:18 CEST
gpg:                using RSA key 3CD2E574BC4207C7
gpg: Good signature from "Mr. Magoo <magoo@example.com>" [unknown]
gpg: WARNING: This key is not certified with a trusted signature!
gpg:          There is no indication that the signature belongs to the owner.
Primary key fingerprint: AA0E 63DC BC06 F864 F53E  F630 3CD2 E574 BC42 07C7
READABLE
            ],
            [
                $package,
                'git verify-commit --verbose HEAD',
                0,
                <<<'OUTPUT'
tree 4b825dc642cb6eb9a060e54bf8d69288fbee4904
author Mr. Magoo <magoo@example.com> 1495041602 +0200
committer Mr. Magoo <magoo@example.com> 1495041602 +0200

signed commit
gpg: Signature made Mi 17 Mai 2017 19:20:02 CEST
gpg:                using RSA key 4889C20D148231DC
gpg: Good signature from "Mr. Magoo <magoo@example.com>" [full]
OUTPUT
                ,
                true,
                <<<'READABLE'
[SIGNED] [VERIFIED] Commit #4b825dc642cb6eb9a060e54bf8d69288fbee4904  By "Mr. Magoo <magoo@example.com>" (Key 4889C20D148231DC)
Command: git verify-commit --verbose HEAD
Exit code: 0
Output: tree 4b825dc642cb6eb9a060e54bf8d69288fbee4904
author Mr. Magoo <magoo@example.com> 1495041602 +0200
committer Mr. Magoo <magoo@example.com> 1495041602 +0200

signed commit
gpg: Signature made Mi 17 Mai 2017 19:20:02 CEST
gpg:                using RSA key 4889C20D148231DC
gpg: Good signature from "Mr. Magoo <magoo@example.com>" [full]
READABLE
            ],
        ];
    }

    /**
     * @dataProvider tagDataProvider
     */
    public function testFromGitTagCheck(
        PackageInterface $package,
        string $command,
        int $exitCode,
        string $commandOutput,
        bool $expectedTrust,
        string $expectedHumanReadableStringFormat
    ) : void {
        $check = GitSignatureCheck::fromGitTagCheck($package, $command, $exitCode, $commandOutput);

        self::assertSame($expectedTrust, $check->canBeTrusted());
        self::assertStringMatchesFormat($expectedHumanReadableStringFormat, $check->asHumanReadableString());
    }

    public function tagDataProvider() : array
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
                <<<'READABLE'
[NOT SIGNED] [NOT VERIFIED]    
Command: 
Exit code: 0
Output: 
READABLE
            ],
            'failed verification - no signed tag' => [
                $package,
                'git tag -v --verbose unsigned-tag',
                1,
                '',
                false,
                <<<'READABLE'
[NOT SIGNED] [NOT VERIFIED]    
Command: git tag -v --verbose unsigned-tag
Exit code: 1
Output: 
READABLE
            ],
        ];
    }
}
