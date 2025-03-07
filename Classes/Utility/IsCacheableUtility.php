<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Utility;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Utility to check if the page can be cached.
 * With the drop of TYPO3 v11 support, the parameters can be dropped along with the registration in ext_localconf.php
 */
class IsCacheableUtility
{
    public function usePageCache(?TypoScriptFrontendController $typoScriptFrontendController = null, bool $usePageCache = true): bool
    {
        if (!$usePageCache) {
            return false;
        }

        $isDisabled = (bool)GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('ssi_include', 'disabled');
        if ($isDisabled) {
            return $usePageCache;
        }

        $context = GeneralUtility::makeInstance(Context::class);
        assert($context instanceof Context);
        $backendUserContext = $context->getAspect('backend.user');
        if ($backendUserContext->isLoggedIn()) {
            return false;
        }

        return $usePageCache;
    }
}
