<?php

namespace bbn\Parsers;

/**
 * Documentation block parser
 *
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @copyright BBN Solutions
 * @category Parsers
 * @version 1.0
 */
class Doc {
  private
    /**
     * @var string $source The current source
     */
    $source = '',
    /**
     * @var string $mode The current mode
     */
    $mode = '',
    /**
     * @var array $modes The modes allowed
     */
    $modes = [
      'js',
      'vue',
      'php'
    ],
    /**
     * @var array $tags
     */
    $tags = [],
    /**
     * @var array $all_tags
     */
    $all_tags = [
      'common' => [
        'author' => ['text'],
        'copyright' => ['text'],
        'deprecated' => ['text'],
        'example' => ['text'],
        'file' => ['text'],
        'ignore' => [],
        'license' => ['text'],
        'link' => ['text'],
        'package' => ['text'],
        'return' => ['type', 'description'],
        'returns' => ['type', 'description'],
        'since' => ['text'],
        'throws' => ['type', 'description'],
        'todo' => ['text'],
        'version' => ['text'],
      ],
      'js' => [
        'abstract' => [],
        'access' => ['text'],
        'alias' => [],
        'arg' => 'param',
        'argument' => 'param',
        'async' => [],
        'augments' => [],
        'borrows' => [],
        'callback' => [],
        'class' => [],
        'classdesc' => [],
        'const' => 'constant',
        'constant' => [],
        'constructor' => 'class',
        'constructs' => [],
        'default' => ['text'],
        'defaultValue' => 'default',
        'desc' => 'description',
        'description' => ['text'],
        'emits' => 'fires',
        'enum' => [],
        'event' => ['name'],
        'exception' => 'throws',
        'exports' => [],
        'extends' => 'augments',
        'external' => [],
        'file' => ['text'],
        'fileoverview' => 'file',
        'fires' => ['name'],
        'func' => 'function',
        'function' => ['name'],
        'generator' => [],
        'global' => [],
        'hidecontructor' => [],
        'host' => 'external',
        'implements' => [],
        'inner' => [],
        'instance' => [],
        'interface' => [],
        'kind' => [],
        'lends' => [],
        'linkcode' => 'link',
        'linkplain' => 'link',
        'listens' => [],
        'member' => [],
        'memberof' => ['name'],
        'method' => 'function',
        'mixes' => [],
        'mixin' => ['name'],
        'module' => [],
        'name' => ['name'],
        'namespace' => [],
        'override' => [],
        'overview' => 'file',
        'param' => ['type', 'default', 'name', 'description'],
        'private' => [],
        'prop' => 'property',
        'property' => ['type', 'default', 'name'],
        'protected' => [],
        'public' => [],
        'readonly' => [],
        'requires' => [],
        'returns' => 'return',
        'see' => ['name'],
        'static' => [],
        'summary' => ['text'],
        'this' => [],
        'tutorial' => [],
        'type' => ['type'],
        'typedef' => [],
        'var' => 'member',
        'variation' => [],
        'virtual' => 'abstract',
        'yield' => 'yields',
        'yields' => [],
      ],
      'vue' => [
        'component' => ['name'],
        'computed' => ['name'],
        'data' => ['type', 'default', 'name', 'description'],
        'emits' => ['name'],
        'method' => ['name'],
        'prop' => ['type', 'default', 'name'],
        'required' => ['text'],
        'watch' => ['name', 'description']
      ],
      'php' => [
        'api' => [],
        'category' => ['text'],
        'filesource' => [],
        'global' => ['type', 'name', 'description'],
        'internal' => ['text'],
        'method' => ['text'],
        'package' => ['text'],
        'param' => ['type', 'name', 'description'],
        'property' => ['type', 'name', 'description'],
        'property-read' => ['type', 'name', 'description'],
        'property-write' => ['type', 'name', 'description'],
        'see' => ['text'],
        'source' => ['text'],
        'subpackage' => ['text'],
        'uses' => ['text'],
        'var' => ['type', 'name', 'description']
      ]
    ],
    /**
     * @var array $pattern A list of patterns
     */
    $pattern = [
      'start' => '/\/\*\*/m',
      'end' => '/\s\*\//m',
      //'tag' => '/\n\s+\*\s{1}\@/m'
      'tag' => '/(\n\s+\*)*\n\s+\*\s{1}\@/m'
    ],
    /**
     * @var array $parsed
     */
    $parsed = [];

  /**
   * Sets the tags list relative to the selected mode
   *
   * @return array
   */
  private function setTags(){
    if ( $this->mode ){
      if ( $this->mode === 'vue' ){
        $tags = \bbn\X::mergeArrays($this->all_tags['js'], $this->all_tags['vue']);
      }
      else {
        $tags = $this->all_tags[$this->mode];
      }
      $this->tags = \bbn\X::mergeArrays($this->all_tags['common'], $tags);
      return $this->tags;
    }
  }


	/**
	 * Removes spaces and not allowed characters from the text
	 *
	 * @param string $text The text to clear
	 * @return string
	 */
	private function clearText(string $text){
    //return trim(str_replace('   ', ' ', Str_replace('  ', ' ', preg_replace('/\n\s+\*\s{0,1}/', PHP_EOL, $text))));
    $st = trim(preg_replace('/\n\s*\*{1}? /', PHP_EOL, $text));
    \bbn\X::log($st, 'clear_text');

    return $st;
	}

	/**
	 * Parses a tag
	 *
	 * @param string $text The tag text to parse
	 * @return array|false
	 */
	private function parseTag(string $text){
    $res = [];
		$text = $this->clearText($text);
    //$tag_end = strpos($text, ' ');
    preg_match('/^\@{1}\w+\s{0,1}/', $text, $tag);
    $tag_end = !empty($tag) && !empty($tag[0]) ? strlen($tag[0]) - 1 : false;
		if ( $tag_end !== false ){
			// Get tag
      $res['tag'] = substr($text, 1, $tag_end - 1);
			if ( in_array($res['tag'], array_keys($this->tags)) ){
        if (
          $this->tagHasText($res['tag']) &&
          ($text = substr($text, $tag_end + 1))
        ){
          $res['text'] = $this->clearText($text);
        }
				else {
					// Get type
					if (
            $this->tagHasType($res['tag']) &&
            ($type = $this->tagGetType($text)) &&
            !empty($type[1])
          ){
            $res['type'] = $type[1][0];
					}
					// Get default value
					if (
            $this->tagHasDefault($res['tag']) &&
            ($def = $this->tagGetDefault($text)) &&
            !empty($def[1])
          ){
						$res['default'] = $def[1][0];
          }
					// Get name
					if ( isset($def[1]) ){
						$n = $def[0][1] + strlen($def[0][0]) + 1;
					}
					else if ( isset($type[1]) ){
						$n = $type[0][1] + strlen($type[0][0]) + 1;
					}
					else {
						$n = $tag_end + 1;
					}
					if (
            $this->tagHasName($res['tag']) &&
						($name = $this->tagGetName(substr($text, $n)))
					){
						$res['name'] = $this->clearText($name[0][0]);
					}
					// Get description
					if ( isset($name[0]) ){
						$d = $n + $name[0][1] + strlen($name[0][0]) + 1;
					}
					else if ( isset($type[1]) ){
						$d = $type[0][1] + strlen($type[0][0]) + 1;
					}
          else {
						$d = $tag_end + 1;
					}
					if (
            $this->tagHasDesc($res['tag']) &&
            ($desc = substr($text, $d))
          ){
						$res['description'] = trim($desc);
					}
				}

				return $res;
			}
		}
		return false;
	}

  /**
   * Gets te tags list of a docblock
   *
   * @param string $block The docblock
   * @return array
   */
  private function getTags(string $block){
    preg_match_all($this->pattern['tag'], $block, $tags, PREG_OFFSET_CAPTURE);
    if ( !empty($tags[0]) ){
      return $tags[0];
    }
    return [];
  }

	/**
	 * Groups tags by name
	 *
	 * @param array $tags The tags list
	 * @return array
	 */
	private function groupTags(array $tags){
    $res = [];
    if ( !empty($tags) ){
      foreach ( $tags as $i => $tag ){
        // Skip the 'memberof' tag
        if ( $tag['tag'] === 'memberof' ){
          continue;
        }
        $t = $tag['tag'];
        unset($tag['tag']);
        $res[$t][] = $tag['text'] ?? $tag;
      }
    }
    return array_map(function($r){
      if ( is_array($r) && (count($r) === 1) ){
        //return $r[0];
      }
      return $r;
    }, $res);
  }

  /**
   * Cheks if a tag has 'type'
   *
   * @param string $tag The tag name
   * @return boolean
   */
  private function tagHasType(string $tag){
    return in_array('type', array_values(
      \is_array($this->tags[$tag]) ?
        $this->tags[$tag] :
        $this->tags[$this->tags[$tag]]
    ));
  }

  /**
   * Cheks if a tag has 'default'
   *
   * @param string $tag The tag name
   * @return boolean
   */
  private function tagHasDefault(string $tag){
    return in_array('default', array_values(
      \is_array($this->tags[$tag]) ?
        $this->tags[$tag] :
        $this->tags[$this->tags[$tag]]
    ));
  }

  /**
   * Cheks if a tag has 'name'
   *
   * @param string $tag The tag name
   * @return boolean
   */
  private function tagHasName(string $tag){
    return in_array('name', array_values(
      \is_array($this->tags[$tag]) ?
        $this->tags[$tag] :
        $this->tags[$this->tags[$tag]]
    ));
  }

  /**
   * Cheks if a tag has 'description'
   *
   * @param string $tag The tag name
   * @return boolean
   */
  private function tagHasDesc(string $tag){
    return in_array('description', array_values(
      \is_array($this->tags[$tag]) ?
        $this->tags[$tag] :
        $this->tags[$this->tags[$tag]]
    ));
  }

  /**
   * Cheks if a tag has 'text'
   *
   * @param string $tag The tag name
   * @return boolean
   */
  private function tagHasText(string $tag){
    return in_array('text', array_values(
      \is_array($this->tags[$tag]) ?
        $this->tags[$tag] :
        $this->tags[$this->tags[$tag]]
    ));
  }

  /**
   * Gets tag 'type'
   *
   * @param string $text The tag text
   * @return array
   */
  private function tagGetType(string $text){
    if (
      ($this->mode === 'js') ||
      ($this->mode === 'vue')
    ){
      preg_match('/(?:\{)(\S+)(?:\})/', $text, $type, PREG_OFFSET_CAPTURE);
    }
    else if ( $this->mode === 'php' ){
      preg_match('/(?:\@[a-z]+\s{1})(\S+)(?:\s{0,1})/', $text, $type, PREG_OFFSET_CAPTURE);
      if ( !empty($type) && isset($type[1]) ){
        $type[0] = $type[1];
      }
    }
    return $type;
  }

  /**
   * Gets tag 'default'
   *
   * @param string $text The tag text
   * @return array
   */
  private function tagGetDefault(string $text){
    if (
      ($this->mode === 'js') ||
      ($this->mode === 'vue')
    ){
      preg_match('/(?:\[)(.+)(?:\])/', $text, $def, PREG_OFFSET_CAPTURE);
    }
    return $def;
  }

  /**
   * Gets tag 'name'
   *
   * @param string $text The tag text
   * @return array
   */
  private function tagGetName(string $text){
    if (
      ($this->mode === 'js') ||
      ($this->mode === 'vue')
    ){
      //preg_match('/\w+/', $text, $name, PREG_OFFSET_CAPTURE);
      preg_match('/[[:graph:]]+/', $text, $name, PREG_OFFSET_CAPTURE);
    }
    else if ( $this->mode === 'php' ){
      preg_match('/\$[a-z]+/', $text, $name, PREG_OFFSET_CAPTURE);
    }
    return $name;
  }

  /**
   * Parses the parsed array to get an array of the given tag
   *
   * @param string $tag The tag name
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function get(string $tag, String $memberof = '', bool $grouped = true){
    if ( empty($this->parsed) ){
      $this->parse();
    }
    if ( !empty($this->parsed) ){
      $res = [];
      foreach ( $this->parsed as $p ){
        if (
          !empty($p['tags']) &&
          (($i = \bbn\X::find($p['tags'], ['tag' => $tag])) !== null) &&
          (
            (
              empty($memberof) &&
              (\bbn\X::find($p['tags'], ['tag' => 'memberof']) === null)
            ) ||
            (
              !empty($memberof) &&
              (($k = \bbn\X::find($p['tags'], ['tag' => 'memberof'])) !== null) &&
              ($p['tags'][$k]['name'] === $memberof)
            )
          )
        ){
          if ( $grouped ){
            $tmp = $p['tags'][$i];
            if ( $p['tags'][$i]['tag'] !== 'file' ){
              $tmp['description'] = $p['description'];
            }
            unset($p['tags'][$i], $tmp['tag']);
            $res[] = array_merge($tmp, $this->groupTags($p['tags']));
          }
          else {
            $res = array_map(function($t){
              unset($t['tag']);
              return $t;
            }, $p['tags']);
          }
        }
      }
      return $res;
    }
    return false;
  }

  /**
   * Gets an array of 'method' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function getMethods(string $memberof = ''){
    return $this->get('method', $memberof);
  }

  /**
   * Gets an array of 'event' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function getEvents(string $memberof = ''){
    return $this->get('event', $memberof);
  }

  /**
   * Gets an array of 'mixin' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function getMixins(string $memberof = ''){
    return $this->get('mixin', $memberof, false);
  }

  /**
   * Gets an array of 'prop' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function getProps(string $memberof = ''){
    return $this->get('prop', $memberof);
  }

  /**
   * Gets an array of 'data' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function getData(string $memberof = ''){
    return $this->get('data', $memberof);
  }

  /**
   * Gets an array of 'computed' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function getComputed(string $memberof = ''){
    return $this->get('computed', $memberof);
  }

  /**
   * Gets an array of 'watch' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function getWatch(string $memberof = ''){
    return $this->get('watch', $memberof);
  }

  /**
   * Gets an array of 'component' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function getComponents(string $memberof = ''){
    $res = [];
    if ( $components = $this->get('component', $memberof) ){
      foreach ( $components as $comp ){
        if ( !empty($comp['name']) ){
          $res[] = array_merge($comp, $this->getVue($comp['name']));
        }
      }
    }
    return $res;
  }

  /**
   * Gets an array of 'todo' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function getTodo(string $memberof = ''){
    return $this->get('todo', $memberof);
  }

  /**
   * Gets the 'file' tag
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function getFile(string $memberof = ''){
    return $this->get('file', $memberof);
  }

  /**
   * __construct
   *
   * @param string $src The source code or an absolute file path
   * @param string $mode The mode to use
   */
  public function __construct(string $src = '', String $mode = 'vue'){
    $this->setMode($mode);
    $this->setTags();
    $this->setSource($src);
  }

  /**
   * Sets the source to parse
   *
   * @param string $src The source code or an absolute file path
   * @return \bbn\Parsers\Doc
   */
  public function setSource(string $src){
    $this->source = is_file($src) ? file_get_contents($src) : $src;
    $this->parsed = [];
    return $this;
  }

  /**
   * Sets the mode
   *
   * @param string $mode The mode to set
   * @return \bbn\Parsers\Doc
   */
  public function setMode(string $mode){
    if ( !empty($mode) && in_array($mode, $this->modes) ){
      $this->mode = $mode;
      return $this;
    }
    die(_('Error: mode not allowed.'));
  }

  /**
   * Parses the current source
   *
   * @return array
   */
  public function parse(){
    preg_match_all($this->pattern['start'], $this->source, $matches, PREG_OFFSET_CAPTURE);
    if ( isset($matches[0]) ){
      foreach ( $matches[0] as $match ){
        preg_match($this->pattern['end'], $this->source, $mat, PREG_OFFSET_CAPTURE, $match[1]);
        $start = $match[1];
        $length = isset($mat[0]) ? ($mat[0][1] - $start) + 3 : 0;
        if ( $db = $this->parseDocblock(substr($this->source, $start, $length)) ){
          $this->parsed[] = $db;
        }
      }
    }
    return $this->parsed;
  }


  /**
   * Parses a given docblock
   *
   * @param string $block The docblock
   * @return array|null
   */
  public function parseDocblock(string $block): ?array
  {
    $b = [
			'description' => '',
			'tags' => []
		];
    // Remove start pattern
    //$block = trim(substr($block, 3));
		// Remove end pattern
		$block = trim(substr($block, 0, Strlen($block) - 2));
		// Tags
    $tags = $this->getTags($block);
    foreach ( $tags as $i => $tag ){
			if (
				(
					isset($tags[$i+1]) &&
					($t = $this->parseTag(substr($block, $tag[1], $tags[$i+1][1] - $tag[1])))
        ) ||
				($t = $this->parseTag(substr($block, $tag[1])))
			){
        if ( !empty($t['tag']) && ($t['tag'] === 'ignore') ){
          return null;
        }
				$b['tags'][] = $t;
			}
    }
    // Get Description
    $b['description'] = $this->clearText(isset($tags[0]) ? substr($block, 3, $tags[0][1]-1) : substr($block, 3));
    return $b;
  }

  /**
   * Gets JavaScript structure
   *
   * @return array
   */
  public function getJs(){
    return [
      'description' => $this->getFile(),
      'methods' => $this->getMethods(),
      'events' => $this->getEvents(),
      //'todo' => $this->getTodo()
    ];
  }

  /**
   * Gets Vue.js structure
   *
   * @param string $memberof The parent tag name
   * @return array
   */
  public function getVue(string $memberof = ''){
    return [
      'description' => $this->getFile($memberof),
      'methods' => $this->getMethods($memberof),
      'events' => $this->getEvents($memberof),
      'mixins' => $this->getMixins($memberof),
      'props' => $this->getProps($memberof),
      'data' => $this->getData($memberof),
      'computed' => $this->getComputed($memberof),
      'watch' => $this->getWatch($memberof),
      'components' => $this->getComponents($memberof),
      //'todo' => $this->getTodo($memberof)
    ];
  }
}
