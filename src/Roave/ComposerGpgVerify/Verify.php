<?php

declare(strict_types=1);

namespace Roave\ComposerGpgVerify;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

final class Verify implements PluginInterface, EventSubscriberInterface
{
    private function __construct()
    {
    }

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
     * @throws \LogicException
     * @throws \RuntimeException
     */
    public static function verify(Event $composerEvent) : void
    {
        $composer         = $composerEvent->getComposer();
        $config           = $composer->getConfig();

        self::assertSourceInstallation($config);

        $vendorDir = $config->get('vendor-dir');

        // @todo that `$vendorDir` may include special chars
        /* @var $vendorDirs \GlobIterator|\SplFileInfo[]*/
        $vendorDirs = new \GlobIterator(
            $vendorDir . '/*/*',
            \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::FOLLOW_SYMLINKS | \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO
        );

        $packages = [];

        foreach ($vendorDirs as $vendorDir) {
            if (! $vendorDir->isDir()) {
                continue;
            }

            $packageName = basename(dirname($vendorDir->getRealPath())) . '/' . $vendorDir->getBasename();

            if (! is_dir($vendorDir->getRealPath() . '/.git')) {
                $packages[$packageName] = [
                    'git'       => false,
                    'signed'    => false,
                    'signature' => '',
                    'verified'  => false,
                ];

                continue;
            }

            // because PHP is a moronic language, by-ref is everywhere in the standard library
            $output = [];

            // @TODO check check tags if the commit isn't signed
            exec(
                sprintf(
                    'git --git-dir %s verify-commit --verbose HEAD 2>&1',
                    escapeshellarg($vendorDir->getRealPath() . '/.git')
                ),
                $output,
                $signed
            );

            $signed = $signed || ! self::verifySignatureValidationHasNoWarnings($output);

            if ($signed) {
                // again, moronic language.
                $tags = [];

                exec(
                    sprintf(
                        'git --git-dir %s tag --points-at HEAD 2>&1',
                        escapeshellarg($vendorDir->getRealPath() . '/.git')
                    ),
                    $tags
                );

                // go through all found tags, see if at least one is signed
                foreach (array_filter($tags) as $tag) {
                    exec(
                        sprintf(
                            'git --git-dir %s tag -v %s 2>&1',
                            escapeshellarg($vendorDir->getRealPath() . '/.git'),
                            escapeshellarg($tag)
                        ),
                        $tagSignatureOutput,
                        $signed
                    );

                    $signed = $signed || ! self::verifySignatureValidationHasNoWarnings($tagSignatureOutput);

                    if (! $signed) {
                        $output = array_merge($output, $tagSignatureOutput);

                        break;
                    }
                }
            }

            $packages[$packageName] = [
                'git'       => 'dunno',
                'signed'    => (bool) $output,
                'signature' => implode("\n", $output),
                'verified'  => ! $signed,
            ];
        }

        // @TODO configuration WILL arrive, but for now I'm too lazy, so GTFO
//        $settings = $composer->getPackage()->getExtra()['composer-gpg-verify'];
//
//        $allowedUnsigned  = $settings['allow-unsigned'];
//        $allowedUntrusted = $settings['allow-untrusted'];

        $allowedUnsigned  = [];
        $allowedUntrusted = [];


        $unSigned = array_keys(array_filter(
            $packages,
            function (array $package) : bool {
                return ! $package['signed'];
            }
        ));

        $unVerified = array_keys(array_filter(
            $packages,
            function (array $package) : bool {
                return ! $package['verified'];
            }
        ));

        $requiringSignature    = array_diff($unSigned, $allowedUnsigned);
        $requiringVerification = array_diff($unVerified, $allowedUntrusted);

        $escapes = array_values(array_unique(array_merge($requiringSignature, $requiringVerification)));

        if (! $escapes) {
            return;
        }

        // @TODO report un-trusted signatures emails, names, identifiers (full length, if possible)

        throw new \RuntimeException(sprintf(
            'The following packages need to be signed and verified, or added to exclusions: %s%s',
            "\n",
            implode("\n", $escapes)
        ));
    }

    /**
     * @TODO there must be a better way, seriously. Why does `git verify-tag` not simply produce
     * @TODO a non-zero exit code when a signature belongs to an untrusted key?!
     */
    private static function verifySignatureValidationHasNoWarnings(array $output) : bool
    {
        return ! array_filter(
            $output,
            function (string $outputRow) {
                return false !== strpos($outputRow, 'gpg: WARNING: ');
            }
        );
    }

    /**
     * @param Config $config
     *
     *
     * @throws \LogicException
     * @throws \RuntimeException
     */
    private static function assertSourceInstallation(Config $config) : void
    {
        $preferredInstall = $config->get('preferred-install');

        if ('source' !== $preferredInstall) {
            throw new \LogicException(sprintf(
                'Expected installation "preferred-install" to be "source", found "%s" instead',
                (string) $preferredInstall
            ));
        }
    }
}
