<?php

namespace tatchan\WraithPortal;

use pocketmine\entity\Entity;
use pocketmine\entity\EntityIds;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\AddVolumeEntityPacket;
use pocketmine\Player;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\utils\Config;
use tatchan\WraithPortal\task\PortalTpTask;

class PortalManger {
    private static PortalManger $instance;
    private Config $config;
    private array $taskhandlers = [];
    private Main $plugin;
    /** @var WraithPortal[] */
    private array $lastPortals = [];
    private array $teleporting = [];

    public function __construct(Main $plugin) {
        $this->config = new Config($plugin->getDataFolder() . "PoertalData.yml", Config::YAML);
        $this->plugin = $plugin;
        self::$instance = $this;
    }

    public static function getInstance(): self {
        return self::$instance;
    }

    public function createportal(Position $position) {
        /** @var WraithPortal $entity */
        $entity = Entity::createEntity("tatchan:wraithportal", $position->getLevel(), WraithPortal::createBaseNBT($position));
        $entity->spawnToAll();
        $this->config->set(self::fromVector3($position), [
            "x" => $position->getX(),
            "y" => $position->getY(),
            "z" => $position->getZ(),
            "worldname" => $position->getLevelNonNull()->getName(),
            "status" => "init",
            "history" => []
        ]);
        return $entity;
    }

    public function startportal(Position $position) {
        $p = $this->config->get(self::fromVector3($position));
        $p["status"] = "start";
        $this->config->set(self::fromVector3($position), $p);
    }

    public function finishportal(Position $position, Position $startpos) {
        $entity = Entity::createEntity("tatchan:wraithportal", $position->getLevel(), WraithPortal::createBaseNBT($position));
        $entity->spawnToAll();
        $p = $this->config->get(self::fromVector3($position));
        $p["status"] = "finish";
        $p["startpos"] = self::fromVector3($startpos);
        $this->config->set(self::fromVector3($position), $p);

    }

    public function getstatus(Position $position) {
        $p = $this->config->get(self::fromVector3($position));
        return $p["status"];
    }

    public function savexyz(Position $position, Position $xyz) {
        $p = $this->config->get(self::fromVector3($position));
        $p["history"][] = [
            "x" => $xyz->x,
            "y" => $xyz->y,
            "z" => $xyz->z,
            "worldname" => $xyz->getLevel()->getName()
        ];
        $this->config->set(self::fromVector3($position), $p);
    }

    /**
     * @return Position[]
     */
    public function getposition(Position $position) {
        $p = $this->config->get(self::fromVector3($position));
        $positions = [];
        foreach ($p["history"] as $pos) {
            $positions[] = new Position($pos["x"], $pos["y"], $pos["z"], Server::getInstance()->getLevelByName($pos["worldname"]));
        }
        return $positions;
    }

    public function getportalentity(Position $position) {
        /** @var WraithPortal|null $portal */
        return $position->getLevelNonNull()->getNearestEntity($position, 1, WraithPortal::class);
    }

    public function save(): void {
        $this->config->save();
    }

    public function reset() {
        $this->config->setAll([]);
    }

    public function useportal(Position $position, Player $player) {
        if ($this->isTeleporting($player)) {
            return;
        }
        $p = $this->config->get(self::fromVector3($position));
        if (isset($p["startpos"])) {//finishのぽーたる
            assert(is_string($p["startpos"]), "startposの値がおかしい...");
            $finished = $p["status"] === "finish";
            $p = $this->config->get($p["startpos"]);//startのぽーたるの$pをとる
            $r = true;
        } else {//startのぽーたるだったら
            $r = false;
            foreach ($this->config->getAll() as $v) {
                if (isset($v["startpos"])) {//finishポータルか？
                    if ($v["startpos"] === self::fromVector3($position)) {
                        $p2 = $v;//みつかった
                        break;
                    }
                }
            }
            if (!isset($p2)) {//finishぽーたる見つからんかった...
                return;
            }
            $finished = $p2["status"] === "finish";
        }
        if (!$finished) {
            return;
        }
        $portalPos = new Position($p["x"], $p["y"], $p["z"], Server::getInstance()->getLevelByName($p["worldname"]));
        /** @var WraithPortal $portal */
        $portal = $this->getportalentity($portalPos);
        $this->setTeleporting($player, true);
        $this->plugin->getScheduler()->scheduleRepeatingTask(new PortalTpTask($portal, $player, $r), 1);
        //$effect = new EffectInstance(Effect::getEffect(Effect::BLINDNESS), 256, 1, false);
        //$player->addEffect($effect);
    }

    public function setLastPortal(Player $player, ?WraithPortal $p) {
        $this->lastPortals[$player->getName()] = $p;
    }

    public function getLastPortal(Player $player) {
        return $this->lastPortals[$player->getName()] ?? null;
    }

    public function setTeleporting(Player $player, bool $teleporting) {
        $this->teleporting[$player->getName()] = $teleporting;
    }

    public function isTeleporting(Player $player) {
        return $this->teleporting[$player->getName()] ?? false;
    }

    /**
     * Vector3から識別子を生成する
     * WARNING: 小数点以下が切り捨てられます
     *
     * @param Vector3|Position $vector3
     */
    public static function fromVector3(Vector3 $vector3): string {
        return
            floor($vector3->getX())
            . ":"
            . floor($vector3->getY())
            . ":"
            . floor($vector3->getZ())
            . (
            $vector3 instanceof Position && $vector3->isValid()
                ? ":" . $vector3->getLevel()->getName()
                : ""
            );
    }

    public function WhenUsingPortalSite(Player $player) {
        $data = new CompoundTag("", [
            new CompoundTag("minecraft:bounds", [
                new StringTag("dimension", "overworld"),
                new ListTag("max", [
                    new IntTag("", 100),
                    new IntTag("", 200),
                    new IntTag("", 300)
                ]),
                new ListTag("min", [
                    new IntTag("", 10),
                    new IntTag("", 20),
                    new IntTag("", 30)
                ]),
            ]),
            new CompoundTag("minecraft:fog", [
                new StringTag("fog_identifier", ""),
                new IntTag("priority", 1)
            ]),
        ]);
        $pk = AddVolumeEntityPacket::create(EntityIds::PLAYER, $data);
        $player->sendDataPacket($pk);
    }

    public function isset(string $name) {
        return isset($this->taskhandlers[$name]);
    }

    public function taskhandlerget(string $name): TaskHandler {
        return $this->taskhandlers[$name];
    }

    public function taskhandlerset(string $name, TaskHandler $handler) {
        $this->taskhandlers[$name] = $handler;
    }

    public function resettaskhandler() {
        $this->taskhandlers = [];
    }

    public function unsetplayerhandler(string $name) {
        unset($this->taskhandlers[$name]);
    }
}