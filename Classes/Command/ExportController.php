<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Command;

use SkillDisplay\Skills\Service\Importer\ExportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ExportController extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this
            ->setDescription('Export a given list of SkillSets to a file')
            ->addArgument(
                'skillSets',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'Specify the list of SkillSet UIDs to export'
            )
            ->addOption(
                'output',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify the target file name',
                'skillsets.json'
            )
            ->addUsage('--output=skillsets_demo.json 1 2 4');
    }

    /**
     * Executes the command for adding the lock file
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $skillSets = $input->getArgument('skillSets');
        $targetFileName = $input->getOption('output');
        $exportService = GeneralUtility::makeInstance(ExportService::class);
        $exportService->doExport($targetFileName, $skillSets);
        return Command::SUCCESS;
    }
}
