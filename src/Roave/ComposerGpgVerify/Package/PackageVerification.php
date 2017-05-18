<?php

declare(strict_types=1);

namespace Roave\ComposerGpgVerify\Package;

/**
 * @internal do not use: I will cut you.
 *
 * This is just a tiny interface to abstract away a package verification status
 *
 * The reason for having this abstraction is that multiple packages are verified in different ways,
 * so the information about verification, whilst printable as string, is internally represented
 * differently.
 */
interface PackageVerification
{
    public function packageName() : string;
    public function isVerified() : bool;
    public function printReason() : string;
}
