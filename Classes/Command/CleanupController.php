<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CleanupController extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    #[\Override]
    protected function configure(): void
    {
        $this->setDescription('Removes stale _mm records and removes invalid tag_mm records pointing to wrong languages.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);

        $clean_mm_statements = [
            'delete FROM `tx_skills_skill_tag_mm` WHERE not exists (select uid from tx_skills_domain_model_skill where uid_local = uid);',
            'delete FROM `tx_skills_skill_tag_mm` WHERE not exists (select uid from tx_skills_domain_model_tag where uid_foreign = uid);',
            'delete FROM `tx_skills_skill_brand_mm` WHERE not exists (select uid from tx_skills_domain_model_skill where uid_local = uid);',
            'delete FROM `tx_skills_skill_brand_mm` WHERE not exists (select uid from tx_skills_domain_model_brand where uid_foreign = uid);',
            'delete FROM `tx_skills_skillset_brand_mm` WHERE not exists (select uid from tx_skills_domain_model_skillpath where uid_local = uid);',
            'delete FROM `tx_skills_skillset_brand_mm` WHERE not exists (select uid from tx_skills_domain_model_brand where uid_foreign = uid);',
            'delete from `tx_skills_skillpath_skill_mm` WHERE not exists (select uid from tx_skills_domain_model_skillpath where uid_local = uid);',
            'delete from `tx_skills_skillpath_skill_mm` WHERE not exists (select uid from tx_skills_domain_model_skill where uid_foreign = uid);',
            'delete from `tx_skills_skillgroup_skill_mm` WHERE not exists (select uid from tx_skills_domain_model_skillgroup where uid_local = uid);',
            'delete from `tx_skills_skillgroup_skill_mm` WHERE not exists (select uid from tx_skills_domain_model_skill where uid_foreign = uid);',
            'delete FROM `tx_skills_user_brand_mm` WHERE not exists (select uid from fe_users where uid_local = uid);',
            'delete FROM `tx_skills_user_brand_mm` WHERE not exists (select uid from tx_skills_domain_model_brand where uid_foreign = uid);',
            'delete FROM `tx_skills_user_certifier_mm` WHERE not exists (select uid from fe_users where uid_local = uid);',
            'delete FROM `tx_skills_user_certifier_mm` WHERE not exists (select uid from tx_skills_domain_model_certifier where uid_foreign = uid);',
            'delete FROM `tx_skills_user_organisation_mm` WHERE not exists (select uid from fe_users where uid_local = uid);',
            'delete FROM `tx_skills_user_organisation_mm` WHERE not exists (select uid from tx_skills_domain_model_brand where uid_foreign = uid);',
            'delete FROM `tx_skills_patron_mm` WHERE not exists (select uid from tx_skills_domain_model_brand where uid_local = uid);',
            'delete FROM `tx_skills_patron_mm` WHERE not exists (select uid from tx_skills_domain_model_brand where uid_foreign = uid);',
        ];
        foreach ($clean_mm_statements as $statement) {
            $connection->executeStatement($statement);
        }

        // remove all relations from skills to translated tags
        $connection->executeStatement('delete tx_skills_skill_tag_mm
            FROM tx_skills_skill_tag_mm
                join tx_skills_domain_model_skill s on s.uid = tx_skills_skill_tag_mm.uid_local
                join tx_skills_domain_model_tag t on t.uid = tx_skills_skill_tag_mm.uid_foreign
            where s.sys_language_uid = 0 AND t.sys_language_uid > 0');
        return Command::SUCCESS;
    }
}
