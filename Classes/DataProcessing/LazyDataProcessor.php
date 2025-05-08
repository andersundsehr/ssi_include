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

    /**
     * Process content object data
     *
     * @param ContentObjectRenderer $cObj The data of the content element or page
     * @param array<mixed> $contentObjectConfiguration The configuration of Content Object
     * @param array<mixed> $processorConfiguration The configuration of this processor
     * @param array<mixed> $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array<mixed> the processed data as key/value store
     */
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData): array
    {
        $realProcessedData = 'LazyDataProcessor $realProcessedData';
        $variables = $processorConfiguration['variables'] ?? ''; // given variable names to proxy
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
