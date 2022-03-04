<?php

declare(strict_types=1);

namespace AUS\SsiInclude\DataProcessing;

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
            $processedData[$variableName] = new Proxy(function () use ($cObj, $processedData, $processorConfiguration, &$realProcessedData, $variableName) {
                if ($realProcessedData === 'LazyDataProcessor $realProcessedData') {
                    $configuration = ['dataProcessing.' => [
                        '10' => $processorConfiguration['proxiedProcessor'],
                        '10.' => $processorConfiguration['proxiedProcessor.'],
                    ]];
                    $microTimeStart = microtime(true);
                    $realProcessedData = $this->contentDataProcessor->process($cObj, $configuration, $processedData);
                    $microTimeEnd = microtime(true);
                    debug($microTimeEnd - $microTimeStart, $variableName);
                    debug($microTimeEnd - $microTimeStart, $variableName);
                    debug($microTimeEnd - $microTimeStart, $variableName);
//                    throw new \Exception();
                }
                return $realProcessedData[$variableName] ?? null;
            });
        }
        return $processedData;
    }
}


final class Proxy implements \Iterator, \Countable
{
    private ?\Closure $callback = null;
    /** @var mixed */
    private $value;

    public function __construct(\Closure $callback)
    {
        $this->callback = $callback;
    }

    private function _processRealInstance(): void
    {
        $callback = $this->callback;
        if ($callback) {
            $this->callback = null;
            $this->value = $callback();
        }
    }

    public function __call($name, $arguments)
    {
        $this->_processRealInstance();
        return call_user_func([$this->value, $name], $arguments);
    }

    public function __invoke(...$arguments)
    {
        $this->_processRealInstance();
        return call_user_func($this->value, $arguments);
    }

    public function __isset($name)
    {
        $this->_processRealInstance();
        return isset($this->value[$name]);
    }

    public function __get($name)
    {
        $this->_processRealInstance();
        return $this->value[$name];
    }

    public function __set($name, $value)
    {
        $this->_processRealInstance();
        $this->value[$name] = $value;
    }

    public function __unset($name)
    {
        $this->_processRealInstance();
        unset($this->value[$name]);
    }

    public function __toString()
    {
        $this->_processRealInstance();
        return $this->value->__toString();
    }

    public function current()
    {
        $this->_processRealInstance();
        return current($this->value);
    }

    public function next()
    {
        $this->_processRealInstance();
        return next($this->value);
    }

    public function key()
    {
        $this->_processRealInstance();
        return key($this->value);
    }

    public function valid()
    {
        return $this->current() !== false;
    }

    public function rewind()
    {
        $this->_processRealInstance();
        return reset($this->value);
    }

    public function count()
    {
        $this->_processRealInstance();
        return count($this->value);
    }
}
