<?php

declare(strict_types=1);

namespace Roave\ComposerGpgVerify\Package\Git;

use Composer\Package\PackageInterface;

/**
 * @internal do not use: I will cut you.
 *
 * This class abstracts the parsing of the output of `git verify-commit` and `git tag -v`, which are broken/useless.
 * Ideally, we'd massively simplify this class once GIT 2.12 is mainstream
 *
 * @link https://stackoverflow.com/questions/17371955/verifying-signed-git-commits/32038784#32038784
 */
final class GitSignatureCheck
{
    /**
     * @var string
     */
    private $packageName;

    /**
     * @var string
     */
    private $command;

    /**
     * @var string|null
     */
    private $commitHash;

    /**
     * @var string|null
     */
    private $tagName;

    /**
     * @var int
     */
    private $exitCode;

    /**
     * @var string
     */
    private $output;

    /**
     * @var bool
     */
    private $isSigned;

    /**
     * @var bool
     */
    private $isVerified;

    /**
     * @var string|null
     */
    private $signatureAuthor;

    public function asHumanReadableString() : string
    {
        return implode(
            "\n",
            [
                (($this->isSigned || $this->signatureKey) ? '[SIGNED]' : '[NOT SIGNED]')
                . ' ' . ($this->isVerified ? '[VERIFIED]' : '[NOT VERIFIED]')
                . ' ' . ($this->commitHash ? 'Commit #' . $this->commitHash : '')
                . ' ' . ($this->tagName ? 'Tag ' . $this->tagName : '')
                . ' ' . ($this->signatureAuthor ? 'By "' . $this->signatureAuthor . '"' : '')
                . ' ' . ($this->signatureKey ? '(Key ' . $this->signatureKey . ')' : ''),
                'Command: ' . $this->command,
                'Exit code: ' . $this->exitCode,
                'Output: ' . $this->output,
            ]
        );
    }

    private function __construct(
        string $packageName,
        ?string $commitHash,
        ?string $tagName,
        string $command,
        int $exitCode,
        string $output,
        bool $isSigned,
        bool $isVerified,
        ?string $signatureAuthor,
        ?string $signatureKey
    ) {
        $this->packageName     = $packageName;
        $this->commitHash      = $commitHash;
        $this->tagName         = $tagName;
        $this->command         = $command;
        $this->exitCode        = $exitCode;
        $this->output          = $output;
        $this->isSigned        = $isSigned;
        $this->isVerified      = $isVerified;
        $this->signatureAuthor = $signatureAuthor;
        $this->signatureKey    = $signatureKey;
    }

    /**
     * @var string|null
     */
    private $signatureKey;

    public static function fromGitCommitCheck(
        PackageInterface $package,
        string $command,
        int $exitCode,
        string $output
    ) : self {
        $signatureKey = self::extractKeyIdentifier($output);
        $signed       = $signatureKey && ! $exitCode;

        return new self(
            $package->getName(),
            self::extractCommitHash($output),
            null,
            $command,
            $exitCode,
            $output,
            $signed,
            $signed && self::signatureValidationHasNoWarnings($output),
            self::extractSignatureAuthor($output),
            $signatureKey
        );
    }

    public static function fromGitTagCheck(
        PackageInterface $package,
        string $command,
        int $exitCode,
        string $output
    ) : self {
        $signatureKey = self::extractKeyIdentifier($output);
        $signed       = $signatureKey && ! $exitCode;

        return new self(
            $package->getName(),
            self::extractCommitHash($output),
            self::extractTagName($output),
            $command,
            $exitCode,
            $output,
            $signed,
            $signed && self::signatureValidationHasNoWarnings($output),
            self::extractSignatureAuthor($output),
            self::extractKeyIdentifier($output)
        );
    }

    public function canBeTrusted() : bool
    {
        return $this->isVerified;
    }

    private static function extractCommitHash(string $output) : ?string
    {
        $keys = array_filter(array_map(
            function (string $outputRow) {
                preg_match('/^(tree|object) ([a-fA-F0-9]{40})$/i', $outputRow, $matches);

                return $matches[2] ?? false;
            },
            explode("\n", $output)
        ));

        return reset($keys) ?: null;
    }

    private static function extractTagName(string $output) : ?string
    {
        $keys = array_filter(array_map(
            function (string $outputRow) {
                preg_match('/^tag (.+)$/i', $outputRow, $matches);

                return $matches[1] ?? false;
            },
            explode("\n", $output)
        ));

        return reset($keys) ?: null;
    }

    private static function extractKeyIdentifier(string $output) : ?string
    {
        $keys = array_filter(array_map(
            function (string $outputRow) {
                preg_match('/gpg:.*using .* key ([a-fA-F0-9]+)/i', $outputRow, $matches);

                return $matches[1] ?? false;
            },
            explode("\n", $output)
        ));

        return reset($keys) ?: null;
    }

    private static function extractSignatureAuthor(string $output) : ?string
    {
        $keys = array_filter(array_map(
            function (string $outputRow) {
                preg_match('/gpg: Good signature from "(.+)" \\[.*\\]/i', $outputRow, $matches);

                return $matches[1] ?? false;
            },
            explode("\n", $output)
        ));

        return reset($keys) ?: null;
    }

    private static function signatureValidationHasNoWarnings(string $output) : bool
    {
        return ! array_filter(
            explode("\n", $output),
            function (string $outputRow) {
                return false !== strpos($outputRow, 'gpg: WARNING: ');
            }
        );
    }
}
