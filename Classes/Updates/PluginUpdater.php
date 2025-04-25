<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Updates;

use Doctrine\DBAL\ArrayParameterType;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('skillsPluginUpdater')]
class PluginUpdater implements UpgradeWizardInterface
{
    protected const array MIGRATION_SETTINGS = [
        'skills_organisations' => 'skills_organisations',
        'skills_skills' => 'skills_skills',
        'skills_shortlink' => 'skills_shortlink',
        'skills_routing' => 'skills_routing',
        'skills_api' => 'skills_api',
        'skills_anonymous' => 'skills_anonymous',
        'skills_users' => [
            [
                'switchableControllerActions' => 'User->new;User->create;User->confirm;User->success;User->terms;User->acceptTerms',
                'targetCType' => 'skills_userregister',
            ],
            [
                'switchableControllerActions' => 'User->edit;User->update;User->updateEmail;User->updatePassword;User->confirmEmail;User->terms;User->acceptTerms',
                'targetCType' => 'skills_useredit',
            ],
        ],
    ];

    public function __construct(
        protected FlexFormService $flexFormService,
        protected FlexFormTools $flexFormTools,
        protected ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return 'Skills Migrate plugins';
    }

    public function getDescription(): string
    {
        $plugins = count($this->getMigrationRecords());
        $begroups = $this->hasBackendUserGroupsToUpdate();

        $description = 'The old plugin using switchableControllerActions has been split into separate plugins. List-Type plugins are migrated to CType. User permissions are migrated too.';

        if ($plugins) {
            $description .= 'This update wizard migrates all existing plugin settings and changes the plugin';
            $description .= 'to use the new plugins available. Count of plugins: ' . $plugins . ' ';
        }
        if ($begroups) {
            $description .= 'BE permissions will be migrated.';
        }
        return $description;
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    public function updateNecessary(): bool
    {
        return $this->getMigrationRecords() || $this->hasBackendUserGroupsToUpdate();
    }

    public function executeUpdate(): bool
    {
        $this->performMigration();
        $this->updateBackendUserGroups();
        return true;
    }

    public function performMigration(): void
    {
        $records = $this->getMigrationRecords();
        foreach ($records as $record) {
            $flexForm = $this->flexFormService->convertFlexFormContentToArray($record['pi_flexform']);
            $targetCtype = $this->getTargetCType($record['list_type'], $flexForm['switchableControllerActions'] ?? '');
            if ($targetCtype === '') {
                continue;
            }

            if (!empty($flexForm['switchableControllerActions'])) {
                // Update record with migrated types (this is needed because FlexFormTools
                // looks up those values in the given record and assumes they're up-to-date)
                $record['CType'] = $targetCtype;
                $record['list_type'] = '';

                // Clean up flexform
                $newFlexform = $this->flexFormTools->cleanFlexFormXML('tt_content', 'pi_flexform', $record);
                $flexFormData = GeneralUtility::xml2array($newFlexform);

                if ($flexFormData['data'] ?? false) {
                    // Remove flexform data which do not exist in flexform of new plugin
                    foreach ($flexFormData['data'] as $sheetKey => $sheetData) {
                        // Remove empty sheets
                        if (!count($flexFormData['data'][$sheetKey]['lDEF']) > 0) {
                            unset($flexFormData['data'][$sheetKey]);
                        }
                    }

                    if (count($flexFormData['data']) > 0) {
                        $newFlexform = $this->array2xml($flexFormData);
                    }
                }
            } else {
                $newFlexform = (string)$record['pi_flexform'];
            }

            $this->updateContentElement($record['uid'], $targetCtype, $newFlexform);
        }
    }

    protected function getMigrationRecords(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('uid', 'pid', 'CType', 'list_type', 'pi_flexform')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'CType',
                    $queryBuilder->createNamedParameter('list')
                ),
                $queryBuilder->expr()->in(
                    'list_type',
                    $queryBuilder->createNamedParameter(array_keys(static::MIGRATION_SETTINGS), ArrayParameterType::STRING)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    protected function getTargetCType(string $sourceListType, string $switchableControllerActions): string
    {
        // direct migration form Plugin to ContentElement
        if (!is_array(static::MIGRATION_SETTINGS[$sourceListType])) {
            return static::MIGRATION_SETTINGS[$sourceListType];
        }
        // migration of switchableControllerActions
        foreach (static::MIGRATION_SETTINGS[$sourceListType] as $setting) {
            if ($setting['switchableControllerActions'] === $switchableControllerActions) {
                return $setting['targetCType'];
            }
        }

        return '';
    }

    /**
     * Updates list_type and pi_flexform of the given content element UID
     *
     * @param int $uid
     * @param string $newCtype
     * @param string $flexform
     */
    protected function updateContentElement(int $uid, string $newCtype, string $flexform): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->update('tt_content')
            ->set('CType', $newCtype)
            ->set('list_type', '')
            ->set('pi_flexform', $flexform)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->executeStatement();
    }

    /**
     * Transforms the given array to FlexForm XML
     *
     * @param array $input
     * @return string
     */
    protected function array2xml(array $input = []): string
    {
        $options = [
            'parentTagMap' => [
                'data' => 'sheet',
                'sheet' => 'language',
                'language' => 'field',
                'el' => 'field',
                'field' => 'value',
                'field:el' => 'el',
                'el:_IS_NUM' => 'section',
                'section' => 'itemType',
            ],
            'disableTypeAttrib' => 2,
        ];
        $spaceInd = 4;
        $output = GeneralUtility::array2xml($input, '', 0, 'T3FlexForms', $spaceInd, $options);
        return '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>' . "\n" . $output;
    }

    protected function hasBackendUserGroupsToUpdate(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $queryBuilder->getRestrictions()->removeAll();

        $searchConstraints = [];
        foreach (array_keys(static::MIGRATION_SETTINGS) as $listTyp) {
            $searchConstraints[] = $queryBuilder->expr()->like(
                'explicit_allowdeny',
                $queryBuilder->createNamedParameter(
                    '%' . $queryBuilder->escapeLikeWildcards('tt_content:list_type:' . $listTyp) . '%'
                )
            );
        }

        $queryBuilder
            ->count('uid')
            ->from('be_groups')
            ->where(
                $queryBuilder->expr()->or(...$searchConstraints),
            );

        return (bool)$queryBuilder->executeQuery()->fetchOne();
    }

    protected function updateBackendUserGroups(): void
    {
        $connection = $this->connectionPool->getConnectionForTable('be_groups');

        /**
         * @var string $listType
         * @var string|string[] $contentTypeMigration
         */
        foreach (static::MIGRATION_SETTINGS as $listType => $contentTypeMigration) {
            if (is_array($contentTypeMigration)) {
                $contentTypes = array_column($contentTypeMigration, 'targetCType');
            } else {
                $contentTypes = [$contentTypeMigration];
            }

            foreach ($this->getBackendUserGroupsToUpdate($listType) as $record) {
                $fields = GeneralUtility::trimExplode(',', $record['explicit_allowdeny'], true);
                foreach ($fields as $key => $field) {
                    if ($field === 'tt_content:list_type:' . $listType) {
                        unset($fields[$key]);
                        foreach ($contentTypes as $contentType) {
                            $fields[] = 'tt_content:CType:' . $contentType;
                        }
                    }
                }

                $connection->update(
                    'be_groups',
                    [
                        'explicit_allowdeny' => implode(',', array_unique($fields)),
                    ],
                    ['uid' => (int)$record['uid']]
                );
            }
        }
    }

    protected function getBackendUserGroupsToUpdate(string $listType): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->select('uid', 'explicit_allowdeny')
            ->from('be_groups')
            ->where(
                $queryBuilder->expr()->like(
                    'explicit_allowdeny',
                    $queryBuilder->createNamedParameter(
                        '%' . $queryBuilder->escapeLikeWildcards('tt_content:list_type:' . $listType) . '%'
                    )
                ),
            );
        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }
}
