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

    public function createportal(Position $position): WraithPortal {
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

    public function startportal(Position $position): void {
        $p = $this->config->get(self::fromVector3($position));
        $p["status"] = "start";
        $this->config->set(self::fromVector3($position), $p);
    }

    public function finishportal(Position $position, Position $startpos): void {
        /** @var WraithPortal $entity */
        $entity = Entity::createEntity("tatchan:wraithportal", $position->getLevel(), WraithPortal::createBaseNBT($position));
        $entity->spawnToAll();
        $p = $this->config->get(self::fromVector3($position));
        $p["status"] = "finish";
        $p["startpos"] = self::fromVector3($startpos);
        $this->config->set(self::fromVector3($position), $p);

    }

    public function addhistory(Position $position, Position $xyz): void {
        $p = $this->config->get(self::fromVector3($position));
        $p["history"][] = [
            "x" => $xyz->x,
            "y" => $xyz->y,
            "z" => $xyz->z,
            "worldname" => $xyz->getLevelNonNull()->getName()
        ];
        $this->config->set(self::fromVector3($position), $p);
    }

    /**
     * @return Position[]
     */
    public function gethistory(Position $position): array {
        $p = $this->config->get(self::fromVector3($position));
        $positions = [];
        foreach ($p["history"] as $pos) {
            $positions[] = new Position($pos["x"], $pos["y"], $pos["z"], Server::getInstance()->getLevelByName($pos["worldname"]));
        }
        return $positions;
    }

    public function getportalentity(Position $position): ?WraithPortal {
        /** @var WraithPortal|null $portal */
        $portal = $position->getLevelNonNull()->getNearestEntity($position, 1, WraithPortal::class);
        return $portal;
    }

    public function save(): void {
        $this->config->save();
    }

    public function reset(): void {
        $this->config->setAll([]);
    }

    public function useportal(Position $position, Player $player): void {
        if ($this->isTeleporting($player)) {
            return;
        }
        $p = $this->config->get(self::fromVector3($position));
        if (isset($p["startpos"])) {//finish???????????????
            assert(is_string($p["startpos"]), "startpos?????????????????????...");
            $finished = $p["status"] === "finish";
            $p = $this->config->get($p["startpos"]);//start??????????????????$p?????????
            $r = true;
        } else {//start???????????????????????????
            $r = false;
            foreach ($this->config->getAll() as $v) {
                //finish???????????????????????????start?????????????????????????????????????????????
                if (isset($v["startpos"]) && $v["startpos"] === self::fromVector3($position)) {
                    $p2 = $v;//???????????????
                    break;
                }
            }
            if (!isset($p2)) {//finish????????????????????????????????????...
                return;
            }
            $finished = $p2["status"] === "finish";
        }
        if (!$finished) {
            return;
        }
        $level = Server::getInstance()->getLevelByName($p["worldname"]);
        if ($level === null) {
            Server::getInstance()->getLevelByName($p["worldname"]);
            $level = Server::getInstance()->getLevelByName($p["worldname"]);
        }
        $portalPos = new Position($p["x"], $p["y"], $p["z"], $level);
        $portal = $this->getportalentity($portalPos);
        if ($portal === null) {
            return;//what
        }
        $this->setTeleporting($player, true);
        $this->plugin->getScheduler()->scheduleRepeatingTask(new PortalTpTask($portal, $player, $r), 1);
        //$effect = new EffectInstance(Effect::getEffect(Effect::BLINDNESS), 256, 1, false);
        //$player->addEffect($effect);
    }

    public function setLastPortal(Player $player, ?WraithPortal $p): void {
        $this->lastPortals[$player->getName()] = $p;
    }

    public function getLastPortal(Player $player): ?WraithPortal {
        return $this->lastPortals[$player->getName()] ?? null;
    }

    public function setTeleporting(Player $player, bool $teleporting): void {
        $this->teleporting[$player->getName()] = $teleporting;
    }

    public function isTeleporting(Player $player): bool {
        return $this->teleporting[$player->getName()] ?? false;
    }

    /**
     * Vector3??????????????????????????????
     * WARNING: ??????????????????????????????????????????
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
                ? ":" . $vector3->getLevelNonNull()->getName()
                : ""
            );
    }

    public function WhenUsingPortalSite(Player $player): void {
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

    public function isset(string $name): bool {
        return isset($this->taskhandlers[$name]);
    }

    public function taskhandlerget(string $name): TaskHandler {
        return $this->taskhandlers[$name];
    }

    public function taskhandlerset(string $name, TaskHandler $handler): void {
        $this->taskhandlers[$name] = $handler;
    }

    public function unsetplayerhandler(string $name): void {
        unset($this->taskhandlers[$name]);
    }
}