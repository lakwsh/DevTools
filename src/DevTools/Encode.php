<?php
	function EncodePHP($file){
		if(substr($file,-4)!='.php') return false;
		//删除换行等
		$c=php_strip_whitespace($file);
		//移除php标记
		if(($head=strpos($c,'<?php'))===false) return false;
		if(($foot=strrpos($c,'?>'))===false) $c=substr($c,$head+5);
		else $c=substr($c,$head+5,$foot-$head);
		//压缩函数：gzcompress gzdeflate gzencode
        //解压函数：gzuncompress gzinflate gzdecode
        //gzcompress使用ZLIB格式 gzdeflate使用DEFLATE格式 gzencode使用GZIP格式
        $start=mt_rand(0,100);
        $end=mt_rand(0,100);
        $c=randomStr($start).base64_encode(gzcompress(trim($c))).randomStr($end);
		//随机变量名
		$out=randomName(9,7);
        $key=str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789~`!@#$%^&*()-=_+{}[]|:;",.<>?/');
		$cmd=randomCommand($key,$out[0],$start+8,$end+8);
		//混合内容
        $c='$'.$out[0].'=hex2bin("'.bin2hex($key).'")'.';$'.$out[1].'='.$cmd[0].';$'.$out[2].'='.$cmd[1].';$'.$out[3].'='.$cmd[2].';$'.$out[4].'='.$cmd[3].';$'.$out[5].'='.$cmd[4].';eval($'.$out[2].'("'.base64_encode('$'.$out[6].'="'.$c.'";eval($'.$out[5].'($'.$out[2].'($'.$out[3].'($'.$out[6].',$'.$out[4].',$'.$out[1].'))));').'"));';
		file_put_contents($file,'<?php '.$c.' ?>');
		return true;
	}
	function randomStr($long){
        $str='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
        $out='bGFrd3No';    // lakwsh
        for($i=0;$i<$long;$i++) $out.=$str{mt_rand(0,63)};
        return str_shuffle($out);
    }
    function randomName($long,$count){
        $out=array();
        for($t=0;$t<$count;$t++){
            $name='';
            for($i=0;$i<$long;$i++){
                if(mt_rand(1,2)==1) $name.='0';
                else $name.='O';
            }
            if(in_array($name,$out)) $t--;
            elseif($name{0}=='0') $t--;
            else $out[$t]=$name;
        }
        return $out;
    }
    function randomCommand($key,$name,$start,$end){
        $cmds=array('-'.(string)$end,'base64_decode','substr',(string)$start,'gzuncompress');
        $out=array();
        foreach($cmds as $cmd){
            $c='';
            for($i=0;$i<strlen($cmd);$i++) $c.='.$'.$name.'{'.stripos($key,$cmd{$i}).'}';
            array_push($out,substr($c,1));
        }
        return $out;
    }