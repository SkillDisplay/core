<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Command;

use SkillDisplay\Skills\Service\StatisticsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class StatisticsController extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Generates statistics');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $service = GeneralUtility::makeInstance(StatisticsService::class);
        $service->run();
        $service->calculateOrganisationStatistics();
        $service->calculateUserActivityStatistics();
        return Command::SUCCESS;
    }
}
