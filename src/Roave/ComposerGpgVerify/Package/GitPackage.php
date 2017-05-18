<?php

declare(strict_types=1);

namespace Roave\ComposerGpgVerify\Package;

use Composer\Package\PackageInterface;
use Roave\ComposerGpgVerify\Package\Git\GitSignatureCheck;

/**
 * @internal do not use: I will cut you.
 *
 * This class represents a package verification performed via GIT+GPG verifications. It really is just a
 * collection of @see GitSignatureCheck
 */
final class GitPackage implements PackageVerification
{
    /**
     * @var string
     */
    private $packageName;

    /**
     * @var GitSignatureCheck[]
     */
    private $checks;

    private function __construct(string $packageName, GitSignatureCheck ...$checks)
    {
        $this->packageName = $packageName;
        $this->checks      = $checks;
    }

    public static function fromPackageAndSignatureChecks(
        PackageInterface $package,
        GitSignatureCheck $firstCheck,
        GitSignatureCheck ...$checks
    ) : self {
        return new self($package->getName(), ...array_merge([$firstCheck], $checks));
    }

    public function packageName() : string
    {
        return $this->packageName;
    }

    public function isVerified() : bool
    {
        return (bool) $this->passedChecks();
    }

    public function printReason() : string
    {
        if ($this->isVerified()) {
            return sprintf('The following GIT GPG signature checks passed for package "%s":', $this->packageName())
                . "\n\n"
                . implode(
                    "\n\n",
                    array_map(
                        function (GitSignatureCheck $check) : string {
                            return $check->asHumanReadableString();
                        },
                        $this->passedChecks()
                    )
                );
        }

        return sprintf('The following GIT GPG signature checks have failed for package "%s":', $this->packageName())
            . "\n\n"
            . implode(
                "\n\n",
                array_map(
                    function (GitSignatureCheck $check) : string {
                        return $check->asHumanReadableString();
                    },
                    $this->failedChecks()
                )
            );
    }

    /**
     * @return GitSignatureCheck[]
     */
    private function passedChecks() : array
    {
        return array_values(array_filter(
            $this->checks,
            function (GitSignatureCheck $check) {
                return $check->canBeTrusted();
            }
        ));
    }

    /**
     * @return GitSignatureCheck[]
     */
    private function failedChecks() : array
    {
        return array_values(array_filter(
            $this->checks,
            function (GitSignatureCheck $check) {
                return ! $check->canBeTrusted();
            }
        ));
    }
}
