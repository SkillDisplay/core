<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Service\Importer;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractImportExportService
{
    protected const VERSION = '5';

    private static ?array $languageMapping = null;

    protected static function getLanguageMapping(): array
    {
        if (self::$languageMapping === null) {
            self::$languageMapping = [];
            $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_language');
            $results = $qb->select('*')->from('sys_language')->executeQuery();
            while ($row = $results->fetchAssociative()) {
                if ($row['language_isocode']) {
                    self::$languageMapping[(int)$row['uid']] = $row['language_isocode'];
                }
            }
            $results->free();
        }
        return self::$languageMapping;
    }

    protected static function emptyHeader(): string
    {
        return self::fileHeader(self::hash(''));
    }

    protected static function fileHeader(string $hash): string
    {
        return json_encode([
            'hash' => $hash,
            'version' => self::VERSION,
        ]);
    }

    protected static function isValidHeader(string $header, string $expectedHash): bool
    {
        if (empty($header) || empty($expectedHash)) {
            return false;
        }
        $header = json_decode($header, true);
        if (empty($header['version']) || empty($header['hash'])) {
            return false;
        }
        return $header['version'] === self::VERSION && $header['hash'] === $expectedHash;
    }

    protected static function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
