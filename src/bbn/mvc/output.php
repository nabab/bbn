<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 15/05/2015
 * Time: 16:55
 */

namespace bbn\mvc;
use bbn;


class output {

  public function __construct(\stdClass $obj, $mode){
    $this->obj = $obj;
    $this->mode = $mode;
  }


  /**
   * Outputs the result.
   *
   * @return void
   */
  public function run()
  {
    if ( count((array)$this->obj) === 0 ){
      header('HTTP/1.0 404 Not Found');
      exit();
    }

    if ( $this->mode === 'cli' ){
      die(isset($this->obj->content) ? $this->obj->content : "no output");
    }

    if ( isset($this->obj->prescript) ){
      if ( empty($this->obj->prescript) ){
        unset($this->obj->prescript);
      }
      else if ( !BBN_IS_DEV ){
        $this->obj->prescript = \JShrink\Minifier::minify($this->obj->prescript);
      }
    }
    if ( isset($this->obj->script) ){
      if ( empty($this->obj->script) ){
        unset($this->obj->script);
      }
      else if ( !BBN_IS_DEV ){
        $this->obj->script = \JShrink\Minifier::minify($this->obj->script);
      }
    }
    if ( isset($this->obj->postscript) ){
      if ( empty($this->obj->postscript) ){
        unset($this->obj->postscript);
      }
      else if ( !BBN_IS_DEV ){
        $this->obj->postscript = \JShrink\Minifier::minify($this->obj->postscript);
      }
    }
    if ( empty($this->obj->content) || ( ($this->mode === 'file') || ($this->mode === 'image') ) ){
      if (!empty($this->obj->file)){
        if (is_string($this->obj->file) && is_file($this->obj->file)){
          $this->obj->file = new bbn\file($this->obj->file);
        }
        if (is_object($this->obj->file) &&
          method_exists($this->obj->file, 'download') &&
          method_exists($this->obj->file, 'test') &&
          $this->obj->file->test()
        ){
          $this->mode = 'file';
        }
      }
      else if (!empty($this->obj->img)){
        if (is_string($this->obj->img) && is_file($this->obj->img)){
          $this->obj->img = new bbn\file\image($this->obj->img);
        }
        if (is_object($this->obj->img) &&
          method_exists($this->obj->img, 'display') &&
          method_exists($this->obj->img, 'test') &&
          $this->obj->img->test()
        ){
          $this->mode = 'image';
        }
      }
    }
    switch ( $this->mode ){
      case 'public':
      case 'json':
      case 'js':
      case 'css':
      case 'doc':
      case 'html':
        if ( !ob_start("ob_gzhandler" ) ){
          ob_start();
        }
        else{
          header('Content-Encoding: gzip');
        }
        break;
      default:
        ob_start();
    }
    if ( (empty($this->obj->content) && empty($this->obj->file) && empty($this->obj->img) && ($this->mode !==
          'public')) ||
      (($this->mode === 'public') && empty($this->obj)) ){
      $this->mode = '';
    }
    else if ( !empty($this->obj->content) && !empty($this->obj->help) ){
      $Parsedown = new \Parsedown();
      $this->obj->help = $Parsedown->text($this->obj->help);
    }
    //die(var_dump($this->mode, $this->obj));

    switch ( $this->mode ){

      case 'public':
        header('Content-type: application/json; charset=utf-8');
        echo json_encode($this->obj);
        break;

      case 'js':
        header('Content-type: application/javascript; charset=utf-8');
        echo $this->obj->content;
        break;

      case 'css':
        header('Content-type: text/css; charset=utf-8');
        echo $this->obj->content;
        break;

      case 'text':
        header('Content-type: text/plain; charset=utf-8');
        echo $this->obj->content;
        break;

      case 'xml':
        header('Content-type: text/xml; charset=utf-8');
        echo $this->obj->content;
        break;

      case 'image':
        if ( isset($this->obj->img) && is_object($this->obj->img) ){
          $this->obj->img->display();
        }
        else{
          $this->log("Impossible to display the following image: ".$this->obj->img->name);
          header('HTTP/1.0 404 Not Found');

        }
        break;

      case 'file':
        if ( isset($this->obj->file) && is_object($this->obj->file) && method_exists($this->obj->file, 'download') ){
          $this->obj->file->download();
        }
        else{
          $this->log("Impossible to display the following controller", $this);
          header('HTTP/1.0 404 Not Found');
          exit();
        }
        break;

      default:
        header('Content-type: text/html; charset=utf-8');
        echo isset($this->obj->content) ? $this->obj->content : '';

    }
  }
}