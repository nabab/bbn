<?php

namespace bbn\parsers;

/**
 * Documentation block parser
 *
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @copyright BBN Solutions
 * @category Parsers
 * @version 1.0
 */
class doc {
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
     * @var array $all_tags
     */
    $all_tags = [
      'common' => [
        'author' => ['text'],
        'copyright' => ['text'],
        'deprecated' => ['text'],
        'example' => ['text'],
        'global' => ['type', 'name', 'description'],
        'ignore' => [],
        'license' => ['text'],
        'link' => ['text'],
        'package' => ['text'],
        'return' => ['type', 'description'],
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
      'tag' => '/\n\s+\*\s{1}\@/m'
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
  private function set_tags(){
    if ( $this->mode ){
      if ( $this->mode === 'vue' ){
        $tags = \bbn\x::merge_arrays($this->all_tags['js'], $this->all_tags['vue']);
      }
      else {
        $tags = $this->all_tags[$this->mode];
      }
      $this->tags = \bbn\x::merge_arrays($this->all_tags['common'], $tags);
      return $this->tags;
    }
  }


	/**
	 * Removes spaces and not allowed characters from the text
	 *
	 * @param string $text The text to clear
	 * @return string
	 */
	private function clear_text(string $text){
    return trim(str_replace('   ', ' ', str_replace('  ', ' ', preg_replace('/\n\s*\*\s{1}/', PHP_EOL, $text))));
	}

	/**
	 * Parses a tag
	 *
	 * @param string $text The tag text to parse
	 * @return array|false
	 */
	private function parse_tag(string $text){
		$res = [];
		$text = $this->clear_text($text);
		$tag_end = strpos($text, ' ');
		if ( $tag_end !== false ){
			// Get tag
			$res['tag'] = substr($text, 1, $tag_end - 1);
			if ( in_array($res['tag'], array_keys($this->tags)) ){
        if (
          $this->tag_has_text($res['tag']) &&
          ($text = substr($text, $tag_end + 1))
        ){
          $res['text'] = $this->clear_text($text);
        }
				else {
					// Get type
					if (
            $this->tag_has_type($res['tag']) &&
            ($type = $this->tag_get_type($text)) &&
            !empty($type[1])
          ){
						$res['type'] = $type[1][0];
					}
					// Get default value
					if (
            $this->tag_has_default($res['tag']) &&
            ($def = $this->tag_get_default($text)) &&
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
            $this->tag_has_name($res['tag']) &&
						($name = $this->tag_get_name(substr($text, $n)))
					){
						$res['name'] = $this->clear_text($name[0][0]);
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
            $this->tag_has_desc($res['tag']) &&
            ($desc = substr($text, $d))
          ){
						$res['description'] = $this->clear_text($desc);
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
  private function get_tags(string $block){
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
	private function group_tags(array $tags){
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
        return $r[0];
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
  private function tag_has_type(string $tag){
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
  private function tag_has_default(string $tag){
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
  private function tag_has_name(string $tag){
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
  private function tag_has_desc(string $tag){
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
  private function tag_has_text(string $tag){
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
  private function tag_get_type(string $text){
    if (
      ($this->mode === 'js') ||
      ($this->mode === 'vue')
    ){
      preg_match('/(?:\{)(\S+)(?:\})/', $text, $type, PREG_OFFSET_CAPTURE);
    }
    else if ( $this->mode === 'php' ){      
      preg_match('/(?:\@[a-z]+\s{1})(.+)(?:\s{1}\$)/', $text, $type, PREG_OFFSET_CAPTURE);
      $type[0] = $type[1];
    }
    return $type;
  }

  /**
   * Gets tag 'default'
   *
   * @param string $text The tag text
   * @return array
   */
  private function tag_get_default(string $text){
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
  private function tag_get_name(string $text){
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
  private function get(string $tag, string $memberof = '', bool $grouped = true){
    if ( empty($this->parsed) ){
      $this->parse();
    }
    if ( !empty($this->parsed) ){
      $res = [];
      foreach ( $this->parsed as $p ){
        if (
          !empty($p['tags']) &&
          (($i = \bbn\x::find($p['tags'], ['tag' => $tag])) !== false) &&
          (
            (
              empty($memberof) &&
              (\bbn\x::find($p['tags'], ['tag' => 'memberof']) === false)
            ) ||
            (
              !empty($memberof) &&
              (($k = \bbn\x::find($p['tags'], ['tag' => 'memberof'])) !== false) &&
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
            $res[] = array_merge($tmp, $this->group_tags($p['tags']));
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
  private function get_methods(string $memberof = ''){
    return $this->get('method', $memberof);
  }

  /**
   * Gets an array of 'event' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function get_events(string $memberof = ''){
    return $this->get('event', $memberof);
  }

  /**
   * Gets an array of 'mixin' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function get_mixins(string $memberof = ''){
    return $this->get('mixin', $memberof, false);
  }

  /**
   * Gets an array of 'prop' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function get_props(string $memberof = ''){
    return $this->get('prop', $memberof);
  }

  /**
   * Gets an array of 'data' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function get_data(string $memberof = ''){
    return $this->get('data', $memberof);
  }

  /**
   * Gets an array of 'computed' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function get_computed(string $memberof = ''){
    return $this->get('computed', $memberof);
  }

  /**
   * Gets an array of 'watch' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function get_watch(string $memberof = ''){
    return $this->get('watch', $memberof);
  }

  /**
   * Gets an array of 'component' tags
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function get_components(string $memberof = ''){
    $res = [];
    if ( $components = $this->get('component', $memberof) ){
      foreach ( $components as $comp ){
        if ( !empty($comp['name']) ){
          $res[] = array_merge($comp, $this->get_vue($comp['name']));
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
  private function get_todo(string $memberof = ''){
    return $this->get('todo', $memberof);
  }

  /**
   * Gets the 'file' tag
   *
   * @param string $memberof The parent tag name
   * @return array|false
   */
  private function get_file(string $memberof = ''){
    return $this->get('file', $memberof);
  }

  /**
   * __construct
   *
   * @param string $src The source code or an absolute file path
   * @param string $mode The mode to use
   */
  public function __construct(string $src = '', string $mode = 'vue'){
    $this->set_mode($mode);
    $this->set_tags();
    $this->set_source($src);
  }

  /**
   * Sets the source to parse
   *
   * @param string $src The source code or an absolute file path
   * @return \bbn\parsers\doc
   */
  public function set_source(string $src){
    $this->source = is_file($src) ? file_get_contents($src) : $src;
    $this->parsed = [];
    return $this;
  }

  /**
   * Sets the mode
   *
   * @param string $mode The mode to set
   * @return \bbn\parsers\doc
   */
  public function set_mode(string $mode){
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
        $this->parsed[] = $this->parse_docblock(substr($this->source, $start, $length));
      }
    }
    return $this->parsed;
  }


  /**
   * Parses a given docblock
   *
   * @param string $block The docblock
   * @return array
   */
  public function parse_docblock(string $block){
    $b = [
			'description' => '',
			'tags' => []
		];
    // Remove start pattern
    //$block = trim(substr($block, 3));
		// Remove end pattern
		$block = trim(substr($block, 0, strlen($block) - 2));
		// Tags
    $tags = $this->get_tags($block);
    foreach ( $tags as $i => $tag ){
			if (
				(
					isset($tags[$i+1]) &&
					($t = $this->parse_tag(substr($block, $tag[1], $tags[$i+1][1] - $tag[1])))
        ) ||
				($t = $this->parse_tag(substr($block, $tag[1])))
			){
				$b['tags'][] = $t;
			}
    }
    // Get Description
    $b['description'] = $this->clear_text(isset($tags[0]) ? substr($block, 3, $tags[0][1]-1) : substr($block, 3));
    return $b;
  }

  /**
   * Gets JavaScript structure
   *
   * @return array
   */
  public function get_js(){
    return [
      'description' => $this->get_file(),
      'methods' => $this->get_methods(),
      'events' => $this->get_events(),
      //'todo' => $this->get_todo()
    ];
  }

  /**
   * Gets Vue.js structure
   *
   * @param string $memberof The parent tag name
   * @return array
   */
  public function get_vue(string $memberof = ''){
    return [
      'description' => $this->get_file($memberof),
      'methods' => $this->get_methods($memberof),
      'events' => $this->get_events($memberof),
      'mixins' => $this->get_mixins($memberof),
      'props' => $this->get_props($memberof),
      'data' => $this->get_data($memberof),
      'computed' => $this->get_computed($memberof),
      'watch' => $this->get_watch($memberof),
      'components' => $this->get_components($memberof),
      //'todo' => $this->get_todo($memberof)
    ];
  }
}
