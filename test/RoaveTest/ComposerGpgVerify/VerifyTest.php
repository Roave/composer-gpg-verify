<?php

declare(strict_types=1);

namespace RoaveTest\ComposerGpgVerify;

use Composer\Composer;
use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use PHPUnit\Framework\TestCase;
use Roave\ComposerGpgVerify\Verify;
use Symfony\Component\Process\Process;

/**
 * @covers \Roave\ComposerGpgVerify\Verify
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

    /**
     * @var RepositoryManager|\PHPUnit_Framework_MockObject_MockObject
     */
    private $repositoryManager;

    /**
     * @var InstallationManager|\PHPUnit_Framework_MockObject_MockObject
     */
    private $installationManager;

    /**
     * @var RepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $localRepository;
//$composer->getRepositoryManager()->getLocalRepository()->getPackages();
//    //$composer->getLocker()->getLockData()
//$composer->getInstallationManager()->getInstallPath();

    /**
     * @var string
     */
    private $originalGpgHome;

    /**
     * @var string
     */
    private $originalLanguage;

    /**
     * @var PackageInterface[] indexed by installation path
     */
    private $installedPackages = [];

    protected function setUp() : void
    {
        parent::setUp();

        $this->installedPackages = [];
        $this->originalGpgHome   = (string) getenv('GNUPGHOME');
        $this->originalLanguage  = (string) getenv('LANGUAGE');

        $this->event               = $this->createMock(Event::class);
        $this->composer            = $this->createMock(Composer::class);
        $this->config              = $this->createMock(Config::class);
        $this->repositoryManager   = $this->createMock(RepositoryManager::class);
        $this->installationManager = $this->createMock(InstallationManager::class);
        $this->localRepository     = $this->createMock(RepositoryInterface::class);

        $this->event->expects(self::any())->method('getComposer')->willReturn($this->composer);
        $this->composer->expects(self::any())->method('getConfig')->willReturn($this->config);
        $this
            ->composer
            ->expects(self::any())
            ->method('getRepositoryManager')
            ->willReturn($this->repositoryManager);
        $this
            ->composer
            ->expects(self::any())
            ->method('getInstallationManager')
            ->willReturn($this->installationManager);
        $this
            ->repositoryManager
            ->expects(self::any())
            ->method('getLocalRepository')
            ->willReturn($this->localRepository);
        $this
            ->installationManager
            ->expects(self::any())
            ->method('getInstallPath')
            ->willReturnCallback(function (PackageInterface $package) : string {
                return array_search($package, $this->installedPackages, true);
            });
        $this
            ->localRepository
            ->expects(self::any())
            ->method('getPackages')
            ->willReturnCallback(function () {
                return array_values($this->installedPackages);
            });
    }

    protected function tearDown() : void
    {
        putenv(sprintf('GNUPGHOME=%s', $this->originalGpgHome));
        putenv(sprintf('LANGUAGE=%s', $this->originalLanguage));

        parent::tearDown();
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

        $this->configureCorrectComposerSetup();

        putenv('GNUPGHOME=' . $gpgHomeDirectory);

        Verify::verify($this->event);
    }

    public function testWillRejectPackageSignedWithImportedButUnTrustedKey() : void
    {
        $personalGpgDirectory = $this->makeGpgHomeDirectory();
        $foreignGpgDirectory  = $this->makeGpgHomeDirectory();

        $vendorName  = 'Mr. Magoo';
        $vendorEmail = 'magoo@example.com';
        $ownKey      = $this->makeKey($personalGpgDirectory, 'me@example.com', 'Just Me');
        $vendorKey   = $this->makeKey($foreignGpgDirectory, $vendorEmail, $vendorName);
        $vendorDir   = $this->makeVendorDirectory();
        $vendor1     = $this->makeDependencyGitRepository($vendorDir, 'vendor1/package1', $vendorEmail, $vendorName);

        $this->signDependency($vendor1, $foreignGpgDirectory, $vendorKey);

        $this->importForeignKeys($personalGpgDirectory, $foreignGpgDirectory, $vendorKey, false);

        $this->configureCorrectComposerSetup();

        putenv('GNUPGHOME=' . $personalGpgDirectory);

        $this->assertWillFailPackageVerification();
    }

    public function testWillRejectPackageSignedWithImportedButUnTrustedKeyWithDifferentLocaleSettings() : void
    {
        $personalGpgDirectory = $this->makeGpgHomeDirectory();
        $foreignGpgDirectory  = $this->makeGpgHomeDirectory();

        $vendorName  = 'Mr. Magoo';
        $vendorEmail = 'magoo@example.com';
        $ownKey      = $this->makeKey($personalGpgDirectory, 'me@example.com', 'Just Me');
        $vendorKey   = $this->makeKey($foreignGpgDirectory, $vendorEmail, $vendorName);
        $vendorDir   = $this->makeVendorDirectory();
        $vendor1     = $this->makeDependencyGitRepository($vendorDir, 'vendor1/package1', $vendorEmail, $vendorName);

        $this->signDependency($vendor1, $foreignGpgDirectory, $vendorKey);

        $this->importForeignKeys($personalGpgDirectory, $foreignGpgDirectory, $vendorKey, false);

        $this->configureCorrectComposerSetup();

        putenv('GNUPGHOME=' . $personalGpgDirectory);
        putenv('LANGUAGE=de_DE');

        try {
            Verify::verify($this->event);
        } catch (\RuntimeException $failure) {
            self::assertSame('de_DE', getenv('LANGUAGE'));

            return;
        }

        self::fail('Exception was not thrown');
    }

    public function testWillAcceptPackageSignedWithImportedAndTrustedKey() : void
    {
        $personalGpgDirectory = $this->makeGpgHomeDirectory();
        $foreignGpgDirectory  = $this->makeGpgHomeDirectory();

        $vendorName  = 'Mr. Magoo';
        $vendorEmail = 'magoo@example.com';
        $ownKey      = $this->makeKey($personalGpgDirectory, 'me@example.com', 'Just Me');
        $vendorKey   = $this->makeKey($foreignGpgDirectory, $vendorEmail, $vendorName);
        $vendorDir   = $this->makeVendorDirectory();
        $vendor1     = $this->makeDependencyGitRepository($vendorDir, 'vendor1/package1', $vendorEmail, $vendorName);

        $this->signDependency($vendor1, $foreignGpgDirectory, $vendorKey);

        $this->importForeignKeys($personalGpgDirectory, $foreignGpgDirectory, $vendorKey, true);

        $this->configureCorrectComposerSetup();

        putenv('GNUPGHOME=' . $personalGpgDirectory);

        Verify::verify($this->event);
    }

    public function testWillRejectPackageTaggedAndSignedWithImportedButUnTrustedKey() : void
    {
        $personalGpgDirectory = $this->makeGpgHomeDirectory();
        $foreignGpgDirectory  = $this->makeGpgHomeDirectory();

        $vendorName  = 'Mr. Magoo';
        $vendorEmail = 'magoo@example.com';
        $ownKey      = $this->makeKey($personalGpgDirectory, 'me@example.com', 'Just Me');
        $vendorKey   = $this->makeKey($foreignGpgDirectory, $vendorEmail, $vendorName);
        $vendorDir   = $this->makeVendorDirectory();
        $vendor1     = $this->makeDependencyGitRepository($vendorDir, 'vendor1/package1', $vendorEmail, $vendorName);

        $this->createDependencySignedTag($vendor1, $foreignGpgDirectory, $vendorKey);

        $this->importForeignKeys($personalGpgDirectory, $foreignGpgDirectory, $vendorKey, false);

        $this->configureCorrectComposerSetup();

        putenv('GNUPGHOME=' . $personalGpgDirectory);

        $this->assertWillFailPackageVerification();
    }

    public function testWillAcceptPackageTaggedAndSignedWithImportedAndTrustedKey() : void
    {
        $personalGpgDirectory = $this->makeGpgHomeDirectory();
        $foreignGpgDirectory  = $this->makeGpgHomeDirectory();

        $vendorName  = 'Mr. Magoo';
        $vendorEmail = 'magoo@example.com';
        $ownKey      = $this->makeKey($personalGpgDirectory, 'me@example.com', 'Just Me');
        $vendorKey   = $this->makeKey($foreignGpgDirectory, $vendorEmail, $vendorName);
        $vendorDir   = $this->makeVendorDirectory();
        $vendor1     = $this->makeDependencyGitRepository($vendorDir, 'vendor1/package1', $vendorEmail, $vendorName);

        $this->createDependencySignedTag($vendor1, $foreignGpgDirectory, $vendorKey);

        $this->importForeignKeys($personalGpgDirectory, $foreignGpgDirectory, $vendorKey, true);

        $this->configureCorrectComposerSetup();

        putenv('GNUPGHOME=' . $personalGpgDirectory);

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

        $this->configureCorrectComposerSetup();

        putenv('GNUPGHOME=' . $gpgHomeDirectory);

        Verify::verify($this->event);
    }

    public function testWillRejectUnSignedCommits() : void
    {
        $vendorName  = 'Mr. Magoo';
        $vendorEmail = 'magoo@example.com';
        $vendorDir   = $this->makeVendorDirectory();
        $vendor1     = $this->makeDependencyGitRepository($vendorDir, 'vendor1/package1', $vendorEmail, $vendorName);

        (new Process('git commit --allow-empty -m "unsigned commit"', $vendor1))
            ->setTimeout(30)
            ->mustRun();

        $this->configureCorrectComposerSetup();

        putenv('GNUPGHOME=' . $this->makeGpgHomeDirectory());

        $this->assertWillFailPackageVerification();
    }

    public function testWillRejectUnSignedTags() : void
    {
        $vendorName  = 'Mr. Magoo';
        $vendorEmail = 'magoo@example.com';
        $vendorDir   = $this->makeVendorDirectory();
        $vendor1     = $this->makeDependencyGitRepository($vendorDir, 'vendor1/package1', $vendorEmail, $vendorName);

        (new Process('git commit --allow-empty -m "unsigned commit"', $vendor1))
            ->setTimeout(30)
            ->mustRun();

        (new Process('git tag unsigned-tag -m "unsigned tag"', $vendor1))
            ->setTimeout(30)
            ->mustRun();

        $this->configureCorrectComposerSetup();

        putenv('GNUPGHOME=' . $this->makeGpgHomeDirectory());

        $this->assertWillFailPackageVerification();
    }

    public function testWillRejectSignedTagsFromUnknownKey() : void
    {
        $personalGpgDirectory = $this->makeGpgHomeDirectory();
        $foreignGpgDirectory  = $this->makeGpgHomeDirectory();
        $vendorName  = 'Mr. Magoo';
        $vendorEmail = 'magoo@example.com';
        $vendorKey   = $this->makeKey($foreignGpgDirectory, $vendorEmail, $vendorName);
        $vendorDir   = $this->makeVendorDirectory();
        $vendor1     = $this->makeDependencyGitRepository($vendorDir, 'vendor1/package1', $vendorEmail, $vendorName);

        $this->signDependency($vendor1, $foreignGpgDirectory, $vendorKey);

        $this->configureCorrectComposerSetup();

        putenv('GNUPGHOME=' . $personalGpgDirectory);

        $this->assertWillFailPackageVerification();
    }

    public function testWillRejectSignedCommitsFromUnknownKeys() : void
    {
        $personalGpgDirectory = $this->makeGpgHomeDirectory();
        $foreignGpgDirectory  = $this->makeGpgHomeDirectory();

        $vendorName  = 'Mr. Magoo';
        $vendorEmail = 'magoo@example.com';
        $vendorKey   = $this->makeKey($foreignGpgDirectory, $vendorEmail, $vendorName);
        $vendorDir   = $this->makeVendorDirectory();
        $vendor1     = $this->makeDependencyGitRepository($vendorDir, 'vendor1/package1', $vendorEmail, $vendorName);

        $this->signDependency($vendor1, $foreignGpgDirectory, $vendorKey);

        $this->configureCorrectComposerSetup();

        putenv('GNUPGHOME=' . $personalGpgDirectory);

        $this->assertWillFailPackageVerification();
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

        /* @var $package PackageInterface|\PHPUnit_Framework_MockObject_MockObject */
        $package = $this->createMock(PackageInterface::class);

        $package->expects(self::any())->method('getName')->willReturn($packageName);

        $this->installedPackages[$dependencyRepository] = $package;

        return $dependencyRepository;
    }

    private function makeGpgHomeDirectory() : string
    {
        $homeDirectory = sys_get_temp_dir() . '/' . uniqid('gpg-verification-test', true);

        self::assertTrue(mkdir($homeDirectory, 0700));

        return $homeDirectory;
    }

    private function makeKey(string $gpgHomeDirectory, string $emailAddress, string $name) : string
    {
        $input = <<<'KEY'
%echo Generating a standard key
Key-Type: RSA
Key-Length: 128
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

    private function configureCorrectComposerSetup() : void
    {
        $this
            ->config
            ->expects(self::any())
            ->method('get')
            ->with('preferred-install')
            ->willReturn('source');
    }

    private function assertWillFailPackageVerification(string ...$packages) : void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'The following packages need to be signed and verified, or added to exclusions: '
            . "\n"
            . implode("\n", $packages)
        );

        Verify::verify($this->event);
    }

    private function importForeignKeys(
        string $localGpgHome,
        string $foreignGpgHome,
        string $foreignKey,
        bool $sign
    ) : void {
        $exportPath = sys_get_temp_dir() . '/' . uniqid('exportedKey', true);

        (new Process(
            sprintf('gpg --export --armor > %s', escapeshellarg($exportPath)),
            null,
            ['GNUPGHOME' => $foreignGpgHome]
        ))
            ->setTimeout(30)
            ->mustRun();

        self::assertFileExists($exportPath);

        (new Process(
            sprintf('gpg --import < %s', escapeshellarg($exportPath)),
            null,
            ['GNUPGHOME' => $localGpgHome]
        ))
            ->setTimeout(30)
            ->mustRun();

        if (! $sign) {
            return;
        }

        (new Process(
            sprintf('gpg --batch --yes --sign-key %s', escapeshellarg($foreignKey)),
            null,
            ['GNUPGHOME' => $localGpgHome]
        ))
            ->setTimeout(30)
            ->mustRun();
    }
}
