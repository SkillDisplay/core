<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SkillCategoryFixController extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Ensures at least one category is assigned to skills and skillsets based on the primary brand.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->updateSkillSets();

        return 0;
    }

    private function updateSkillSets(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $qb = $connectionPool->getQueryBuilderForTable('tx_skills_domain_model_skillpath');

        // fetch all skillsets without category
        $catJoinCond = $qb->expr()->andX(
            $qb->expr()->eq('mm.uid_foreign', 'p.uid'),
            $qb->expr()->eq('mm.tablenames', '\'tx_skills_domain_model_skillpath\''),
            $qb->expr()->eq('mm.fieldname', '\'categories\''),
        );
        $setsWoCat = $qb->select('p.uid')
                        ->from('tx_skills_domain_model_skillpath', 'p')
                        ->leftJoin('p', 'sys_category_record_mm', 'mm', (string)$catJoinCond)
                        ->where($qb->expr()->isNull('mm.uid_local'))
                        ->groupBy('p.uid')
                        ->execute()
                        ->fetchAll();

        foreach ($setsWoCat as $skillSet) {
            // fetch first brand
            $brandQb = $connectionPool->getQueryBuilderForTable('tx_skills_domain_model_brand');
            $brandJoinCond = $brandQb->expr()->andX(
                $brandQb->expr()->eq('mm.uid_foreign', 'b.uid'),
                $brandQb->expr()->eq('mm.uid_local', $skillSet['uid'])
            );
            $brand = $brandQb->select('b.uid')
                             ->from('tx_skills_domain_model_brand', 'b')
                             ->join('b', 'tx_skills_skillset_brand_mm', 'mm', (string)$brandJoinCond)
                             ->orderBy('mm.sorting')
                             ->execute()
                             ->fetch();
            if ($brand) {
                // fetch first category of brand
                $category = $this->fetchCategoryOfBrand($brand['uid']);
                if ($category) {
                    // add to skillset
                    $connectionPool->getConnectionForTable('sys_category_record_mm')->insert(
                        'sys_category_record_mm',
                        [
                            'uid_local' => $category['uid'],
                            'uid_foreign' => $skillSet['uid'],
                            'tablenames' => 'tx_skills_domain_model_skillpath',
                            'fieldname' => 'categories',
                        ]
                    );
                }
            }
        }
    }

    private function fetchCategoryOfBrand(int $brandUid)
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $catQb = $connectionPool->getQueryBuilderForTable('tx_skills_domain_model_brand');
        $catJoinCond = $catQb->expr()->andX(
            $catQb->expr()->eq('mm.uid_local', 'c.uid'),
            $catQb->expr()->eq('mm.uid_foreign', $brandUid),
            $catQb->expr()->eq('mm.tablenames', '\'tx_skills_domain_model_brand\''),
            $catQb->expr()->eq('mm.fieldname', '\'categories\''),
        );
        $category = $catQb->select('c.uid')
                          ->from('sys_category', 'c')
                          ->join('c', 'sys_category_record_mm', 'mm', (string)$catJoinCond)
                          ->execute()
                          ->fetch();
        return $category;
    }
}
