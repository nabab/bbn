<?php
namespace bbn\parsers;

use bbn;

class php extends bbn\models\cls\basic
{
  
  private function _closureSource(\ReflectionMethod $rfx)
  {
    $args = [];
    $default = '88888888888888888888888888888888';
    $i = 0;
    foreach($rfx->getParameters() as $p){
      $args[] = ($p->isArray() ? 'array ' : ($p->getClass() ? $p->getClass()->name.' ' : ''))
        .($p->isPassedByReference() ? '&' : '').'$'.$p->name;
      try {
        if ( $p->isOptional() ){
          $default = $p->getDefaultValue();
          if ( $default !== '88888888888888888888888888888888' ){
            $args[$i] .= ' = '.($default === [] ? '[]' : var_export($default,true));
          }
        }
      }
      catch ( \ReflectionException $e ){
        //die(var_dump($e));
      }
      $i++;
    }
    $content = file($rfx->getFileName());
    $s = $rfx->getStartLine();
    if ( strpos($content[$s-1], '  {') === false ){
      $s++;
    }
    return 'function(' . implode(', ', $args) .')'.PHP_EOL.'  {'.PHP_EOL
      . implode('', array_slice($content, $s, $rfx->getEndLine()-$s-1)).'  }';
  }

  public function __construct()
  {
    $this->docParser = \phpDocumentor\Reflection\DocBlockFactory::createInstance();
    $this->parser = new \bbn\parsers\doc('', 'php');
  }
  
  public function iparse($text)
  {
    if ( $text ){
      $docblock = $this->docParser->create($text);
      $res = [
        'summary' => $docblock->getSummary(),
        'tags' => [],
        'description' => (string)$docblock->getDescription()
      ];
      $tags = $docblock->getTags();
      // Contains \phpDocumentor\Reflection\DocBlock\Description object
      $res['description_obj'] = $docblock->getDescription();
      foreach ( $tags as $i => $t ){
        $desc = $t->getDescription() ?: false;
        $res['tags'][] = [
          'index' => $i,
          'type' => method_exists($t, 'getType') ? $t->getType() :null,
          'varname' => method_exists($t, 'getVariableName') ? $t->getVariableName() :null,
          'isVariadic' => method_exists($t, 'isVariadic') ? $t->isVariadic() :null,
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

  public function parse($class_name)
  {
    $rc = new \ReflectionClass($class_name);
    //die(var_dump($rc->hasConstant('PARAM_BOOL')));
    $constants = $rc->getConstants();
    $parent = $rc->getParentClass();
    $parent_constants = [];
    if ( $parent ){
      $parent_constants = $parent->getConstants();
    }
    $cparser =& $this;
    $cls = [
      'doc' => [
        'title' => $this->iparse($rc->getDocComment()),
      ],
      'name' => $rc->getName(),
      'constants' => array_map(function($a)use($constants, $parent_constants){
        return [
          'name' => $a->name,
          'value' => $constants[$a->name]
        ];
      }, array_filter($rc->getReflectionConstants(), function($a) use ($parent_constants, $constants){
        return !array_key_exists($a->name, $parent_constants) || ($parent_constants[$a->name] !== $constants[$a->name]);
      })),
      'namespace' => $rc->getNamespaceName(),
      'traits' => $rc->getTraits(),
      'interfaces' => $rc->getInterfaces(),
      'parent' => $parent ? $parent->getName() : null,
      'properties' => array_map(function($m) use ($cparser){
        //$m->setAccessible(true);
        return [
          'name' => $m->getName(),
          //'value' => $m->getValue(),
          'static' => $m->isStatic(),
          'private' => $m->isPrivate(),
          'protected' => $m->isProtected(),
          'public' => $m->isPublic(),
          'doc' => $cparser->iparse($m->getDocComment()),
        ];
      }, $rc->getProperties()),
      'methods' => array_map(function($m) use ($cparser){
        return [
          'name' => $m->getName(),
          'static' => $m->isStatic(),
          'private' => $m->isPrivate(),
          'protected' => $m->isProtected(),
          'public' => $m->isPublic(),
          'final' => $m->isFinal(),
          'code' => $this->_closureSource($m),
          'doc' => $cparser->iparse($m->getDocComment()),
          'arguments' => array_map(function($p)use($m){
            return [
              'name' => $p->getName(),
              'position' => $p->getPosition(),
              'type' => $p->getType(),
              'required' => !$p->isOptional(),
              'has_default' => $p->isDefaultValueAvailable(),
              'default' => $p->isDefaultValueAvailable() ? $p->getDefaultValue() : '',
              'default_name' => $p->isDefaultValueAvailable() && $p->isDefaultValueConstant() ?
                $p->getDefaultValueConstantName() : ''
            ];
          }, $m->getParameters())
        ];
      }, $rc->getMethods())
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
                  \bbn\x::hdump($i, (string)$t->getType(), $t->getName);
                  $desc = $t->getDescription()->render();
                  var_dump($desc);
                }
                echo '<pre>';
                var_dump($summary, $description, $tags);
                echo '</pre>';
              }
            }
          }
          \bbn\x::hdump("HEY??", count($node['stmts']));
        }
      }
      \bbn\x::hdump(count($arr[0]['stmts']));
      \bbn\x::hdump($arr[0]['stmts']);
    }
    catch (PhpParser\Error $e) {
        echo 'Parse Error: ', $e->getMessage();
    }
    */
    return $cls;
  }

  public function analyze($class, $type = false): ?array
  {
    $ok = true;
    try {
      $ref = new \ReflectionClass($class);       
    }
    catch ( \Exception $e ){
      die(var_dump($e->getMessage()));
      $ok = false;
    }
    
    if ( $ok ){
      $fs = new bbn\file\system();
      $idx = 'class';
    
      if ( $ref->isTrait() ){
        $idx = 'trait';
      }
      else if ( $ref->isAbstract() ){
        $idx = 'abstract';
      }
      else if ( $ref->isInterface() ){
        $idx = 'interface';
      }
      $tmp = $ref->getFileName();
      $file = $tmp && $fs->is_file($tmp) ? $tmp : null;
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
        'traits' => [],
        'unused' => [] 
      ];

      //for parents 
      $parents = $ref->getParentClass();
      if ( !empty($parents) ){
        foreach ($parents as $parent){
          $arr['parents'][$parent] = $this->analyze($parent, 'parent');

          foreach ( $arr['parents'][$parent]['methods'] as $i => $m ){              
            if ( count($m) ){
              $arr['methods'][$i] = array_merge($m, $arr['methods'][$i]);                
            }
          }            
        }
      }        
      
      //for traits
      $traits = $ref->getTraitNames();
      if ( !empty($traits) ){
        foreach ($traits as $trait){
          $arr['traits'][$trait] = $this->analyze($trait, 'trait');
          
          foreach ( $arr['traits'][$trait]['methods'] as $i => $m ){              
            if ( count($m) ){
              $arr['methods'][$i] = array_merge($m, $arr['methods'][$i]);                
            }
          }           
        }
      }

      if ( !empty($arr['methods']['private']) ){
        foreach ( $arr['methods']['private'] as $priv ){          
          $str = ($priv[$idx]['static'] ? '::' : '->').$priv['name'];
          if ( \bbn\x::indexOf($fs->get_contents($arr['file']), $str) === -1 ){
            $arr['unused'][] = $arr['name'].'::'.$priv['name'];
          }
        }
      }      
      return $arr;
    }
    return null;
  }

  public function addMethods($class_object, $origin = false, $file = null)
  {

    $methods = [
      'private' => [],
      'protected' => [],
      'public' => []
    ];
    
    foreach ( $class_object->getMethods() as $meth ){
      $idx = 'public';
      if ( $meth->isPrivate() ){
        $idx = 'private';
      }
      else if ( $meth->isProtected() ){
        $idx = 'protected';
      }

      if ( $meth->getDeclaringClass()->getName() === $class_object->getName() ){
        $doc = is_null($file) ? false :  $meth->getDocComment();
        
        $methods[$idx][$meth->getName()] = [
          'type' => $meth->isStatic() ? 'static' : 'non-static',
          'doc' =>  is_null($file) ? false : $doc,
          'parsed' => is_null($file) ? false : $this->parser->parse_docblock($doc),
          'line' => is_null($file) ? false : $meth->getStartLine(),
          'type' => $origin !== false ? $origin : 'origin', 
          'file' => $meth->getDeclaringClass()->getName() 
        ];

      }
    }
    
    if ( $origin === 'parent' ){
      unset($methods['private']);
    }
    return $methods;
  }    
    
}