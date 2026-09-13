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
      
			$parser = new MD();
			$res = $parser->transform($str);
		}
		catch (Exception $e) {
			X::logError($e);
		}

		return $res;
	}


	public function compile(string $str): string
	{
		return MD::defaultTransform($str);
	}



}


