<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Tests;

use AUS\SsiInclude\Register\LastRenderedContentRegister;
use AUS\SsiInclude\Utility\FilenameUtility;
use AUS\SsiInclude\ViewHelpers\RenderIncludeViewHelper;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class RenderIncludeViewHelperTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('invalidFragmentNames')]
    public function unsafeFragmentNamesAreRejectedBeforeRendering(string $name): void
    {
        $viewHelper = new RenderIncludeViewHelper(
            new Context(),
            new CacheManager(),
            new FilenameUtility(),
            new LastRenderedContentRegister(),
        );
        $viewHelper->setArguments(['name' => $name]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(sprintf('Only Alphanumeric characters allowed got: "%s"', $name));

        $viewHelper->render();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidFragmentNames(): array
    {
        return [
            'directory traversal' => ['../menu'],
            'absolute path' => ['/menu'],
            'empty name' => [''],
        ];
    }
}
