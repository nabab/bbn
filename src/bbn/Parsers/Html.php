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

class Html extends bbn\Models\Cls\Basic
{
  
  
  /**
   * Construct function
   */
  public function __construct()
  {
    $this->docParser = DocBlockFactory::createInstance();
    $this->parser = new Doc('', 'php');
  }


  public function parse($file, $options = [])
  {
    if (is_file($file)) {
      $this->file = $file;
      $this->options = $options;
      $this->parseFile();
    }
    else {
      throw new Exception('File not found');
    }
  }
  
  
}
