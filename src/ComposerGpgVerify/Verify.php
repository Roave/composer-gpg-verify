<?php

declare(strict_types=1);

namespace ComposerGpgVerify;

use Composer\Composer;
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
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        // Nothing to do here, as all features are provided through event listeners
    }

    public static function verify(Event $composerEvent) : void
    {
        $composer  = $composerEvent->getComposer();
        $vendorDir = $composer->getConfig()->get('vendor-dir');

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

            if (! $signed) {
                // again, moronic language.
                $tags = [];

                exec(
                    sprintf(
                        'git --git-dir %s tag --points-at HEAD',
                        escapeshellarg($vendorDir->getRealPath() . '/.git')
                    ),
                    $tags
                );

                // go through all found tags, see if at least one is signed
                foreach (array_filter($tags) as $tag) {
                    exec(
                        sprintf(
                            'git --git-dir %s tag -v %s',
                            escapeshellarg($vendorDir->getRealPath() . '/.git'),
                            escapeshellarg($tag)
                        ),
                        $tagOutput,
                        $signed
                    );

                    if ($signed) {
                        $output = array_merge($output, $tagOutput);

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

        var_dump($packages);
    }
}
