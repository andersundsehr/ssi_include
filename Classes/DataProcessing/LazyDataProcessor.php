<?php

declare(strict_types=1);

namespace AUS\SsiInclude\DataProcessing;

use AUS\SsiInclude\Proxy\Proxy;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * usage:
 *
 */
class LazyDataProcessor implements DataProcessorInterface
{
    protected ContentDataProcessor $contentDataProcessor;

    public function __construct()
    {
        $this->contentDataProcessor = GeneralUtility::makeInstance(ContentDataProcessor::class);
    }

    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData)
    {
        $realProcessedData = 'LazyDataProcessor $realProcessedData';
        $variables = $processorConfiguration['variables']; // given variable names to proxy
        $variables .= ',' . $cObj->stdWrapValue('as', $processorConfiguration['proxiedProcessor.'] ?? [], ''); // invert variable name to proxy

        foreach (GeneralUtility::trimExplode(',', $variables, true) as $variableName) {
            // don't overwrite existing variables with proxies
            // your data processor should be implemented in a way that it doesn't overwrite existing variables
            if (!array_key_exists($variableName, $processedData)) {
                $processedData[$variableName] = new Proxy(function () use ($cObj, $processedData, $processorConfiguration, &$realProcessedData, $variableName) {
                    if ($realProcessedData === 'LazyDataProcessor $realProcessedData') {
                        $configuration = ['dataProcessing.' => [
                            '10' => $processorConfiguration['proxiedProcessor'],
                            '10.' => $processorConfiguration['proxiedProcessor.'],
                        ]];
                        $realProcessedData = $this->contentDataProcessor->process($cObj, $configuration, $processedData);
                    }
                    return $realProcessedData[$variableName] ?? null;
                });
            }
        }
        return $processedData;
    }
}

