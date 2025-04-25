<?php

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

$columns = [
    'icon' => [
        'exclude' => true,
        'label' => 'Icon',
        'config' => [
            //## !!! Watch out for fieldName different from columnName
            'type' => 'file',
            'allowed' => $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'],
            'appearance' => [
                'createNewRelationLinkTitle' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:images.addFileReference',
            ],
            'foreign_match_fields' => [
                'fieldname' => 'icon',
                'tablenames' => 'sys_category',
            ],
            'overrideChildTca' => [
                'types' => [
                    0 => [
                        'showitem' => '
                            --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                            --palette--;;filePalette',
                    ],
                    File::FILETYPE_IMAGE => [
                        'showitem' => '
                            --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                            --palette--;;filePalette',
                    ],
                ],
            ],
            'maxitems' => 1,
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('sys_category', $columns);
ExtensionManagementUtility::addToAllTCAtypes(
    'sys_category',
    '--div--;Skill Options, icon',
    '',
    'after:parent'
);

$GLOBALS['TCA']['sys_category']['columns']['description']['label'] = 'Associated verification level (one number 1-4)';
unset($GLOBALS['TCA']['sys_category']['ctrl']['descriptionColumn']);
