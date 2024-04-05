<?php
namespace bbn\Compilers;

use Exception;
use Less_Parser;
use bbn\Models\Cls\Basic;
use bbn\X;


class Less extends Basic
{
	public function __construct() {

	}

	public function compile(string $str): string
	{
		$res = '';
		try {
			$less = new Less_Parser();
			$less->parse($str);
			$res = $less->getCss();
		}
		catch (Exception $e) {
			X::logError($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
		}

		return $res;
	}



}


