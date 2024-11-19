<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Middleware;

use AUS\SsiInclude\ViewHelpers\RenderIncludeViewHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\Uri;

class InternalSsiRedirectMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (isset($request->getQueryParams()['ssi_include'])) {
            $originalRequestUri = new Uri($request->getQueryParams()['originalRequestUri'] ?? '');
            $ssiInclude = $request->getQueryParams()['ssi_include'];
            if (!preg_match('/^(\w+)$/', (string) $ssiInclude)) {
                return new HtmlResponse('ssi_include invalid', 400);
            }

            $cacheFileName = RenderIncludeViewHelper::SSI_INCLUDE_DIR . $ssiInclude;
            $absolutePath = Environment::getPublicPath() . $cacheFileName;
            if (!file_exists($absolutePath)) {
                $subRequest = $request
                    ->withAttribute('noCache', true)
                    ->withQueryParams([])
                    ->withUri($request->getUri()->withPath($originalRequestUri->getPath())->withQuery($originalRequestUri->getQuery()));
                // ignore the response and instead use the content of the file
                $handler->handle($subRequest);
            }

            return new HtmlResponse(file_get_contents($absolutePath) ?: '<error>EXT:ssi_include error path:' . $absolutePath . '</error>');
        }

        return $handler->handle($request);
    }
}
