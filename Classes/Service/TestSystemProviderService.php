<?php

declare(strict_types=1);
/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Service;

use SkillDisplay\Skills\TestSystemProviderInterface;
use TYPO3\CMS\Core\SingletonInterface;

class TestSystemProviderService implements SingletonInterface
{
    /** @var TestSystemProviderInterface[] */
    protected array $providers = [];

    public function __construct() {}

    /**
     * Register Provider
     *
     * @param TestSystemProviderInterface $provider
     */
    public function registerProvider(TestSystemProviderInterface $provider): void
    {
        $this->providers[$provider->getId()] = $provider;
    }

    public function getProviderById(string $id): TestSystemProviderInterface
    {
        return $this->providers[$id];
    }

    public function getProviderListForTCA(array $params): void
    {
        $params['items'] = [
            ['', ''],
        ];
        foreach ($this->providers as $provider) {
            $params['items'][]  = [
                $provider->getLabel(),
                $provider->getId(),
            ];
        }
    }
}
