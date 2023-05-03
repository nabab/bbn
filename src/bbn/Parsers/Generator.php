<?php

namespace bbn\Parsers;

use bbn\X;

class Generator {
  
  private $cfg;
  
  private $spacing;
  
  public function __construct(array $cfg = [], int $spacing = 2)
  {
    $this->cfg = $cfg;
    $this->spacing = $spacing;
  }
  
  public function generateClass() {
    $res = "<?php\n\n";
    if ( !empty($this->cfg['namespace'])) {
      $res .= "namespace " . $this->cfg['namespace'] . ";\n\n";
    }
    
    if (str_contains($this->cfg['name'], $this->cfg['namespace'])) {
      $res .= "class " . substr($this->cfg['name'], strlen($this->cfg['namespace']) + 1);
    } else {
      $res .= "class " . $this->cfg['name'];
    }
    
    if ( !empty($this->cfg['extends'])) {
      $res .= " extends " . $this->cfg['extends'];
    }

    $res .= "\n{\n";
    
    if ( !empty($this->cfg['traits'])) {
      foreach ($this->cfg['traits'] as $trait) {
        $res .= str_repeat(' ', $this->spacing) . "use " . $trait->name . ";\n";
      }
      $res .= "\n";
    }
    
    
    if ( !empty($this->cfg['properties'])) {
      foreach ($this->cfg['properties'] as $property) {
        $res .= $this->generateProperty($property) . "\n";
      }
    }
    
    if ( !empty($this->cfg['methods'])) {
      foreach ($this->cfg['methods'] as $method) {
        $res .= $this->generateMethod($method) . "\n\n";
      }
    }
    
    return $res . "}";
    
  }
  
  public function generateMethod(array $cfg) {
    $res = str_repeat(' ', $this->spacing);
  
    if ( !empty($cfg['doc'])) {
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
      
      $res .= str_repeat(' ', $this->spacing) . " */\n";
      
    }

    
    if ( !empty($cfg['final']) ) {
      $res .= "final ";
    }
    
    if ( !empty($cfg['public']) ) {
      $res .= "public ";
    } else if ( !empty($cfg['protected']) ) {
      $res .= "protected ";
    } else if ( !empty($cfg['private']) ) {
      $res .= "private ";
    }
    
    if ( !empty($cfg['static']) ) {
      $res .= "static ";
    }
  
    if (!empty($cfg['name'])) {
      $res .= "function " . $cfg['name'] . "(";
    
      if (!empty($cfg['arguments'])) {
        foreach ($cfg['arguments'] as $arg) {
          $argStr = "";
        
          if (!empty($arg['type'])) {
            $argStr .= $arg['type'] . " ";
          }
        
          $argStr .= "$" . $arg['name'];
        
          if (!empty($arg['has_default'])) {
            $argStr .= " = " . var_export($arg['default'], true);
          }
        
          $res .= $argStr . ", ";
        }
      
        // Remove the trailing comma and space
        $res = substr($res, 0, -2);
      }
    
      $res .= ") ";
      
      if ( !empty($cfg['returns']) ) {
        $has_null = false;
        
        $res_returns = ": ";
        
        foreach ($cfg['returns'] as $ret) {
          if (str_contains($ret, '?')) {
            $has_null = true;
          }
        }
        
        foreach ($cfg['returns'] as $ret) {
          if ($ret === null && !$has_null) {
            $res_returns .= "null|";
          } else if ($ret !== null) {
            $res_returns .= $ret . "|";
          }
        }

        $last_pipe = strrpos($res_returns, '|');
        
        $res .= substr($res_returns, 0, $last_pipe) . " ";
        
        
      }
    
    }
  
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