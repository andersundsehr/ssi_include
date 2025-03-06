<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Tests;

use AUS\SsiInclude\Cache\Frontend\SsiIncludeCacheFrontend;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class SsiIncludeCacheFrontendTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    /**
     * @test
     */
    public function validFilenamesPass(): void
    {
        $validFilenames = [
            'index.html',
            'main_0_mainMenu.html',
            'test-File_1.html',
            '123-file.html',
            'valid_filename.html',
        ];

        foreach ($validFilenames as $filename) {
            self::assertMatchesRegularExpression(SsiIncludeCacheFrontend::PATTERN_ENTRYIDENTIFIER, $filename, 'Failed for: ' . $filename);
        }
    }

    /**
     * @test
     */
    public function invalidFilenamesFail(): void
    {
        $invalidFilenames = [
            '/index.html',
            'index.html/',
            'index.txt',
            'indexhtml',
            'index..html',
            '.html',
            'file.html.php',
            'file name.html',
        ];

        foreach ($invalidFilenames as $filename) {
            self::assertDoesNotMatchRegularExpression(SsiIncludeCacheFrontend::PATTERN_ENTRYIDENTIFIER, $filename, 'Failed for: ' . $filename);
        }
    }
}
