<?php

namespace SkillDisplay\Skills\Service;

use PDO;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class VerifierPermissionService
{
    private const PERMISSIONS_TABLE = 'tx_skills_domain_model_certifierpermission';

    protected ObjectManager $objectManager;

    public function __construct(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param int[] $verifiers
     * @param int[] $skillSets
     * @param array $permissions
     * @return int
     * @throws Exception
     */
    public static function grantPermissions(array $verifiers, array $skillSets, array $permissions): int
    {
        $created = 0;
        $skills = self::loadSkills($skillSets);

        foreach ($skills as $skillId) {
            foreach ($verifiers as $verifierId) {
                self::updateOrCreatePermission($verifierId, $skillId, $permissions);
                $created += 1;
            }
        }

        return $created;
    }

    /**
     * @param int[] $verifiers
     * @param int[] $skillSets
     * @param array $permissions
     * @return int
     * @throws Exception
     */
    public static function revokePermissions(array $verifiers, array $skillSets, array $permissions): int
    {
        $revoked = 0;
        $skills = self::loadSkills($skillSets);

        foreach ($skills as $skillId) {
            foreach ($verifiers as $verifierId) {
                $currentPermission = self::fetchPermission($verifierId, $skillId);

                if ($currentPermission === []) {
                    continue;
                }

                $updateData = [];
                foreach ($permissions as $key => $value) {
                    $currentPermission[$key] = 0;
                    $updateData[$key] = 0;
                }

                if (self::permissionEmpty($currentPermission)) {
                    self::deletePermissionByUid((int)$currentPermission['uid']);
                } else {
                    self::updatePermission((int)$currentPermission['uid'], $updateData);
                }
                $revoked += 1;
            }
        }

        return $revoked;
    }

    /**
     * @param int[] $skillSets
     * @return int[]
     * @throws Exception
     */
    private static function loadSkills(array $skillSets): array
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var SkillPathRepository $skillPathRepository */
        $skillPathRepository = $objectManager->get(SkillPathRepository::class);
        $skills = [];
        foreach ($skillSets as $skillSetId) {
            $skillSet = $skillPathRepository->findByUid((int)$skillSetId);
            array_push($skills, ...$skillSet->getSkillIds());
        }
        return array_unique($skills);
    }

    private static function updateOrCreatePermission(int $verifierId, int $skillId, array $permissions): void
    {
        $currentPermission = self::fetchPermission($verifierId, $skillId);

        if ($currentPermission !== []) {
            self::updatePermission((int)$currentPermission['uid'], $permissions);
        } else {
            self::createPermission($verifierId, $skillId, $permissions);
        }
    }

    private static function deletePermissionByUid(int $uid): void
    {
        /** @var QueryBuilder $qb */
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::PERMISSIONS_TABLE);

        $qb
            ->delete(self::PERMISSIONS_TABLE)
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid, PDO::PARAM_INT))
            )
            ->execute();
    }

    private static function updatePermission(int $uid, array $permissions): void
    {
        /** @var QueryBuilder $qb */
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::PERMISSIONS_TABLE);

        $qb = $qb
            ->update(self::PERMISSIONS_TABLE)
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid, PDO::PARAM_INT))
            )
            ->set('tstamp', $GLOBALS['SIM_EXEC_TIME']);

        foreach ($permissions as $key => $value) {
            $qb = $qb->set($key, $value);
        }

        $qb->execute();
    }

    private static function createPermission(int $verifierId, int $skillId, array $permissions): void
    {
        /** @var QueryBuilder $qb */
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::PERMISSIONS_TABLE);

        $data = $permissions;
        $data['skill'] = $skillId;
        $data['certifier'] = $verifierId;
        $data['pid'] = self::fetchPidFromVerifier($verifierId);
        $data['tstamp'] = $GLOBALS['SIM_EXEC_TIME'];
        $data['crdate'] = $GLOBALS['SIM_EXEC_TIME'];
        $data['cruser_id'] = 1;
        $qb
            ->insert(self::PERMISSIONS_TABLE)
            ->values($data)
            ->execute();
    }

    private static function fetchPermission(int $verifierId, int $skillId): array
    {
        /** @var QueryBuilder $qb */
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::PERMISSIONS_TABLE);

        $permissionList = $qb
            ->select('*')
            ->from(self::PERMISSIONS_TABLE)
            ->where(
                $qb->expr()->eq('certifier', $qb->createNamedParameter($verifierId, PDO::PARAM_INT)),
                $qb->expr()->eq('skill', $qb->createNamedParameter($skillId, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAll();


        if (count($permissionList) > 0) {
            $currentPermission = array_shift($permissionList);

            // if there are multiple permissions present for same verifier/skill delete the remaining permissions
            if (count($permissionList) > 0) {
                self::unifyPermission($currentPermission, $permissionList);
            }

            return $currentPermission;
        }

        return [];
    }

    private static function unifyPermission(array $currentPermission, array $permissionList): void
    {
        $permissions = [
            'tier1' => (int)$currentPermission['tier1'],
            'tier2' => (int)$currentPermission['tier2'],
            'tier4' => (int)$currentPermission['tier4'],
        ];

        foreach ($permissionList as $deletePermission) {
            if ($deletePermission['tier1']) {
                $permissions['tier1'] = 1;
            }
            if ($deletePermission['tier2']) {
                $permissions['tier2'] = 1;
            }
            if ($deletePermission['tier3']) {
                $permissions['tier4'] = 1;
            }
            self::deletePermissionByUid((int)$deletePermission['uid']);
        }
        $permissions['pid'] = self::fetchPidFromVerifier((int)$currentPermission['certifier']);
        self::updatePermission((int)$currentPermission['uid'], $permissions);
    }

    private static function permissionEmpty(array $permission): bool
    {
        return (int)$permission['tier1'] === 0 && (int)$permission['tier2'] === 0 && (int)$permission['tier4'] === 0 ;
    }

    private static function fetchPidFromVerifier(int $verifierId): int
    {
        /** @var QueryBuilder $qb */
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_skills_domain_model_certifier');

        $result = $qb
            ->select('pid')
            ->from('tx_skills_domain_model_certifier')
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($verifierId, PDO::PARAM_INT))
            )
            ->execute()
            ->fetchAll();

        if (count($result) === 1) {
            return (int)$result[0]['pid'];
        }

        return 0;
    }
}
