<?php declare(strict_types=1);

/**
 *
 * This file is part of the "Skills" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Johannes Kasberger <support@reelworx.at>, Reelworx GmbH
 *
 **/

namespace SkillDisplay\Skills\Command;

use SkillDisplay\Skills\Service\GuestUserCleanupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GuestUserController extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setDescription('Cleanup old guest users');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        (new GuestUserCleanupService())->run();
        return 0;
    }
}
