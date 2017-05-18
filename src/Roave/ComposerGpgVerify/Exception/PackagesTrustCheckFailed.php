<?php

declare(strict_types=1);

namespace Roave\ComposerGpgVerify\Exception;

use LogicException;
use Roave\ComposerGpgVerify\Package\PackageVerification;

class PackagesTrustCheckFailed extends LogicException
{
    public static function fromFailedPackageVerifications(
        PackageVerification $failedVerification,
        PackageVerification ...$furtherFailedVerifications
    ) : self {
        return new self(sprintf(
            'The following packages need to be signed and verified, or added to exclusions: %s%s',
            "\n",
            implode(
                "\n\n",
                array_map(
                    function (PackageVerification $failedVerification) : string {
                        return $failedVerification->printReason();
                    },
                    array_merge([$failedVerification], $furtherFailedVerifications)
                )
            )
        ));
    }
}
