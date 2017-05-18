<?php

declare(strict_types=1);

namespace Roave\ComposerGpgVerify;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Roave\ComposerGpgVerify\Exception\PackagesTrustCheckFailed;
use Roave\ComposerGpgVerify\Exception\PreferredInstallIsNotSource;
use Roave\ComposerGpgVerify\Package\Git\GitSignatureCheck;
use Roave\ComposerGpgVerify\Package\GitPackage;
use Roave\ComposerGpgVerify\Package\PackageVerification;
use Roave\ComposerGpgVerify\Package\UnknownPackageFormat;

final class Verify implements PluginInterface, EventSubscriberInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents() : array
    {
        return [
            ScriptEvents::PRE_AUTOLOAD_DUMP => 'verify',
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    public function activate(Composer $composer, IOInterface $io) : void
    {
        // Nothing to do here, as all features are provided through event listeners
    }

    /**
     * @param Event $composerEvent
     *
     * @throws \Roave\ComposerGpgVerify\Exception\PackagesTrustCheckFailed
     * @throws \RuntimeException
     * @throws \Roave\ComposerGpgVerify\Exception\PreferredInstallIsNotSource
     */
    public static function verify(Event $composerEvent) : void
    {
        $originalLanguage = getenv('LANGUAGE');
        $composer         = $composerEvent->getComposer();
        $config           = $composer->getConfig();

        self::assertSourceInstallation($config);

        // prevent output changes caused by locale settings on the system where this script is running
        putenv(sprintf('LANGUAGE=%s', 'en_US'));

        $installationManager = $composer->getInstallationManager();
        /* @var $checkedPackages PackageVerification[] */
        $checkedPackages     = array_map(
            function (PackageInterface $package) use ($installationManager) : PackageVerification {
                return self::verifyPackage($installationManager, $package);
            },
            $composer->getRepositoryManager()->getLocalRepository()->getPackages()
        );

        putenv(sprintf('LANGUAGE=%s', (string) $originalLanguage));

        $escapes = array_filter(
            $checkedPackages,
            function (PackageVerification $verification) : bool {
                return ! $verification->isVerified();
            }
        );

        if (! $escapes) {
            return;
        }

        throw PackagesTrustCheckFailed::fromFailedPackageVerifications(...$escapes);
    }

    private static function verifyPackage(
        InstallationManager $installationManager,
        PackageInterface $package
    ) : PackageVerification {
        $gitDirectory = $installationManager->getInstallPath($package) . '/.git';

        if (! is_dir($gitDirectory)) {
            return UnknownPackageFormat::fromNonGitPackage($package);
        }

        return GitPackage::fromPackageAndSignatureChecks(
            $package,
            self::checkCurrentCommitSignature($gitDirectory, $package),
            ...self::checkTagSignatures($gitDirectory, $package, ...self::getTagsForCurrentCommit($gitDirectory))
        );
    }

    private static function checkCurrentCommitSignature(
        string $gitDirectory,
        PackageInterface $package
    ) : GitSignatureCheck {
        $command = sprintf(
            'git --git-dir %s verify-commit --verbose HEAD 2>&1',
            escapeshellarg($gitDirectory)
        );

        exec($command, $output, $exitCode);

        return GitSignatureCheck::fromGitCommitCheck($package, $command, $exitCode, implode("\n", $output));
    }

    /**
     * @param string $gitDirectory
     *
     * @return string[]
     */
    private static function getTagsForCurrentCommit(string $gitDirectory) : array
    {
        exec(
            sprintf(
                'git --git-dir %s tag --points-at HEAD 2>&1',
                escapeshellarg($gitDirectory)
            ),
            $tags
        );

        return array_values(array_filter($tags));
    }


    /**
     * @param string           $gitDirectory
     * @param PackageInterface $package
     * @param string[]         $tags
     *
     * @return GitSignatureCheck[]
     */
    private static function checkTagSignatures(string $gitDirectory, PackageInterface $package, string ...$tags) : array
    {
        return array_map(
            function (string $tag) use ($gitDirectory, $package) : GitSignatureCheck {
                $command = sprintf(
                    'git --git-dir %s tag -v %s 2>&1',
                    escapeshellarg($gitDirectory),
                    escapeshellarg($tag)
                );

                exec($command, $tagSignatureOutput, $exitCode);

                return GitSignatureCheck::fromGitTagCheck(
                    $package,
                    $command,
                    $exitCode,
                    implode("\n", $tagSignatureOutput)
                );
            },
            $tags
        );
    }

    /**
     * @param Config $config
     *
     *
     * @throws \RuntimeException
     * @throws \Roave\ComposerGpgVerify\Exception\PreferredInstallIsNotSource
     */
    private static function assertSourceInstallation(Config $config) : void
    {
        $preferredInstall = $config->get('preferred-install');

        if ('source' !== $preferredInstall) {
            throw PreferredInstallIsNotSource::fromPreferredInstall($preferredInstall);
        }
    }
}
