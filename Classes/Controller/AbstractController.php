<?php declare(strict_types=1);
/*
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Reelworx GmbH <office@reelworx.at>
 *
 */

namespace SkillDisplay\Skills\Controller;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Reelworx\TYPO3\MailService\ExtbaseMailTrait;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Mvc\View\JsonView;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

abstract class AbstractController extends ActionController implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use ExtbaseMailTrait;

    protected bool $isAjax = false;

    /**
     * {inherit}
     */
    protected function resolveView()
    {
        // if the action name contains "Ajax" we exchange the view
        $this->isAjax = strpos($this->actionMethodName, 'Ajax') || $this->request->getFormat() === 'json';
        if ($this->isAjax) {
            $this->defaultViewObjectName = JsonView::class;
        }
        return parent::resolveView();
    }

    /**
     * {inherit}
     */
    protected function callActionMethod()
    {
        try {
            parent::callActionMethod();
        } catch (AuthenticationException $e) {
            $this->response->setStatus(403);
        }
        if ($this->isAjax) {
            $this->response->setHeader('Access-Control-Allow-Origin', $_SERVER['HTTP_ORIGIN']);
            $this->response->setHeader('Access-Control-Allow-Credentials', 'true');
            $this->response->setHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Avoid error flash messages
     *
     * @return bool
     */
    protected function getErrorFlashMessage()
    {
        return false;
    }

    protected function getCurrentUser(bool $validateTerms = true, string $apiKey = ''): ?User
    {
        $user = null;
        if ($apiKey) {
            /** @var User $user */
            $user = $this->objectManager->get(UserRepository::class)->findByApiKey($apiKey);
        } else {
            /** @var UserAspect $userAspect */
            $userAspect = GeneralUtility::makeInstance(Context::class)->getAspect('frontend.user');
            if ($userAspect->isUserOrGroupSet()) {
                /** @var User $user */
                $user = $this->objectManager
                    ->get(UserRepository::class)
                    ->findByIdentifier($userAspect->get('id'));
            }
        }
        if ($user) {
            if (!$this->isAjax && $validateTerms && !$user->isTermsAccepted() && !$user->isAnonymous()) {
                $this->redirect('terms', 'User', null,
                    ['redirect' => GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL')],
                    $this->settings['pids']['registration']);
            }
            $user->setAdminGroupId((int)$this->settings['adminUserGroup']);
        }
        return $user;
    }

    protected function getCurrentBrand(string $apiKey): ?Brand
    {
        if (!$apiKey) {
            return null;
        }
        return $this->objectManager->get(BrandRepository::class)->findByApiKey($apiKey);
    }

    /**
     * @param $object
     * @throws ImmediateResponseException
     * @throws PageNotFoundException
     */
    protected function assertEntityAvailable($object): void
    {
        if ($object === null) {
            $response = GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction(
                $GLOBALS['TYPO3_REQUEST'],
                'Resource not found',
            );
            throw new ImmediateResponseException($response);
        }
    }

    protected function getTSFE(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }
}
