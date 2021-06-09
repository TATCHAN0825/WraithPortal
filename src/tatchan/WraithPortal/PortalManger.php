<?php

namespace tatchan\WraithPortal;

use pocketmine\entity\Entity;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\utils\Config;

class PortalManger
{
    private static PortalManger $instance;
    private array $portal;
    private Config $config;
    private array $taskhandlers = [];
    private Main $plugin;
    private array $teleporting = [];

    public function __construct(Main $plugin) {
        $this->config = new Config($plugin->getDataFolder() . "PoertalData.yml", Config::YAML);
        $this->plugin = $plugin;
        self::$instance = $this;

    }

    public static function getInstance(): self {
        return self::$instance;
    }

  public function createportal(Position $position){
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

    public function startportal(Position $position){
        $p = $this->config->get(self::fromVector3($position));
        $p["status"] = "start";
        $this->config->set(self::fromVector3($position), $p);
    }
    public function finishportal(Position $position, Position $startpos){
        $entity = Entity::createEntity("tatchan:wraithportal", $position->getLevel(), WraithPortal::createBaseNBT($position));
        $entity->spawnToAll();
        $p = $this->config->get(self::fromVector3($position));
        $p["status"] = "finish";
        $p["startpos"] = self::fromVector3($startpos);
        $this->config->set(self::fromVector3($position), $p);

    }
    public function getstatus(Position $position){
        $p = $this->config->get(self::fromVector3($position));
        return $p["status"];
    }
    public function savexyz(Position $position,Position $xyz){
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
    public function getxyz(Position $position){
        $p = $this->config->get(self::fromVector3($position));
        $positions = [];
        foreach ($p["history"] as $pos){
            $positions[] = new Position($pos["x"], $pos["y"], $pos["z"], Server::getInstance()->getLevelByName($pos["worldname"]));
        }
        return $positions;
    }

    public function getportalentity(Position $position) {
        /** @var WraithPortal|null $portal */
        $portal = $position->getLevelNonNull()->getNearestEntity($position, 1, WraithPortal::class);
        return $portal;
    }

  public function save():void{
        $this->config->save();
  }
  public function reset(){
        $this->config->setAll([]);
  }
  public function useportal(Position $position, Player $player){
        if ($this->teleporting[$player->getName()] ?? false) {
            return;
        }
      $p = $this->config->get(self::fromVector3($position));
      $r = false;
      if (isset($p["startpos"])) {//finishのぽーたる
          $p = $this->config->get($p["startpos"]);
          $r = true;
      }
      $portalPos = new Position($p["x"], $p["y"], $p["z"], Server::getInstance()->getLevelByName($p["worldname"]));
      $this->teleporting[$player->getName()] = true;
      $this->plugin->getScheduler()->scheduleRepeatingTask(new PortalTpTask($this->getportalentity($portalPos), $player, $r), 20);
  }
    public function setTeleporting(Player  $player, bool $b) {
        $this->teleporting[$player->getName()] = $b;
    }
    /**
     * Vector3から識別子を生成する
     * WARNING: 小数点以下が切り捨てられます
     *
     * @param Vector3|Position $vector3
     */
    public static function fromVector3(Vector3 $vector3): string
    {
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

  public function isset(string $name){
        return isset($this->taskhandlers[$name]);
  }

  public function taskhandlerget(string $name):TaskHandler{
        return $this->taskhandlers[$name];
  }
  public function taskhandlerset(string $name,TaskHandler $handler) {
        $this->taskhandlers[$name] = $handler;
  }
  public function resettaskhandler(){
      $this->taskhandlers = [];
  }
  public function unsetplayerhandler(string $name){
        unset($this->taskhandlers[$name]);
  }

}