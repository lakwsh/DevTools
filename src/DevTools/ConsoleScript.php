<?php
	const VERSION='1.12.8';
	date_default_timezone_set('Asia/Hong_Kong');
	$opts=getopt('',['make:','relative:','out:','entry:','stub:']);
	if(!isset($opts['make'])){
		echo '== PocketMine-MP DevTools-lakwsh CLI interface =='.PHP_EOL.PHP_EOL;
		echo 'Usage: '.PHP_BINARY.' -dphar.readonly=0 '.$argv[0].' --make <sourceFolder1[,sourceFolder2[,sourceFolder3...]]> --relative <relativePath> --entry "relativeSourcePath.php" --out <pharName.phar>'.PHP_EOL;
		exit(0);
	}
	if(ini_get('phar.readonly')==1){
		echo 'Set phar.readonly to 0 with -dphar.readonly=0'.PHP_EOL;
		exit(1);
	}
	$includedPaths=explode(',',$opts['make']);
	array_walk($includedPaths,function(&$path,$key){
		$realPath=realpath($path);
		if($realPath===false){
			echo "[ERROR] make directory `$path` does not exist or permission denied".PHP_EOL;
			exit(1);
		}
		$path=rtrim(str_replace("\\",'/',$realPath),'/').'/';
	});
	$basePath='';
	if(!isset($opts['relative'])){
		if(count($includedPaths)>1){
			echo 'You must specify a relative path with --relative <path> to be able to include multiple directories'.PHP_EOL;
			exit(1);
		}else{
			$basePath=rtrim(str_replace("\\",'/',realpath(array_shift($includedPaths))),'/').'/';
		}
	}else{
		$basePath=rtrim(str_replace("\\",'/',realpath($opts['relative'])),'/').'/';
	}
	$includedPaths=array_filter(array_map(function(string $path) use ($basePath):string{return str_replace($basePath,'',$path);},$includedPaths),function(string $v):bool{return $v!=='';});
	$pharName=$opts['out']??'DevTools-lakwsh_v'.date('Y-m-d').'.phar';
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
	$phar=new \Phar($pharName);
	$start=microtime(true);
	if(file_exists($basePath.$stubPath)){
		echo 'Using stub '.$basePath.$stubPath.PHP_EOL;
		$phar->setStub('<?php require("phar://".__FILE__."/'.$stubPath.'"); __HALT_COMPILER();');
	}elseif(isset($opts['entry'])){
		$realEntry=realpath($opts['entry']);
		if($realEntry===false) exit('Entry point not found');
		$entry=addslashes(str_replace("\\",'/',$realEntry));
		$entry=str_replace($basePath,'',$entry);
		echo "Setting entry point to ".$entry.PHP_EOL;
		$phar->setStub('<?php require("phar://".__FILE__."/'.$entry.'"); __HALT_COMPILER();');
	}else{
		if(file_exists($basePath.'plugin.yml')){
			$metadata=yaml_parse_file($basePath.'plugin.yml');
		}else{
			echo "Missing entry point or plugin.yml\n";
			exit(1);
		}
		$phar->setMetadata(['name'=>$metadata['name'],'version'=>$metadata['version'],'main'=>$metadata['main'],'api'=>$metadata['api'],'depend'=>($metadata['depend']??''),'description'=>($metadata['description']??''),'authors'=>($metadata['authors']??''),'website'=>($metadata['website']??''),'creationDate'=>time()]);
		$phar->setStub('<?php echo "PocketMine-MP plugin '.$metadata['name'].' v'.$metadata['version'].'\nThis file has been generated using DevTools-lakwsh v'.VERSION.' at '.date('r').'\n----------------\n";if(extension_loaded("phar")){$phar=new \Phar(__FILE__);foreach($phar->getMetadata() as $key=>$value){echo ucfirst($key).": ".(is_array($value)?implode(",",$value):$value)."\n";}} __HALT_COMPILER();');
	}
	$phar->setSignatureAlgorithm(\Phar::SHA1);
	$phar->startBuffering();
	echo 'Checking files...'.PHP_EOL;
	$flag=false;
	$output=array();
	foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath)) as $file){
		if(substr($file,-4)!=='.php') continue;
		exec('php -l "'.$file.'"',$output,$status);
		if($status!==0) $flag=true;
	}
	if($flag){
		foreach($output as $error){
			if(stripos($error,'No syntax errors detected')!==false) continue;
			echo $error;
		}
		exit(1);
	}
	echo 'Adding files...'.PHP_EOL;
	$excludedSubstrings=['/.',$pharName];
	$regex=sprintf('/^(?!.*(%s))^%s(%s).*/i',implode('|',preg_quote_array($excludedSubstrings,'/')),preg_quote($basePath,'/'),implode('|',preg_quote_array($includedPaths,'/')));
	$count=count($phar->buildFromDirectory($basePath,$regex));
	echo 'Added '.$count.' files'.PHP_EOL;
	$phar->compressFiles(\Phar::GZ);
	$phar->stopBuffering();
	echo PHP_EOL.'Done in '.round(microtime(true)-$start,3).'s'.PHP_EOL;
	exit(0);
	function preg_quote_array(array $strings,string $delim=null):array{
		return array_map(function(string $str) use ($delim):string{return preg_quote($str,$delim);},$strings);
	}