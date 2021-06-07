<?php

namespace tatchan\WraithPortal;

use pocketmine\entity\Human;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\Server;

class WraithPortal extends Human
{
    protected $gravity = 0;


    public function __construct(Level $level, CompoundTag $nbt) {
        $this->setSkin(new Skin(
            "cc18e65aa-7b21-4637-9b63-8ad63622ef01_CustomSlim",
            Main::pngToBytes(Main::$resourcePath . "texture.png"),
            "",
            "geometry.WraithPortal",
            file_get_contents(Main::$resourcePath . "WraithPortal.geo.json")
        ));

        parent::__construct($level, $nbt);
        $this->setScale(1.5);
        $this->width = 0.01;
        $this->height = 0.01;
        $this->recalculateBoundingBox();

    }

    public function onCollideWithPlayer(Player $player): void {
        //Server::getInstance()->broadcastMessage(PortalManger::getInstance()->getstatus($this) . mt_rand(1,100));
        PortalManger::getInstance()->useportal($this, $player);
    }

    public function attack(EntityDamageEvent $source): void {

    }



}