<?php

namespace tatchan\WraithPortal;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\plugin\Plugin;
use pocketmine\Server;

class PortalClearCommand extends PluginCommand implements CommandExecutor {
    public function __construct(Plugin $owner) {
        parent::__construct("portalclear", $owner);
        $this->setDescription("aiueo");
        $this->setUsage("aiueo");
        $this->setExecutor($this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        $count = 0;
        foreach (Server::getInstance()->getLevels() as $level) {
            foreach ($level->getEntities() as $entity) {
                if ($entity instanceof WraithPortal) {
                    $entity->kill();
                    $count++;
                }
            }
        }
        if ($count > 0) {
            $sender->sendMessage("§a{$count}個のポータルをクリアしたよ");
            PortalManger::getInstance()->reset();
        }
        return true;
    }
}