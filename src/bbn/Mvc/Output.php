<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 15/05/2015
 * Time: 16:55
 */

namespace bbn\Mvc;

use bbn\X;
use bbn\Models\Cls\Basic;
use JShrink\Minifier;
use stdClass;
use RuntimeException;
use Exception;
use bbn\File;
use bbn\File\Image;
use bbn\Compilers\Markdown;
final class Output extends Basic {
  /**
   * Returns an array with the status and the code for the given code, and sends the corresponding header if not disabled
   *
   * @param integer $code The status code
   * @param bool    $send If false the header won't be sent
   * @return array
   */
  static function statusHeader(int $code, bool $send = true): array
  {
    $http = [
      100 => 'HTTP/1.1 100 Continue',
      101 => 'HTTP/1.1 101 Switching Protocols',
      200 => 'HTTP/1.1 200 OK',
      201 => 'HTTP/1.1 201 Created',
      202 => 'HTTP/1.1 202 Accepted',
      203 => 'HTTP/1.1 203 Non-Authoritative Information',
      204 => 'HTTP/1.1 204 No Content',
      205 => 'HTTP/1.1 205 Reset Content',
      206 => 'HTTP/1.1 206 Partial Content',
      300 => 'HTTP/1.1 300 Multiple Choices',
      301 => 'HTTP/1.1 301 Moved Permanently',
      302 => 'HTTP/1.1 302 Found',
      303 => 'HTTP/1.1 303 See Other',
      304 => 'HTTP/1.1 304 Not Modified',
      305 => 'HTTP/1.1 305 Use Proxy',
      307 => 'HTTP/1.1 307 Temporary Redirect',
      400 => 'HTTP/1.1 400 Bad Request',
      401 => 'HTTP/1.1 401 Unauthorized',
      402 => 'HTTP/1.1 402 Payment Required',
      403 => 'HTTP/1.1 403 Forbidden',
      404 => 'HTTP/1.1 404 Not Found',
      405 => 'HTTP/1.1 405 Method Not Allowed',
      406 => 'HTTP/1.1 406 Not Acceptable',
      407 => 'HTTP/1.1 407 Proxy Authentication Required',
      408 => 'HTTP/1.1 408 Request Time-out',
      409 => 'HTTP/1.1 409 Conflict',
      410 => 'HTTP/1.1 410 Gone',
      411 => 'HTTP/1.1 411 Length Required',
      412 => 'HTTP/1.1 412 Precondition Failed',
      413 => 'HTTP/1.1 413 Request Entity Too Large',
      414 => 'HTTP/1.1 414 Request-URI Too Large',
      415 => 'HTTP/1.1 415 Unsupported Media Type',
      416 => 'HTTP/1.1 416 Requested Range Not Satisfiable',
      417 => 'HTTP/1.1 417 Expectation Failed',
      500 => 'HTTP/1.1 500 Internal Server Error',
      501 => 'HTTP/1.1 501 Not Implemented',
      502 => 'HTTP/1.1 502 Bad Gateway',
      503 => 'HTTP/1.1 503 Service Unavailable',
      504 => 'HTTP/1.1 504 Gateway Time-out',
      505 => 'HTTP/1.1 505 HTTP Version Not Supported',
    ];

    if (!isset($http[$code])) {
      throw new Exception("The given status doesn't exist");
    }

    if ($send) {
      header($http[$code]);
      if ($code !== 200) {
        exit();
      }
    }
 
    return [
      'code' => $code,
      'error' => $http[$code],
    ];
  }


  public function __construct(
    private stdClass $obj,
    private string $mode
  ) {
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
    if (\count((array)$this->obj) === 0) {
      self::statusHeader(404);
    }

    if ($this->mode === 'cli') {
      if (!headers_sent() && !$this->obj->content) {
        exit('No output...');
      }
      if ( $this->obj->content ){
        echo $this->obj->content;
      }
      exit();
    }

    if ( isset($this->obj->prescript) ){
      if ( empty($this->obj->prescript) ){
        unset($this->obj->prescript);
      }
      else if ( !BBN_IS_DEV ){
        try{
          $tmp = Minifier::minify($this->obj->prescript, ['flaggedComments' => false]);
        }
        catch ( RuntimeException $e ){
          X::log($this->obj->prescript, 'js_shrink');
        }
        if ( $tmp ){
          $this->obj->prescript = $tmp;
        }
      }
    }
    if ( isset($this->obj->script) ){
      if ( empty($this->obj->script) ){
        unset($this->obj->script);
      }
      else if ( !BBN_IS_DEV ){
        try{
          $tmp = Minifier::minify($this->obj->script, ['flaggedComments' => false]);
        }
        catch ( RuntimeException $e ){
          X::log($this->obj->script, 'js_shrink');
        }
        if ( $tmp ){
          $this->obj->script = $tmp;
        }
      }
    }
    if ( isset($this->obj->postscript) ){
      if ( empty($this->obj->postscript) ){
        unset($this->obj->postscript);
      }
      else if ( !BBN_IS_DEV ){
        try{
          $tmp = Minifier::minify($this->obj->postscript, ['flaggedComments' => false]);
        }
        catch ( RuntimeException $e ){
          X::log($this->obj->postscript, 'js_shrink');
        }
        if ( $tmp ){
          $this->obj->postscript = $tmp;
        }
      }
    }
    if ((empty($this->obj->content) && (X::countProperties($this->obj) === 1)) || in_array($this->mode, ['file', 'image'])) {
      if (!empty($this->obj->file)){
        if (\is_string($this->obj->file) && is_file($this->obj->file)){
          $this->obj->file = new File($this->obj->file);
        }
        if (\is_object($this->obj->file) &&
          method_exists($this->obj->file, 'download') &&
          method_exists($this->obj->file, 'test') &&
          $this->obj->file->test()
        ){
          $this->mode = 'file';
        }
      }
      else if (!empty($this->obj->img)){
        if (\is_string($this->obj->img) && is_file($this->obj->img)){
          $this->obj->img = new Image($this->obj->img);
        }
        if (\is_object($this->obj->img) &&
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
      $mdParser = new Markdown();
      $this->obj->help = $mdParser->compile($this->obj->help);
    }
    
    switch ( $this->mode ){

      case 'public':
        header('Content-type: application/json; charset=utf-8');
        if (BBN_IS_DEV) {
          echo json_encode($this->obj, JSON_PRETTY_PRINT);
        }
        else {
          echo json_encode($this->obj);
        }
        break;

      case 'js':
        header('Content-type: text/javascript');
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
        if ( isset($this->obj->img) && \is_object($this->obj->img) ){
          $this->obj->img->display();
        }
        else{
          $this->log("Impossible to display the following image: ".$this->obj->img->name);
          self::statusHeader(404);
        }
        break;

      case 'file':
        if ( isset($this->obj->file) && \is_object($this->obj->file) && method_exists($this->obj->file, 'download') ){
          $this->obj->file->download();
        }
        else{
          $this->log("Impossible to display the following controller", $this);
          self::statusHeader(404);
        }
        break;

      default:
        header('Content-type: text/html; charset=utf-8');
        echo isset($this->obj->content) ? $this->obj->content : '';
    }
  }
}