<?php

namespace tatchan\WraithPortal\task;

use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use tatchan\WraithPortal\PortalManger;
use tatchan\WraithPortal\WraithPortal;

class PortalCrateTask extends Task
{
    private static $instance;
    private Player $player;
    private WraithPortal $portal;
    private float $defdistance;
    private float $lastDistance;
    private float $distance;

    public function __construct(Player $player, WraithPortal $portal, float $distance) {
        $this->player = $player;
        $this->portal = $portal;
        $this->defdistance = $distance;
        $this->lastDistance = $this->player->distance($this->portal);
        $this->distance = 0;
    }
    public function cancel() {
        $this->getHandler()->cancel();
    }
    public function isAlive(){
        return $this->portal->isAlive();
    }

    public function onRun(int $currentTick) {
        if (!$this->portal->isAlive()) {
            $this->getHandler()->cancel();
            return;
        }
        if ($this->lastDistance !== ($this->lastDistance = $this->player->distance($this->portal))) {
            $this->distance++;
        }
        $this->player->sendActionBarMessage(TextFormat::YELLOW . round($dis = $this->defdistance - $this->distance, 2));
        if($dis < 0){
            PortalManger::getInstance()->finishportal($this->player,$this->portal);
            PortalManger::getInstance()->startportal($this->portal);
            PortalManger::getInstance()->unsetplayerhandler($this->player->getName());
            $this->getHandler()->cancel();
        }
    }

    /**
     * @return WraithPortal
     */
    public function getPortal(): WraithPortal {
        return $this->portal;
    }
}