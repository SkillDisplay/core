<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OptionMethod implements MiddlewareInterface
{
    /**
     * Send CORS header if request is done via OPTIONS method
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if (strtolower($request->getMethod()) === 'options') {
            // CORS request, answer as needed
            $response = GeneralUtility::makeInstance(Response::class);
            return $response
                ->withStatus(200)
                ->withHeader('Access-Control-Allow-Origin', $_SERVER['HTTP_ORIGIN'])
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Methods', 'POST, GET, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])
                ->withHeader('Access-Control-Max-Age', '86400');
        }

        return $handler->handle($request);
    }
}
