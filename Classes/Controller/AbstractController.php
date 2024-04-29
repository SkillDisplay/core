<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Reelworx GmbH <office@reelworx.at>
 **/

namespace SkillDisplay\Skills\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Reelworx\TYPO3\MailService\ExtbaseMailTrait;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Mvc\View\JsonView;
use SkillDisplay\Skills\TermsException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

abstract class AbstractController extends ActionController implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use ExtbaseMailTrait;

    protected bool $isAjax = false;

    public function __construct(
        protected readonly UserRepository $userRepository
    ) {}

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

    protected function callActionMethod(RequestInterface $request): ResponseInterface
    {
        try {
            $response = parent::callActionMethod($request);
        } catch (AuthenticationException $e) {
            $response = new Response(statusCode: 403, reasonPhrase: $e->getMessage());
        } catch (TermsException $e) {
            $response = new RedirectResponse($this->addBaseUriIfNecessary($e->getUrl()), 303);
        }
        if ($this->isAjax) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $_SERVER['HTTP_ORIGIN'] ?? '*')
                ->withHeader('Access-Control-Allow-Credentials', 'true');
        }
        return $response;
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
            $user = $this->userRepository->findByApiKey($apiKey);
        } else {
            /** @var UserAspect $userAspect */
            $userAspect = GeneralUtility::makeInstance(Context::class)->getAspect('frontend.user');
            if ($userAspect->isUserOrGroupSet()) {
                /** @var User $user */
                $user = $this->userRepository->findByIdentifier($userAspect->get('id'));
            }
        }
        if ($user) {
            if (!$this->isAjax && $validateTerms && !$user->isTermsAccepted() && !$user->isAnonymous()) {
                $this->uriBuilder->reset()->setCreateAbsoluteUri(true)
                    ->setTargetPageUid((int)$this->settings['pids']['registration']);
                $uri = $this->uriBuilder->uriFor(
                    'terms',
                    ['redirect' => GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL')],
                    'User'
                );
                throw new TermsException($uri);
            }
        }
        return $user;
    }

    /**
     * @param object|null $object
     * @throws PageNotFoundException
     * @throws PropagateResponseException
     */
    protected function assertEntityAvailable(?Object $object): void
    {
        if ($object === null) {
            $response = GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction(
                $this->request,
                'Resource not found',
            );
            throw new PropagateResponseException($response);
        }
    }

    protected function createResponse(): ResponseInterface
    {
        return $this->view instanceof JsonView ? $this->jsonResponse() : $this->htmlResponse();
    }

    protected function getTSFE(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }
}
