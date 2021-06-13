<?php

namespace tatchan\WraithPortal\task;

use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskHandler;
use tatchan\WraithPortal\PortalManger;
use tatchan\WraithPortal\WraithPortal;

class PortalRecordingTask extends Task {
    private WraithPortal $portal;
    private Player $player;

    public function __construct(WraithPortal $portal, Player $player) {
        $this->portal = $portal;
        $this->player = $player;
    }

    public function onRun(int $currentTick): void {
        if ((!PortalManger::getInstance()->isset($this->player->getName())) || PortalManger::getInstance()->taskhandlerget($this->player->getName())->isCancelled()) {
            /** @var TaskHandler $handler */
            $handler = $this->getHandler();
            $handler->cancel();
            return;
        }
        PortalManger::getInstance()->addhistory($this->portal, $this->player);
    }
}