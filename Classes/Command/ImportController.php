<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Command;

use SkillDisplay\Skills\Service\Importer\ImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ImportController extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    #[\Override]
    protected function configure(): void
    {
        $this
            ->setDescription('Import data from json export file')
            ->addArgument(
                'pid',
                InputArgument::REQUIRED,
                'Specify the target page uid (sysfolder) for the imported data'
            )
            ->addArgument(
                'sourceFile',
                InputArgument::REQUIRED,
                'Specify the path to the source json file'
            )
            ->addOption(
                'mode',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify the mode for detected changes.'
                . "\n" . ' - "ask": Ask whether to import the record'
                . "\n" . ' - "ignore": Ignore all data from the import file which exist already'
                . "\n" . ' - "force": Overwrite all local data with the values from the import file',
                'ignore'
            )
            ->addOption(
                'validate',
                null,
                InputOption::VALUE_NONE,
                'Only validate the source file'
            )
            ->addUsage('[--mode=<ask|ignore|force>|--validate] 456 skillsets.json');
    }

    /**
     * Executes the command for adding the lock file
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceFile = $input->getArgument('sourceFile');
        $pid = (int)$input->getArgument('pid');
        $validate = $input->getOption('validate');
        $mode = $input->getOption('mode');

        switch ($mode) {
            case 'ignore':
                $useMode = ImportService::RESOLVE_IGNORE;
                break;
            case 'force':
                $useMode = ImportService::RESOLVE_FORCE;
                break;
            case 'ask':
                $useMode = ImportService::RESOLVE_ASK;
                break;
            default:
                $output->writeln('unknown resolve mode. allowed values are ignore, force, ask');
                return Command::INVALID;
        }

        if (!$validate && $pid <= 0) {
            $output->writeln('page uid must be greater than 0');
            return Command::INVALID;
        }

        $importService = GeneralUtility::makeInstance(ImportService::class, new SymfonyStyle($input, $output));
        if ($validate) {
            $result = $importService->validate($sourceFile);
            if ($result) {
                $output->writeln('Source file is valid.');
            } else {
                $output->writeln('Invalid source file!');
            }
            return $result ? Command::SUCCESS : 300;
        }
        $importService->doImport($sourceFile, 1, $pid, $useMode);
        return Command::SUCCESS;
    }
}
