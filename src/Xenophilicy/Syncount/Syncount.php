<?php
# MADE BY:
#  __    __                                          __        __  __  __                     
# /  |  /  |                                        /  |      /  |/  |/  |                    
# $$ |  $$ |  ______   _______    ______    ______  $$ |____  $$/ $$ |$$/   _______  __    __ 
# $$  \/$$/  /      \ /       \  /      \  /      \ $$      \ /  |$$ |/  | /       |/  |  /  |
#  $$  $$<  /$$$$$$  |$$$$$$$  |/$$$$$$  |/$$$$$$  |$$$$$$$  |$$ |$$ |$$ |/$$$$$$$/ $$ |  $$ |
#   $$$$  \ $$    $$ |$$ |  $$ |$$ |  $$ |$$ |  $$ |$$ |  $$ |$$ |$$ |$$ |$$ |      $$ |  $$ |
#  $$ /$$  |$$$$$$$$/ $$ |  $$ |$$ \__$$ |$$ |__$$ |$$ |  $$ |$$ |$$ |$$ |$$ \_____ $$ \__$$ |
# $$ |  $$ |$$       |$$ |  $$ |$$    $$/ $$    $$/ $$ |  $$ |$$ |$$ |$$ |$$       |$$    $$ |
# $$/   $$/  $$$$$$$/ $$/   $$/  $$$$$$/  $$$$$$$/  $$/   $$/ $$/ $$/ $$/  $$$$$$$/  $$$$$$$ |
#                                         $$ |                                      /  \__$$ |
#                                         $$ |                                      $$    $$/ 
#                                         $$/                                        $$$$$$/

namespace Xenophilicy\Syncount;

use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

use Xenophilicy\Syncount\Command\SyncountCommand;
use Xenophilicy\Syncount\Task\QueryTaskCaller;

class Syncount extends PluginBase implements Listener{

    public static $plugin;

    public function onEnable(){
        self::$plugin = $this;
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->config = new Config($this->getDataFolder()."config.yml", Config::YAML);
        $this->config->getAll();
        $this->interval = $this->config->get("Query-Interval");
        $this->total = 0;
        if(!is_numeric($this->interval)){
            $this->getLogger()->critical("Invalid query interval found, it must be an integer! Plugin will remain disabled...");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
        $this->queryResults = [];
        foreach($this->config->get("Servers") as $server){
            $values = explode(":", $server);
            $host = $values[0];
            $port = $values[1];
            $this->queryResults[$host.":".$port] = [0,0];
            $this->startQueryTask($host, $port);
        }
        $this->getServer()->getCommandMap()->register("syncount", new SyncountCommand("syncount", $this));
    }

    public static function getPlugin() : Syncount{
        return self::$plugin;
    }

    public function onQueryRegenerate(QueryRegenerateEvent $event) : void{
        $online = count($this->getServer()->getOnlinePlayers());
        $max = $this->getServer()->getMaxPlayers();
        foreach($this->queryResults as $result){
            $online += $result[0];
            $max += $result[1];
        }
        $event->setPlayerCount($online);
        $event->setMaxPlayerCount($max);
    }

    public function queryTaskCallback($result, string $host = "", int $port = 0, string $plugins = "") : void{
        if($plugins !== ""){
            if(preg_match("/Syncount/", $plugins)){
                $this->getLogger()->critical("Server ".$host.":".$port." has Syncount installed! To avoid infinite player counts, install the plugin on only one server!");
                $this->getLogger()->notice("Server ".$host.":".$port." has been disabled!");
                unset($this->queryResults[$host.":".$port]);
                return;
            }
        }
        $this->getScheduler()->scheduleDelayedTask(new QueryTaskCaller($this, $host, $port), $this->interval);
        $this->queryResults[$host.":".$port] = $result;
    }
    
    public function startQueryTask(string $host, int $port) : void{
        $this->getScheduler()->scheduleTask(new QueryTaskCaller($this, $host, $port));
    }
}