<?php

namespace tatchan\WraithPortal\task;

use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use tatchan\WraithPortal\PortalManger;
use tatchan\WraithPortal\WraithPortal;

class PortalTpTask extends Task
{
    /** @var WraithPortal */
    private $portal;
    /** @var Player */
    private $player;
    /** @var Position[] */
    private $positions;
    /** @var int */
    private $i = 0;

    /**
     * @param bool $reverse false => start to finish, true => finish to start
     */
    public function __construct(WraithPortal $portal, Player $player, bool $reverse) {
        $this->portal = $portal;
        $this->player = $player;
        $this->positions = PortalManger::getInstance()->getposition($portal);
        if ($reverse) {
            $this->positions = array_reverse($this->positions);
        }
        $this->positions = array_values($this->positions);
        $firstPos = $this->positions[0];
        $lastPos = $this->positions[array_key_last($this->positions)];
        //$lastPos->z += $firstPos->z < $lastPos->z ? 2 : -2;
    }

    public function onRun(int $currentTick) {
        if (!isset($this->positions[$this->i])) {
            $this->getHandler()->cancel();
            PortalManger::getInstance()->setTeleporting($this->player, false);
            PortalManger::getInstance()->setLastPortal($this->player, PortalManger::getInstance()->getportalentity($this->positions[array_key_last($this->positions)]));
            return;
        }
        //$this->player->sendMessage($this->positions[$this->i]->asPosition()->__toString());
        //$this->player->teleport($this->positions[$this->i], $this->player->getYaw(), $this->player->getPitch());
        //$this->player->setPosition($this->positions[$this->i]);
        $position = $this->positions[$this->i];
        //$oldPosition = $this->positions[$this->i - 1] ?? null;
        $oldPosition = $this->player;
        if ($this->positions[array_key_last($this->positions)] === $position) {//ぽじしょんはtp最後の
            $this->player->teleport($position, $this->player->getYaw(), $this->player->getPitch());
        } else {
            $this->player->setMotion((new Vector3($position->x - $oldPosition->x, $position->y - $oldPosition->y, $position->z - $oldPosition->z))->divide(3));
        }
        ++$this->i;
        $this->player->getLevel()->addParticle(new CriticalParticle($this->player));
    }
    public function onCancel() {
        $this->player->setInvisible(false);
    }
}