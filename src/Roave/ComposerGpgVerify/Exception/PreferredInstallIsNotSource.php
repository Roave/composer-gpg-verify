<?php

declare(strict_types=1);

namespace Roave\ComposerGpgVerify\Exception;

use InvalidArgumentException;

class PreferredInstallIsNotSource extends InvalidArgumentException
{
    /**
     * @param string|array $preferredInstall
     *
     * @return self
     */
    public static function fromPreferredInstall($preferredInstall) : self
    {
        return new self(sprintf(
            'The detected preferred install required for the git verification to work correctly is "source", '
            . 'but your composer.json configuration reported %s.'
            . "\n"
            . 'Please edit your composer.json to enforce "source" installation as described at '
            . 'https://getcomposer.org/doc/06-config.md#preferred-install',
            json_encode($preferredInstall)
        ));
    }
}
