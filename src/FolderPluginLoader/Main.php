<?php
namespace FolderPluginLoader;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLoadOrder;

	class Main extends PluginBase{
		public function onLoad(){}
		public function onEnable(){
			$this->getServer()->getPluginManager()->registerInterface("FolderPluginLoader\\FolderPluginLoader");
			$this->getServer()->getPluginManager()->loadPlugins($this->getServer()->getPluginPath(),["FolderPluginLoader\\FolderPluginLoader"]);
			$this->getServer()->enablePlugins(PluginLoadOrder::STARTUP);
		}
	}