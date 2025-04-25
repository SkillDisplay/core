<?php

declare(strict_types=1);

/**
 * This file is part of the "Skills" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Johannes Kasberger <support@reelworx.at>, Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Service;

use RuntimeException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TranslatedUuidService
{
    public const array UUID_TABLES = [
        'tx_skills_domain_model_brand',
        'tx_skills_domain_model_link',
        'tx_skills_domain_model_skill',
        'tx_skills_domain_model_skillpath',
        'tx_skills_domain_model_tag',
    ];

    public static function getUuidForTranslatedRecord(string $table, array $fields): string
    {
        $langId = (int)($fields['sys_language_uid'] ?? 0);
        $parentId = (int)($fields['l10n_parent'] ?? 0);
        if (!$parentId || !$langId) {
            return '';
        }
        $isoCode = self::getLanguageIsoCode($langId);
        $parentUuid = self::getParentUuid($table, $parentId);
        return self::getTranslatedUuid($parentUuid, $isoCode);
    }

    public static function getTranslatedUuid(string $uuid, string $iso): string
    {
        if (empty($uuid) || empty($iso)) {
            throw new RuntimeException('invalid arguments for translated uuid generator', 6743119699);
        }
        return $uuid . '_' . $iso;
    }

    private static function getParentUuid(string $table, int $parent): string
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $records = $qb->select('uuid')
            ->from($table)
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($parent)))
            ->executeQuery()
            ->fetchAllAssociative();

        if (count($records) !== 1) {
            return '';
        }

        return $records[0]['uuid'];
    }

    public static function getLanguageIsoCode(int $languageUid): string
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_language');
        $languages = $qb->select('language_isocode')
            ->from('sys_language')
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($languageUid)))
            ->executeQuery()
            ->fetchAllAssociative();

        if (count($languages) !== 1) {
            return '';
        }

        return $languages[0]['language_isocode'];
    }
}
