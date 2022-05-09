<?php declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Markus Klein
 *
 ***/

namespace SkillDisplay\Skills\Controller;

use SkillDisplay\Skills\Service\ShortLinkService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;

class ShortLinkController extends AbstractController
{
    protected ShortLinkService $shortLinkService;

    public function __construct(ShortLinkService $shortLinkService)
    {
        $this->shortLinkService = $shortLinkService;
    }

    /**
     * @return string|null
     * @throws StopActionException
     */
    public function handleAction()
    {
        $shortLinkHash = GeneralUtility::_GET('code');
        if (!$shortLinkHash) {
            return 'No code specified';
        }
        try {
            $shortLink = $this->shortLinkService->handleShortlink($shortLinkHash);
            $this->forward($shortLink['action'], $shortLink['controller'], null, $shortLink['parameters']);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Invalid code in shortlink', ['exception' => $e, 'shortLinkHash' => $shortLinkHash, 'shortLink' => $shortLink ?? '']);
            return 'Your code has expired';
        }
        return null;
    }
}
