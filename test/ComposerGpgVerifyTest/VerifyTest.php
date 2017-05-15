<?php

declare(strict_types=1);

namespace ComposerGpgVerifyTest;

use Composer\Composer;
use Composer\Config;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use ComposerGpgVerify\Verify;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @covers \ComposerGpgVerify\Verify
 */
final class VerifyTest extends TestCase
{
    /**
     * @var Event|\PHPUnit_Framework_MockObject_MockObject
     */
    private $event;

    /**
     * @var Composer|\PHPUnit_Framework_MockObject_MockObject
     */
    private $composer;

    /**
     * @var Config|\PHPUnit_Framework_MockObject_MockObject
     */
    private $config;

    protected function setUp() : void
    {
        parent::setUp();

        $this->event    = $this->createMock(Event::class);
        $this->composer = $this->createMock(Composer::class);
        $this->config   = $this->createMock(Config::class);

        $this->event->expects(self::any())->method('getComposer')->willReturn($this->composer);
        $this->composer->expects(self::any())->method('getConfig')->willReturn($this->config);

        // @TODO restore GNUPGHOME after test execution - back it up here, then reset later
    }

    public function testWillDisallowPluginInstantiation() : void
    {
        $this->expectException(\Throwable::class);

        new Verify();
    }

    public function testWillDisallowInstallationOnNonSourceInstall() : void
    {
        $this
            ->config
            ->expects(self::any())
            ->method('get')
            ->with('preferred-install')
            ->willReturn('foo');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected installation "preferred-install" to be "source", found "foo" instead');

        Verify::verify($this->event);
    }

    public function testWillRetrieveSubscribedEvents() : void
    {
        $events = Verify::getSubscribedEvents();

        self::assertNotEmpty($events);

        $availableEvents = (new \ReflectionClass(ScriptEvents::class))->getConstants();

        foreach ($events as $eventName => $callback) {
            self::assertContains($eventName, $availableEvents);
            self::assertInternalType('string', $callback);
            self::assertInternalType('callable', [Verify::class, $callback]);
        }
    }

    public function testWillAcceptSignedAndTrustedPackages() : void
    {
        $gpgHomeDirectory = $this->makeGpgHomeDirectory();

        $vendorName  = 'Mr. Magoo';
        $vendorEmail = 'magoo@example.com';
        $vendorKey   = $this->makeKey($gpgHomeDirectory, $vendorEmail, $vendorName);
        $vendorDir   = $this->makeVendorDirectory();
        $vendor1     = $this->makeDependencyGitRepository($vendorDir, 'vendor1/package1', $vendorEmail, $vendorName);

        $this->signDependency($vendor1, $gpgHomeDirectory, $vendorKey);

        $this
            ->config
            ->expects(self::any())
            ->method('get')
            ->with(self::logicalOr('preferred-install', 'vendor-dir'))
            ->willReturnCallback(function (string $key) use ($vendorDir) {
                if ('preferred-install' === $key) {
                    return 'source';
                }

                return $vendorDir;
            });

        putenv('GNUPGHOME=' . $gpgHomeDirectory);

        Verify::verify($this->event);
    }

    public function testWillAcceptSignedAndTrustedTaggedPackages() : void
    {
        $gpgHomeDirectory = $this->makeGpgHomeDirectory();

        $vendorName  = 'Mr. Magoo';
        $vendorEmail = 'magoo@example.com';
        $vendorKey   = $this->makeKey($gpgHomeDirectory, $vendorEmail, $vendorName);
        $vendorDir   = $this->makeVendorDirectory();
        $vendor1     = $this->makeDependencyGitRepository($vendorDir, 'vendor1/package1', $vendorEmail, $vendorName);

        $this->createDependencySignedTag($vendor1, $gpgHomeDirectory, $vendorKey);

        $this
            ->config
            ->expects(self::any())
            ->method('get')
            ->with(self::logicalOr('preferred-install', 'vendor-dir'))
            ->willReturnCallback(function (string $key) use ($vendorDir) {
                if ('preferred-install' === $key) {
                    return 'source';
                }

                return $vendorDir;
            });

        putenv('GNUPGHOME=' . $gpgHomeDirectory);

        Verify::verify($this->event);
    }

    private function makeVendorDirectory() : string
    {
        $vendorDirectory = sys_get_temp_dir() . '/' . uniqid('vendor', true);

        self::assertTrue(mkdir($vendorDirectory));

        return $vendorDirectory;
    }

    private function signDependency(
        string $dependencyDirectory,
        string $gpgHomeDirectory,
        string $signingKey
    ) : void {
        (new Process(sprintf('git config --local --add user.signingkey %s', escapeshellarg($signingKey)), $dependencyDirectory))
            ->setTimeout(30)
            ->mustRun();

        (new Process(
            'git commit --allow-empty -m "signed commit" -S',
            $dependencyDirectory,
            ['GNUPGHOME' => $gpgHomeDirectory, 'GIT_TRACE' => '2']
        ))
            ->setTimeout(30)
            ->mustRun();
    }

    private function createDependencySignedTag(
        string $dependencyDirectory,
        string $gpgHomeDirectory,
        string $signingKey
    ) : void {
        (new Process(sprintf('git config --local --add user.signingkey %s', escapeshellarg($signingKey)), $dependencyDirectory))
            ->setTimeout(30)
            ->mustRun();

        (new Process('git commit --allow-empty -m "unsigned commit"', $dependencyDirectory))
            ->setTimeout(30)
            ->mustRun();

        (new Process(
            'git tag -s "tag-name" -m "signed tag"',
            $dependencyDirectory,
            ['GNUPGHOME' => $gpgHomeDirectory, 'GIT_TRACE' => '2']
        ))
            ->setTimeout(30)
            ->mustRun();
    }

    private function makeDependencyGitRepository(
        string $vendorDirectory,
        string $packageName,
        string $email,
        string $name
    ) : string {
        $dependencyRepository = $vendorDirectory . '/' . $packageName;

        self::assertTrue(mkdir($dependencyRepository, 0777, true));

        (new Process('git init', $dependencyRepository))
            ->setTimeout(30)
            ->mustRun();

        (new Process(sprintf('git config --local --add user.email %s', escapeshellarg($email)), $dependencyRepository))
            ->setTimeout(30)
            ->mustRun();

        (new Process(sprintf('git config --local --add user.name %s', escapeshellarg($name)), $dependencyRepository))
            ->setTimeout(30)
            ->mustRun();

        return $dependencyRepository;
    }

    private function makeGpgHomeDirectory() : string
    {
        $homeDirectory = sys_get_temp_dir() . '/' . uniqid('gpg-verification-test', true);

        self::assertTrue(mkdir($homeDirectory));

        return $homeDirectory;
    }

    private function makeKey(string $gpgHomeDirectory, string $emailAddress, string $name) : string
    {
        $input = <<<'KEY'
%echo Generating a standard key
Key-Type: RSA
Key-Length: 1024
Name-Real: <<<NAME>>>
Name-Email: <<<EMAIL>>>
Expire-Date: 0
%no-protection
%no-ask-passphrase
%commit
%echo done

KEY;
        self::assertGreaterThan(
            0,
            file_put_contents(
                $gpgHomeDirectory . '/key-info.txt',
                str_replace(['<<<NAME>>>', '<<<EMAIL>>>'], [$name, $emailAddress], $input)
            )
        );

        $keyOutput = (new Process(
            'gpg --batch --gen-key -a key-info.txt',
            $gpgHomeDirectory,
            ['GNUPGHOME' => $gpgHomeDirectory]
        ))
            ->setTimeout(30)
            ->mustRun()
            ->getErrorOutput();

        self::assertRegExp('/key [0-9A-F]+ marked as ultimately trusted/i', $keyOutput);

        preg_match('/key ([0-9A-F]+) marked as ultimately trusted/i', $keyOutput, $matches);

        return $matches[1];
    }
}
