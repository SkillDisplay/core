<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Service;

use DateTime;
use RuntimeException;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BrandSetupService
{
    public const DEFAULT_BE_GROUP = 'Skill Editor - PARTNERDEFAULT';
    public const DEFAULT_BE_USER = 'backenduser-partnerdefault';
    public const BRANDS_BASE_PATH = 'Brands/';
    public const FAL_ID = 1;

    protected string $brandName = '';
    protected string $sysFolderName = '';
    protected string $brandDescription = '';
    protected string $categoryName = '';
    protected string $url = '';
    protected string $vatId = '';
    protected string $foreignId = '';
    protected string $stripeSubscription = '';

    protected int $now = 0;
    protected int $sysFolderUid = 0;
    protected int $pid = 0;
    protected int $backendUserGroupUid = 0;
    protected int $fileMountUid = 0;
    protected int $brandUid = 0;
    protected int $verificationLevel = 0;
    protected int $categoryUid = 0;

    protected ?Folder $brandFolder = null;
    protected ?Folder $parentFolder = null;

    protected ConnectionPool $connectionPool;

    /**
     * BrandSetupService constructor.
     *
     * The name for the sys folder is generated from brand name and has to be unique.
     * The category is used to lookup the parent sys folder + parent FAL folder. So if the category=Business you have
     * to make sure that a sys folder Business exists and
     * that a folder Business exists in Brands in the file storage with ID 1
     *
     * @param string $brandName
     * @param string $brandDescription
     * @param string $category determines parent folder for sys folder and fal folder
     * @param int $verificationLevel is used to search the correct category that has this number in the description field
     * @param string $url
     * @param string $vatId
     * @param string $foreignId
     */
    public function __construct(
        string $brandName,
        string $brandDescription,
        string $category,
        int $verificationLevel,
        string $url,
        string $vatId,
        string $foreignId
    ) {
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $this->brandName = $brandName;
        $this->brandDescription = $brandDescription;
        $this->categoryName = self::cleanupString($category);
        $this->url = $url;
        $this->now = time();
        $this->verificationLevel = $verificationLevel;
        $this->vatId = $vatId;
        $this->foreignId = $foreignId;
    }

    public function createBrand()
    {
        $this->parentFolder = static::fetchParentFileFolder($this->categoryName);
        $this->categoryUid = $this->fetchCategory($this->verificationLevel);
        $this->pid = $this->fetchPid($this->categoryName);
        $this->sysFolderName = static::generateSysFolderName($this->brandName);
        $this->sysFolderUid = $this->generateSysFolder();
        $this->generateBrand();

        $this->brandFolder = $this->generateFileFolder();
        $this->fileMountUid = $this->generateFileMount();
        $this->generateBackendGroup();
        $this->setSysFolderPermissions();
    }

    public function createBackendUser(string $email, string $firstName, string $lastName): array
    {
        $firstNameForUser = self::cleanupString($firstName);
        $lastNameForUser = self::cleanupString($lastName);
        $username = substr(trim(strtolower($firstNameForUser) . '.' . strtolower($lastNameForUser)), 0, 50);
        return $this->createBackendUserFromUsername($email, $username, $firstName . ' ' . $lastName);
    }

    public function createBackendUserFromUsername(string $email, string $username, string $realName): array
    {
        $passwordHashFactory = GeneralUtility::makeInstance(PasswordHashFactory::class);
        $hashedPassword = $passwordHashFactory->getDefaultHashInstance('BE')
                                              ->getHashedPassword(bin2hex(random_bytes(16)));

        $qb = $this->connectionPool->getQueryBuilderForTable('be_users');
        $existing = $qb->select('*')
                       ->from('be_users')
                       ->where(
                           $qb->expr()->eq('username', $qb->createNamedParameter($username))
                       )
                       ->executeQuery()
                       ->fetchAssociative();
        if ($existing) {
            return $this->updateBeUserGroups($existing);
        }

        $userFields = $this->fetchDefaultBackendUser();
        $userFields['username'] = $username;
        $userFields['password'] = $hashedPassword;
        $userFields['admin'] = 0;
        $userFields['tstamp'] = $this->now;
        $userFields['crdate'] = $this->now;
        $userFields['email'] = $email;
        $userFields['realName'] = $realName;
        $userFields['disable'] = 0;
        $userFields['description'] = '';
        $userFields['usergroup'] = (string)$this->backendUserGroupUid;

        $connection = $this->connectionPool->getConnectionForTable('be_users');
        $connection->insert('be_users', $userFields);
        $userFields['uid'] = (int)$connection->lastInsertId('be_users');

        return $userFields;
    }

    private function updateBeUserGroups(array $user): array
    {
        $existingGroups = explode(',', $user['usergroup']);
        $foundGroup = false;
        foreach ($existingGroups as $existingGroup) {
            if ((int)$existingGroup === $this->backendUserGroupUid) {
                $foundGroup = true;
                break;
            }
        }

        if (!$foundGroup) {
            $existingGroups[] = $this->backendUserGroupUid;
            $user['usergroup'] = implode(',', $existingGroups);
        }

        $qb = $this->connectionPool->getQueryBuilderForTable('be_users');
        $qb->update('be_users')
            ->set('usergroup', $user['usergroup'])
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($user['uid'], Connection::PARAM_INT))
            )
            ->executeStatement();

        return $user;
    }

    public function assignFeUserMembership(int $feUserId): void
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_skills_user_organisation_mm');
        $count = $qb->count('*')
            ->from('tx_skills_user_organisation_mm')
            ->where(
                $qb->expr()->eq('uid_local', $qb->createNamedParameter($feUserId, Connection::PARAM_INT)),
                $qb->expr()->eq('uid_foreign', $qb->createNamedParameter($this->brandUid, Connection::PARAM_INT))
            )->executeQuery()->fetchOne();
        if (!$count) {
            $qb->resetQueryParts();
            $qb->insert('tx_skills_user_organisation_mm')
                ->values([
                    'uid_local' => $feUserId,
                    'uid_foreign' => $this->brandUid,
                ])
                ->executeStatement();
        }
    }

    public function assignFeUserManager(int $feUserId): void
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_skills_user_brand_mm');
        $count = $qb->count('*')
                    ->from('tx_skills_user_brand_mm')
                    ->where(
                        $qb->expr()->eq('uid_local', $qb->createNamedParameter($feUserId, Connection::PARAM_INT)),
                        $qb->expr()->eq('uid_foreign', $qb->createNamedParameter($this->brandUid, Connection::PARAM_INT))
                    )->executeQuery()->fetchOne();
        if (!$count) {
            $qb->resetQueryParts();
            $qb->insert('tx_skills_user_brand_mm')
               ->values([
                   'uid_local' => $feUserId,
                   'uid_foreign' => $this->brandUid,
               ])
               ->executeStatement();
        }
    }

    private function generateSysFolder(): int
    {
        $folder = $this->fetchFolder($this->sysFolderName);
        if ($folder) {
            return $folder;
        }

        $values = [
            'pid' => $this->pid,
            'title' => $this->sysFolderName,
            'doktype' => 254,
            'tstamp' => $this->now,
            'sorting' => 100,
            'perms_userid' => 0,
            'perms_groupid' => 0,
            'perms_user' => 0,
            'perms_group' => 0,
            'perms_everybody' => 0,
            'crdate' => $this->now,
            'cruser_id' => 1,
            'is_siteroot' => 0,
        ];

        $connection = $this->connectionPool->getConnectionForTable('pages');
        $connection->insert('pages', $values);
        return (int)$connection->lastInsertId('pages');
    }

    private function generateFileFolder(): Folder
    {
        /** @var StorageRepository $storageRepository */
        $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
        $storage = $storageRepository->findByUid(self::FAL_ID);
        if ($this->parentFolder->hasFolder($this->sysFolderName)) {
            return $this->parentFolder->getSubfolder($this->sysFolderName);
        }
        return $storage->createFolder($this->sysFolderName, $this->parentFolder);
    }

    private function generateBrand()
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_skills_domain_model_brand');
        $qb = $qb->select('uid')
            ->from('tx_skills_domain_model_brand')
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->andX(
                        $qb->expr()->eq('pid', $qb->createNamedParameter($this->sysFolderUid, Connection::PARAM_INT)),
                        $qb->expr()->eq('name', $qb->createNamedParameter($this->brandName))
                    )
                )
            );

        if (!empty($this->foreignId)) {
            $qb = $qb->orWhere(
                $qb->expr()->eq(
                    'foreign_id',
                    $qb->createNamedParameter($this->foreignId)
                )
            );
        }

        $existing = $qb->executeQuery()->fetchAssociative();

        if ($existing) {
            $this->brandUid = $existing['uid'];
            return;
        }
        $values = [
            'pid' => $this->sysFolderUid,
            'tstamp' => $this->now,
            'crdate' => $this->now,
            'name' => $this->brandName,
            'description' => $this->brandDescription,
            'url' => $this->url,
            'vat_id' => $this->vatId,
            'deleted' => 0,
            'hidden' => 0,
            'sys_language_uid' => 0,
            'api_key' => bin2hex(random_bytes(24)),
            'show_in_search' => 0,
            'foreign_id' => $this->foreignId,
            'stripe_subscription' => $this->stripeSubscription,
            'uuid' => CertoBot::uuid(),
        ];

        $connection = $this->connectionPool->getConnectionForTable('tx_skills_domain_model_brand');
        $connection->insert('tx_skills_domain_model_brand', $values);
        $this->brandUid = (int)$connection->lastInsertId('tx_skills_domain_model_brand');

        $categoryValues = [
            'uid_local' => $this->categoryUid,
            'uid_foreign' => $this->brandUid,
            'tablenames' => 'tx_skills_domain_model_brand',
            'fieldname' => 'categories',
        ];
        $this->connectionPool->getConnectionForTable('sys_category_record_mm')
                             ->insert('sys_category_record_mm', $categoryValues);
    }

    public function grantInitialCredit(int $points, array $user): void
    {
        if (!$this->brandUid) {
            return;
        }
        $connection = $this->connectionPool->getConnectionForTable('tx_skills_domain_model_verificationcreditpack');
        $qb = $connection->createQueryBuilder();
        $numberOfExistingPacks = $qb
            ->count('*')
            ->from('tx_skills_domain_model_verificationcreditpack')
            ->where(
                $qb->expr()->eq('brand', $qb->createNamedParameter($this->brandUid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchOne();

        // only create initial pack if none exists yet
        if ($numberOfExistingPacks) {
            return;
        }
        $values = [
            'tstamp' => $this->now,
            'crdate' => $this->now,
            'pid' => $this->sysFolderUid,
            'valuta' => $this->now,
            'valid_thru' => (new DateTime('first day of january next year'))->getTimestamp(),
            'brand' => $this->brandUid,
            'title' => 'Starter Pack',
            'current_points' => $points,
            'initial_points' => $points,
            'brand_name' => $this->brandName,
            'user_username' => $user['username'],
            'user_firstname' => $user['first_name'],
            'user_lastname' => $user['last_name'],
        ];
        $connection->insert('tx_skills_domain_model_verificationcreditpack', $values);
    }

    private function generateFileMount(): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_filemounts');
        $existing = $qb->select('uid')
                       ->from('sys_filemounts')
                       ->where(
                           $qb->expr()->eq('title', $qb->createNamedParameter($this->sysFolderName)),
                           $qb->expr()->eq('path', $qb->createNamedParameter($this->brandFolder->getReadablePath())),
                       )
                       ->executeQuery()
                       ->fetchAssociative();
        if ($existing) {
            return (int)$existing['uid'];
        }
        $values = [
            'pid' => 0,
            'tstamp' => $this->now,
            'title' => $this->sysFolderName,
            'description' => 'Default file mount for ' . $this->brandName,
            'base' => $this->brandFolder->getStorage()->getUid(),
            'path' => $this->brandFolder->getReadablePath(),
            'hidden' => 0,
            'deleted' => 0,
            'read_only' => 0,
        ];

        $connection = $this->connectionPool->getConnectionForTable('sys_filemounts');
        $connection->insert('sys_filemounts', $values);
        return (int)$connection->lastInsertId('sys_filemounts');
    }

    private function generateBackendGroup()
    {
        $groupFields = $this->fetchDefaultBackendGroup();
        $groupTitle = substr(str_replace('PARTNERDEFAULT', $this->sysFolderName, $groupFields['title']), 0, 50);

        $qb = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $existing = $qb->select('uid')
                       ->from('be_groups')
                       ->where(
                           $qb->expr()->eq('title', $qb->createNamedParameter($groupTitle)),
                           $qb->expr()->eq('db_mountpoints', $qb->createNamedParameter((string)$this->sysFolderUid)),
                       )
                       ->executeQuery()
                       ->fetchAssociative();
        if ($existing) {
            $this->backendUserGroupUid = (int)$existing['uid'];
            return;
        }

        $groupFields['title'] = $groupTitle;
        $groupFields['db_mountpoints'] = (string)$this->sysFolderUid;
        $groupFields['file_mountpoints'] = (string)$this->fileMountUid;
        $groupFields['TSconfig'] = 'defaultSkillStoragePid = ' . $this->sysFolderUid . "\nTCAdefaults.tx_skills_domain_model_skill.brands = " . $this->brandUid;
        $groupFields['hidden'] = 0;

        $connection = $this->connectionPool->getConnectionForTable('be_groups');
        $connection->insert('be_groups', $groupFields);
        $this->backendUserGroupUid = (int)$connection->lastInsertId('be_groups');
    }

    private function setSysFolderPermissions()
    {
        $this->connectionPool->getConnectionForTable('pages')->update(
            'pages',
            [
                'perms_groupid' => $this->backendUserGroupUid,
                'perms_group' => Permission::PAGE_SHOW | Permission::CONTENT_EDIT,
            ],
            ['uid' => $this->sysFolderUid]
        );
    }

    private function fetchDefaultBackendUser(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('be_users');
        $qb->getRestrictions()->removeAll();
        $result = $qb
            ->select('*')
            ->from('be_users')
            ->where(
                $qb->expr()->eq('username', $qb->createNamedParameter(self::DEFAULT_BE_USER))
            )
            ->executeQuery()->fetchAllAssociative();
        if (count($result) === 1) {
            unset($result[0]['uid']);
            return $result[0];
        }

        throw new RuntimeException('Could not fetch default BE user');
    }

    private function fetchDefaultBackendGroup(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $qb->getRestrictions()->removeAll();
        $statement = $qb
            ->select('*')
            ->from('be_groups')
            ->where(
                $qb->expr()->eq('title', $qb->createNamedParameter(self::DEFAULT_BE_GROUP))
            )
            ->executeQuery();
        $result = $statement->fetchAllAssociative();
        if (count($result) === 1) {
            unset($result[0]['uid']);
            return $result[0];
        }

        throw new RuntimeException('Could not fetch default BE Group');
    }

    private function fetchCategory(int $verificationLevel): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('sys_category');
        $statement = $qb
            ->select('uid')
            ->from('sys_category')
            ->where(
                $qb->expr()->eq('description', $qb->createNamedParameter($verificationLevel)),
            )
            ->executeQuery();
        $result = $statement->fetchAllAssociative();
        if (count($result) === 1) {
            return (int)$result[0]['uid'];
        }

        throw new RuntimeException('Failed to get category for level' . $verificationLevel);
    }

    private static function fetchParentFileFolder(string $categoryName): Folder
    {
        /** @var StorageRepository $storageRepository */
        $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
        $storage = $storageRepository->findByUid(self::FAL_ID);
        $folder = $storage->getFolder(self::BRANDS_BASE_PATH . $categoryName);

        if ($folder !== null) {
            return $folder;
        }

        throw new RuntimeException('Failed to get folder for ' . self::BRANDS_BASE_PATH . $categoryName);
    }

    private function fetchFolder(string $title): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $result = $qb
            ->select('uid')
            ->from('pages')
            ->where(
                $qb->expr()->eq('title', $qb->createNamedParameter($title)),
                $qb->expr()->eq('doktype', 254)
            )
            ->executeQuery()->fetchAllAssociative();
        if (count($result) === 1) {
            return (int)$result[0]['uid'];
        }
        return 0;
    }

    private function fetchPid(string $categoryName): int
    {
        $pid = $this->fetchFolder($categoryName);
        if ($pid > 0) {
            return $pid;
        }

        throw new RuntimeException('Parent folder for category ' . $categoryName . ' cannot be found');
    }

    private static function generateSysFolderName(string $brandName): string
    {
        $tmpName = ucwords(strtolower(self::cleanupString($brandName)));
        $tmpName = str_replace(' ', '', $tmpName);
        return substr($tmpName, 0, 255);
    }

    private static function cleanupString(string $cleanup): string
    {
        return trim(preg_replace('/[^A-Za-z0-9 ]+/', '', $cleanup));
    }

    /**
     * @return string
     */
    public function getStripeSubscription(): string
    {
        return $this->stripeSubscription;
    }

    /**
     * @param string $stripeSubscription
     */
    public function setStripeSubscription(string $stripeSubscription): void
    {
        $this->stripeSubscription = $stripeSubscription;
    }
}
