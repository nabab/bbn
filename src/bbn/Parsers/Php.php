<?php

namespace bbn\Parsers;

use bbn;
use bbn\X;
use bbn\Str;
use bbn\File\System;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionException;
use phpDocumentor\Reflection\DocBlockFactory;

class Php extends bbn\Models\Cls\Basic
{
  
  
  /**
   * Construct function
   */
  public function __construct()
  {
    $this->docParser = DocBlockFactory::createInstance();
    $this->parser = new Doc('', 'php');
  }
  
  
  /**
   * Function to take all the information related to the method sought and if it also contains the method of its relative
   *
   * @param string $meth Name of the method to search for to take information
   * @param ReflectionClass $cls
   * @return array|null
   */
  public function analyzeMethod(string $meth, $cls): ?array
  {
    if (is_string($cls)) {
      $cls = new ReflectionClass($cls);
    }
    
    $arr = null;
    if (
      !empty($meth)
      && !empty($cls)
      && $cls->hasMethod($meth)
    ) {
      $f = &$this;
      
      //get method in current class
      $arr = $this->_get_method_info($cls->getMethod($meth), $cls);
      
      //get method in parent class
      $parent = $cls->getParentClass();
      
      while ($parent) {
        if ($parent->hasMethod($meth)) {
          $arr['parent'] = $this->_get_method_info($parent->getMethod($meth), $cls);
        }
        
        $parent = $parent->getParentClass();
      }
    }
    
    return $arr ?: null;
  }
  
  
  /**
   * Function to take all the information relating to the property sought and if it also contains that of his relative
   *
   * @param string $prop Name of the property to be searched
   * @param ReflectionClass $cls
   * @return array|null
   */
  public function analyzeProperty(string $prop, ReflectionClass $cls): ?array
  {
    if ($arr = $this->_get_property_info($prop, $cls)) {
      $parent = $cls->getParentClass();
      while ($parent) {
        if ($arr_parent = $this->_get_property_info($prop, $parent)) {
          $arr['parent'] = $arr_parent;
          break;
        }
        
        $parent = $parent->getParentClass();
      }
    }
    
    return $arr ?: null;
  }
  
  
  /**
   * Function that analyzes the constant passed to him and even if it contains the relative parent of the class of belonging
   *
   * @param string $const Name of constant to search for information
   * @param \ReflectionClass $cls
   * @return array|null
   */
  public function analyzeConstant(string $const, ReflectionClass $cls): ?array
  {
    if (
      !empty($const)
      && !empty($cls)
      && $cls->hasConstant($const)
    ) {
      $cst = $cls->getReflectionConstant($const);
      $arr = [
        'name' => $cst->name,
        'value' => $cls->getConstant($const),
        'class' => $cls->name,
        'parent' => false,
        'private' => $cst->isPrivate(),
        'protected' => $cst->isProtected(),
        'public' => $cst->isPublic(),
        'final' => $cst->isFinal(),
        'doc' => $this->parsePropertyComments($cst->getDocComment()),
      ];
      $parent = $cls->getParentClass();
      while ($parent) {
        if ($parent->hasConstant($const)) {
          $cst = $cls->getReflectionConstant($const);
          $arr['parent'] = [
            'name' => $cst->name,
            'doc' => $this->parsePropertyComments($cst->getDocComment()),
            'value' => $parent->getConstant($const),
            'protected' => $cst->isProtected(),
            'public' => $cst->isPublic(),
            'class' => $parent->name,
            'parent' => false
          ];
        }
        
        $parent = $parent->getParentClass();
      }
    }
    
    if (isset($arr)) {
      return $arr;
    }
    
    return null;
  }


  public function getDummyComments($code) {

    $res = [
    ];
    $tokens = token_get_all($code, TOKEN_PARSE);

    foreach ($tokens as $i => $token) {
      if (is_array($token)) {
        $t = token_name($token[0]);
        if ($t === 'T_CLASS') {
          break;
        }
        if ($t === 'T_DOC_COMMENT') {
          $res[] = $token[1];
        }
      }
    }
    return $res;
  }

  public function getUses($code) {
    $res = [
    ];
    $usable = ['T_WHITESPACE', 'T_NAME_QUALIFIED', 'T_AS', 'T_STRING'];
    $isUse = false;
    $tokens = token_get_all($code, TOKEN_PARSE);
  //   foreach ($tokens as $token) {
  //     if (is_array($token)) {
  //         echo htmlentities("Line {$token[2]}: ". token_name($token[0]). " ('{$token[1]}')". PHP_EOL).'<br>';
  //     }
  // }
  // die();
  
    foreach ($tokens as $i => $token) {
      if (is_array($token)) {
        $t = token_name($token[0]);
        if ($t === 'T_CLASS') {
          break;
        }
        if ($isUse) {
          if (in_array($t, $usable)) {
            switch ($t) {
              case 'T_NAME_QUALIFIED':
                $isUse['fqn'] = $token[1];
                break;
              case 'T_AS':
                $isUse['as'] = true;
                break;
              case 'T_STRING':
                if ($isUse['as']) {
                  $isUse['alias'] = $token[1];
                  //throw new Exception("No alias without as");
                }
                else {
                  $isUse['fqn'] = $token[1];
                }
                break;
            }
            if (!empty($isUse['fqn'])) {
              if (!isset($res[$isUse['fqn']])) {
                $res[$isUse['fqn']] = '';
              }
              elseif ($isUse['alias']) {
                $res[$isUse['fqn']] = $isUse['alias'];
              }
            }
          }
          else {
            $isUse = false;
          }
        }
        if ($t === 'T_USE') {
          $isUse = ['fqn' => '', 'alias' => '', 'as' => false];
        }
      }
    }
    foreach ($res as $fqn => $alias) {
      if (empty($alias)) {
        $res[$fqn] = basename(str_replace("\\", "/", $fqn));
      }
    }
    return $res;
  }
  
  
  /**
   * Function that analyzes the desired class by returning the information belonging to it
   *
   * @param string $cls
   * @param string $path
   * @return array|null
   */
  public function analyzeClass(string $cls, string $path = '', string $level = null): ?array
  {
    $rc = new ReflectionClass($cls);
    if (!empty($cls) && is_object($rc)) {
      $constructor = $rc->getConstructor();
      $filter = null;
      if ($level && defined('ReflectionMethod::IS_' . strtoupper($level))) {
        $filter = constant('ReflectionMethod::IS_' . strtoupper($level));
      }
      $fullname = $rc->getName();
      $name = basename(str_replace("\\", "/", $fullname));
      $namespace = str_replace("/", "\\", dirname(str_replace("\\", "/", $fullname)));
      $methods = $rc->getMethods($filter);
      $props = $rc->getProperties($filter);
      $statprops = $rc->getStaticProperties();
      $constants = $rc->getConstants();
      $parent = $rc->getParentClass();
      /*$tokens = token_get_all(file_get_contents($rc->getFileName()), TOKEN_PARSE);
      foreach ($tokens as $token) {
        if (is_array($token)) {
            echo htmlentities("Line {$token[2]}: ". token_name($token[0]). " ('{$token[1]}')". PHP_EOL).'<br>';
        }
      }
      die();
      X::ddump($rc->getDocComment());*/
      $res = [
        'doc' => $this->parseClassComments($rc->getDocComment()),
        'name' => $rc->getName(),
        'namespace' => $rc->getNamespaceName(),
        'realName' => $name,
        'realNamespace' => $namespace,
        'traits' => $rc->getTraitNames(),
        'interfaces' => $rc->getInterfaces(),
        //'isInstantiable' => $rc->isInstantiable(),
        //'cloneable' =>  $rc->isCloneable(),
        'fileName' => substr($rc->getFileName(), strlen($path)),
        'startLine' => $rc->getStartLine(),
        'endLine' => $rc->getEndLine(),
        'numMethods' => $methods ? count($methods) : 0,
        'numProperties' => $props ? count($props) : 0,
        'numConstants' => $constants ? count($constants) : 0,
        'numStaticProperties' => $statprops ? count($statprops) : 0,
        'interfaces' => $rc->getInterfaces(),
        'interfaceNames' => $rc->getInterfaceNames(),
        'isInterface' => $rc->isInterface(),
        'traitAliases' => $rc->getTraitAliases(),
        'isTrait' => $rc->isTrait(),
        'isAbstract' => $rc->isAbstract(),
        'isFinal' => $rc->isFinal(),
        'modifiers' => $rc->getModifiers(),
        'parentClass' => $parent ? $parent->name : null,
        'isSubclassOf' => $rc->isSubclassOf($cls),
        'defaultProperties' => $rc->getDefaultProperties(),
        'isIterable' => $rc->isIterable(),
        //'implementsInterface' => $rc->implementsInterface(),
        'extensionName' => $rc->getExtensionName(),
        'namespace' => $rc->inNamespace(),
        'namespaceName' => $rc->getNamespaceName(),
        'shortName' => $rc->getShortName(),
        'contentConstructor' => !empty($constructor) ? array_filter(
          $this->analyzeMethod($constructor->name, $rc),
          function ($m, $i) {
            return in_array($i, ['file', 'returns']);
          },
          ARRAY_FILTER_USE_BOTH
        ) : null,
        'methods' => $methods ? $this->orderElement($methods, 'methods', $rc) : null,
        'properties' => $props ? $this->orderElement($props, 'properties', $rc) : null,
        'staticProperties' => $statprops,
        'constants' => !empty($constants) ? $this->orderElement($constants, 'constants', $rc) : null
      ];
      $code = file_get_contents($res['fileName']);
      //X::ddump($code);
      try {
        $res['uses'] = $this->getUses($code);
        $res['dummyComments'] = $this->getDummyComments($code);
      }
      catch(Exception $e) {
        throw new Exception("Problem getting uses in $cls");
      }
      if (!empty($res['traits'])) {
      
      }
      if (
        $res['doc']
        && ($extracted = $this->_extract_description($res['doc']['description']))
      ) {
        $res = X::mergeArrays($res, $extracted);
      }
      
      return $res;
    }
  }
  
  
  public function getLibraryClasses(string $path, string $namespace = ''): ?array
  {
    if (!empty($namespace)) {
      if (substr($namespace, -1) !== '\\') {
        $namespace = '';
      }
    }
    if (!empty($path)) {
      $fs = new System();
      if ($fs->cd($path)) {
        $files = $fs->scan('.', '.php', false);
        $arr = [];
        if (is_array($files) && count($files)) {
          foreach ($files as $file) {
            $bits = X::split($file, '/');
            $name = X::basename(array_pop($bits), '.php');
            $class = $namespace . (empty($bits) ? '' : X::join($bits, '\\') . '\\') . $name;
            $res = [
              'name' => $name,
              'file' => $file,
              'class' => $class
            ];
            if (class_exists($class, true)) {
              $res['type'] = 'class';
            } elseif (interface_exists($class, true)) {
              $res['type'] = 'interface';
            } elseif (trait_exists($class, true)) {
              $res['type'] = 'trait';
            }
            if (!empty($res['type'])) {
              $arr[] = $res;
            }
          }
        }
      }
      
      return $arr;
    }
    
    return null;
    
  }
  
  /**
   * Function that analyzes the whole library with the same name space returning all the information of all the classes making part of it
   *
   * @param string $path of the library
   * @param string $namespace of the class
   * @return array|null
   */
  public function analyzeLibrary(string $path, string $namespace = ''): ?array
  {
    if ($files = $this->getLibraryClasses($path, $namespace)) {
      $arr = [];
      foreach ($files as $file) {
        try {
          $arr[$file['file']] = $this->analyzeCLass($file['class'], $path);
        } catch (Exception $e) {
          die(var_dump($file, $e));
          if (isset($arr[$file])) {
            unset($arr[$file]);
          }
        }
        
      }
      
      return $arr;
    }
    
    return null;
  }
  
  
  /**
   * Generally analyzes a docBLock returning the information in a structured way
   *
   * @param string $text
   * @return void
   */
  public function iparse(string $text)
  {
    if ($text) {
      $docblock = $this->docParser->create($text);
      $res = [
        'summary' => $docblock->getSummary(),
        'tags' => [],
        'description' => (string)$docblock->getDescription()
      ];
      $tags = $docblock->getTags();
      // Contains \phpDocumentor\Reflection\DocBlock\Description object
      $res['description_obj'] = $docblock->getDescription();
      foreach ($tags as $i => $t) {
        $desc = $t->getDescription() ?: false;
        $res['tags'][] = [
          'index' => $i,
          'type' => method_exists($t, 'getType') ? $t->getType() : null,
          'varname' => method_exists($t, 'getVariableName') ? $t->getVariableName() : null,
          'isVariadic' => method_exists($t, 'isVariadic') ? $t->isVariadic() : null,
          'name' => $t->getName(),
          'desc0' => (string)$desc,
          'desc1' => $desc ? $t->getDescription()->getTags() : '',
          'desc2' => $desc ? $t->getDescription()->render() : ''
        ];
      }
      
      return $res;
    }
    
    return false;
  }
  
  
  /**
   * Function that analyzes the class by returning the information in detail
   *
   * @param string $class_name
   * @return void
   */
  public function parse(string $class_name)
  {
    $rc = new ReflectionClass($class_name);
    //die(var_dump($rc->hasConstant('PARAM_BOOL')));
    $constants = $rc->getConstants();
    $parent = $rc->getParentClass();
    $parent_constants = [];
    if ($parent) {
      $parent_constants = $parent->getConstants();
    }
    
    $cparser =& $this;
    $full_name = $rc->getName();
    $name = basename(str_replace("\\", "/", $fullname));
    $namespace = str_replace("/", "\\", dirname(str_replace("\\", "/", $fullname)));
    $cls = [
      'doc' => [
        'title' => $this->iparse($rc->getDocComment()),
      ],
      'name' => $name,
      'constants' => array_map(
        function ($a) use ($constants, $parent_constants) {
          return [
            'name' => $a->name,
            'value' => $constants[$a->name]
          ];
        },
        array_filter(
          $rc->getReflectionConstants(),
          function ($a) use ($parent_constants, $constants) {
            return !array_key_exists($a->name, $parent_constants) || ($parent_constants[$a->name] !== $constants[$a->name]);
          }
        )
      ),
      'namespace' => $namespace,
      'traits' => $rc->getTraits(),
      'interfaces' => $rc->getInterfaces(),
      'parent' => $parent ? $parent->getName() : null,
      'properties' => array_map(
        function ($m) use ($cparser) {
          //$m->setAccessible(true);
          return [
            'name' => $m->getName(),
            //'value' => $m->getValue(),
            'static' => $m->isStatic(),
            'private' => $m->isPrivate(),
            'protected' => $m->isProtected(),
            'promoted' => $m->isPromoted(),
            'public' => $m->isPublic(),
            'doc' => $cparser->iparse($m->getDocComment())
          ];
        },
        $rc->getProperties()
      ),
      'methods' => array_map(
        function ($m) use ($cparser) {
          $ret = null;
          if ($m->hasReturnType()) {
            $type = $m->getReturnType();
            $ret = [(string)$type];
            if ($type->allowsNull()) {
              $ret[] = null;
            }
          }
          
          return [
            'name' => $m->getName(),
            'static' => $m->isStatic(),
            'private' => $m->isPrivate(),
            'protected' => $m->isProtected(),
            'public' => $m->isPublic(),
            'final' => $m->isFinal(),
            'code' => $this->_closureSource($m),
            'doc' => $cparser->iparse($m->getDocComment()),
            'returns' => $ret,
            'arguments' => array_map(
              function ($p) use ($m) {
                $types = [];
                $type = $p->getType();
                if (is_object($type)) {
                  if (method_exists($type, 'getTypes')) {
                    $types = $type->getTypes();
                  } else {
                    $types = [$type];
                  }
                }
                
                $type_st = '';
                foreach ($types as $i => $tp) {
                  $type_st .= $tp->getName() . ($i ? '|' : '');
                }
                return [
                  'name' => $p->getName(),
                  'position' => $p->getPosition(),
                  'type' => $type_st,
                  'required' => !$p->isOptional(),
                  'has_default' => $p->isDefaultValueAvailable(),
                  'default' => $p->isDefaultValueAvailable() ? $p->getDefaultValue() : '',
                  'default_name' => $p->isDefaultValueAvailable() && $p->isDefaultValueConstant() ? $p->getDefaultValueConstantName() : ''
                ];
              },
              $m->getParameters()
            )
          ];
        },
        $rc->getMethods()
      )
    ];
    
    /*
    try {
      $obj = $parser->parse($code);
      $arr = json_decode(json_encode($obj), true);
      foreach ( $arr[0]['stmts'] as $node ){
        if ( $node['nodeType'] === 'Stmt_Class' ){
          $res['class'] = $node['name']['name'];
          $res['elements'] = [];
          foreach ( $node['stmts'] as $stmts ){
            if ( isset($stmts['attributes'], $stmts['attributes']['comments']) ){
              foreach ( $stmts['attributes']['comments'] as $c ){
                $docblock = $doc_parser->create($c['text']);

                // Contains the summary for this DocBlock
                $res['summary'] = $docblock->getSummary();

                $tags = $docblock->getTags();
                // Contains \phpDocumentor\Reflection\DocBlock\Description object
                $res['description_obj'] = $docblock->getDescription();
                foreach ( $tags as $i => $t ){
                  X::hdump($i, (string)$t->getType(), $t->getName);
                  $desc = $t->getDescription()->render();
                  var_dump($desc);
                }
                echo '<pre>';
                var_dump($summary, $description, $tags);
                echo '</pre>';
              }
            }
          }
          X::hdump("HEY??", count($node['stmts']));
        }
      }
      X::hdump(count($arr[0]['stmts']));
      X::hdump($arr[0]['stmts']);
    }
    catch (PhpParser\Error $e) {
        echo 'Parse Error: ', $e->getMessage();
    }
    */
    return $cls;
  }
  
  
  /**
   * Function that analyzes the class by returning the non-detailed information
   *
   * @param string $class
   * @param boolean $type
   * @return array|null
   */
  public function analyze(string $class, $type = false): ?array
  {
    $ok = true;
    try {
      $ref = new ReflectionClass($class);
    } catch (Exception $e) {
      throw new Exception($e->getMessage());
    }
    
    if ($ok) {
      $fs = new System();
      $tmp = $ref->getFileName();
      $file = $tmp && $fs->isFile($tmp) ? $tmp : null;
      $arr = [
        'name' => $class,
        'file' => $file,
        'parents' => [],
        'isAnonymous' => $ref->isAnonymous(),
        'isCloneable' => $ref->isCloneable(),
        'isFinal' => $ref->isFinal(),
        'isInstantiable' => $ref->isInstantiable(),
        'isInternal' => $ref->isInternal(),
        'isIterateable' => $ref->isIterateable(),
        'isUserDefined' => $ref->isUserDefined(),
        'methods' => $this->addMethods($ref, $type, $file),
        'properties' => [],
        'traits' => [],
        'unused' => []
      ];
      
      $props = $ref->getProperties();
      if (!empty($props)) {
        foreach ($props as $prop) {
          $type_prop = false;
          if ($prop->isPublic()) {
            $type_prop = 'public';
          } elseif ($prop->isPrivate()) {
            $type_prop = 'private';
          } elseif ($prop->isProtected()) {
            $type_prop = 'protected';
          } elseif ($prop->isStatic()) {
            $type_prop = 'static';
          }
          
          if (!empty($type_prop)) {
            $arr['properties'][$type_prop][] = $prop->getName();
          }
        }
      }
      
      //for parents
      $parents = $ref->getParentClass();
      if (!empty($parents)) {
        foreach ($parents as $parent) {
          $arr['parents'][$parent] = $this->analyze($parent, 'parent');
          
          foreach ($arr['parents'][$parent]['methods'] as $i => $m) {
            if (count($m)) {
              $arr['methods'][$i] = array_merge($m, $arr['methods'][$i]);
            }
          }
        }
      }
      
      //for traits
      $traits = $ref->getTraitNames();
      if (!empty($traits)) {
        foreach ($traits as $trait) {
          $arr['traits'][$trait] = $this->analyze($trait, 'trait');
          
          foreach ($arr['traits'][$trait]['methods'] as $i => $m) {
            if (count($m)) {
              $arr['methods'][$i] = array_merge($arr['methods'][$i], $m);
            }
          }
        }
      }
      
      //for interfaces
      if ($interfaces = $ref->getInterfaceNames()) {
        foreach ($interfaces as $interface) {
          $arr['interfaces'][$interface] = $this->analyze($interface, 'interface');
          foreach (array_keys($arr['interfaces'][$interface]['methods']) as $i) {
            if (isset($arr['methods'][$i]['interfaces'])) {
              $arr['methods'][$i]['interfaces'][] = $interface;
            } else {
              $arr['methods'][$i]['interfaces'] = [$interface];
            }
          }
        }
      }
      
      if (!empty($arr['methods']['private'])) {
        foreach ($arr['methods']['private'] as $name => $priv) {
          $str = ($priv['static'] ? '::' : '->') . $name;
          if (X::indexOf($fs->getContents($arr['file']), $str) === -1) {
            $arr['unused'][] = $arr['name'] . '::' . $priv['name'];
          }
        }
      }
      
      return $arr;
    }
    
    return null;
  }
  
  
  /**
   * This function returns all the information of the methods cataloged by type
   *
   * @param object $class_object
   * @param boolean $origin
   * @param $file
   * @return void
   */
  public function addMethods($class_object, $origin = false, $file = null)
  {
    $methods = [
      'private' => [],
      'protected' => [],
      'public' => []
    ];
    
    foreach ($class_object->getMethods() as $m) {
      $idx = 'public';
      if ($m->isPrivate()) {
        $idx = 'private';
      } elseif ($m->isProtected()) {
        $idx = 'protected';
      }
      
      if ($m->getDeclaringClass()->getName() === $class_object->getName()) {
        $doc = is_null($file) ? false : $m->getDocComment();
        $ret = null;
        if ($m->hasReTurnType()) {
          $type = $m->getReturnType();
          $ret = [(string)$type];
          if ($type->allowsNull()) {
            $ret[] = null;
          }
        }
        
        $methods[$idx][$m->getName()] = [
          'static' => $m->isStatic(),// ? 'static' : 'non-static',
          'returns' => $ret,
          'doc' => is_null($file) ? false : $doc,
          'parsed' => is_null($file) ? false : $this->parser->parseDocblock($doc),
          'line' => is_null($file) ? false : $m->getStartLine(),
          'type' => $origin !== false ? $origin : 'origin',
          'file' => $m->getDeclaringClass()->getName()
        ];
      }
    }
    
    if ($origin === 'parent') {
      unset($methods['private']);
    }
    
    return $methods;
  }
  
  
  /**
   * This function analyzes the docblock of a method by returning the information in a structured way
   *
   * @param string $txt docBlock
   * @return array|null
   */
  protected function parseMethodComments(string $txt): ?array
  {
    if (!empty($txt)) {
      $arr = $this->parser->parseDocblock($txt);
      try {
        $docBlock = $this->docParser->create($txt);
      } catch (Exception $e) {
        $this->log($e->getMessage() . PHP_EOL . PHP_EOL . $txt);
        return null;
      }
      
      if (count($arr['tags'])) {
        $tags = $arr['tags'];
        unset($arr['tags']);
        $arr['params'] = [];
        $arr['return'] = '';
        foreach ($tags as $tag) {
          if ($tag['tag'] === 'param') {
            $arr['params'][] = $tag;
          } elseif ($tag['tag'] === 'return') {
            $arr['return'] = isset($tag['description']) ? $tag['description'] : '';
          } else {
            $arr[$tag['tag']] = $tag;
          }
        }
      }
      
      if ($arr['description']) {
        $start_example = stripos($arr['description'], "* ```php");
        $end_example = strpos($arr['description'], "```");
        if (($start_example !== false) && ($end_example !== false)) {
          $arr['example_method'] = (string)$docBlock->getDescription(); //substr($arr['description'], $start_example+1,);
          $arr['description'] = $this->parser->parseDocblock($txt);//$docBlock->getSummary();
        }
      }
    }
    
    return (isset($arr) && is_array($arr)) ? $arr : null;
  }
  
  
  /**
   * This function analyzes the docblock of a property by returning the information in a structured way
   *
   * @param string $txt docblock
   * @return array|null
   */
  protected function parsePropertyComments(string $txt): ?array
  {
    if (is_string($txt)) {
      $arr = $this->parser->parseDocblock($txt);
    }
    
    return (isset($arr) && is_array($arr)) ? $arr : null;
  }
  
  
  /**
   *This function analyzes the docblock of a class by returning the information in a structured way
   *
   * @param string $txt dockBlock
   * @return array|null
   */
  protected function parseClassComments(string $txt): ?array
  {
    if (is_string($txt)) {
      $arr = $this->parser->parseDocblock($txt);
      if (is_array($arr)) {
        if (count($arr['tags'])) {
          $tags = $arr['tags'];
          $arr['tags'] = [];
          foreach ($tags as $tag) {
            $arr['tags'][$tag['tag']] = $tag['text'];
          }
        }
      }
    }
    
    return (isset($arr) && is_array($arr)) ? $arr : null;
  }
  
  
  /**
   * Function that returns the content of an element of a class
   *
   * @param ReflectionMethod $rfx
   * @return void
   */
  private function _closureSource(ReflectionMethod $rfx)
  {
    $args = [];
    $default = '88888888888888888888888888888888';
    $i = 0;
    foreach ($rfx->getParameters() as $p) {
      $arg = '';
      if ($type = $p->getType()) {
        if (method_exists($type, 'getName')) {
          $arg .= $type->getName() . ' ';
        } elseif (method_exists($type, 'getTypes') && ($types = $type->getTypes())) {
          $tmp = [];
          foreach ($types as $t) {
            $tmp[] = $t->getName();
          }
          
          $arg .= X::join($tmp, '|') . ' ';
        }
      }
      
      $args[] = $arg . ($p->isPassedByReference() ? '&' : '') . '$' . $p->name;
      if ($p->isOptional()) {
        try {
          $default = $p->getDefaultValue();
          if ($default !== '88888888888888888888888888888888') {
            $args[$i] .= ' = ' . ($default === [] ? '[]' : var_export($default, true));
          }
        } catch (ReflectionException $e) {
          // No default
          X::log([$rfx->getName(), $e->getMessage()], 'phpParser');
        }
      }
      
      $i++;
    }
    
    if ($filename = $rfx->getFileName()) {
      $content = file($filename);
      $s = $rfx->getStartLine();
      while (strpos(trim($content[$s - 1]), '{') === false) {
        $s++;
      }
      
      return 'function(' . implode(', ', $args) . ')' . PHP_EOL . '  {' . PHP_EOL
        . implode('', array_slice($content, $s, $rfx->getEndLine() - $s - 1)) . '  }';
    }
    
    return '';
  }
  
  
  /**
   * Order the elements (methods, porperties and costant of the class) used and the functions analyze
   *
   * @param array $elements
   * @param string $typeEle
   * @param ReflectionClass $rc
   * @return array|null
   */
  private function orderElement(array $elements, string $typeEle, ReflectionClass $rc): ?array
  {
    if (is_array($elements) && is_string($typeEle)) {
      $arr = [];
      foreach ($elements as $ele) {
        if ($typeEle === 'methods') {
          $ret = null;
          if ($ele->hasReTurnType()) {
            $type = $ele->getReturnType();
            //  $ret  = [$type->getName()];
            if ($type->allowsNull()) {
              $ret[] = null;
            }
          }
          
          /*$arr[$idx][$ele->getName()] = [
            'static' => $ele->isStatic(),
            'returns' => $ret
          ];*/
          $arr[$ele->getName()] = $this->analyzeMethod($ele->getName(), $rc);
        } elseif ($typeEle === 'properties') {
          $arr[$ele->name] = array_filter(
            $this->analyzeProperty($ele->name, $rc),
            function ($p, $i) {
              return $i !== 'name';
            },
            ARRAY_FILTER_USE_BOTH
          );
        } elseif ($typeEle === 'constants') {
          foreach (array_keys($elements) as $constant) {
            $arr[$constant] = $this->analyzeConstant($constant, $rc);
          }
        }
      }
    }
    
    return isset($arr) ? $arr : null;
  }
  
  
  /**
   * Return an array of information about a method.
   *
   * @param ReflectionMethod $method The method object
   * @return array
   */
  private function _get_method_info(ReflectionMethod $method, ReflectionClass $cls)
  {
    $ret = [];
    $refCls = $method->getDeclaringClass();
    if ($method->hasReturnType()) {
      $type = $method->getReturnType();
      if ($type->allowsNull()) {
        $ret[] = null;
      }
      
      if (method_exists($type, 'getTypes')) {
        foreach ($type->getTypes() as $stype) {
          if (method_exists($type, 'getName')) {
            if (!in_array($stype->getName(), $ret)) {
              $ret[] = $stype->getName();
            }
          }
        }
      } else {
        $ret[] = $type->getName();
      }
    }
    
    if ($method->isPrivate() || $method->isProtected()) {
      $method->setAccessible(true);
    }
    
    $ar = [
      'name' => $method->getName(),
      'summary' => '',
      'description' => '',
      'description_parts' => [],
      'class' => $refCls->getName(),
      'filename' => $method->getFileName(),
      'static' => $method->isStatic(),
      'visibility' => $method->isPrivate() ? 'private' : ($method->isProtected() ? 'protected' : 'public'),
      'final' => $method->isFinal(),
      'code' => $this->_closureSource($method),
      'parent' => false,
      'trait' => false,
      'startLine' => $method->getStartLine(),
      'endLine' => $method->getEndLine(),
      // 'isClosure' => $method->isClousure(),
      'isDeprecated' => $method->isDeprecated(),
      'isGenerator' => $method->isGenerator(),
      'isInternal' => $method->isInternal(),
      'isUserDefined' => $method->isUserDefined(),
      'isVariadic' => $method->isVariadic(),
      'returnsReference' => $method->returnsReference(),
      'numberOfParameters' => $method->getNumberOfParameters(),
      'numberOfRequiredParameters' => $method->getNumberOfRequiredParameters(),
      'shortName' => $method->getShortName(),
      'returns' => $ret,
      'arguments' => array_map(
        function ($p) {
          $types = [];
          $type = $p->getType();
          if (is_object($type)) {
            if (method_exists($type, 'getTypes')) {
              $types = $type->getTypes();
            } else {
              $types = [$type];
            }
          }
          
          $type_st = '';
          foreach ($types as $i => $tp) {
            $type_st .= $tp->getName() . ($i ? '|' : '');
          }
          
          return [
            'name' => $p->getName(),
            'position' => $p->getPosition(),
            'type' => $type_st,
            'required' => !$p->isOptional(),
            'has_default' => $p->isDefaultValueAvailable(),
            'default' => $p->isDefaultValueAvailable() ? $p->getDefaultValue() : '',
            'default_name' => $p->isDefaultValueAvailable() && $p->isDefaultValueConstant() ? $p->getDefaultValueConstantName() : ''
          ];
        },
        $method->getParameters()
      )
    ];

    if ($ar['name'] === '__construct') {
      foreach ($ar['arguments'] as $index => $arg) {
        $prop = $this->_get_property_info($arg['name'], $cls);
        //$props = $props ? $this->orderElement($props, 'properties', $rc) : null;
        if ($prop && $prop['promoted']) {   
          $ar['arguments'][$index]['promoted'] = $prop['visibility'];
        }
      }
    }
    
    if ($ar['filename'] !== $refCls->getFileName()) {
      if ($traits = $refCls->getTraits()) {
        foreach ($traits as $trait) {
          if ($trait->getFileName() === $ar['filename']) {
            $ar['trait'] = $trait->getName();
          }
        }
      }
      if (!$ar['trait']) {
        $cls = $refCls;
        while ($cls = $cls->getParentClass()) {
          if ($cls->getFileName() === $ar['filename']) {
            $ar['parent'] = $cls->getName();
            break;
          }
        }
      }
    }
    
    $comments = $method->getDocComment();
    if (
      ($doc = $this->parseMethodComments($comments))
      && ($extracted = $this->_extract_description(is_array($doc['description']) ? $doc['description']['description'] : $doc['description']))
    ) {
      $ar = X::mergeArrays($ar, $extracted);
      $ar['doc'] = $doc;
      $ar['comments'] = "  " . $comments;
    }
    
    if ($doc && !empty($doc['params'])) {
      foreach ($doc['params'] as $i => $a) {
        if (!empty($a['description']) && isset($ar['arguments'][$i])) {
          $ar['arguments'][$i]['description'] = $a['description'];
        }
      }
      
      unset($a);
    }
    
    return $ar;
  }
  
  
  /**
   * Makes an array of information out of a description string.
   *
   * @param string $desc The description string
   */
  private function _extract_description(string $desc): array
  {
    $ar = [];
    $bits = X::split($desc, PHP_EOL);
    if (!empty($bits)) {
      $ar['summary'] = trim(array_shift($bits));
      $ar['description'] = '';
      $ar['description_parts'] = [];
      if (!empty($bits)) {
        $description = trim(X::join($bits, PHP_EOL));
        $num_matches = preg_match_all('/```(?:php)?(.+)```/s', $description, $matches, PREG_OFFSET_CAPTURE);
        $len = strlen($description);
        $start = 0;
        
        if ($num_matches) {
          foreach ($matches[0] as $i => $m) {
            if (isset($m[1])) {
              if (
                ($i === 0)
                && ($m[1] !== 0)
                && $tmp = trim(substr($description, $start, $m[1]))
              ) {
                //$content = trim(Str::markdown2html($tmp));
                $content = $tmp;
                if ($content) {
                  $ar['description_parts'][] = [
                    'type' => 'text',
                    'content' => $content
                  ];
                }
              }
              
              $ar['description_parts'][] = [
                'type' => 'code',
                'content' => trim($matches[1][$i][0])
              ];
              $start = $m[1] + strlen($m[0]);
              $end = isset($matches[0][$i + 1]) ? $matches[0][$i + 1][1] : $len;
              if (
                ($start < $len)
                && ($tmp = trim(substr($description, $start, $end - $start)))
              ) {
                $ar['description_parts'][] = [
                  'type' => 'text',
                  'content' => $tmp
                ];
              }
            }
          }
        } else {
          //$content = trim(Str::markdown2html($description));
          $content = $description;
          if ($content) {
            $ar['description_parts'][] = [
              'type' => 'text',
              'content' => $content
            ];
          }
        }
        foreach ($ar['description_parts'] as $p) {
          if ($p['type'] === 'text') {
            $ar['description'] = $p['content'];
            break;
          }
        }
      }
    }
    
    return $ar;
  }


  /**
   * Finds the trait that declares $className::$propertyName
   */
  public function getDeclaringTraitForProperty($className, $propertyName) {
    $reflectionClass = new ReflectionClass($className);
    
    // Let's scan all traits
    $trait = $this->deepScanTraitsForProperty($reflectionClass->getTraits(), $propertyName);
    if ($trait != null) {
        return $trait;
    }
    // The property is not part of the traits, let's find in which parent it is part of.
    if ($reflectionClass->getParentClass()) {
        $declaringClass = $this->getDeclaringTraitForProperty($reflectionClass->getParentClass()->getName(), $propertyName);
        if ($declaringClass != null) {
            return $declaringClass;
        }
    }
    if ($reflectionClass->hasProperty($propertyName)) {
        return $reflectionClass;
    }
    
    return null;
  }

  /**
  * Recursive method called to detect a method into a nested array of traits.
  * 
  * @param $traits ReflectionClass[]
  * @param $propertyName string
  * @return ReflectionClass|null
  */
  public function deepScanTraitsForProperty(array $traits, $propertyName) {
    foreach ($traits as $trait) {
        // If the trait has a property, it's a win!
        $result = $this->deepScanTraitsForProperty($trait->getTraits(), $propertyName);
        if ($result != null) {
            return $result;
        } else {
            if ($trait->hasProperty($propertyName)) {
                return $trait;
            }
        }
    }
    return null;
  }
  
  
  private function _get_property_info(string $prop, ReflectionClass $cls): ?array
  {
    $arr = null;
    if (
      !empty($prop)
      && !empty($cls)
      && $cls->hasProperty($prop)
    ) {
      $property = $cls->getProperty($prop);
      $defaults = $cls->getDefaultProperties();
      $arr = [
        'name' => $property->getName(),
        'trait' => false,
        'static' => $property->isStatic(),
        'declaring' => $property->getDeclaringClass(),
        'declaring_trait' => $this->getDeclaringTraitForProperty($cls->getName(), $property->getName()),
        'promoted' => $property->isPromoted(),
        'visibility' => $property->isPrivate() ? 'private' : ($property->isProtected() ? 'protected' : 'public'),
        'doc' => empty($property->getDocComment()) ? '' : $this->parsePropertyComments($property->getDocComment()),
        'parent' => false,
        'value' => $defaults[$prop] ?? null
      ];
    }
    
    return $arr ?: null;
  }
}
