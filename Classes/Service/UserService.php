<?php declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein
 *
 ***/

namespace SkillDisplay\Skills\Service;

use SkillDisplay\Skills\Domain\Model\Password;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUserGroup;
use TYPO3\CMS\Extbase\Domain\Repository\FrontendUserGroupRepository;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class UserService
{
    /** @var UserRepository */
    protected $userRepository;

    /** @var FrontendUserGroupRepository */
    protected $userGroupRepository;

    /** @var int */
    protected $acceptedGroupId = 0;

    /** @var int */
    protected $storagePid = 0;

    public function __construct(FrontendUserGroupRepository $userGroupRepository, UserRepository $userRepository)
    {
        $this->userGroupRepository = $userGroupRepository;
        $this->userRepository = $userRepository;
    }

    public static function cleanupUser(int $userId): void
    {
        // delete verifications of user
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                                      ->getQueryBuilderForTable('tx_skills_domain_model_certification');

        $queryBuilder
            ->delete('tx_skills_domain_model_certification')
            ->where(
                $queryBuilder->expr()->eq('user', $queryBuilder->createNamedParameter($userId, Connection::PARAM_INT))
            )
            ->execute();

        $queryBuilder
            ->delete('tx_skills_domain_model_recommendedskillset')
            ->execute();
    }

    public function setAcceptedUserGroup($id): void
    {
        $this->acceptedGroupId = (int)$id;
    }

    public function setStoragePid($pid): void
    {
        $this->storagePid = (int)$pid;
    }

    public function add(User $user): void
    {
        $user->setPid($this->storagePid);
        $user->setDisable(true);
        $user->setPassword($this->encryptPassword($user->getPassword()));
        $this->userRepository->add($user);

        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $persistenceManager = $objectManager->get(PersistenceManager::class);
        $persistenceManager->persistAll();
    }

    public function activate(User $user)
    {
        /** @var FrontendUserGroup $usergroup */
        $usergroup = $this->userGroupRepository->findByUid($this->acceptedGroupId);
        $user->addUsergroup($usergroup);
        $user->setDisable(false);
        $this->userRepository->update($user);
    }

    public function activateEmail(User $user): void
    {
        $user->setEmail($user->getPendingEmail());
        $user->setUsername($user->getPendingEmail());
        $user->setPendingEmail('');
        $this->userRepository->update($user);
    }

    public function update(User $user, bool $updatePassword = false): void
    {
        if ($updatePassword) {
            $user->setPassword($this->encryptPassword($user->getPassword()));
        }
        $this->userRepository->update($user);
    }

    protected function encryptPassword(string $password) : string
    {
        $saltFactory = GeneralUtility::makeInstance(PasswordHashFactory::class);
        $salt = $saltFactory->getDefaultHashInstance('FE');
        return $salt->getHashedPassword($password);
    }

    public function validatePassword(Password $password): string
    {
        $feUserId = GeneralUtility::makeInstance(Context::class)->getAspect('frontend.user')->get('id');
        /** @var User $user */
        $user = $this->userRepository->findByUid($feUserId);
        if (!$user) {
            return 'No login user found.';
        }

        $saltFactory = GeneralUtility::makeInstance(PasswordHashFactory::class);
        $salt = $saltFactory->get($user->getPassword(), 'FE');
        if (!$salt->checkPassword($password->getOldPassword(), $user->getPassword())) {
            return 'The entered password was incorrect!';
        }
        $user->setPassword($password->getPassword());
        $user->setPasswordRepeat($password->getPasswordRepeat());
        if (empty($user->getPassword()) || empty($user->getPasswordRepeat())) {
            return 'The new password cannot be empty!';
        } elseif ($user->getPassword() !== $user->getPasswordRepeat()) {
            return 'The repeated password does not match!';
        } elseif (!preg_match('/\\A(?=\\D*\\d).{8,}/', $user->getPassword())) {
            // min 8 chars and a digit
            return 'The password does not match the criteria!';
        }

        return '';
    }
}
