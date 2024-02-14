<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;

class PortalController extends AbstractController
{
    public function linksAction(): ResponseInterface
    {
        if ($this->view instanceof JsonView) {
            $configuration = [
                'links' => [],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }

        $this->uriBuilder->setCreateAbsoluteUri(true);

        $links = [];
        foreach (['root', 'tour', 'registration', 'login'] as $name) {
            $links[$name] = $this->uriBuilder
                ->setTargetPageUid((int)$this->settings['pids'][$name])
                ->buildFrontendUri();
        }
        $links['logout'] = $this->uriBuilder
            ->setTargetPageUid((int)$this->settings['pids']['root'])
            ->setArguments(['logintype' => 'logout'])
            ->buildFrontendUri();

        $this->view->assign('links', $links);
        return $this->createResponse();
    }
}
