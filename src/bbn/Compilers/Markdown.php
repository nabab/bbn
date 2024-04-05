<?php
namespace bbn\Compilers;

use Exception;
use Michelf\Markdown as MD;
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
      
			$parser = new Mardown();
			$less->parse($str);
			$res = $less->getCss();
		}
		catch (Exception $e) {
			X::logError($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
		}

		return $res;
	}


	public function compile(string $str): string
	{
		return MD::defaultTransform($str);
	}



}


