<?php
/**
 * @package bbn\str
 */
namespace bbn\str;

define("ERR_OPEN_MAP_FILE","ERR_OPEN_MAP_FILE");

class utf8 {
	var $ascMap = array();
	var $utfMap = array();

	/* Constructor */
	function utf8($charset='ISO-8859-1')
	{
		$this->$charset = $charset;
		$this->loadCharset($charset);
	}

	/* Load charset */
	function loadCharset($charset)
	{
		if ( stripos($charset,'ISO-') !== false ){
			$filename = BBN_LIB_PATH . 'bbn/bbn/src/bbn/str/charset/' . substr($charset, stripos($charset, 'ISO-')+4) . '.txt';
		}
		else {
			return false;
		}
		$lines = @file_get_contents($filename)
		or exit($this->onError(ERR_OPEN_MAP_FILE,"Error openning file: " . $this->filename));
		$this->charset = $charset;
		$lines = preg_replace("/#.*$/m","",$lines);
		$lines = preg_replace("/\n\n/","",$lines);
		$lines = explode("\n",$lines);
		foreach($lines as $line){
			$parts = explode('0x',$line);
			if(count($parts)==3){
				$asc=hexdec(substr($parts[1],0,2));
				$utf=hexdec(substr($parts[2],0,4));
				$this->ascMap[$charset][$asc]=$utf;
			}
		}
		$this->utfMap = array_flip($this->ascMap[$charset]);
		return true;
	}

	/* Error handler */
	function onError($err_code,$err_text)
	{
		print($err_code . " : " . $err_text . "<hr>\n");
	}

	/* Translate string ($str) to UTF-8 from given charset */
	function strToUtf8($str)
	{
		$chars = unpack('C*', $str);
		$cnt = count($chars);
		for($i=1;$i<=$cnt;$i++)
			$this->_charToUtf8($chars[$i]);
		return implode("",$chars);
	}

	/* Translate UTF-8 string to single byte string in the given charset */
	function utf8ToStr($utf)
	{
		$chars = unpack('C*', $utf);
		$cnt = count($chars);
		$res = ""; /* No simple way to do it in place... concatenate char by char */
		for ($i=1;$i<=$cnt;$i++)
			$res .= $this->_utf8ToChar($chars, $i);
		return $res;
	}

	/* Char to UTF-8 sequence */
	function _charToUtf8(&$char)
	{
		$c = (int)$this->ascMap[$this->charset][$char];
		if ($c < 0x80)
			$char = chr($c);
		else if($c<0x800) /* 2 bytes */
			$char = (chr(0xC0 | $c>>6) . chr(0x80 | $c & 0x3F));
		else if($c<0x10000) /* 3 bytes */
			$char = (chr(0xE0 | $c>>12) . chr(0x80 | $c>>6 & 0x3F) . chr(0x80 | $c & 0x3F));
		else if($c<0x200000) /* 4 bytes */
			$char = (chr(0xF0 | $c>>18) . chr(0x80 | $c>>12 & 0x3F) . chr(0x80 | $c>>6 & 0x3F) . chr(0x80 | $c & 0x3F));
	}

	/* UTF-8 sequence to single byte character */
	function _utf8ToChar(&$chars, &$idx)
	{
		if(($chars[$idx] >= 240) && ($chars[$idx] <= 255))
			/* 4 bytes */
			$utf =    (intval($chars[$idx]-240)   << 18) +
				(intval($chars[++$idx]-128) << 12) +
				(intval($chars[++$idx]-128) << 6) +
				(intval($chars[++$idx]-128) << 0);
		else if (($chars[$idx] >= 224) && ($chars[$idx] <= 239))
			/* 3 bytes */
			$utf =    (intval($chars[$idx]-224)   << 12) +
				(intval($chars[++$idx]-128) << 6) +
				(intval($chars[++$idx]-128) << 0);
		else if (($chars[$idx] >= 192) && ($chars[$idx] <= 223))
			/* 2 bytes */
			$utf =    (intval($chars[$idx]-192)   << 6) +
				(intval($chars[++$idx]-128) << 0);
		else
			/* 1 byte */
			$utf = $chars[$idx];
		if(array_key_exists($utf,$this->utfMap))
			return chr($this->utfMap[$utf]);
		else
			return "?";
	}
}
?>