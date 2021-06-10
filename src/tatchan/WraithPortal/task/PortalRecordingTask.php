<?php

namespace tatchan\WraithPortal\task;

use pocketmine\Player;
use pocketmine\scheduler\Task;
use tatchan\WraithPortal\PortalManger;
use tatchan\WraithPortal\WraithPortal;

class PortalRecordingTask extends Task
{
    /** @var WraithPortal */
    private $portal;
    /** @var Player */
    private $player;

    public function __construct(WraithPortal $portal, Player $player) {
        $this->portal = $portal;
        $this->player = $player;
    }

    public function onRun(int $currentTick) {
        if ((!PortalManger::getInstance()->isset($this->player->getName())) || PortalManger::getInstance()->taskhandlerget($this->player->getName())->isCancelled()) {
            $this->getHandler()->cancel();
            return;
        }
        PortalManger::getInstance()->savexyz($this->portal, $this->player);
    }
}