<?php

/*
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * Copyright (c) Reelworx GmbH
 *
 */

namespace SkillDisplay\Skills\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

class AppLinkViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'a';

    /**
     * Arguments initialization
     */
    public function initializeArguments()
    {
        $this->registerArgument('url', 'string', 'The URL of the App', true);
        $this->registerArgument('appRoute', 'string', 'Route in App', true);
        $this->registerArgument('class', 'string', 'Additional css classes', false);
        $this->registerArgument('languageUid', 'int', 'Language UID', false);
        $this->registerArgument('onlyUri', 'bool', 'When true just return the uri', false);
    }

    /**
     * @return mixed|string
     */
    public function render()
    {
        # todo reimplement when App is multilanguage
        if (isset($this->arguments['languageUid'])) {
            $language = (int)$this->arguments['languageUid'];
            if ($language > 0) {
                $arguments = [ 'L' => $language];
            }
        }

        $url = rtrim($this->arguments['url'], '/') . '/';
        if ($url !== '') {
            $url .= $this->arguments['appRoute'];

            if ($this->arguments['onlyUri']) {
                return $url;
            }

            $this->tag->addAttribute('href', $url);
            if (!empty($this->arguments['class'])) {
                $this->tag->addAttribute('class', $this->arguments['class']);
            }
            $this->tag->setContent($this->renderChildren());
            $this->tag->forceClosingTag(true);
            $result = $this->tag->render();
        } else {
            $result = $this->renderChildren();
        }
        return $result;
    }
}
