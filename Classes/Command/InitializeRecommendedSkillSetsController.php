<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Command;

use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Service\SkillSetRelationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class InitializeRecommendedSkillSetsController extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    #[\Override]
    protected function configure(): void
    {
        $this->setDescription('Calculates recommended SkillSets for all users. Wipes all existing data.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service = GeneralUtility::makeInstance(SkillSetRelationService::class);

        $service->wipe();

        $output->writeln('Calculation of popularity');
        $service->calculatePopularityForSets();

        $output->writeln('Calculation of scores');
        /** @var UserRepository $userRepository */
        $userRepository = GeneralUtility::makeInstance(UserRepository::class);
        $users = $userRepository->findAll();
        /** @var User $user */
        foreach ($users as $user) {
            if ($user->isAnonymous()) {
                continue;
            }
            $service->calculateByUser($user);
        }
        return Command::SUCCESS;
    }
}
