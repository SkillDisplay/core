<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Markus Klein
 **/

namespace SkillDisplay\Skills\Controller;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Service\ShortLinkService;
use TYPO3\CMS\Extbase\Http\ForwardResponse;

class ShortLinkController extends AbstractController
{
    public function __construct(
        UserRepository $userRepository,
        protected readonly ShortLinkService $shortLinkService
    ) {
        parent::__construct($userRepository);
    }

    public function handleAction(): ResponseInterface
    {
        $shortLinkHash = $this->request->getQueryParams()['regcode'] ?? null;
        if (!$shortLinkHash) {
            return $this->htmlResponse('No code specified');
        }
        try {
            $shortLink = $this->shortLinkService->handleShortlink($shortLinkHash);
            return (new ForwardResponse($shortLink['action']))
                ->withControllerName($shortLink['controller'])
                ->withArguments($shortLink['parameters']);
        } catch (InvalidArgumentException $e) {
            $this->logger->warning(
                'Invalid code in shortlink',
                ['exception' => $e, 'shortLinkHash' => $shortLinkHash, 'shortLink' => $shortLink ?? '']
            );
            return $this->htmlResponse('Your code has expired');
        }
    }
}
