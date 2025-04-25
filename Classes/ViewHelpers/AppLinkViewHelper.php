<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) Reelworx GmbH
 **/

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
    public function initializeArguments(): void
    {
        $this->registerArgument('url', 'string', 'The URL of the App', true);
        $this->registerArgument('appRoute', 'string', 'Route in App', true);
        $this->registerArgument('class', 'string', 'Additional css classes');
        $this->registerArgument('languageUid', 'int', 'Language UID');
        $this->registerArgument('onlyUri', 'bool', 'When true just return the uri');
    }

    public function render()
    {
        // todo reimplement when App is multilanguage
        if (isset($this->arguments['languageUid'])) {
            $language = (int)$this->arguments['languageUid'];
            if ($language > 0) {
                $arguments = [ 'L' => $language];
            }
        }

        $url = rtrim((string)$this->arguments['url'], '/') . '/' . $this->arguments['appRoute'];

        if ($this->arguments['onlyUri']) {
            return $url;
        }

        $this->tag->addAttribute('href', $url);
        if (!empty($this->arguments['class'])) {
            $this->tag->addAttribute('class', $this->arguments['class']);
        }
        $this->tag->setContent($this->renderChildren());
        $this->tag->forceClosingTag(true);
        return $this->tag->render();
    }
}
