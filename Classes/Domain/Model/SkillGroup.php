<?php declare(strict_types=1);

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 ***/

namespace SkillDisplay\Skills\Domain\Model;

use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class SkillGroup extends AbstractEntity
{
    /** @var string */
    protected $name = '';

    /** @var string */
    protected $description = '';

    /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Skill> */
    protected $skills = null;

    /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Link> */
    protected $links = null;

    /** @var CertificationRepository */
    protected $certificationRepository = null;

    /** @var \SkillDisplay\Skills\Domain\Model\User */
    protected $user = null;

    /** @var string */
    protected $skillupCommentPlaceholder = '';

    /** @var string */
    protected $skillupCommentPreset = '';

    public function __construct()
    {
        $this->skills = new ObjectStorage();
        $this->links = new ObjectStorage();
    }

    public function injectCertificationRepository(CertificationRepository $certificationRepository)
    {
        $this->certificationRepository = $certificationRepository;
    }

    public function setUserForCompletedChecks(User $user): void
    {
        $this->user = $user;
        /** @var Skill $skill */
        foreach ($this->getSkills() as $skill) {
            $skill->setUserForCompletedChecks($user);
        }
    }

    public function getSkillGroupId(): string
    {
        return 'skillGroup-' . $this->getUid() . '-' . uniqid('group');
    }

    public function getCompletedInformation(): CertificationStatistics
    {
        $certStats = new CertificationStatistics();
        if (!$this->user) {
            return $certStats;
        }
        $certifications = $this->certificationRepository->findBySkillsAndUser($this->skills->toArray(), $this->user);
        foreach ($certifications as $cert) {
            $certStats->addCertification($cert);
        }
        // check if all skills are granted of the path, so the path itself is granted
        $certStats->removeVerificationsNotMatchingNumber('granted', $this->skills->count());
        $certStats->removeNonGroupRequests('pending', $this->uid);
        $certStats->seal();
        return $certStats;
    }

    /**
     * @return float[]
     */
    public function getProgressPercentage(): array
    {
        $stats = [
            'tier3' => 0,
            'tier2' => 0,
            'tier1' => 0,
            'tier4' => 0,
        ];
        $skillCount = $this->skills->count();
        if (!$skillCount) {
            return $stats;
        }

        /** @var Skill $skill */
        foreach ($this->skills as $skill) {
            $stat = $skill->getSingleProgressPercentage();
            $stats = [
                'tier3' => $stats['tier3'] + $stat['tier3'],
                'tier2' => $stats['tier2'] + $stat['tier2'],
                'tier1' => $stats['tier1'] + $stat['tier1'],
                'tier4' => $stats['tier4'] + $stat['tier4'],
            ];
        }

        $stats = [
            'tier3' => $stats['tier3'] / $skillCount,
            'tier2' => $stats['tier2'] / $skillCount,
            'tier1' => $stats['tier1'] / $skillCount,
            'tier4' => $stats['tier4'] / $skillCount,
        ];
        return $stats;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function addSkill(Skill $skill): void
    {
        $this->skills->attach($skill);
    }

    public function removeSkill(Skill $skillToRemove): void
    {
        $this->skills->detach($skillToRemove);
    }

    public function getSkills(): ObjectStorage
    {
        return $this->skills;
    }

    public function setSkills(ObjectStorage $skills): void
    {
        $this->skills = $skills;
    }

    public function getLinks(): ObjectStorage
    {
        return $this->links;
    }

    public function setLinks(ObjectStorage $links): void
    {
        $this->links = $links;
    }

    public function getSkillupCommentPlaceholder(): string
    {
        return $this->skillupCommentPlaceholder;
    }

    public function setSkillupCommentPlaceholder(string $skillupCommentPlaceholder): void
    {
        $this->skillupCommentPlaceholder = $skillupCommentPlaceholder;
    }

    public function getSkillupCommentPreset(): string
    {
        return $this->skillupCommentPreset;
    }

    public function setSkillupCommentPreset(string $skillupCommentPreset): void
    {
        $this->skillupCommentPreset = $skillupCommentPreset;
    }
}
