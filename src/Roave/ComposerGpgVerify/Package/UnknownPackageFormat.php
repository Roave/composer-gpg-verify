<?php

declare(strict_types=1);

namespace Roave\ComposerGpgVerify\Package;

use Composer\Package\PackageInterface;

/**
 * @internal do not use: I will cut you.
 *
 * This class represents a package verification for an unknown package type.
 * Currently, this repository only knows how to verify GPG+GIT, so anything ZIP/HG/CVS/etc will be considered "unknown"
 */
final class UnknownPackageFormat implements PackageVerification
{
    /**
     * @var string
     */
    private $packageName;

    private function __construct(string $packageName)
    {
        $this->packageName = $packageName;
    }

    public static function fromNonGitPackage(PackageInterface $package) : self
    {
        return new self($package->getName());
    }

    public function packageName(): string
    {
        return $this->packageName;
    }

    public function isVerified(): bool
    {
        return false;
    }

    public function printReason(): string
    {
        return sprintf(
            'Package "%s" is in a format that Roave\\ComposerGpgVerify cannot verify:'
            . ' try forcing it to be downloaded as GIT repository',
            $this->packageName()
        );
    }
}
