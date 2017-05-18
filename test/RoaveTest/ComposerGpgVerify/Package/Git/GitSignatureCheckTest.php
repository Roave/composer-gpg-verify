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
            'failed verification - signed tag, unknown key' => [
                $package,
                'git tag -v tag-name',
                1,
                <<<'OUTPUT'
object bf2fabeabe00f14f0ce0090adc7a2b9b770edbe3
type commit
tag tag-name
tagger Mr. Magoo <magoo@example.com> 1495094925 +0200

signed tag
gpg: keybox '/tmp/gpg-verification-test591d568c5d0947.51554486//pubring.kbx' created
gpg: Signature made Do 18 Mai 2017 10:08:45 CEST
gpg:                using RSA key 4B95C0CE4DE340CC
gpg: Can't check signature: No public key
OUTPUT
                ,
                false,
                <<<'READABLE'
[SIGNED] [NOT VERIFIED] Commit #bf2fabeabe00f14f0ce0090adc7a2b9b770edbe3 Tag tag-name  (Key 4B95C0CE4DE340CC)
Command: git tag -v tag-name
Exit code: 1
Output: object bf2fabeabe00f14f0ce0090adc7a2b9b770edbe3
type commit
tag tag-name
tagger Mr. Magoo <magoo@example.com> 1495094925 +0200

signed tag
gpg: keybox '/tmp/gpg-verification-test591d568c5d0947.51554486//pubring.kbx' created
gpg: Signature made Do 18 Mai 2017 10:08:45 CEST
gpg:                using RSA key 4B95C0CE4DE340CC
gpg: Can't check signature: No public key
READABLE
            ],
            'failed verification - signed tag, untrusted key' => [
                $package,
                'git tag -v tag-name',
                1,
                <<<'OUTPUT'
object be3b7a6f0ee4d90a72e1e1a19f89d8eeef746200
type commit
tag tag-name
tagger Mr. Magoo <magoo@example.com> 1495095245 +0200

signed tag
gpg: Signature made Do 18 Mai 2017 10:14:05 CEST
gpg:                using RSA key 865E20A60B500B00
gpg: checking the trustdb
gpg: marginals needed: 3  completes needed: 1  trust model: pgp
gpg: depth: 0  valid:   1  signed:   0  trust: 0-, 0q, 0n, 0m, 0f, 1u
gpg: Good signature from "Mr. Magoo <magoo@example.com>" [unknown]
gpg: WARNING: This key is not certified with a trusted signature!
gpg:          There is no indication that the signature belongs to the owner.
Primary key fingerprint: D8BE 3E96 4271 2378 9551  AF87 865E 20A6 0B50 0B00
OUTPUT
                ,
                false,
                <<<'READABLE'
[SIGNED] [NOT VERIFIED] Commit #be3b7a6f0ee4d90a72e1e1a19f89d8eeef746200 Tag tag-name By "Mr. Magoo <magoo@example.com>" (Key 865E20A60B500B00)
Command: git tag -v tag-name
Exit code: 1
Output: object be3b7a6f0ee4d90a72e1e1a19f89d8eeef746200
type commit
tag tag-name
tagger Mr. Magoo <magoo@example.com> 1495095245 +0200

signed tag
gpg: Signature made Do 18 Mai 2017 10:14:05 CEST
gpg:                using RSA key 865E20A60B500B00
gpg: checking the trustdb
gpg: marginals needed: 3  completes needed: 1  trust model: pgp
gpg: depth: 0  valid:   1  signed:   0  trust: 0-, 0q, 0n, 0m, 0f, 1u
gpg: Good signature from "Mr. Magoo <magoo@example.com>" [unknown]
gpg: WARNING: This key is not certified with a trusted signature!
gpg:          There is no indication that the signature belongs to the owner.
Primary key fingerprint: D8BE 3E96 4271 2378 9551  AF87 865E 20A6 0B50 0B00
READABLE
            ],
        ];
    }
}
