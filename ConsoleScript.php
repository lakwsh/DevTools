<?php
	const VERSION='1.0.3';
	date_default_timezone_set('Asia/Hong_Kong');
	$opts=getopt('',['make:','relative:','out:','entry:','stub:']);
	global $argv;
	if(!isset($opts['make'])){
		echo '== PocketMine-MP DevTools-lakwsh CLI interface =='.PHP_EOL.PHP_EOL;
		echo 'Usage: '.PHP_BINARY.' -dphar.readonly=0 '.$argv[0].' --make <src1[,src2[,src3...]]> --exclude <1.php[,2.php[,3.php...]]> --relative <relativePath> --entry "relativePath.php" --out <pharName.phar>'.PHP_EOL;
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
			echo "[ERROR] make directory `$path` does not exist or permission denied".PHP_EOL;
			exit(1);
		}
		$path=rtrim($realPath,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
	});
	$basePath='';
	if(!isset($opts['relative'])){
		if(count($includedPaths)>1){
			echo 'You must specify a relative path with --relative [path] to be able to include multiple directories'.PHP_EOL;
			exit(1);
		}else{$basePath=rtrim(realpath(array_shift($includedPaths)),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;}
	}else{$basePath=rtrim(realpath($opts['relative']),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;}
	$includedPaths=array_filter(array_map(function(string $path) use ($basePath):string{return str_replace($basePath,'',$path);},$includedPaths),function(string $v):bool{return $v!=='';});
	$pharName=$opts['out']??'Output_'.time().'.phar';
	$stubPath=$opts['stub']??'stub.php';
	if(!is_dir($basePath)){
		echo $basePath.' is not a folder'.PHP_EOL;
		exit(1);
	}
	echo PHP_EOL;
	if(file_exists($pharName)){
		echo $pharName.' already exists, overwriting...'.PHP_EOL;
		@unlink($pharName);
	}
	echo 'Creating '.$pharName.'...'.PHP_EOL;
	$start=microtime(true);
	$phar=new \Phar($pharName);
	if(file_exists($basePath.$stubPath)){
		echo 'Using stub '.$basePath.$stubPath.PHP_EOL;
		$phar->setStub('<?php require("phar://".__FILE__."/'.$stubPath.'");__HALT_COMPILER();');
	}elseif(isset($opts['entry'])){
		$realEntry=realpath($opts['entry']);
		if($realEntry===false) exit('Entry point not found');
		$realEntry=addslashes(str_replace([$basePath, "\\"],['','/'],$realEntry));
		echo 'Setting entry point to '.$realEntry.PHP_EOL;
		$phar->setStub('<?php require("phar://".__FILE__."/'.$realEntry.'");__HALT_COMPILER();');
	}else{
		if(!file_exists($basePath.'plugin.yml')){
			echo "Missing entry point or plugin.yml\n";
			exit(1);
		}
		$phar->setStub('<?php __HALT_COMPILER();');
	}
	$phar->setSignatureAlgorithm(\Phar::SHA1);
	$phar->startBuffering();
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
			echo $error;
		}
		exit(1);
	}
	echo 'Adding files...'.PHP_EOL;
	$excluded=[DIRECTORY_SEPARATOR.'.',realpath($pharName),'ConsoleScript.php'];
	if(isset($opts['exclude'])) array_push($excluded,explode(',',$opts['exclude']));
	$regex=sprintf('/^(?!.*(%s))^%s(%s).*/i',implode('|',preg_quote_array($excluded,'/')),preg_quote($basePath,'/'),implode('|',preg_quote_array($includedPaths,'/')));
	$directory=new \RecursiveDirectoryIterator($basePath,\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::FOLLOW_SYMLINKS|\FilesystemIterator::CURRENT_AS_PATHNAME);
	$regexIterator=new \RegexIterator(new \RecursiveIteratorIterator($directory),$regex);
	$count=count($phar->buildFromIterator($regexIterator, $basePath));
	echo 'Added '.$count.' files'.PHP_EOL;
	$phar->compressFiles(\Phar::GZ);
	$phar->stopBuffering();
	echo PHP_EOL.'Done in '.round(microtime(true)-$start,3).'s'.PHP_EOL;
	exit(0);
	function preg_quote_array(array $strings,string $delim=null):array{
		return array_map(function(string $str) use ($delim):string{return preg_quote($str,$delim);},$strings);
	}