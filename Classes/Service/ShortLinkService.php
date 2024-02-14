<?php

declare(strict_types=1);
/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein
 **/

namespace SkillDisplay\Skills\Service;

use InvalidArgumentException;
use SkillDisplay\Skills\Domain\Model\Shortlink;
use SkillDisplay\Skills\Domain\Repository\ShortlinkRepository;
use TYPO3\CMS\Core\SingletonInterface;

class ShortLinkService implements SingletonInterface
{
    protected static array $handlers = [];

    public function __construct(protected ShortlinkRepository $shortLinkRepo) {}

    public static function addHandler(string $action, array $handler): void
    {
        self::$handlers[$action] = $handler;
    }

    public function createCode($action, array $parameters): string
    {
        if (!isset(self::$handlers[$action])) {
            throw new InvalidArgumentException('Given action "' . $action . '" has no registered handler.', 1474505952);
        }
        $parameters = json_encode($parameters);
        $hash = md5($action . date('U') . $parameters);

        $shortlink = new Shortlink();
        $shortlink->setAction($action);
        $shortlink->setParameters($parameters);
        $shortlink->setHash($hash);
        $this->shortLinkRepo->add($shortlink);
        return $shortlink->getHash();
    }

    public function handleShortlink(string $hash): array
    {
        /** @var Shortlink $shortlink */
        $shortlink = $this->shortLinkRepo->findByHash($hash)->getFirst();
        if (!$shortlink) {
            throw new InvalidArgumentException('No shortlink for hash "' . $hash . '" available.', 1474505953);
        }
        if (!isset(self::$handlers[$shortlink->getAction()])) {
            throw new InvalidArgumentException('Given action "' . $shortlink->getAction() . '" has no registered handler.', 1474505954);
        }
        $this->shortLinkRepo->remove($shortlink);
        $parameters = (array)json_decode($shortlink->getParameters());
        return [
            'action' => self::$handlers[$shortlink->getAction()][1],
            'controller' => self::$handlers[$shortlink->getAction()][0],
            'parameters' => $parameters,
        ];
    }
}
