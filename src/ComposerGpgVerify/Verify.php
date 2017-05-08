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
                var_dump('Not a dir: ' . $vendorDir->getRealPath());

                continue;
            }

            $packageName = basename(dirname($vendorDir->getRealPath())) . '/' . $vendorDir->getBasename();

            var_dump($vendorDir->getRealPath()); // @TODO will need to check that it is a dir

            // because PHP is a moronic language, by-ref is everywhere in the standard library
            $output = [];

            exec(
                sprintf(
                    'git --git-dir %s verify-commit --verbose HEAD',
                    escapeshellarg($vendorDir->getRealPath() . '/.git')
                ),
                $output,
                $return
            );

            $packages[$packageName] = [
                'git'       => 'dunno',
                'signed'    => (bool) $output,
                'signature' => implode("\n", $output),
                'verified'  => ! $return,
            ];
        }

        var_dump($packages);
    }
}
