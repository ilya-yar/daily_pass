<?php

declare(strict_types=1);

namespace Tests\Unit;

final class SourceHasNoPlatformPasswordTest extends \Codeception\Test\Unit
{
    public function testSrcAndConfigDoNotEmbedThePlatformPassword(): void
    {
        $password = getenv('PLATFORM_PASSWORD');
        $this->assertIsString($password);
        $this->assertNotSame('', $password);

        $roots = [
            dirname(__DIR__, 2) . '/src',
            dirname(__DIR__, 2) . '/config',
            dirname(__DIR__, 2) . '/migrations',
        ];
        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $contents = file_get_contents($file->getPathname());
                $this->assertIsString($contents);
                $this->assertStringNotContainsString(
                    $password,
                    $contents,
                    $file->getPathname() . ' must not embed PLATFORM_PASSWORD.',
                );
            }
        }
    }
}
