<?php

namespace bbn\Parsers;

use bbn\X;

class Generator {
  
  public $blala = 1;

  public static $nonClassesReturns = [
    'string',
    'int',
    'float',
    'array',
    'bool',
    'boolean',
    'void',
    'self',
    'null',
  ];
  
  public function __construct(
    /** array  */
    private array $cfg = [],
    private int $spacing = 2
  ) {
  
  }

  public function formatExport($value): string 
  {
    $export = var_export($value, true);
    if (is_array($value)) {
      $export = preg_replace("/^([ ]*)(.*)/m", '$1$1$2', $export);
      $array = preg_split("/\r\n|\n|\r/", $export);
      $array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [NULL, ']$1', ' => ['], $array);
      $export = str_replace('['.PHP_EOL.']','[]', join(PHP_EOL, array_filter(["["] + $array)));
    }
    return $export;
  }
  
  public function generateClass() {
    //X::ddump('Tests');
    $res = "<?php\n\n";
    
    if ( !empty($this->cfg['realNamespace'])) {
      $res .= "namespace " . $this->cfg['realNamespace'] . ";\n\n";
    }
    
    /*if (str_contains($this->cfg['name'], $this->cfg['namespace'])) {
      $res .= "class " . substr($this->cfg['name'], strlen($this->cfg['namespace']) + 1);
    } else {
      $res .= "class " . $this->cfg['name'];
    }*/

    if (!empty($this->cfg['uses'])) {
      foreach ($this->cfg['uses'] as $fqn => $alias) {
        $res .= "use $fqn";
        if (end(X::split($fqn, "\\")) !== $alias) {
          $res .= " as $alias";
        }
        $res .= ";" . PHP_EOL;
      }
    }

    $res .= PHP_EOL . PHP_EOL;

    if (!empty($this->cfg['dummyComments'])) {
      foreach ($this->cfg['dummyComments'] as $comment) {
        $res .= $comment . PHP_EOL . PHP_EOL;
      }
    }

    /*if (!empty($this->cfg['doc']) && (!empty($this->cfg['doc']['description'])) || !empty($this->cfg['doc']['tags'])) {
      $res .= PHP_EOL . "/**" . PHP_EOL . " * ";
      if (!empty($this->cfg['doc']['description'])) {
        $res .= str_replace("\n", "\n * ", $this->cfg['doc']['description']);
      }
      if (!empty($this->cfg['doc']['tags'])) {
        foreach ($this->cfg['doc']['tags'] as $tag => $value) {
          $res .= PHP_EOL . " * @" . $tag . "  " . $value; 
        }
      }
      $res .= PHP_EOL . " *" . PHP_EOL . " * /" . PHP_EOL;
    }*/

    $res .= PHP_EOL . "class " . $this->cfg['realName'];
    
    if ( !empty($this->cfg['parentClass'])) {
      $res .= " extends " . ($this->cfg['uses'][$this->cfg['parentClass']] ?? $this->cfg['parentClass']) ;
    }
    if ( !empty($this->cfg['interfaceNames'])) {
      $implements = array_filter($this->cfg['interfaceNames'], function($a) {
        return str_contains($a, "bbn");
     });
      if (!empty($implements)) {
        $res .= " implements " . join(", ", array_map(function($elem) {
          if ( !empty($this->cfg['realNamespace'])) {
            return str_replace(($this->cfg['realNamespace'] . "\\"), "", $elem);
          }
        }, $implements));
      }
    }

    $res .= "\n{\n";
    

    if ( !empty($this->cfg['traits'])) {
      foreach ($this->cfg['traits'] as $trait) {
        if ( !empty($this->cfg['realNamespace'])) {
          $use = str_replace(($this->cfg['realNamespace'] . "\\"), "", ($this->cfg['uses'][$trait] ?? $trait));
        }
        $res .= str_repeat(' ', $this->spacing) . "use " . $use . ";\n";
      }
      $res .= "\n";
    }

    if (!empty($this->cfg['constants'])) {
      $res .= PHP_EOL;
      foreach ($this->cfg['constants'] as $const) {
        if (!$const['parent']) {
          if (!empty($const['doc']) && (!empty($const['doc']['description'])) || !empty($const['doc']['tags'])) {
            $res .= PHP_EOL . "  /**";
            if (!empty($const['doc']['description'])) {
              $res .= PHP_EOL . "   * ";
              $res .= str_replace("\n", "\n   * ", $const['doc']['description']);
            }
            if (!empty($const['doc']['tags'])) {
              foreach ($const['doc']['tags'] as $tag) {
                $str = "";
                if (!empty($tag['type'])) {
                  $str .= $tag['type'] . " ";
                }
                if (!empty($tag['description'])) {
                  $str .= $tag['description'];
                }
                $res .= PHP_EOL . "   * @" . $tag['tag'] . " " . $str; 
              }
            }
            $res .= PHP_EOL . "   */" . PHP_EOL;
          }
          $line = "  ";
          if ($const['final']) {
            $line .= "final ";
          }
          if ($const['protected']) {
            $line .= "protected ";
          }
          if ($const['private']) {
            $line .= "private ";
          }
          if ($const['public']) {
            $line .= "public ";
          }
          $line .= "const ". $const['name'];
          if ($const['value']) {
            $line .= " = " . $this->formatExport($const['value']);
          }
          $line .=  ";" . PHP_EOL;
          $res .= $line;
        }
      }
      $res .= PHP_EOL;
    }
    
    
    if (!empty($this->cfg['properties'])) {
      foreach ($this->cfg['properties'] as $property => $info) {
        if ($info['promoted']) {
          continue;
        }
        if ($info['parent']) {
          continue;
        }
        if ($info['declaring_trait']->name !== $this->cfg['name']) {
          continue;
        }
        if (!empty($info['doc']) && (!empty($info['doc']['description'])) || !empty($info['doc']['tags'])) {
          $res .= PHP_EOL . "  /**";
          if (!empty($info['doc']['description'])) {
            $res .= PHP_EOL . "   * ";
            $res .= str_replace("\n", "\n   * ", $info['doc']['description']);
          }
          if (!empty($info['doc']['tags'])) {
            foreach ($info['doc']['tags'] as $tag) {
              $str = "";
              if (!empty($tag['type'])) {
                $str .= $tag['type'] . " ";
              }
              if (!empty($tag['description'])) {
                $str .= $tag['description'];
              }
              $res .= PHP_EOL . "   * @" . $tag['tag'] . " " . $str; 
            }
          }
          $res .= PHP_EOL . "   */" . PHP_EOL;
        }
        $static = ($info["static"]) ? " static" : "";
        $val = is_null($info["value"]) ? "" : " = " . $this->formatExport($info["value"]);
        $res .= str_repeat(" ", $this->spacing) . $info["visibility"] . $static .' $'. $property .  $val . ";" . PHP_EOL;
      } 
    }
    
    if ( !empty($this->cfg['methods'])) {
      foreach ($this->cfg['methods'] as $method) {
        if (!empty($method['trait'])) {
          continue;
        }
        if ($method['class'] !== $this->cfg['name']) {
          continue;
        }
        $res .= $this->generateMethod($method) . "\n\n";
      }
    }
    
    return $res . "}";
    
  }
  
  public function generateMethod(array $cfg) {
    $res = "";
    if (!empty($cfg['comments'])) {
      $res .= $cfg['comments'] . PHP_EOL;
    }
    $res .= str_repeat(' ', $this->spacing);
  
    /*if ( !empty($cfg['doc'])) {
      $res .= "/**\n" . str_repeat(' ', $this->spacing) . " * " . $cfg['doc']['summary'] . "\n";
  
  
      if ($cfg['doc']['description']) {
        $res .= str_repeat(' ', $this->spacing) . " * \n";
        $res .= str_repeat(' ', $this->spacing) . " * " . $cfg['doc']['description'] . "\n";
      }
      
      foreach ($cfg['doc']['tags'] as $tag) {
        $res .= str_repeat(' ', $this->spacing) . " * \n";
        if ($tag['name'] === 'param' && !empty($cfg['arguments']) && !empty($cfg['arguments'][$tag['index']])) {
          $res .= str_repeat(' ', $this->spacing) . " * @" . $tag['name'] . " " . $cfg['arguments'][$tag['index']]['type'] . " " . $tag['varname'] . "\n";
        } else if ($tag['name'] === 'return' && !empty($cfg['returns'])) {
          $return = "";
          
          foreach ($cfg['returns'] as $ret) {
            if ($ret === null) {
              $return .= "null|";
            }
            // if string contain "?" remove it
            else if (str_contains($ret, '?')) {
              $return .= str_replace('?', '', $ret) . "|";
            } else {
              $return .= $ret . "|";
            }
          }
          
          $return = substr($return, 0, -1);
          
          $res .= str_repeat(' ', $this->spacing) . " * @" . $tag['name'] . " " . $return . " " . $tag['varname'] . "\n";
        }
      }
      
      $res .= str_repeat(' ', $this->spacing) . " * /\n";
      
    }*/


    
    if ( !empty($cfg['final']) ) {
      $res .= "final ";
    }
    
    /*if ( !empty($cfg['public']) ) {
      $res .= "public ";
    } else if ( !empty($cfg['protected']) ) {
      $res .= "protected ";
    } else if ( !empty($cfg['private']) ) {
      $res .= "private ";
    }*/
    if ( !empty($cfg['visibility']) ) {
      $res .= $cfg['visibility'] . ' ';
    }
    
    if ( !empty($cfg['static']) ) {
      $res .= "static ";
    }
  
    if (!empty($cfg['name'])) {
      $res .= "function " . $cfg['name'] . "(";
    
      if (!empty($cfg['arguments'])) {
        foreach ($cfg['arguments'] as $arg) {
          $argStr = "";
          if (!empty($arg['promoted'])) {
            $argStr .= $arg['promoted'] . " ";
          }
          
          /*if (!empty($arg['type'])) {
            $type = $arg['type'];
            if (!empty($this->cfg['realNamespace'])
                && (strpos($type, ($this->cfg['realNamespace'] . '\\')) === 0))
            {
              $type = str_replace(($this->cfg['realNamespace'] . '\\'), '', $type);
            }
            $argStr .= $type . " ";
          }*/

          if (!empty($arg['type_arr'])) {
            $has_null = false;
            $type = "";
            foreach ($arg['type_arr'] as $t) {
              if (str_contains($t, '?')) {
                $has_null = true;
              }
              else if ($t === 'null') {
                $has_null = true;
              }
            }
            if ($has_null && sizeof($arg['type_arr']) === 2) {
              $type .= "?";
            }
            else if ($has_null && sizeof($arg['type_arr']) > 2) {
              $type .= 'null|';
            }
            foreach ($arg['type_arr'] as $t) {
              if ($t !== 'null') {
                if (!in_array($t, self::$nonClassesReturns)
                    && !in_array($t, $this->cfg['uses'] ?? [])
                    && (strpos($t, '\\') !== 0)
                    && !class_exists(('\\' . ($this->cfg['realNamespace'] ? $this->cfg['realNamespace'] . '\\' : '') . $t))
                ) {
                  $t = '\\' . $t;
                }
                $type .= $t . "|";
              }
            }
            $last_pipe = strrpos($type, '|');
            
            $type = substr($type, 0, $last_pipe);
            $argStr .= $type . " ";
          }

          
          if ($arg['variadic']) {
            $argStr .= "...";
          }
          if ($arg['reference']) {
            $argStr .= "&";
          }
          $argStr .= "$" . $arg['name'];
        
          if (!empty($arg['has_default'])) {
            $argStr .= " = " .  $this->formatExport($arg['default']);
          }
        
          $res .= $argStr . ", ";
        }
      
        // Remove the trailing comma and space
        $res = substr($res, 0, -2);
      }
    
      $res .= ")";
      
      
      if ( !empty($cfg['returns']) ) {
        $has_null = false;
        
        $res_returns = ": ";
        
        foreach ($cfg['returns'] as $ret) {
          if (str_contains($ret, '?')) {
            $has_null = true;
          }
          else if ($ret === null) {
            $has_null = true;
          }
        }

        if ($has_null) {
          $res_returns .= "?";
        }
        
        foreach ($cfg['returns'] as $ret) {
          if ($ret !== null) {
            if (!in_array($ret, self::$nonClassesReturns)
                && !in_array($ret, $this->cfg['uses'] ?? [])
                && (strpos($ret, '\\') !== 0)
                && !class_exists(('\\' . ($this->cfg['realNamespace'] ? $this->cfg['realNamespace'] . '\\' : '') . $ret))
            ) {
              $ret = '\\' . $ret;
            }
            $res_returns .= $ret . "|";
          }
        }
        $last_pipe = strrpos($res_returns, '|');
        
        $res .= substr($res_returns, 0, $last_pipe) . " ";
        
        
      }
      
    }
    
   // X::ddump($res, $cfg[]);
    if (!empty($cfg['code'])) {
      // Get the position of the first opening curly brace
      $pos = strpos($cfg['code'], '{');
        
        // Get everything from the opening curly brace to the end of the string
      $newCode = substr($cfg['code'], $pos);
    
      // Add the code to the function definition
      $res .= "\n" . str_repeat(' ', $this->spacing) . $newCode;
    }
    
    return $res;
    
  }
  
  public function generateProperty(array $cfg = [])
  {
    $res = str_repeat(' ', $this->spacing);
    $res .= $cfg['protected'] ? 'protected ' : ($cfg['private'] ? 'private ' : 'public ');
  
    if (!empty($cfg['static'])) {
      $res .= 'static ';
    }
    if (!empty($cfg['name'])) {
      $res .= "$" . $cfg['name'] . ";" . "\n";
    }
    return $res;
  }
  
  
  
}