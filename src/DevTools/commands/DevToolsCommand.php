<?php
namespace DevTools\commands;
use DevTools\DevTools;
use pocketmine\command\Command;
use pocketmine\command\PluginIdentifiableCommand;

	abstract class DevToolsCommand extends Command implements PluginIdentifiableCommand{
		/** @var \pocketmine\plugin\Plugin */
		private $owningPlugin;
		public function __construct($name,DevTools $plugin){
			parent::__construct($name);
			$this->owningPlugin=$plugin;
			$this->usageMessage='';
		}
		public function getPlugin(){
			return $this->owningPlugin;
		}
	}