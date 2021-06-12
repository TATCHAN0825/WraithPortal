<?php

declare(strict_types=1);

namespace tatchan\WraithPortal;

use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use tatchan\WraithPortal\task\PortalCrateTask;
use tatchan\WraithPortal\task\PortalRecordingTask;

class Main extends PluginBase implements Listener
{
    public static $resourcePath;

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

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        self::$resourcePath = $this->getFile() . "resources/";
        Entity::registerEntity(WraithPortal::class, true, ["tatchan:wraithportal"]);
        $this->getServer()->getCommandMap()->register($this->getName(), new PortalClearCommand($this));
        new Config($this->getDataFolder() . "config.yml", Config::DETECT, ["distanceblock" => 100, "itemid" => 264]);
        (new PortalManger($this));
    }

    public function onDisable() {
        PortalManger::getInstance()->save();
    }

    public function ontap(PlayerInteractEvent $event) {
        $name = $event->getPlayer()->getName();
        if ($this->getConfig()->get("itemid") == $event->getItem()->getId()) {
            $distance = $this->getConfig()->get("distanceblock");
            if (!PortalManger::getInstance()->isset($name)) {
                $entity = PortalManger::getInstance()->createportal($event->getPlayer()->getPosition());
                $taskhandler = $this->getScheduler()->scheduleRepeatingTask(new PortalCrateTask($event->getPlayer(), $entity, $distance), 1);
                PortalManger::getInstance()->taskhandlerset($name, $taskhandler);
                $this->getScheduler()->scheduleRepeatingTask(new PortalRecordingTask($entity, $event->getPlayer()), 1);
                $event->getPlayer()->sendMessage("ポータルを置くわ");
            } else {
                $th = PortalManger::getInstance()->taskhandlerget($name);
                $th->cancel();
                PortalManger::getInstance()->unsetplayerhandler($name);
                PortalManger::getInstance()->finishportal($event->getPlayer()->getPosition(), $th->getTask()->getPortal());
                $event->getPlayer()->sendMessage("ポータルを設置");
            }
        }
    }
    public function onMove(PlayerMoveEvent $event){
        $player =$event->getPlayer();
        if (PortalManger::getInstance()->isTeleporting($player)) {
            return;
        }

        if(($portal = PortalManger::getInstance()->getLastPortal($player)) !== null){
            if($portal->distance($player) > 4){//ポータルからnブロック以上離れたら(出たらからポータル)
                PortalManger::getInstance()->setLastPortal($player, null);
            }
        }
    }
}
