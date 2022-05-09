<?php declare(strict_types=1);
/***
 *
 * This file is part of the "Skills" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Markus Klein <support@reelworx.at>, Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Service;

use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Central session handling class
 */
class SessionService
{
    const SESSION_KEY = 'skills_ses_';

    /**
     * Set session data.
     *
     * @param string $key Unique key
     * @param mixed $data Data to store
     *
     * @return void
     */
    public static function set(string $key, $data): void
    {
        self::getTypoScriptFrontendController()->fe_user->setAndSaveSessionData(self::SESSION_KEY . $key, $data);
    }

    /**
     * Get session data.
     *
     * @param string $key Key to look for
     *
     * @return mixed Data from session
     */
    public static function get(string $key)
    {
        return self::getTypoScriptFrontendController()->fe_user->getSessionData(self::SESSION_KEY . $key);
    }

    /**
     * Set session data for current user.
     *
     * @param string $key Unique key
     * @param mixed $data Data to store
     *
     * @return void
     */
    public static function setUser(string $key, $data)
    {
        self::getTypoScriptFrontendController()->fe_user->setKey('user', self::SESSION_KEY . $key, $data);
        self::getTypoScriptFrontendController()->fe_user->storeSessionData();
    }

    /**
     * Get session data from current user.
     *
     * @param string $key Key to look for
     *
     * @return mixed Data from session
     */
    public static function getUser(string $key)
    {
        return self::getTypoScriptFrontendController()->fe_user->getKey('user', self::SESSION_KEY . $key);
    }

    /**
     * Removes all session data
     */
    public static function removeSessionData(): void
    {
        self::getTypoScriptFrontendController()->fe_user->removeSessionData();
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected static function getTypoScriptFrontendController()
    {
        return $GLOBALS['TSFE'];
    }
}
