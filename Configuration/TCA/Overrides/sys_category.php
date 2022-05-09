<?php
defined('TYPO3_MODE') || die();

$columns = [
    'icon' => [
        'exclude' => true,
        'label' => 'Icon',
        'config' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig(
            'icon',
            [
                'appearance' => [
                    'createNewRelationLinkTitle' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:images.addFileReference'
                ],
                'foreign_match_fields' => [
                    'fieldname' => 'icon',
                    'tablenames' => 'sys_category',
                    'table_local' => 'sys_file',
                ],
                'overrideChildTca' => [
                    'types' => [
                        0 => [
                            'showitem' => '
                            --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                            --palette--;;filePalette'
                        ],
                        \TYPO3\CMS\Core\Resource\File::FILETYPE_IMAGE => [
                            'showitem' => '
                            --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                            --palette--;;filePalette'
                        ],
                    ]
                ],
                'maxitems' => 1
            ],
            $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
        ),
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_category', $columns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('sys_category',
    '--div--;Skill Options, icon', '', 'after:parent');

$GLOBALS['TCA']['sys_category']['columns']['description']['label'] = 'Associated verification level (one number 1-4)';
unset($GLOBALS['TCA']['sys_category']['ctrl']['descriptionColumn']);
