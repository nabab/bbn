<?php
/**
 * Class virtualmin
 * @package api
 *
 * @author Edwin Mugendi <edwinmugendi@gmail.com>
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 *
 */
namespace bbn\Api;
use bbn;
use bbn\X;

class Virtualmin {
  use bbn\Models\Tts\Cache;

  const cache_name = 'bbn/api/virtualmin';

  private
    /** @var  Virtualmin username */
    $user,
    /** @var  Virtualmin password */
    $pass,
    /** @var  Virtualmin hostname */
    $hostname,
    /** @var string mode */
    $mode = 'virtualmin',
    /** @var  Check instance existence */
    $checked = false,
    /** @var  Array of all commands */
    $commands = false,
    /** @var cache */
    $cacher;


  public
    // The last action to have been performed
    $last_action = false,
    $error = false,
    $message;

  /**
   * virtualmin constructor.
   * @param array $cfg
   */
  public function __construct(array $cfg){
    if ( isset($cfg['user'], $cfg['pass']) ){
      self::cacheInit();
      $this->user = $cfg['user'];
      $this->pass = $cfg['pass'];
      $this->mode = $cfg['mode'] === 'cloudmin' ? 'cloudmin' : 'virtualmin';
      $this->hostname = isset($cfg['host']) ? $cfg['host'] : 'localhost';
      $this->checked = true;
      /*if ( class_exists('\\bbn\\Cache') ){
        $this->cacher = bbn\Cache::getEngine();

        if ( !$this->cacher->has(self::cache_name) ){
          $this->fetchCommands();
        }
        $this->commands = $this->cacher->get(self::cache_name);
      }
      else{
        $this->commands = $this->fetchCommands();
      }*/
    }
  }

  /**
   * @param $name
   * @param $arguments
   * @return array|bool
   */
  public function __call($name, $arguments){
    if ( $this->checked ){
      $cmd_name = str_replace('_', '-', $name);
      if ( isset($this->commands[$cmd_name]) || ($name === 'info')){

        //Setting the last action performed
        $this->last_action = $cmd_name;
        //Defining  the $url_part and the command to be executed
        $url_part = $cmd_name;
        if (\is_array($this->commands)) {
          $cmd = $this->commands[$cmd_name];
          if ( !empty($arguments[0]) ){
            //Prepping, processing and validating the create user parameters
            $args = $this->processParameters($arguments[0]);
            if (!empty($cmd['args'])) {
              foreach ( $cmd['args'] as $k => $v ){
                if ( !empty($v['mandatory']) && !isset($args[$k]) ){
                  if ( (strpos($k, 'pass') === false) &&
                    (!isset($args['pass']) && !isset($args['encpass']) && !isset($args['passfile']))
                  ){
                    var_dump("Parameter $k mandatory for $name!");
                    return false;
                  }
                }
                if ( isset($args[$k]) ){
                  if ( $v['binary'] && $args[$k] ){
                    $url_part .= "&$k";
                  }
                  else if ( \is_array($v) && $v['multiple'] ){
                    foreach ( $args[$k] as $w ){
                      $url_part .= "&$k=$w";
                    }
                  }
                  else{
                    $url_part .= "&$k=".$args[$k];
                  }
                }
              }
            }
          }
        }
        //Concatenating the closing single quote
        $url_part .= "'";
        //Concatenating the header url and $url_part to create the full url to be executed
        $url_part = $this->getHeaderUrl() . $url_part;

        //Calling shell_exec and returning the result array
    //    return $this->callShellExec($url_part);

        if ( (strpos($cmd_name, 'list-') !== false) ||
          (strpos($cmd_name, 'get-') !== false) ||
          (strpos($cmd_name, 'info') !== false)
        ){
          $uid = $this->hostname;
          if ( !empty($arguments) ){
            $uid .= md5(json_encode($arguments));
          }
          if ( $this->cacheHas($uid, $name) ){
            $result_call = $this->cacheGet($uid, $name);
          }
          else {
            $result_call = $this->callShellExec($url_part);
            $this->cacheSet($uid, $name, $result_call);
          }
        }
        else{
          $result_call = $this->callShellExec($url_part);
        }
        //Calling shell_exec and returning the result array
        return $result_call;
      }
      // We force even if we don't have the command in the list
      else {
        if ( !empty($arguments) ){
          $args = $this->processParameters($arguments[0]);
        }
        $url_part = $cmd_name;
        //todo
        if ( !empty($args) ){
          foreach ( $args as $k => $v ){
            if ( \is_array($v) ){
              foreach ( $v as $w ){
                $url_part .= "&$k=$w";
              }
            }
            else if ( $v === 1 ){
              $url_part .= "&$k";
            }
            else{
              $url_part .= "&$k=$v";
            }
          }
        }
        //Concatenating the closing single quote
        $url_part .= "'";
        //Concatenating the header url and $url_part to create the full url to be executed
        $url_part = $this->getHeaderUrl() . $url_part;
        X::log($url_part, 'webmin');

        //Calling shell_exec and returning the result array
        return $this->callShellExec($url_part);
      }
      // else{
      //   die("The command $name doesn't exist...");
      // }
    }
    return false;
  }


  /**
   * This function allows the cancellation of the cache of the used commands
   *
   * @param $uid file cache
   * @param $method name
   * @return bool
   */
  public function deleteCache($command_name = '', $arguments= false){
    $uid = $this->hostname;
    if ( !empty($arguments) ){
      $uid .= md5(json_encode($arguments));
    }
    if ( !empty($this->cacheDelete($uid, $command_name)) ){
      X::log([$uid, $command_name], 'cache_delete');
      return true;
    }
    return false;
  }


  /**
   * @return array
   */
  private function fetchCommands(){
    if ( $this->checked ){
      $raw_commands = $this->listCommands();
      $commands = [];
      foreach ( $raw_commands as $com ){
        if ( $cmd = $this->getCommand($com['name']) ){
          array_shift($cmd)['value'];
          $args = [];
          foreach ( $cmd as $cm ){
            $args[$cm['name']] = [
              'desc' => !empty($cm['values']['value']) ? $cm['values']['value'][0] : '',
              'binary' => $cm['values']['binary'][0] === 'No' ? false : true,
              'multiple' => $cm['values']['repeats'][0] === 'No' ? false : true,
              'mandatory' => $cm['values']['optional'][0] === 'No' ? true : false,
            ];
          }
          ksort($args);
          $cm = [
            'cat' => $com['values']['category'][0],
            'desc' => $com['values']['description'][0],
            'args' => $args,
            'cmd' => $cmd
          ];
          $commands[$com['name']] = $cm;
        }
      }
      ksort($commands);
    //  $this->cacher->set(self::cache_name, $commands);
      return $commands;
    }
  }

  /**
   * This function is used to sanitize the strings which are given as parameters
   * @param string $st
   * @return string The the header url part to be executed
   */
  private function sanitize($st){
    $st = trim((string)$st);
    if ( strpos($st, ';') !== false ){
      return '';
    }
    if ( strpos($st, '<') !== false ){
      return '';
    }
    if ( strpos($st, '"') !== false ){
      return '';
    }
    if ( strpos($st, "'") !== false ){
      return '';
    }
    return $st;
  }

  /**
   * This function is used to get the header url part to be executed
   * @return string The the header url part to be executed
   */
  private function getHeaderUrl(){
    return "wget -O - --quiet --http-user=" . $this->user . " --http-passwd=" . escapeshellarg($this->pass) . " --no-check-certificate 'https://" . $this->hostname . ":10000/".(
      $this->mode === 'cloudmin' ? 'server-manager' : 'virtual-server'
    )."/remote.cgi?json=1&multiline=&program=";
  }

  /**
   * Executes the $request using shell_exec
   * @param string $request the command to be excecuted
   * @return array an array with the execution status and message
   */
  private function callShellExec($request){
    //Executing the shell_exec
    //die(var_dump($this->mode, $request));
    if ( $result = shell_exec($request) ){
      //Decoding the json result into an array
      $result_array = json_decode($result, TRUE);
      if ( isset($result_array['error']) ){
        $this->error = $result_array['error'];
      }
      if ($result_array['status'] === 'success' ){
        if (isset($result_array['data'])){
          if ( isset($result_array['data'][0], $result_array['data'][0]['name']) &&
            ($result_array['data'][0]['name'] === 'Warning') ){
            $result_array['data'] = \array_slice($result_array['data'], 1);
          }
          return $result_array['data'];
        }
        else if (isset($result_array['output'])){
          return $result_array['output'];
        }
      }
    }
    return false;
  }

  /**
   * Sanitize each parameter
   * @param array $param the raw parameters
   * @return array the processed parameters
   */
  private function processParameters($param){
    foreach ($param as $key => $val){
      //$val is an array
      if (\is_array($val)){
        $param[$key] = $this->processParameters($val);
      }
      else {
        $param[$key] = $this->sanitize($val);
      }
    }
    //Return the processed parameters
    return $param;
  }

  /**
   * Returns the arguments description of a given command
   * @param $name The command name
   * @return array
   */
  public function getArgs($name){
    if ( $this->checked ){
      $cmd_name = str_replace('_', '-', $name);
      return isset($this->commands[$cmd_name], $this->commands[$cmd_name]['args']) ? $this->commands[$cmd_name]['args'] : [];
    }
  }

  /**
   * Returns an array containing all the commands and their parameters
   * @return array
   */
  public function getCommands(){
    if ( $this->checked ){
      return $this->commands;
    }
  }


  /**
   * Gets all the commands directly from the API
   * @param array $param
   * @return array
   */
  public function listCommands($param = []){
    //Prepping, processing and validating the create user parameters
    $param = $this->processParameters($param);
    //Setting the last action performed
    $this->last_action = "list-commands";

    //Defining  the $url_part and the command to be executed
    $url_part = "list-commands";
    if (isset($param['short'])){//short parameter is set
      $url_part .= "&short";
    }

    if (isset($param['nameonly'])){//nameonly parameter is set
      $url_part .= "&nameonly";
    }
    //Concatenating the closing single quote
    $url_part .="'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->getHeaderUrl() . $url_part;

    //test
    $uid = $this->hostname;
    if ( !empty($param) ){
      $uid .= md5(json_encode($param));
    }

    if ( $this->cacheHas($uid, 'list_commands') ){
      $result_call = $this->cacheGet($uid, 'list_commands');
    }
    else {
      $result_call = $this->callShellExec($url_part);
      $this->cacheSet($uid, 'list_commands', $result_call);
    }
    return $result_call;
  }


  /**
   * @param $command
   * @return array
   */
  public function getCommand($command){
    $command = str_replace('_', '-', $command);
    //Setting the last action performed
    $this->last_action = "get-command";

    //Defining  the $url_part and the command to be executed
    $url_part = "get-command&command=".$this->sanitize($command);
    //Concatenating the closing single quote
    $url_part .="'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->getHeaderUrl() . $url_part;
    //Calling shell_exec and returning the result array
    return $this->callShellExec($url_part);
  }

  /**
   * Returns a string of PHP code for executing a given command with all its possible parameters pre-populated
   * @param $command
   * @return bool|string
   */
  public function generate($command){
    $perl_cmd = str_replace('_', '-', $command);
    if ( isset($this->commands[$perl_cmd]) ){
      $cmd = $this->commands[$perl_cmd];
      $st = '$vm->'.$command.'(['.PHP_EOL;
      foreach ( $cmd['args'] as $k => $v ){
        $st .= "'$k' => ".($v['binary'] ? '0' : "''").PHP_EOL;
      }
      $st .= ']);';
      return $st;
    }
    return false;
  }
}



/*
$config = array(
  'user' => 'root',
  'pass' => 'JvLy3HbJ',
  'hostname' => 'localhost'
);
//Creating a virtualmin object
$vm = new Virtualmin($config);
$list_commands_param = [];
//$vm->listCommands($list_commands_param);
if ($r = $vm->list_domains()){
  print_r($r);
} else {
  var_dump($vm->error);
}
$create_domain_param = array(
  'domain' => 'fr1s2.co',
  'pass' => 'mugendi',
  'dns' => 1,
  'web' => 1,
  'mail' => 1
);
echo '</pre><p>create domain with mail: </p><pre>';
//if ($r = $vm->create_domain($create_domain_param)){
//    print_r($r);
//} else {
//   var_dump($vm->error);
//}
$create_user_param = array(
  'domain' => 'fr1s.co',
  'user' => 'edwin61',
  'pass' => 'mugendi',
  'quota' => 20,
  'realname' => 'Edwin Mugendi'
);
echo '<p>create user: </p><pre>';
if ($r = $vm->createUser($create_user_param)){
  print_r($r);
} else {
  var_dump($vm->error);
}
echo '</pre><p>repeat create user: </p><pre>';
if ($r = $vm->createUser($create_user_param)){
  print_r($a);
} else {
  var_dump($vm->error);
}
$create_domain_param = array(
  'domain' => 'edw661.co',
  'pass' => 'mugendi',
  'dns' => 1,
  'web' => 1
  );
  echo '</pre><p>create domain: </p><pre>';
  print_r($vm->create_domain($create_domain_param));
  $create_domain_param = array(
  'domain' => 'edw4441.co',
  'pass' => 'mugendi',
  'dns' => 1,
  'web' => 1
  );
  echo '</pre><p>create domain: </p><pre>';
  print_r($vm->create_domain($create_domain_param));
  $create_domain_param = array(
  'domain' => 'edw155.co',
  'pass' => 'mugendi',
  'dns' => 1,
  'web' => 1
  );
  echo '</pre><p>repeat create domain: </p><pre>';
  print_r($vm->create_domain($create_domain_param));

  $delete_domain_param = array(
  'domain' => array('edw661.co', 'edw155.co')
  );
  echo '</pre><p>delete domain: </p><pre>';
  print_r($vm->delete_domain($delete_domain_param));
  echo '</pre><p>repeat delete domain: </p><pre>';
  print_r($vm->delete_domain($delete_domain_param));

  $disable_domain_param = array(
  'domain' => 'edw4441.co',
  'why' => 'No in use'
  );
  echo '</pre><p>disable domain: </p><pre>';
  print_r($vm->disable_domain($disable_domain_param));
  $enable_domain_param = array(
  'domain' => 'edw4441.co'
  );
  echo '</pre><p>enable domain: </p><pre>';
  print_r($vm->enable_domain($enable_domain_param));
  echo '</pre><p>List domains: </p><pre>';
  print_r($vm->list_domains());
  echo '</pre>';
 *
 */
