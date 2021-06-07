<?php

declare(strict_types=1);

namespace tatchan\WraithPortal;

use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener
{
    public static $resourcePath;


    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        self::$resourcePath = $this->getFile() . "resources/";
        Entity::registerEntity(WraithPortal::class, true, ["tatchan:wraithportal"]);
        $this->getServer()->getCommandMap()->register($this->getName(), new PortalClearCommand($this));
        new Config($this->getDataFolder() . "config.yml", Config::DETECT, ["distanceblock" => 100,"itemid" => 264]);
        (new PortalManger($this));
    }

    public function onDisable() {
        PortalManger::getInstance()->save();
    }

    public function ontap(PlayerInteractEvent $event) {
        $name = $event->getPlayer()->getName();
        if ($this->getConfig()->get("itemid") == $event->getItem()->getId()) {
            $distance = $this->getConfig()->get("distanceblock");
            $portal = $event->getPlayer()->getPosition();
            if(!PortalManger::getInstance()->isset($name)) {
                $entity = PortalManger::getInstance()->createportal($event->getPlayer()->getPosition());
                $taskhandler = $this->getScheduler()->scheduleRepeatingTask(new PortalCrateTask($event->getPlayer(), $entity, $distance), 1);
                PortalManger::getInstance()->taskhandlerset($name,$taskhandler);
                $this->getScheduler()->scheduleRepeatingTask(new PortalRecordingTask($entity, $event->getPlayer()), 20);
                $event->getPlayer()->sendMessage("ポータルを置くわ");
            }else{
                $th = PortalManger::getInstance()->taskhandlerget($name);
                $th->cancel();
                PortalManger::getInstance()->unsetplayerhandler($name);
                PortalManger::getInstance()->finishportal($event->getPlayer()->getPosition(),$th->getTask()->getPortal());
                PortalManger::getInstance()->startportal($portal);
                $event->getPlayer()->sendMessage("ポータルを設置");
            }
        }
    }

    /**
     * 与えられたパスのpngファイルからバイトを生成する
     */
    public static function pngToBytes(string $path): string {
        $image = imagecreatefrompng($path);
        $bytes = '';
        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                $rgba = @imagecolorat($image, $x, $y);
                $a = ((~((int)($rgba >> 24))) << 1) & 0xff;
                $r = ($rgba >> 16) & 0xff;
                $g = ($rgba >> 8) & 0xff;
                $b = $rgba & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }
        @imagedestroy($image);
        return $bytes;
    }
}

class PortalRecordingTask extends Task {
    /** @var WraithPortal */
    private $portal;
    /** @var Player */
    private $player;
    public function __construct(WraithPortal $portal, Player $player) {
        $this->portal = $portal;
        $this->player = $player;
    }

    public function onRun(int $currentTick) {
        if (PortalManger::getInstance()->taskhandlerget($this->player->getName())->isCancelled()) {
            $this->getHandler()->cancel();
            return;
        }
        PortalManger::getInstance()->savexyz($this->portal, $this->player);
    }
}

class PortalTpTask extends Task {
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
        $this->positions = PortalManger::getInstance()->getxyz($portal);
        if ($reverse) {
            $this->positions = array_reverse($this->positions);
        }
        $this->positions = array_values($this->positions);
    }

    public function onRun(int $currentTick) {
        if (!isset($this->positions[$this->i])) {
            $this->getHandler()->cancel();
            return;
        }
        $this->player->teleport($this->positions[$this->i]);
        ++$this->i;
    }
}
