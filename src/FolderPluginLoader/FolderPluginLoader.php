<?php
namespace FolderPluginLoader;
use pocketmine\event\plugin\PluginDisableEvent;
use pocketmine\event\plugin\PluginEnableEvent;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginDescription;
use pocketmine\plugin\PluginLoader;
use pocketmine\Server;
use pocketmine\utils\MainLogger;
use pocketmine\utils\TextFormat;

class FolderPluginLoader implements PluginLoader{
	/** @var Server */
	private $server;
	/**
	 * @param Server $server
	 */
	public function __construct(Server $server){
		$this->server=$server;
	}
	/**
	 * Loads the plugin contained in $file
	 *
	 * @param string $file
	 *
	 * @return Plugin
	 */
	public function loadPlugin($file){
		if(is_dir($file) and file_exists($file.'/plugin.yml') and file_exists($file.'/src/')){
			if(($description=$this->getPluginDescription($file)) instanceof PluginDescription){
				$logger=$this->server->getLogger();
				$logger->getLogger()->info(TextFormat::LIGHT_PURPLE.'Loading source plugin '.$description->getFullName());
				$dataFolder=dirname($file). DIRECTORY_SEPARATOR .$description->getName();
				if(file_exists($dataFolder) and !is_dir($dataFolder)){
					$logger->warning("Projected dataFolder '".$dataFolder."' for source plugin ".$description->getName().' exists and is not a directory');
					return null;
				}
				$className=$description->getMain();
				$this->server->getLoader()->addPath($file.'/src');
				if(class_exists($className,true)){
					$plugin=new $className();
					$this->initPlugin($plugin,$description,$dataFolder,$file);
					return $plugin;
				}else{
					$logger->warning("Couldn't load source plugin ".$description->getName().': main class not found');
					return null;
				}
			}
		}
		return null;
	}
	/**
	 * Gets the PluginDescription from the file
	 *
	 * @param string $file
	 *
	 * @return PluginDescription
	 */
	public function getPluginDescription($file){
		if(is_dir($file) and file_exists($file.'/plugin.yml')){
			$yaml=@file_get_contents($file.'/plugin.yml');
			if($yaml!='') return new PluginDescription($yaml);
		}
		return null;
	}
	/**
	 * Returns the filename patterns that this loader accepts
	 *
	 * @return string
	 */
	// public function getPluginFilters(){
	public function getPluginFilters():string{
		return "/[^\\.]/";
	}
	public function canLoadPlugin(string $path):bool{
		return is_dir($path);
	}
	/**
	 * @param PluginBase        $plugin
	 * @param PluginDescription $description
	 * @param string            $dataFolder
	 * @param string            $file
	 */
	private function initPlugin(PluginBase $plugin,PluginDescription $description,$dataFolder,$file){
		$plugin->init($this,$this->server,$description,$dataFolder,$file);
		$plugin->onLoad();
	}
	/**
	 * @param Plugin $plugin
	 */
	public function enablePlugin(Plugin $plugin){
		if($plugin instanceof PluginBase and !$plugin->isEnabled()){
			MainLogger::getLogger()->info('Enabling '.$plugin->getDescription()->getFullName());
			$plugin->setEnabled(true);
			Server::getInstance()->getPluginManager()->callEvent(new PluginEnableEvent($plugin));
		}
	}
	/**
	 * @param Plugin $plugin
	 */
	public function disablePlugin(Plugin $plugin){
		if($plugin instanceof PluginBase and $plugin->isEnabled()){
			MainLogger::getLogger()->info('Disabling '.$plugin->getDescription()->getFullName());
			Server::getInstance()->getPluginManager()->callEvent(new PluginDisableEvent($plugin));
			$plugin->setEnabled(false);
		}
	}
}