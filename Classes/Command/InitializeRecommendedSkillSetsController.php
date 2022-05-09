<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Command;

use Doctrine\DBAL\Exception;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Service\SkillSetRelationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

class InitializeRecommendedSkillSetsController extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Calculates recommended SkillSets for all users. Wipes all existing data.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws InvalidQueryException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $service = GeneralUtility::makeInstance(SkillSetRelationService::class);

        $service->wipe();

        $output->writeln('Calculation of popularity');
        $service->calculatePopularityForSets();

        $output->writeln('Calculation of scores');
        $users = GeneralUtility::makeInstance(UserRepository::class)->findAll();
        /** @var User $user */
        foreach ($users as $user) {
            if ($user->isAnonymous()) {
                continue;
            }
            $service->calculateByUser($user);
        }
        return 0;
    }
}
