<?php

declare(strict_types=1);
/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Service;

use TYPO3\CMS\Core\Utility\CsvUtility;

class CsvService
{
    /**
     * Send the given lines as CSV file.
     * Separation character is semicolon
     * Encoding: iso-8859-1 or utf-8
     *
     * @param array $lines Lines to write to CSV file
     * @param string $filename Filename for the browser
     */
    public static function sendCSVFile(array $lines, string $filename): never
    {
        $lines = array_map(fn($line) =>
            //  disabled because of SKILL-1127
            // return mb_convert_encoding(CsvUtility::csvValues($line, ';'), 'iso-8859-1', 'utf-8');
            CsvUtility::csvValues($line, ';'), $lines);

        $content = implode(chr(13) . chr(10), $lines);

        $headers = [
            'Pragma' => 'public',
            'Expires' => 0,
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Description' => 'File Transfer',
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename,
            'Content-Transfer-Encoding' => 'binary',
            'Content-Length' => strlen($content),
        ];

        // send headers
        foreach ($headers as $header => $data) {
            header($header . ': ' . $data);
        }

        // Printing the content of the CSV lines
        echo $content;

        exit;
    }
}
