<?php declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2018 Markus Klein
 *
 ***/

namespace SkillDisplay\Skills\Controller;

use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;

class StatisticsController extends AbstractController
{
    protected CertificationRepository $certificationRepository;

    public function __construct(CertificationRepository $repo)
    {
        $this->certificationRepository = $repo;
    }

    public function showAction()
    {
        $certs = $this->certificationRepository->findCompletedByOrganisationMembership(
            (int)$this->settings['brand'],
            new \DateTime('@' . $this->settings['from']),
            new \DateTime('@' . $this->settings['to'])
        );

        $levels = [
            3 => 0,
            4 => 0,
            2 => 0,
            1 => 0
        ];
        /** @var Certification $cert */
        foreach ($certs as $cert) {
            $levels[$cert->getLevelNumber()]++;
        }
        $this->view->assign('levels', $levels);
        $this->view->assign('max', (int)$this->settings['max']);
    }
}
