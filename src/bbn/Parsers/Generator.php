<?php

namespace bbn\Parsers;

use bbn\X;
use bbn\Str;

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
    if (!empty($this->cfg['name'])) {
      $tmp =  explode("\\", $this->cfg['name']);
      if (count($tmp) > 2) {
        $str = $tmp[1];
        $res .= "/**\n" . " *@package " . $str . "\n";
        $res .= " */\n";
      }
    }
    if (!empty($this->cfg['namespace'])) {
      $res .= "namespace " . $this->cfg['namespaceName'] . ";\n\n";
    }

    //Add the use statement

    if (!empty($this->cfg['uses'])) {
      foreach ($this->cfg['uses'] as $key => $value) {
        //$res .= "Clé : " . $key . ", Valeur : " . $value . ";\n";
        /*X::ddump($value);*/
    		$res .= "use " . $value;
				if (strcmp(basename(str_replace("\\", "/", $value)), $key) != 0) {
          $res .= " as " . $key;
        }
        $res .= ";\n";
      }
      $res .= "\n";
    }

    if (!empty($this->cfg['uses_function'])) {
      foreach ($this->cfg['uses_function'] as $key => $value) {
        $res .= "use function " . $value;
        if (strcmp(basename(str_replace("\\", "/", $value)), $key) != 0) {
          $res .= " as " . $key;
        }
        $res .= ";\n";
      }
      $res .= "\n";
    }

    //Add the description of the class

    if (!empty($this->cfg['doc'])) {
      if (!empty($this->cfg['doc']['description'])) {
        $res .=  "/**\n";
        if (strpos($this->cfg['doc']['description'], "\n")) {
          $arr_description = explode("\n", $this->cfg['doc']['description']);
          foreach ($arr_description as $arr_d) {
            $res .= " * " . $arr_d ;
            $res .= "\n";
          }
        }
        else {
          $res .= str_repeat(' ', $this->spacing) . " * " . $this->cfg['doc']['description'] . "\n";
        }
      }

      //Add the tags (author, copyright, etc) of the description's class

      if (!empty($this->cfg['doc']['tags'])) {
        foreach ($this->cfg['doc']['tags'] as $key => $value) {
          $res .= " * ";
          $res .= "@" . $key . " " . $value . "\n";
        }
        $res .=  " */\n\n";
      }
    }

    //Add the class naming

    if (!empty($this->cfg['shortName'])) {
      $res .= "class " . $this->cfg['shortName'] . ($this->cfg['parentClass'] ? (" extends " . $this->cfg['parentClass'] ) : "") ;
    }
    $res .= "\n{\n";

    //Add traits

    if ( !empty($this->cfg['traits'])) {
      foreach ($this->cfg['traits'] as $trait) {
        $res .= str_repeat(' ', $this->spacing) . "use " . $trait . ";\n";
      }
      $res .= "\n";
    }

    //Add properties with all features

    if (!empty($this->cfg['properties'])) {
      foreach ($this->cfg['properties'] as  $property => $value) {
        $res .= $this->generateProperty($value, $property);
      }
    }

    if ( !empty($this->cfg['methods'])) {
      foreach ($this->cfg['methods'] as $method) {
        if ($method['parent'] == false && $method['trait'] == false) {
        	$res .= $this->generateMethod($method) . "\n\n";
        }
      }
    }

    return $res . "}";

  }

  public function generateMethod(array $cfg) {
    $res = str_repeat(' ', $this->spacing);

    if (!empty($cfg['description_parts']) || !empty($cfg['summary'])) {
      if (!empty($cfg['summary'])) {
        $res .= "/**\n" . str_repeat(' ', $this->spacing) . " * " . $cfg['summary'] . "\n";
      }
      if ($cfg['description']) {
        $res .= str_repeat(' ', $this->spacing) . " * \n";
        if (strpos($cfg['description'], "//")) {
          $arr_description = explode("//", $cfg['description']);
          foreach ($arr_description as $arr_d) {
            $res .= str_repeat(' ', $this->spacing) . " * " . Str::html2text(substr($arr_d, 0, -1)) ;
            $res .= "\n";
          }
        }
        else {
          $res .= str_repeat(' ', $this->spacing) . " * " . $cfg['description'] . "\n";
        }
      }
      if (!empty($cfg['doc']['todo'])) {
        $todo = $cfg['doc']['todo'];
        $res .= " * @" . $todo['tag'] . " " . $todo['text'] . "\n";
      }
      /*if (!empty($cfg['doc']['params'])) {
        X::ddump($cfg['doc']['params']);
        foreach ($param as $cfg['doc']['params']) {
          $res .= " * @" . $param['tag'] . " " . $param['type'] . " " . $param['name'] . " " . $param['description'] . "\n";
        }
      }*/
      if ($cfg['example']) {
        $res .= str_repeat(' ', $this->spacing) . " * \n";
        $res .= str_repeat(' ', $this->spacing) . " *```php\n";
        $arr_example = explode("\n", $cfg['example']);
        foreach ($arr_example as $arr_e) {
          $res .= str_repeat(' ', $this->spacing) . " * " . $arr_e . "\n";
        }
        $res .= str_repeat(' ', $this->spacing) ." * ```\n";
      }
      if (!empty($cfg['doc'])) {
        foreach ($cfg['doc']['params'] as $tag) {
          $res .= str_repeat(' ', $this->spacing) . " * \n";
          if ($tag['tag'] === 'param' /*&& !empty($cfg['arguments']) && !empty($cfg['arguments'][$tag['index']])*/) {
            $res .= str_repeat(' ', $this->spacing) . " * @" . $tag['tag'] . " " . $tag['type'] . " " . $tag['name'] . " " . $tag['description'] . "\n";
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

            $res .= str_repeat(' ', $this->spacing) . " * @" . $tag['name'] . " " . $return /*. " " . $tag['varname'] */. "\n";
          }
        }
      }
      $res .= str_repeat(' ', $this->spacing) . " */\n";
      if (!empty($cfg['final']) ) {
        $res .= "final ";
      }
    }
    if (!empty($cfg['visibility'])) {
      $res .= str_repeat(' ', $this->spacing) . $cfg['visibility'] . " ";
    }
    /*if ( !empty($cfg['public']) ) {
      $res .= "public ";
    } else if ( !empty($cfg['protected']) ) {
      $res .= "protected ";
    } else if ( !empty($cfg['private']) ) {
      $res .= "private ";
    } */

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
      if (!empty($cfg['returns']) ) {
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
      // Get the position of the first opening curly curly bracket
      $pos = strpos($cfg['code'], '{');

      // Get everything from the opening curly brace to the end of the string
      $newCode = substr($cfg['code'], $pos);

      // Add the code to the function definition
      $res .= "\n" . str_repeat(' ', $this->spacing) . $newCode;
    }
    return $res;
  }

  public function generateProperty(array $cfg = [], string $prop_name)
  {
    if ($cfg['parent'] == false && strlen($cfg['doc']['description']) > 1) {
      $res .= ($cfg['doc']['description'] ? (str_repeat(' ', $this->spacing) . "/** " . $cfg['doc']['description']. " */\n") : "");
      $res .= str_repeat(' ', $this->spacing) . $cfg["visibility"];
      if ($cfg['static'] == true) {
        $res .= ' static ';
      }
      $res .= " $" . $prop_name;

      if ($cfg['doc']['description']) {
        $array = explode(" ", $cfg['doc']['description']);
        ($array[1] == "array" ? ($res .= " = []") : $res .= "");
      }

      return($res .= ";\n\n");
    }
    else {
      return($res .= "");
    }
  }
}