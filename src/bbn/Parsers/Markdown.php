<?php
namespace bbn\Parsers;

use Exception;
use Less_Parser;
use bbn\Models\Cls\Basic;
use bbn\X;


class Markdown extends Basic
{
	public function __construct() {

	}

	public function parse(string $str): string
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

