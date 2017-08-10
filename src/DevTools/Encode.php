<?php
	function EncodePHP($file){
		if(substr($file,-4)!='.php') return false;
		if(substr(file_get_contents($file),0,5)!='<?php') return false;
		//删除换行等
		$c=php_strip_whitespace($file);
		//移除php标记
		$headerPos=strpos($c,'<?php ');
		$footerPos=strrpos($c,' ?>');
		if($footerPos==false) $c=substr($c,$headerPos+6);
		else $c=substr($c,$headerPos+6,$footerPos-$headerPos);
		//压缩函数：gzcompress gzdeflate gzencode
		//解压函数：gzuncompress gzinflate gzdecode
		//gzcompress使用ZLIB格式 gzdeflate使用DEFLATE格式 gzencode使用GZIP格式
		$c=base64_encode(gzcompress($c));
		//随机字符串
		$str='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$c=str_shuffle($str).$c.str_shuffle($str);
		//随机变量名
		$out=array();
		for($t=0;$t<8;$t++){
			$name='';
			for($i=0;$i<9;$i++){
				$rad=mt_rand(1,2);
				if($rad==1) $name.='0';
				else $name.='O';
			}
			if(in_array($name,$out)) $t--;
			elseif($name{0}=='0') $t--;
			else $out[$t]=$name;
		}
		$q1=$out[1];
		$q2=$out[2];
		$q3=$out[3];
		$q4=$out[4];
		$q5=$out[5];
		$q6=$out[6];
		$q7=$out[7];
		//混合内容
		$c='$'.$q6.'=hex2bin("'.bin2hex('n1zb/ma5\vt0i28-pxuqy*6lrkdg9_ehcswo4 f37j').'");$'.$q7.'=$'.$q6.'{27}.$'.$q6.'{2}.$'.$q6.'{18}.$'.$q6.'{0}.$'.$q6.'{32}.$'.$q6.'{35}.$'.$q6.'{5};$'.$q1.'=$'.$q6.'{3}.$'.$q6.'{6}.$'.$q6.'{33}.$'.$q6.'{30};$'.$q3.'=$'.$q6.'{33}.$'.$q6.'{10}.$'.$q6.'{24}.$'.$q6.'{10}.$'.$q6.'{24};$'.$q4.'=$'.$q3.'{0}.$'.$q6.'{18}.$'.$q6.'{3}.$'.$q3.'{0}.$'.$q3.'{1}.$'.$q6.'{24};$'.$q7.'.=$'.$q6.'{16}.$'.$q6.'{24}.$'.$q6.'{30}.$'.$q6.'{33}.$'.$q6.'{33};$'.$q5.'=$'.$q6.'{15}.$'.$q6.'{7}.$'.$q6.'{13};$'.$q1.'.=$'.$q6.'{22}.$'.$q6.'{36}.$'.$q6.'{29}.$'.$q6.'{26}.$'.$q6.'{30}.$'.$q6.'{32}.$'.$q6.'{35}.$'.$q6.'{26}.$'.$q6.'{30};eval($'.$q1.'("'.base64_encode('$'.$q2.'="'.$c.'";eval($'.$q7.'($'.$q1.'($'.$q4.'($'.$q2.',$'.$q6.'{7}.$'.$q6.'{13},$'.$q5.'))));').'"));';
		file_put_contents($file,'<?php '.$c.' ?>');
		return true;
	}
?>