<?php
namespace bbn\db;
/**
* This is a simple sql tokenizer / parser.
*
* I needed code to get the total count of a paged recordset, so I wrote this.
* It seems to handle sane mssql/mysql queries.
*
*
* @author Justin Carlson <justin.carlson@gmail.com>
* @license LGPL 3
* @version 0.0.4
*/
class parser 
{
	/**
	 * @var mixed
	 */
	public $handle=null;

	/**
	 * @var array
	 */
	public static $querysections=array('alter','create','drop','select','show','delete','insert','update','from','where','group','limit','order');

	/**
	 * @var array
	 */
	public static $operators=array('!=','=','<>','<','<=','>','>=','like','clike','slike','not','is','is not', 'in','between');

	/**
	 * @var array
	 */
	public static $types=array('character','char','varchar','nchar','bit','numeric','decimal','dec','integer','int','smallint','float','real','double','date','datetime','time','timestamp','interval','bool','boolean','set','enum','text');

	/**
	 * @var array
	 */
	public static $conjuctions=array('by','as','on','into','from','where','with');

	/**
	 * @var array
	 */
	public static $funcitons=array('avg','count','max','min','sum','nextval','currval','concat');

	/**
	 * @var array
	 */
	public static $reserved=array('absolute','action','add','all','allocate','and','any','are','asc','ascending','assertion','at','authorization','begin','bit_length','both','cascade','cascaded','case','cast','catalog','char_length','character_length','check','close','coalesce','collate','collation','column','commit','connect','connection','constraint','constraints','continue','convert','corresponding','cross','current','current_date','current_time','current_timestamp','current_user','cursor','day','deallocate','declare','default','deferrable','deferred','desc','descending','describe','descriptor','diagnostics','disconnect','distinct','domain','else','end','end-exec','escape','except','exception','exec','execute','exists','external','extract','false','fetch','first','for','foreign','found','full','get','global','go','goto','grant','group','having','hour','identity','immediate','indicator','initially','inner','input','insensitive','intersect','isolation','join','key','language','last','leading','left','level','limit','local','lower','match','minute','module','month','names','national','natural','next','no','null','nullif','octet_length','of','only','open','option','or','order','outer','output','overlaps','pad','partial','position','precision','prepare','preserve','primary','prior','privileges','procedure','public','read','references','relative','restrict','revoke','right','rollback','rows','schema','scroll','second','section','session','session_user','size','some','space','sql','sqlcode','sqlerror','sqlstate','substring','system_user','table','temporary','then','timezone_hour','timezone_minute','to','trailing','transaction','translate','translation','trim','true','union','unique','unknown','upper','usage','user','using','value','values','varying','view','when','whenever','work','write','year','zone','eoc');

	/**
	 * @var array
	 */
	public static $startparens=array('{','(');

	/**
	 * @var array
	 */
	public static $endparens=array('}',')');

	/**
	 * @var array
	 */
	public static $tokens=array(',',' ');

	/**
	 * @var string
	 */
	private $query='';


	/**
	 * @return void 
	 */
	public function __construct()
	{
	}

	/**
	 * Simple SQL Tokenizer
	 *
	 * @author Justin Carlson <justin.carlson@gmail.com>
	 * @license GPL
	 * @param string $sqlQuery
	 * @return token array
	 */
	public static function Tokenize($sqlQuery, $cleanWhitespace=true)
	{
		/**
		* Strip extra whitespace from the query
		*/
		if($cleanWhitespace)
			$sqlQuery = ltrim(preg_replace('/[\\s]{2,}/',' ',$sqlQuery));
		/**
		* Regular expression based on SQL::Tokenizer's Tokenizer.pm by Igor Sutton Lopes
		**/
		$regex = '('; /* begin group */
		$regex .= '(?:--|\\#)[\\ \\t\\S]*'; /* inline comments */
		$regex .= '|(?:<>|<=>|>=|<=|==|=|!=|!|<<|>>|<|>|\\|\\||\\||&&|&|-|\\+|\\*(?!\/)|\/(?!\\*)|\\%|~|\\^|\\?)'; /* logical operators */
		$regex .= '|[\\[\\]\\(\\),;`]|\\\'\\\'(?!\\\')|\\"\\"(?!\\"")'; /* empty single/double quotes */
		$regex .= '|".*?(?:(?:""){1,}"|(?<!["\\\\])"(?!")|\\\\"{2})|\'.*?(?:(?:\'\'){1,}\'|(?<![\'\\\\])\'(?!\')|\\\\\'{2})'; /* quoted strings */
		$regex .= '|\/\\*[\\ \\t\\n\\S]*?\\*\/'; /* c style comments */
		$regex .= '|(?:[\\w:@]+(?:\\.(?:\\w+|\\*)?)*)'; /* words, placeholders, database.table.column strings */
		$regex .= '|[\t\ ]+';
		$regex .= '|[\.]'; /* period */
		$regex .= '|[\s]'; /* whitespace */
		$regex .= ')'; /* end group */
		/* get global match */
		preg_match_all( '/' . $regex . '/smx', $sqlQuery, $result );
		/* return tokens */
		return $result[0];
	}

	/**
	 * Simple SQL parser
	 *
	 * @author Justin Carlson <justin.carlson@gmail.com>
	 * @license LGPL 3
	 * @param string $sqlQuery
	 * @param bool optional $cleanup
	 * @return Sqlparser Object
	 */
	public static function ParseString($sqlQuery, $cleanWhitespace=true)
	{
		/* returns a Sqlparser object */
		if (! isset( $this ))
			$handle = new parser();
		else
			$handle = $this;
		/* copy and cut the query */
		$tokens = self::Tokenize( $sqlQuery, $cleanWhitespace );
		$tokenCount = count( $tokens );
		$queryParts = array();
		$section = $tokens[0];
		/* parse the tokens */
		for ($t = 0; $t < $tokenCount; $t ++)
		{
			if (in_array( $tokens[$t], self::$startparens ))
			{
				$sub = $handle->readsub( $tokens, $t );
				$handle->query[$section].= $sub;
			}
			else
			{
				if ( in_array(strtolower($tokens[$t]),self::$querysections) && !isset($handle->query[$tokens[$t]]) )
					$section = strtolower($tokens[$t]);
				/* rebuild the query in sections */
				if (!isset($handle->query[$section]))
					$handle->query[$section] = '';
				$handle->query[$section] .= $tokens[$t];  
			}
		}
		return $handle;
	}

	/**
	 * Parses a section of a query ( usually a sub-query or where clause )
	 *
	 * @param array $tokens
	 * @param int $position
	 * @return string section
	 */
	private function readsub($tokens, &$position)
	{
		$sub = $tokens[$position];
		$tokenCount = count( $tokens );
		$position ++;
		while ( ! in_array( $tokens[$position], self::$endparens ) && $position < $tokenCount )
		{
			if (in_array( $tokens[$position], self::$startparens ))
				$sub.= $this->readsub( $tokens, $position );
			else
				$sub.= $tokens[$position];
			$position ++;
		}
		$sub.= $tokens[$position];
		return $sub;
	}

	/**
	 * Returns manipulated sql to get the number of rows in the query.
	 *
	 * @author Justin Carlson <justin.carlson@gmail.com>
	 * @license LGPL 3
	 * @return string sql
	 */
	public function getCountQuery()
	{
		$res = $this->query;
		$res['select'] = 'select count(*) as `count` ';
		unset($res['limit']);
		unset($res['order']);
		return implode('',$res);
	}

	/**
	 * Returns manipulated sql to get the unlimited number of rows in the query.
	 *
	 * @author Justin Carlson <justin.carlson@gmail.com>
	 * @license LGPL 3
	 * @return string sql
	 */
	public function getLimitedCountQuery()
	{
		$res = $this->query;
		$res['select'] = 'select count(*) as `count` ';
		unset($res['order']);
		return implode('',$res);
	}

	/**
	 * Returns the select section of the query.
	 *
	 * @author Justin Carlson <justin.carlson@gmail.com>
	 * @license LGPL 3
	 * @return string sql
	 */
	public function getSelectStatement()
	{
		return isset($this->query['show']) ? $this->query['show'] : $this->query['select'];
	}

	/**
	 * Returns the from section of the query.
	 *
	 * @author Justin Carlson <justin.carlson@gmail.com>
	 * @license LGPL 3
	 * @return string sql
	 */
	public function getFromStatement()
	{
		return $this->query['from'];
	}

	/**
	 * Returns the where section of the query.
	 *
	 * @author Justin Carlson <justin.carlson@gmail.com>
	 * @license LGPL 3
	 * @return string sql
	 */
	public function getWhereStatement()
	{
		return $this->query['where'];
	}

	/**
	 * Returns the limit section of the query.
	 *
	 * @author Justin Carlson <justin.carlson@gmail.com>
	 * @license LGPL 3
	 * @return string sql
	 */
	public function getLimitStatement()
	{
		return $this->query['limit'];
	}

	/**
	 * Returns the specified section of the query.
	 *
	 * @author Justin Carlson <justin.carlson@gmail.com>
	 * @license LGPL 3
	 * @return string sql
	 */
	public function get($which)
	{
		if(!isset($this->query[$which]))
			return false;
		return $this->query[$which];
	}

	/**
	 * Returns the sections of the query.
	 *
	 * @author Justin Carlson <justin.carlson@gmail.com>
	 * @license LGPL 3
	 * @return string sql
	 */
	public function getArray()
	{
		return $this->query;
	}

}
?>