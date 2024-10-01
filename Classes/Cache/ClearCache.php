<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Cache;

use AUS\SsiInclude\ViewHelpers\RenderIncludeViewHelper;
use TYPO3\CMS\Core\Core\Environment;

class ClearCache
{
    /** @param array<mixed> $parameters */
    public function clearCache(array $parameters): void
    {
        if (isset($parameters['cacheCmd']) && ($parameters['cacheCmd'] === 'pages' || $parameters['cacheCmd'] === 'all')) {
            $path = Environment::getPublicPath() . RenderIncludeViewHelper::SSI_INCLUDE_DIR;
            $this->removeFiles($path);
        }
    }

    protected function removeFiles(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            if (!$objects) {
                return;
            }

            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    $filePath = $dir . DIRECTORY_SEPARATOR . $object;
                    if (is_file($filePath) && is_writable($filePath)) {
                        unlink($filePath);
                    }
                }
            }
        }
    }
}
