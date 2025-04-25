<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Command;

use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Service\SkillSetRelationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class RecommendedSkillSetsController extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    #[\Override]
    protected function configure(): void
    {
        $this->setDescription('Updates SkillSet recommendation scores');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var Registry $registry */
        $registry = GeneralUtility::makeInstance(Registry::class);
        /** @var SkillSetRelationService $service */
        $service = GeneralUtility::makeInstance(SkillSetRelationService::class);
        /** @var UserRepository $userRepository */
        $userRepository = GeneralUtility::makeInstance(UserRepository::class);
        /** @var SkillPathRepository $skillSetRepo */
        $skillSetRepo = GeneralUtility::makeInstance(SkillPathRepository::class);
        /** @var PersistenceManager $pm */
        $pm = GeneralUtility::makeInstance(PersistenceManager::class);

        $service->calculatePopularityForSets();

        // todo add locking here
        $setUids = $registry->get('skills', SkillSetRelationService::REGISTRY_SKILL_SETS, []);
        if ($setUids) {
            $users = $userRepository->findAllRecentlyLoggedIn(24);
            /** @var User $user */
            foreach ($users as $user) {
                if ($user->isAnonymous()) {
                    continue;
                }
                foreach ($setUids as $skillSetUid) {
                    /** @var ?SkillPath $skillSet */
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
        foreach ($userIds as $userId) {
            /** @var ?User $user */
            $user = $userRepository->findByUid($userId);
            if ($user) {
                $service->calculateByUser($user);
            }
        }
        $registry->remove('skills', SkillSetRelationService::REGISTRY_USERS);

        return Command::SUCCESS;
    }
}
