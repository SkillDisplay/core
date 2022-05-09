<?php declare(strict_types=1);

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;

class ApiRouter implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var NormalizedParams $requestParams */
        $requestParams = $request->getAttribute('normalizedParams');
        $siteScript = $requestParams->getSiteScript();
        if (preg_match('#^(?:de/)?api/#', $siteScript)) {
            $get = $request->getQueryParams();
            $get['type'] = 1550667922;
            $get['tx_skills_api']['apiKey'] = $request->getHeader('x-api-key')[0] ?? '';
            $request = $request->withQueryParams($get);
        }
        return $handler->handle($request);
    }
}
