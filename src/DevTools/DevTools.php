<?php
namespace DevTools;
use DevTools\commands\ExtractPluginCommand;
use FolderPluginLoader\FolderPluginLoader;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLoadOrder;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
require 'Encode.php';

class DevTools extends PluginBase{
	public function onLoad(){
		$this->getServer()->getCommandMap()->register('devtools',new ExtractPluginCommand($this));
	}
	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->getServer()->getPluginManager()->registerInterface("FolderPluginLoader\\FolderPluginLoader");
		$this->getServer()->getPluginManager()->loadPlugins($this->getServer()->getPluginPath(),["FolderPluginLoader\\FolderPluginLoader"]);
		$this->getLogger()->info('Registered folder plugin loader');
		$this->getServer()->enablePlugins(PluginLoadOrder::STARTUP);
	}
	public function onCommand(CommandSender $sender,Command $command,string $label,array $args):bool{
		switch($command->getName()){
			case 'makeplugin':
				if(!isset($args[0]))return false;
				if($args[0]=='FolderPluginLoader'){
					return $this->makePluginLoader($sender);
				}elseif($args[0]=='*'){
					$plugins=$this->getServer()->getPluginManager()->getPlugins();
					$succeeded=$failed=array();
					$skipped=0;
					foreach($plugins as $plugin){
						if(!$plugin->getPluginLoader() instanceof FolderPluginLoader){
							$skipped++;
							continue;
						}
						$args[0]=$plugin->getName();
						if($this->makePluginCommand($sender,$args)) $succeeded[]=$plugin->getName();
						else $failed[]=$plugin->getName();
					}
					if(count($failed)>0) $sender->sendMessage(TextFormat::RED.count($failed).' plugin(s) failed to build: '.implode(',',$failed));
					if(count($succeeded)>0) $sender->sendMessage(TextFormat::GREEN.count($succeeded).'/'.(count($plugins)-$skipped).' plugin(s) successfully built: '.implode(', ',$succeeded));
					return true;
				}else{
					return $this->makePluginCommand($sender,$args);
				}
			case 'makeserver':
				return $this->makeServerCommand($sender);
			default:
				return false;
		}
	}
	private function makePluginLoader(CommandSender $sender){
		$pharPath=$this->getDataFolder(). DIRECTORY_SEPARATOR .'FolderPluginLoader.phar';
		if(file_exists($pharPath)){
			$sender->sendMessage('Phar plugin already exists,overwriting...');
			\Phar::unlinkArchive($pharPath);
		}
		$phar=new \Phar($pharPath);
		$phar->setMetadata([
			'name'=>'FolderPluginLoader',
			'version'=>'1.0.1',
			'main'=>'FolderPluginLoader\\Main',
			'api'=>['1.0.0','2.0.0'],
			'depend'=>[],
			'description'=>'Loader of folder plugins',
			'authors'=>['PocketMine Team'],
			'website'=>'https://github.com/PocketMine/DevTools',
			'creationDate'=>time()
		]);
		$phar->setStub('<?php __HALT_COMPILER();');
		$phar->setSignatureAlgorithm(\Phar::SHA1);
		$phar->startBuffering();
		$phar->addFromString('plugin.yml',"name: FolderPluginLoader\nversion: 1.0.1\nmain: FolderPluginLoader\\Main\napi: [1.0.0,2.0.0]\nload: STARTUP\n");
		$phar->addFile($this->getFile().'src/FolderPluginLoader/FolderPluginLoader.php','src/FolderPluginLoader/FolderPluginLoader.php');
		$phar->addFile($this->getFile().'src/FolderPluginLoader/Main.php','src/FolderPluginLoader/Main.php');
		$phar->compressFiles(\Phar::GZ);
		$phar->stopBuffering();
		$sender->sendMessage('Folder plugin loader has been created on '.$pharPath);
		return true;
	}
	private function makePluginCommand(CommandSender $sender,array $args){
		$pluginName=trim($args[0]);
		if($pluginName==='' or !(($plugin=Server::getInstance()->getPluginManager()->getPlugin($pluginName)) instanceof Plugin)){
			$sender->sendMessage(TextFormat::RED.'Invalid plugin name,check the name case.');
			return false;
		}
		$description=$plugin->getDescription();
		if(!($plugin->getPluginLoader() instanceof FolderPluginLoader)){
			$sender->sendMessage(TextFormat::RED.'Plugin '.$description->getName().' is not in folder structure.');
			return false;
		}
		$pharPath=$this->getDataFolder().DIRECTORY_SEPARATOR.$description->getName().'_v'.$description->getVersion().'.phar';
		$metadata=array('name'=>$description->getName(),'version'=>$description->getVersion(),'main'=>$description->getMain(),'api'=>$description->getCompatibleApis(),'depend'=>$description->getDepend(),'description'=>$description->getDescription(),'authors'=>$description->getAuthors(),'website'=>$description->getWebsite(),'creationDate'=>time());
		$stub='<?php echo "PocketMine-MP plugin '.$description->getName().' v'.$description->getVersion().'\nThis file has been generated using DevTools-lakwsh v'.$this->getDescription()->getVersion().' at '.date('r').'\n----------------\n";if(extension_loaded("phar")){$phar=new \Phar(__FILE__);foreach($phar->getMetadata() as $key=>$value){echo ucfirst($key).": ".(is_array($value)?implode(",",$value):$value)."\n";}} __HALT_COMPILER();';
		try{
			$reflection=new \ReflectionClass("pocketmine\\plugin\\PluginBase");
		}catch(\Exception $e){
			return false;
		}
		$file=$reflection->getProperty('file');
		$file->setAccessible(true);
		$filePath=realpath($file->getValue($plugin));
		assert(is_string($filePath));
		$filePath=rtrim($filePath,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
		if(isset($args[1])){
			foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filePath)) as $file){
				$path=ltrim(str_replace(["\\",$filePath],['/',''],$file),'/');
				if(substr($file,-4)!=='.php') continue;
				if(EncodePHP($file)) $sender->sendMessage('[DevTools-lakwsh] Encoding '.$path);
			}
		}
        self::buildPhar($sender,$pharPath,$filePath,[],$metadata,$stub);
		return true;
	}
	private function makeServerCommand(CommandSender $sender){
        if(stripos(\pocketmine\PATH,"phar://")===0){
            $sender->sendMessage(TextFormat::RED.'This command can only be used on a server running from source code');
            return true;
        }
	    $server=$sender->getServer();
		$pharPath=$this->getDataFolder(). DIRECTORY_SEPARATOR .$server->getName().'_'.$server->getPocketMineVersion().'.phar';
		$metadata=[
			'name'=>$server->getName(),
			'version'=>$server->getPocketMineVersion(),
			'api'=>$server->getApiVersion(),
			'minecraft'=>$server->getVersion(),
			'creationDate'=>time(),
			'protocol'=>\pocketmine\network\mcpe\protocol\ProtocolInfo::CURRENT_PROTOCOL
		];
		$stub='<?php require_once("phar://". __FILE__ ."/src/pocketmine/PocketMine.php"); __HALT_COMPILER();';
        $filePath=rtrim(str_replace("\\",'/',realpath(\pocketmine\PATH).DIRECTORY_SEPARATOR),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $this->buildPhar($sender,$pharPath,$filePath,['src','vendor'],$metadata,$stub);
        return true;
    }
    private function preg_quote_array(array $strings,string $delim=null):array{
	    return array_map(function(string $str) use ($delim):string{return preg_quote($str,$delim);},$strings);
	}
    private function buildPhar(CommandSender $sender,string $pharPath,string $basePath,array $includedPaths,array $metadata,string $stub,int $signatureAlgo=\Phar::SHA1){
        if(file_exists($pharPath)){
            $sender->sendMessage('Phar file already exists, overwriting...');
	        try{
	        	\Phar::unlinkArchive($pharPath);
	        }catch(\Exception $e){
		        unlink($pharPath);
	        }
        }
        $start=microtime(true);
        $phar=new \Phar($pharPath);
        $phar->setMetadata($metadata);
        $phar->setStub($stub);
        $phar->setSignatureAlgorithm($signatureAlgo);
        $phar->startBuffering();
        $sender->sendMessage('[DevTools-lakwsh] Adding files...');
        $excludedSubstrings=[DIRECTORY_SEPARATOR.'.',realpath($pharPath)];
        $regex=sprintf('/^(?!.*(%s))^%s(%s).*/i',implode('|',self::preg_quote_array($excludedSubstrings,'/')),preg_quote($basePath,'/'),implode('|',self::preg_quote_array($includedPaths,'/')));
		$directory=new \RecursiveDirectoryIterator($basePath,\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::FOLLOW_SYMLINKS|\FilesystemIterator::CURRENT_AS_PATHNAME);
		$regexIterator=new \RegexIterator(new \RecursiveIteratorIterator($directory),$regex);
	    $count=count($phar->buildFromIterator($regexIterator,$basePath));
	    $sender->sendMessage("[DevTools] Added $count files");
  		$phar->compressFiles(\Phar::GZ);
        $phar->stopBuffering();
        $sender->sendMessage('[DevTools-lakwsh] Done in '.round(microtime(true)-$start,3).'s');
        return true;
    }
}