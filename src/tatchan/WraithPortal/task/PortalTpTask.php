<?php

namespace tatchan\WraithPortal\task;

use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskHandler;
use tatchan\WraithPortal\PortalManger;
use tatchan\WraithPortal\WraithPortal;

class PortalTpTask extends Task {
    private Player $player;
    /** @var Position[] */
    private array $positions;
    private int $i = 0;

    /**
     * @param bool $reverse false => start to finish, true => finish to start
     */
    public function __construct(WraithPortal $portal, Player $player, bool $reverse) {
        $this->player = $player;
        $this->positions = PortalManger::getInstance()->gethistory($portal);
        if ($reverse) {
            $this->positions = array_reverse($this->positions);
        }
        $this->positions = array_values($this->positions);
        $firstPos = $this->positions[0];
        $lastPos = $this->positions[array_key_last($this->positions)];
        $lastPos->z += $firstPos->z < $lastPos->z ? 2 : -2;
    }

    public function onRun(int $currentTick): void {
        if (!isset($this->positions[$this->i])) {
            /** @var TaskHandler $handler */
            $handler = $this->getHandler();
            $handler->cancel();
            PortalManger::getInstance()->setTeleporting($this->player, false);
            return;
        }
        //$this->player->sendMessage($this->positions[$this->i]->asPosition()->__toString());
        $this->player->teleport($this->positions[$this->i], $this->player->getYaw(), $this->player->getPitch());
        ++$this->i;
    }
}