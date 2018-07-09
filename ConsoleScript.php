<?php
	const VERSION='2018-07-09';
	date_default_timezone_set('Asia/Hong_Kong');
	const FILE_STUB='<?php require("phar://".__FILE__."/%s");__HALT_COMPILER();';
	const PLUGIN_STUB='<?php __HALT_COMPILER();';
	$opts=getopt('',['make:','relative:','out:','entry:','exclude:','stub:']);
	global $argv;
	if(!isset($opts['make'])){
		echo '== PocketMine-MP DevTools CLI interface =='.PHP_EOL.PHP_EOL;
		echo 'Usage: '.PHP_BINARY.' -dphar.readonly=0 '.$argv[0].' --make <src1[,src2[,src3...]]> --exclude <1.php[,2.php[,3.php...]]> --relative <relativePath> --entry "relativeSourcePath.php" --out <Name.phar>'.PHP_EOL;
		exit(0);
	}
	if(ini_get('phar.readonly')==1){
		echo 'Set phar.readonly to 0 with -dphar.readonly=0'.PHP_EOL;
		exit(1);
	}
	$includedPaths=explode(',',$opts['make']);
	array_walk($includedPaths,function(&$path){
		$realPath=realpath($path);
		if($realPath===false){
			echo '[ERROR] make directory '.$path.' does not exist or permission denied'.PHP_EOL;
			exit(1);
		}
		$path=rtrim($realPath,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
	});
	if(!isset($opts['relative'])){
		if(count($includedPaths)>1){
			echo 'You must specify a relative path with --relative <relativePath> to be able to include multiple directories'.PHP_EOL;
			exit(1);
		}
		$basePath=rtrim(realpath(array_shift($includedPaths)),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
	}else{$basePath=rtrim(realpath($opts['relative']),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;}
	$includedPaths=array_filter(array_map(function(string $path) use ($basePath):string{return str_replace($basePath,'',$path);},$includedPaths),function(string $v):bool{return $v!=='';});
	$pharName=$opts['out']??'output.phar';
	$stubPath=$opts['stub']??'stub.php';
	if(!is_dir($basePath)){
		echo $basePath.' is not a folder'.PHP_EOL;
		return;
	}
	echo PHP_EOL;
	$metadata=[];
	if(file_exists($basePath.$stubPath)){
		echo 'Using stub '.$basePath.$stubPath.PHP_EOL;
		$stub=sprintf(FILE_STUB,$stubPath);
	}elseif(isset($opts['entry'])){
		$realEntry=realpath($opts['entry']);
		if($realEntry===false){
			die('Entry point not found');
		}
		$realEntry=addslashes(str_replace([$basePath,'\\'],['','/'],$realEntry));
		echo 'Setting entry point to '.$realEntry.PHP_EOL;
		$stub=sprintf(FILE_STUB,$realEntry);
	}else{
		$metadata=generatePluginMetadataFromYml($basePath.'plugin.yml');
		if($metadata===null){
			echo 'Missing entry point or plugin.yml'.PHP_EOL;
			exit(1);
		}
		$stub=PLUGIN_STUB;
	}
	if(isset($opts['exclude'])) $exclude=explode(',',$opts['exclude']);
	else $exclude=null;
	echo PHP_EOL;
	if(!buildPhar($pharName,$basePath,$includedPaths,$metadata,$stub,$exclude)) exit(1);
	exit(0);
	function generatePluginMetadataFromYml(string $pluginYmlPath){
		if(!file_exists($pluginYmlPath)) return null;
		$pluginYml=yaml_parse_file($pluginYmlPath);
		return array(
			'name'=>$pluginYml['name'],
			'version'=>$pluginYml['version'],
			'main'=>$pluginYml['main'],
			'api'=>$pluginYml['api'],
			'depend'=>$pluginYml['depend']??'',
			'description'=>$pluginYml['description']??'',
			'authors'=>$pluginYml['authors']??'',
			'website'=>$pluginYml['website']??'',
			'creationDate'=>time()
		);
	}
	function buildPhar(string $pharPath,string $basePath,array $included,array $metadata,string $stub,$excludedFiles=null,int $signature=\Phar::SHA1){
		if(file_exists($pharPath)){
			echo 'Phar file already exists, overwriting...'.PHP_EOL;
			@unlink($pharPath);
		}
		echo 'Checking files...'.PHP_EOL;
		$flag=false;
		$output=array();
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath)) as $file){
			if(substr($file,-4)!=='.php') continue;
			exec('php -l "'.$file.'"',$output,$status);
			if($status!==0 or !@file_put_contents($file,@php_strip_whitespace($file))) $flag=true;
		}
		if($flag){
			foreach($output as $error){
				if(stripos($error,'No syntax errors detected')!==false) continue;
				echo $error.PHP_EOL;
			}
			return false;
		}
		echo 'Adding files...'.PHP_EOL;
		$start=microtime(true);
		$phar=new \Phar($pharPath);
		$phar->setMetadata($metadata);
		$phar->setStub($stub);
		$phar->setSignatureAlgorithm($signature);
		$phar->startBuffering();
		$excluded=[DIRECTORY_SEPARATOR.'.',realpath($pharPath),'ConsoleScript.php'];
		if(is_array($excludedFiles)) array_push($excluded,$excludedFiles);
		$regex=sprintf('/^(?!.*(%s))^%s(%s).*/i',
			implode('|',preg_quote_array($excluded,'/')),
			preg_quote($basePath,'/'),
			implode('|',preg_quote_array($included,'/'))
		);
		$directory=new \RecursiveDirectoryIterator($basePath,\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::FOLLOW_SYMLINKS|\FilesystemIterator::CURRENT_AS_PATHNAME);
		$iterator=new \RecursiveIteratorIterator($directory);
		$regexIterator=new \RegexIterator($iterator,$regex);
		$count=count($phar->buildFromIterator($regexIterator,$basePath));
		echo 'Added '.$count.' files'.PHP_EOL;
		$phar->compressFiles(\Phar::GZ);
		$phar->stopBuffering();
		echo 'Done in '.round(microtime(true)-$start,3).'s'.PHP_EOL;
		return true;
	}
	function preg_quote_array(array $strings,string $delim=null):array{
		return array_map(function(string $str) use ($delim):string{return preg_quote($str,$delim);},$strings);
	}