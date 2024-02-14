<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Command;

use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Service\SkillSetRelationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\NotImplementedException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class RecommendedSkillSetsController extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Updates SkillSet recommendation scores');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws InvalidQueryException
     * @throws NotImplementedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $registry = GeneralUtility::makeInstance(Registry::class);
        $service = GeneralUtility::makeInstance(SkillSetRelationService::class);
        $userRepository = GeneralUtility::makeInstance(UserRepository::class);
        $skillSetRepo = GeneralUtility::makeInstance(SkillPathRepository::class);
        $pm = GeneralUtility::makeInstance(PersistenceManager::class);

        $service->calculatePopularityForSets();

        // todo add locking here
        $setUids = $registry->get('skills', SkillSetRelationService::REGISTRY_SKILL_SETS, []);
        if ($setUids) {
            $users = $userRepository->findAll();
            /** @var User $user */
            foreach ($users as $user) {
                if ($user->isAnonymous()) {
                    continue;
                }
                foreach ($setUids as $skillSetUid) {
                    $skillSet = $skillSetRepo->findByUid($skillSetUid);
                    if ($skillSet) {
                        $service->updateScoreWithSet($user, $skillSet);
                    } else {
                        $output->writeln('SkillSet does not exist: ' . $skillSetUid);
                    }
                }
                $pm->persistAll();
                $pm->clearState();
                gc_collect_cycles();
            }
        }
        $registry->remove('skills', SkillSetRelationService::REGISTRY_SKILL_SETS);

        $userIds = $registry->get('skills', SkillSetRelationService::REGISTRY_USERS, []);
        foreach ($userIds as $skillSetUid) {
            $user = $userRepository->findByUid($skillSetUid);
            if ($user) {
                $service->calculateByUser($user);
            }
        }
        $registry->remove('skills', SkillSetRelationService::REGISTRY_USERS);

        return Command::SUCCESS;
    }
}
