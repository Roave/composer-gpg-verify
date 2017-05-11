<?php

declare(strict_types=1);

namespace ComposerGpgVerifyTest;

use ComposerGpgVerify\Verify;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ComposerGpgVerify\Verify
 */
final class VerifyTest extends TestCase
{
    public function testWillDisallowPluginInstantiation() : void
    {
        $this->expectException(\Throwable::class);

        new Verify();
    }
}
