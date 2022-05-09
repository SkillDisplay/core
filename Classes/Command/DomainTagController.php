<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DomainTagController extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Set all tags as domain tag if they are currently used as domain tag');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var QueryBuilder $qb */
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_skills_domain_model_tag');

        $qb
            ->update('tx_skills_domain_model_tag')
            ->set('domain_tag', 1)
            ->where(
                $qb->expr()->in(
                    'uid',
                    'select domain_tag from tx_skills_domain_model_skill where domain_tag > 0 group by domain_tag'
                )
            )
            ->execute();
        return 0;
    }
}
