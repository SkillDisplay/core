<?php

declare(strict_types=1);

/**
 * This file is part of the "Skills" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein <support@reelworx.at>, Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Hook;

use Doctrine\DBAL\Connection as ConnectionAlias;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Repository\RecommendedSkillSetRepository;
use SkillDisplay\Skills\Service\SkillCleanupService;
use SkillDisplay\Skills\Service\SkillSetRelationService;
use SkillDisplay\Skills\Service\TranslatedUuidService;
use SkillDisplay\Skills\Service\UserService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\Exception\AspectPropertyNotFoundException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DataHandlerHook
{
    public function processDatamap_preProcessFieldArray(
        &$incomingFieldArray,
        $table,
        $id,
        DataHandler $dataHandler
    ) {
        if ($table === 'tx_skills_domain_model_skill' && isset($incomingFieldArray['visibility']) &&
            (int)$incomingFieldArray['visibility'] === Skill::VISIBILITY_ORGANISATION) {
            if ($this->skillHasPublicSkillSet((int)$id)) {
                $incomingFieldArray['visibility'] = Skill::VISIBILITY_PUBLIC;
            }
        }

        if ($table === 'tx_skills_domain_model_skillpath' && isset($incomingFieldArray['visibility']) &&
            (int)$incomingFieldArray['visibility'] === SkillPath::VISIBILITY_PUBLIC) {
            if ($this->skillPathHasPrivateSkills((int)$id)) {
                $incomingFieldArray['visibility'] = SkillPath::VISIBILITY_ORGANISATION;
                $this->generateFlashMessage('Cannot make SkillSet public due to non-public skills', 'Warning', AbstractMessage::WARNING);
            }
        }
    }

    /**
     * @param string $status
     * @param string $table
     * @param int|string $id
     * @param array $fieldArray
     * @param DataHandler $dataHandler
     * @throws AspectNotFoundException
     * @throws AspectPropertyNotFoundException
     */
    public function processDatamap_postProcessFieldArray(string $status, string $table, int|string $id, array &$fieldArray, DataHandler $dataHandler)
    {
        if ($table === 'fe_users') {
            if (isset($fieldArray['username'])) {
                $fieldArray['email'] = $fieldArray['username'];
            } elseif (!empty($fieldArray['email'])) {
                $fieldArray['username'] = $fieldArray['email'];
            }
        }
        if ($table === 'tx_skills_domain_model_brand' && isset($fieldArray['credit_overdraw'])) {
            $newValue = $fieldArray['credit_overdraw'];
            $beUserId = GeneralUtility::makeInstance(Context::class)->getAspect('backend.user')->get('id');
            GeneralUtility::makeInstance(LogManager::class)
                          ->getLogger(__CLASS__)
                          ->info("Credit overdraw changed to $newValue for Brand ID $id by BE user ID $beUserId");
        }
        if ($table === 'tx_skills_domain_model_verificationcreditpack') {
            if ($status === 'new') {
                $fieldArray['current_points'] = $fieldArray['initial_points'];
                if ($fieldArray['price_charged'] == '0.00') {
                    $fieldArray['price_charged'] = $fieldArray['price'];
                }
            }
            if ($fieldArray['valid_thru'] && $fieldArray['valid_thru'] < $fieldArray['valuta']) {
                $this->generateFlashMessage('Valuta has to be before Valid thru!', 'Error', AbstractMessage::ERROR);
            }
            $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
            if ($fieldArray['initial_points'] == 0 && $fieldArray['valuta'] > $now) {
                $this->generateFlashMessage('Valuta cannot be in the future!', 'Error', AbstractMessage::ERROR);
            }
        }
        if (in_array($table, TranslatedUuidService::UUID_TABLES, true)) {
            if ($status === 'new' && !$dataHandler->isImporting) {
                // make sure there is no initial uuid when a new record is created
                // the afterDatabase hook will take care of setting a UUID
                $fieldArray['uuid'] = '';
            }
        }
    }

    private function generateFlashMessage(string $messageText, string $messsageTitle, $type): void
    {
        /** @var FlashMessage $message */
        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            $messageText,
            $messsageTitle,
            $type,
            true
        );
        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $flashMessageService->getMessageQueueByIdentifier()->enqueue($message);
    }

    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        string $id,
        array $fieldArray,
        DataHandler $dataHandler
    ) {
        $realId = $status === 'new' ? (int)$dataHandler->substNEWwithIDs[$id] : (int)$id;

        if (in_array($table, TranslatedUuidService::UUID_TABLES, true)) {
            if ($status === 'new' && empty($fieldArray['uuid'])) {
                if ($realId) {
                    $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                    if (empty($fieldArray['l10n_parent'])) {
                        $uuid = 'uuid()';
                    } else {
                        $uuid = $qb->quote(TranslatedUuidService::getUuidForTranslatedRecord($table, $fieldArray));
                    }
                    $qb->update($table)
                       ->set('uuid', $uuid, false)
                       ->where('uid = ' . $realId)
                       ->executeStatement();
                }
            }
        }

        if ($status === 'new' &&
            $table === 'tx_skills_domain_model_skill' &&
            $fieldArray['visibility'] === Skill::VISIBILITY_ORGANISATION
        ) {
            $defaultBrandIds = self::getDefaultBrandIdsOfBackendUser();
            if ($defaultBrandIds && !$this->fetchBrandIdsOfSkill($realId)) {
                $this->addBrandsToSkill($realId, $defaultBrandIds);
            }
        }

        // if this a pack to cover existing debt (aka no points are granted)
        if ($table === 'tx_skills_domain_model_verificationcreditpack') {
            $row = BackendUtility::getRecord($table, $realId);
            if ($row['initial_points'] > 0) {
                return;
            }
            $qb = GeneralUtility::makeInstance(ConnectionPool::class)
                                ->getQueryBuilderForTable('tx_skills_domain_model_verificationcreditusage');
            $usages = $qb->count('*')
                ->from('tx_skills_domain_model_verificationcreditusage')
                ->where(
                    $qb->expr()->eq('credit_pack', $qb->createNamedParameter($id, Connection::PARAM_INT)),
                )
                ->execute()->fetchOne();
            if (!$usages) {
                $this->balanceOpenVerifications($row);
            }
        }

        if ($table === 'tx_skills_domain_model_skillpath') {
            // fix assigned brands
            $defaultBrandIds = self::getDefaultBrandIdsOfBackendUser();
            if ($defaultBrandIds) {
                $brandIds = $this->fetchBrandIdsOfSkillSet($realId);
                // remove disallowed brands from set
                $removeIds = array_diff($brandIds, $defaultBrandIds);
                if ($removeIds) {
                    $this->removeBrandsFromSkillSet($realId, $removeIds);
                }
                // add default brands if set has no brands now
                if (!array_diff($brandIds, $removeIds)) {
                    $this->addBrandsToSkillSet($realId, $defaultBrandIds);
                }
            }
            // remember this id in registry for further asynchronous recommendation processing
            SkillSetRelationService::registerForUpdate(SkillSetRelationService::REGISTRY_SKILL_SETS, $realId);
        }

        if ($table === 'fe_users') {
            // remember this id in registry for further asynchronous recommendation processing
            SkillSetRelationService::registerForUpdate(SkillSetRelationService::REGISTRY_USERS, $realId);
        }
    }

    public function processCmdmap_deleteAction(string $table, $id)
    {
        switch ($table) {
            case 'tx_skills_domain_model_skill':
                (new SkillCleanupService((int)$id))->doCleanup();
                break;
            case 'fe_users':
                UserService::cleanupUser((int)$id);
                break;
            case 'tx_skills_domain_model_skillset':
                $repo = GeneralUtility::makeInstance(RecommendedSkillSetRepository::class);
                $repo->deleteRecommendations(0, $id, 0);
                $repo->deleteRecommendations(0, 0, $id);
                break;
        }
    }

    /**
     * @param int $skillId
     * @return int[]
     */
    private function fetchBrandIdsOfSkill(int $skillId): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
                            ->getQueryBuilderForTable('tx_skills_skill_brand_mm');

        return $qb
            ->select('uid_foreign')
            ->from('tx_skills_skill_brand_mm')
            ->where(
                $qb->expr()->eq('uid_local', $skillId)
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param int $skillSetId
     * @return int[]
     */
    private function fetchBrandIdsOfSkillSet(int $skillSetId): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
                            ->getQueryBuilderForTable('tx_skills_skillset_brand_mm');

        return $qb
            ->select('uid_foreign')
            ->from('tx_skills_skillset_brand_mm')
            ->where(
                $qb->expr()->eq('uid_local', $skillSetId)
            )
            ->executeQuery()
            ->fetchFirstColumn();
    }

    private function addBrandsToSkill(int $skillId, array $brandIds): void
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
                            ->getQueryBuilderForTable('tx_skills_skill_brand_mm');
        $qb->insert('tx_skills_skill_brand_mm');

        $count = 0;
        foreach ($brandIds as $brandId) {
            $qb->insert('tx_skills_skill_brand_mm')
               ->values([
                   'uid_local' => $skillId,
                   'uid_foreign' => $brandId,
                   'sorting' => $count++,
               ])
               ->executeStatement();
        }
    }

    private function addBrandsToSkillSet(int $skillSetId, array $brandIds): void
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
                            ->getQueryBuilderForTable('tx_skills_skillset_brand_mm');
        $qb->insert('tx_skills_skillset_brand_mm');

        $count = 0;
        foreach ($brandIds as $brandId) {
            $qb->values([
                'uid_local' => $skillSetId,
                'uid_foreign' => $brandId,
                'sorting' => $count++,
            ])
               ->executeStatement();
        }
    }

    private function removeBrandsFromSkillSet(int $skillSetId, array $brandIds): void
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
                            ->getQueryBuilderForTable('tx_skills_skillset_brand_mm');
        $qb->delete('tx_skills_skillset_brand_mm')
           ->where(
               $qb->expr()->eq('uid_local', $skillSetId),
               $qb->expr()->in('uid_foreign', $qb->createNamedParameter($brandIds, ConnectionAlias::PARAM_INT_ARRAY))
           )
           ->executeStatement();
    }

    private function skillHasPublicSkillSet(int $skillId): bool
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
                            ->getQueryBuilderForTable('tx_skills_domain_model_skillpath');

        $qb = $qb->select('sp.uid')
                 ->from('tx_skills_domain_model_skillpath', 'sp')
                 ->join(
                     'sp',
                     'tx_skills_skillpath_skill_mm',
                     'mm',
                     'mm.uid_local = sp.uid and mm.uid_foreign=' . $skillId
                 )
                 ->where(
                     $qb->expr()->eq('sp.visibility', SkillPath::VISIBILITY_PUBLIC)
                 );

        $result = $qb->executeQuery()->fetchAllAssociative();

        return count($result) > 0;
    }

    private function skillPathHasPrivateSkills(int $skillId): bool
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
                            ->getQueryBuilderForTable('tx_skills_domain_model_skillpath');

        $qb = $qb->select('sp.uid')
                 ->from('tx_skills_domain_model_skillpath', 'sp')
                 ->join('sp', 'tx_skills_skillpath_skill_mm', 'mm', 'mm.uid_local = sp.uid')
                 ->join('mm', 'tx_skills_domain_model_skill', 's', 'mm.uid_foreign = s.uid')
                 ->where(
                     $qb->expr()->eq('sp.uid', $skillId),
                     $qb->expr()->eq('s.visibility', Skill::VISIBILITY_ORGANISATION)
                 );

        $result = $qb->executeQuery()->fetchAllAssociative();

        return count($result) > 0;
    }

    private function balanceOpenVerifications(array $pack)
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
                            ->getQueryBuilderForTable('tx_skills_domain_model_certification');
        $result = $qb->select('c.*')
                     ->from('tx_skills_domain_model_certification', 'c')
                     ->leftJoin('c', 'tx_skills_domain_model_verificationcreditusage', 'u', 'u.verification = c.uid')
                     ->where(
                         $qb->expr()->eq('c.brand', $pack['brand']),
                         $qb->expr()->isNotNull('c.grant_date'),
                         $qb->expr()->isNull('u.uid')
                     )
                     ->orderBy('c.grant_date', 'ASC')
                     ->executeQuery();
        $qb->resetQueryParts();
        $qb->insert('tx_skills_domain_model_verificationcreditusage');

        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        $credit = $pack['price'];
        while ($verification = $result->fetchAssociative()) {
            if ($credit < $verification['price']) {
                continue;
            }

            $qb->values([
                'pid' => $verification['pid'],
                'crdate' => $now,
                'tstamp' => $now,
                'credit_pack' => $pack['uid'],
                'verification' => $verification['uid'],
                'points' => $verification['points'],
                'price' => $verification['price'],
            ])->executeStatement();
            $credit -= $verification['price'];
        }
    }

    /**
     * @return int[]
     */
    public static function getDefaultBrandIdsOfBackendUser(): array
    {
        $userTsConfig = $GLOBALS['BE_USER']->getTSConfig();
        $brandIdList = $userTsConfig['TCAdefaults.']['tx_skills_domain_model_skill.']['brands'] ?? '';
        if ($brandIdList) {
            return GeneralUtility::intExplode(',', $brandIdList);
        }
        return [];
    }

    public function clearProgressCache(array $params)
    {
        if ($params['table'] === 'tx_skills_domain_model_skillpath') {
            /** @var CacheManager $cacheManager */
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            $cacheManager->flushCachesByTag('tx_skills_domain_model_skillpath_' . $params['uid']);
        }
    }
}
