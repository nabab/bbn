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
class Docblock {

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
      'cp',
      'php'
    ];
  /**
   * @var array $all_tags
   */
  protected static $all_tags = [
    'common' => [
      'author' => ['text'],
      'copyright' => ['text'],
      'deprecated' => ['text'],
      'example' => ['text'],
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
      'param' => ['type', 'name', 'description'],
      'private' => [],
      'prop' => 'property',
      'property' => ['type', 'name', 'description'],
      'protected' => [],
      'public' => [],
      'readonly' => [],
      'requires' => [],
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
    'cp' => [
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
  ];
  protected static $multiple = ['author', 'param', 'example', 'emits', 'fires'];
  /**
   * @var array $tags The current set of tags according to the mode
   */
  protected $tags;
  /**
   * @var array $parsed
   */
  protected $parsed = [];

  public function __construct($mode) {
    if (isset(self::$all_tags[$mode])) {
      $this->tags = array_merge(self::$all_tags['common'], self::$all_tags[$mode]);
    }
  }

  public function getAllTags(): ?array
  {
    return $this->tags;
  }

  public function isMultiple(string $tag): bool
  {
    return in_array($tag, self::$multiple);
  }

  public function parse(string $source) {
    // Each bit of JSDOC comment
    preg_match_all('/\/\*\*(.+)\*\//Us', $source, $matches);
    /** @var array Preliminar result */
    $res = [];
    // The matches without the comments
    if (!empty($matches[1])) {
      foreach ($matches[1] as $it) {
        /** @var array Representation of each comment block found in the source  */
        $tmp = [
          'summary' => '',
          'description' => ''
        ];
        /** @var string The trimmed whole comment block */
        $it = trim($it);
        /** @var array Each line from the block */
        $lines = \bbn\X::split($it, PHP_EOL);
        /** @var bool The first part of the comment block we get should be the summary and optionally the description */
        $is_desc = true;
        /** @var int The number of tags that have been parsed in the comment block */
        $num_tags = 0;
        /** @var string The last tag parsed in the comment block */
        $tag = null;
        if (isset($current)) {
          unset($current);
        }
        /** @var string|array The current tag */
        $current = false;
        // Each line
        foreach ($lines as $line) {
          /** @var string The line, trimmed until the first asterisk */
          $ln = ltrim($line);
          /** @var bool If the line is a summary, a description, or the continuation of another tag it will remain false */
          $is_tag = false;
          // Empty line case
          if ($ln === '*') {
            $ln = '';
          }
          // Removing the asterisk and the first space
          elseif (substr($ln, 0, 2) === '* ') {
            $ln = substr($ln, 2);
          }
          /** @var string The completely trimmed line (but we will need the spaces for markdown) */
          $trimmed = trim($ln);
          // If trimmed is empty this is an empty line to treat as is
          if (strlen($trimmed)) {
            // Case where the line is a tag
            if (substr($ln, 0, 1) === '@') {
              /** @var array The line split in bits by space */
              $bits = preg_split('/\s+/', $trimmed);
              // The first part is the tag
              $tag = substr(array_shift($bits), 1);
              // The first tag MUST be the type (i.e. file, method, var...)
              if (!$num_tags) {
                $tmp['type'] = $tag;
                if (($tag === 'file') && $is_desc) {
                  $tmp['summary'] .= \bbn\X::join($bits, ' ');
                }
                else {
                  $tmp['name'] = count($bits) ? array_shift($bits) : '';
                  if (!empty($tmp['summary'])) {
                    $tmp['summary'] = trim($tmp['summary']);
                  }
                  elseif (!empty($tmp['description'])) {
                    $tmp['description'] = trim($tmp['description']);
                  }
                }
              }
              else {
                if (!isset($tmp[$tag])) {
                  $tmp[$tag] = [];
                }
                if (count($bits)) {
                  if (isset($this->tags[$tag])) {
                    $tmp2 = [];
                    if (is_array($this->tags[$tag])) {
                      $num_items = count($this->tags[$tag]);
                      foreach ($this->tags[$tag] as $i => $item) {
                        if ($i === $num_items - 1) {
                          $tmp2[$item] = \bbn\X::join($bits, ' ');
                        }
                        elseif ($num_items > $i) {
                          $tmp2[$item] = array_shift($bits);
                        }
                      }
                    }
                    else{
                      $num_items = 1;
                    }
                    if (isset($current)) {
                      unset($current);
                    }
                    if ($this->isMultiple($tag)) {
                      $tmp[$tag][] = $tmp2;
                      $current =& $tmp[$tag][count($tmp[$tag]) - 1];
                    }
                    else {
                      $tmp[$tag] = $tmp2;
                      $current =& $tmp[$tag];
                    }
                    $keys = array_keys($current);
                    $last_key = end($keys);
                  }
                }
                elseif (isset($this->tags[$tag]) && (count($this->tags[$tag]) === 1)) {
                  $last_key = $this->tags[$tag][0];
                  if ($this->isMultiple($tag)) {
                    $tmp[$tag][] = [$last_key => ''];
                    $current =& $tmp[$tag][count($tmp[$tag]) - 1];
                  }
                  else {
                    $tmp[$tag] = [$last_key => ''];
                    $current =& $tmp[$tag];
                  }
                }
              }
              // After having found the first tag summary and description are not anymore expected
              $is_desc = false;
              $is_tag = true;
              $num_tags++;
            }
            elseif ($is_desc) {
              if (empty($tmp['summary'])) {
                $tmp['summary'] .= $trimmed;
              }
              else {
                $tmp['description'] .= empty($tmp['description']) ? $ln : PHP_EOL.$ln;
              }
            }
            elseif ($current) {
              $current[$last_key] .= PHP_EOL.$ln;
            }
          }
          elseif ($is_desc) {
            if (!empty($tmp['description'])) {
              $tmp['description'] .= PHP_EOL;
            }
          }
          elseif ($current) {
            $current[$last_key] .= PHP_EOL;
          }
        }
        $res[] = $tmp;
      }
    }
    if (isset($current)) {
      unset($current);
    }
    //die(var_dump($res));
    $result = array_shift($res);
    $result['methods'] = [];
    foreach ($res as $r) {
      if (!empty($r['type']) && ($r['type'] === 'method')) {
        $result['methods'][$r['name']] = $r;
      }
    }
    //die(\bbn\X::dump($result));
    return $result;
  }

  public function getJs($st)
  {
    if ($bits = $this->parse($st)) {
      $res = [];
      foreach ($bits as $bit) {

      }
      return $res;
    }

  }

  public function rebuild(array $parsed)
  {
    $st = '';

  }
}
