<?php
/**
 * @package bbn\util
 */
namespace bbn\util;
/**
 * A tool for parsing code.
 *
 *
 * This class will work with PHP, Javascript, and CSS
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Utilities
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 & @todo Change make_love_with_me to \bbn\str\text::clean
 */
class code_parser 
{
	/**
	 * @var mixed
	 */
	public $type;

	/**
	 * will be the resulting array of the string, divided in sequences, having a kind and a content
	 * @var array
	 */
	private $sequences=array(array('kind'=>'code','content'=>''));

	/**
	 * Special chars we know don't need space around in the code - no / * or \ on purpose
	 * @var array
	 */
	private static $specials=array(';','=','+','-','@','(',')','{','}','[',']',',',':');


	/**
	 * @return void 
	 */
	public function __construct($string, $type='js')
	{
		$this->type = $type;
		$this->css = ( $this->type === 'css' );
		/* An array of each char of the string */
		$chars = str_split($string);
		/* Says if the actual char is escaped by \ or not */
		$escape = false;
		/* Says if we are in a single quotes sequence */
		$single_quotes = false;
		/* Says if we are in a double quotes sequence */
		$double_quotes = false;
		/* Says if we are in a regex sequence */
		$regex = false;
		/* Says if we are in a block comments (like here!) sequence */
		$block_comments = false;
		/* Says if we are in a line comments (like here!) sequence */
		$line_comments = false;
		/* $index is the actual index of the sequence (in the $seq array) */
		$index = 0;
		/* We will go through each char called $c 
		$a is the previous char, excluded of spaces, new lines and tabs
		$b is the previous char, whatever it is
		$d is the next char
		*/
		$a = $b = $d = '';
		foreach ( $chars as $i => $c )
		{
			/* When we will have to change sequence, either $cur or $var will be defined */
			$cur = false;
			$next = false;
			if ( isset($chars[$i+1]) )
				$d = $chars[$i+1];
			/* Single quote */
			if ( $c === "'" && !$double_quotes && !$regex && !$block_comments && !$line_comments && !$escape && !$this->css )
			{
				if ( $single_quotes )
				{
					$single_quotes = false;
					$cur = 'code';
				}
				else
				{
					$single_quotes = 1;
					$next = 'single_quotes';
				}
			}
			/* Double quote */
			else if ( $c === '"' && !$single_quotes && !$regex && !$block_comments && !$line_comments && !$escape && !$this->css )
			{
				if ( $double_quotes )
				{
					$double_quotes = false;
					$cur = 'code';
				}
				else
				{
					$double_quotes = 1;
					$next = 'double_quotes';
				}
			}
			/* Slash */
			else if ( $c === '/' )
			{
				if ( $block_comments && $b === '*' )
				{
					$block_comments = false;
					$cur = 'code';
				}
				else if ( !$single_quotes && !$double_quotes && !$block_comments && !$line_comments )
				{
					if ( $d === '/' && !$escape && !$this->css )
					{
						$line_comments = 1;
						$next = 'line_comments';
					}
					else if ( $d === '*' && !$escape )
					{
						/* Checks whether it's conditional compilation for IE or not */
						if ( !isset($chars[$i+2]) || $chars[$i+2] !== '@' )
						{
							$block_comments = 1;
							$next = 'block_comments';
						}
					}
					else if ( !$escape && !$this->css )
					{
						if ( $regex )
						{
							$regex = false;
							$cur = 'code';
						}
						else if ( !$regex && ( $a === '=' || $a === '[' || $a === ':' || $a === '(' || $a === '!' || $a === '&' ) )
						{
							$regex = 1;
							$next = 'regex';
						}
					}
				}
			}
			/* New line */
			else if ( $c === "
" && $line_comments )
			{
				$line_comments = false;
				$cur = 'code';
			}
			/* Check if the next char will be escaped */
			if ( $c === '\\' && !$this->css )
				$escape = $escape ? false : 1;
			else
				$escape = false;
			/*
			If $cur is defined, the character $c will finish the current sequence
			If $next is defined, the character $c will start the next sequence
			In both cases, we add a sequence and increment index
			Otherwise we just add the character to the current sequence
			*/
			if ( $cur )
			{
				$this->sequences[$index]['content'] .= $c;
				if ( trim($this->sequences[$index]['content']) !== '' || $cur !== 'code' )
					$index++;
				$this->sequences[$index] = array('kind'=>$cur,'content'=>'');
			}
			else if ( $next )
			{
				if ( trim($this->sequences[$index]['content']) !== '' || $cur !== 'code' )
					$index++;
				$this->sequences[$index] = array('kind'=>$next,'content'=>$c);
			}
			else
				$this->sequences[$index]['content'] .= $c;
			$b = $c;
			if ( $b !== ' ' && $b !== '	' && $b !== "
	" )
				$a = $b;
		}
		/* file_put_contents('_log/log.log',print_r($this->sequences,true),FILE_APPEND); */
		return $this;
	}

	/**
	 * @return void 
	 */
	private function make_love_with_me($s)
	{
		$s = str_replace("\n"," ",$s);
		$s = str_replace("\r","",$s);
		$s = str_replace("\t"," ",$s);
		foreach ( self::$specials as $char )
		{
			/* negative value used in css shortcuts need their spaces */
			if ( !$this->css || $char !== '-' )
			{
				while ( strpos($s,$char.' ') !== false )
					$s = str_replace($char.' ',$char,$s);
				while ( strpos($s,' '.$char) !== false )
					$s = str_replace(' '.$char,$char,$s);
			}
		}
		$s = str_replace('<?p'.'hp','<?p'.'hp ',$s);
		$s = str_replace('?'.'>',' ?'.'>',$s);
		return preg_replace('/\s{2,}/',' ',$s);
	}

	/**
	 * @return void 
	 */
	public function get_sequences()
	{
		return $this->sequences;
	}

	/**
	 * @return void 
	 */
	public function get_code()
	{
		$r = '';
		foreach ( $this->sequences as $s )
		{
			if ( $s['kind'] == 'code' || $s['kind'] == 'double_quotes' || $s['kind'] == 'single_quotes' || $s['kind'] == 'regex' )
				$r .= $s['content'];
		}
		return $r;
	}

	/**
	 * @return void 
	 */
	public function get_minified()
	{
		$r = '';
		foreach ( $this->sequences as $s )
		{
			if ( $s['kind'] === 'code' )
				$r .= $this->make_love_with_me($s['content']);
			else if ( $s['kind'] == 'double_quotes' || $s['kind'] == 'single_quotes' || $s['kind'] == 'regex' )
				$r .= $s['content'];
		}
		return $r;
	}

}
?>