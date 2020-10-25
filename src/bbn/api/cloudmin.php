<?php
namespace bbn\api;

use bbn;
use bbn\x;

/**
 * A class for executing Cloudmin commands.
 * php version 7.4
 *
 * @category Api
 * @package BBN_Library
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @author Vito Fava <vito.nabet@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @version Release: 0.1
 * @link https://bbn.io/bbn-php/doc/class/appui/api
 * @since Apr 4, 2011, 23:23:55 +0000
 *
 */
class cloudmin
{

  const CACHE_NAME = 'bbn/api/cloudmin';

  /** @var string username */
  private $_user;

  /** @var string password */
  private $_pass;

  /** @var bool Check instance existence */
  private $_checked = false;

  /** @var array Array of all commands */
  private $_commands;

  /** @var bbn\cache cache */
  private $_cacher;

  /** @var string The last action to have been performed */
  public $last_action = false;

  /** @var */
  public $error = false;

  /** @var [type] */
  public $message;


  /**
   * Constructor.
   *
   * @param array $cfg The connection configuration.
   */
  public function __construct(array $cfg)
  {
    if (isset($cfg['user'], $cfg['pass'])) {
      $this->_user     = $cfg['user'];
      $this->_pass     = $cfg['pass'];
      $this->_hostname = 'cloudmin.lan';
      $this->_checked  = true;
    }

  }


  /**
   * return list of virtual machine
   *
   * @return array
   **/
  public function list_systems()
  {
    $this->last_action = "list-systems";
    //Defining  the $url_part and the command to be executed
    $url_part = "list-systems";
    //Concatenating the closing single quote
    $url_part .= "'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->_get_header_url() . $url_part;

    //Calling shell_exec and returning the result array
    return array_map(
      function ($a) {
        array_walk(
          $a['values'], function (&$b) {
            if (\is_array($b) && array_key_exists(0, $b) && (count($b) === 1)) {
              $b = $b[0];
            }
          }
        );
        $a['values']['name'] = $a['name'];
        if ($a['values']['filesystem']) {
          array_walk(
            $a['values']['filesystem'], function (&$b) {
              $tmp = explode(' ', $b);
              $b   = [
              'name' => $tmp[0],
              'size' => $tmp[2],
              'size_unit' => $tmp[3],
              'used' => $tmp[5],
              'used_unit' => $tmp[6],
              'free' => $tmp[8],
              'free_unit' => $tmp[9]
              ];
            }
          );
        }

        $a['values']['available_updates'] = count(explode(', ', $a['values']['available_updates']));
        return $a['values'];
      }, $this->_call_shell_exec($url_part)
    );

  }


  /**
   * @param string $name      The name of the method called
   * @param array  $arguments The arguments given to the method
   * @return array|bool
   */
  public function __call(string $name, array $arguments)
  {
    if ($this->_checked) {
      $cmd_name = str_replace('-', '_', $name);

      // TODO ho tolto il controllo $this->commands[$cmd_name]
      // if ( isset($this->commands[$cmd_name]) ){

      //Setting the last action performed
      $this->last_action = $cmd_name;
      //Defining  the $url_part and the command to be executed
      $url_part = $cmd_name;
      if (!empty($arguments[0])) {
        //Prepping, processing and validating the create user parameters
        $args = $this->process_parameters($arguments[0]);

        // TODO tolto i comandi, messo gli argomenti
        foreach ($args as $k => $v){
          if (!empty($v['mandatory']) && !isset($args[$k])) {
            if ((strpos($k, 'pass') === false)
                && (!isset($args['pass']) && !isset($args['encpass']) && !isset($args['passfile']))
            ) {
              var_dump("Parameter $k mandatory for $name!");
              return false;
            }
          }

          // TODO controlllo se questi valori sono boolean e se nono  a true
          if (isset($v)) {
            if (is_bool($v['binary'])
                && ($v['binary'] == true)
            ) {
              $url_part .= "&$k";
            }
            elseif (\is_array($v)
                && is_bool($v['multiple'])
                && ($v['multiple'] == true)
            ) {
              foreach ($v as $w){
                $url_part .= "&$k=$w";
              }
            }
            else{
              $url_part .= "&$k=".$args[$k];
            }
          }
        }

        // }
        //Concatenating the closing single quote
        $url_part .= "'";
        //Concatenating the header url and $url_part to create the full url to be executed
        $url_part = $this->_get_header_url() . $url_part;
        //Calling shell_exec and returning the result array
        return $this->_call_shell_exec($url_part);
      }
      // We force even if we don't have the command in the list
      elseif (!empty($arguments[1])) {
        $args     = $this->process_parameters($arguments[0]);
        $url_part = $cmd_name;
        foreach ($args as $k => $v){
          if (\is_array($v)) {
            foreach ($v as $w){
              $url_part .= "&$k=$w";
            }
          }
          elseif ($v === 1) {
            $url_part .= "&$k";
          }
          else{
            $url_part .= "&$k=$v";
          }
        }

        //Concatenating the closing single quote
        $url_part .= "'";
        //Concatenating the header url and $url_part to create the full url to be executed
        $url_part = $this->_get_header_url() . $url_part;
        x::log($url_part, 'webmin');
        //Calling shell_exec and returning the result array
        return $this->_call_shell_exec($url_part);
      }
      else{
        die("The command $name doesn't exist...");
      }
    }

    return false;

  }


  /**
   * @return array
   */
  private function _fetch_commands()
  {
    if ($this->_checked) {
      $raw_commands = $this->list_commands();
      $commands     = [];
      foreach ($raw_commands as $com){
        if ($cmd = $this->get_command($com['name'])) {
          array_shift($cmd)['value'];
          $args = [];
          foreach ($cmd as $cm){
            $args[$cm['name']] = [
              'desc' => !empty($cm['values']['value']) ? $cm['values']['value'][0] : '',
              'binary' => $cm['values']['binary'][0] === 'No' ? false : true,
              'multiple' => $cm['values']['repeats'][0] === 'No' ? false : true,
              'mandatory' => $cm['values']['optional'][0] === 'No' ? true : false,
            ];
          }

          ksort($args);
          $cm                     = [
            'cat' => $com['values']['category'][0],
            'desc' => $com['values']['description'][0],
            'args' => $args,
            'cmd' => $cmd
          ];
          $commands[$com['name']] = $cm;
        }
      }

      ksort($commands);
      $this->_cacher->set(self::CACHE_NAME, $commands);
      return $commands;
    }

  }


  /**
   * This function is used to sanitize the strings which are given as parameters
   * @param string $st
   * @return string The the header url part to be executed
   */
  private function _sanitize($st)
  {
    $st = trim((string)$st);
    if (strpos($st, ';') !== false) {
      return '';
    }

    if (strpos($st, '<') !== false) {
      return '';
    }

    if (strpos($st, '"') !== false) {
      return '';
    }

    if (strpos($st, "'") !== false) {
      return '';
    }

    return $st;

  }


  /**
   * This function is used to get the header url part to be executed
   * @return string The the header url part to be executed
   */

  /*
  *  TODO ho sostituito questa funzione
  private function _get_header_url(){
    return "wget -O - --quiet --http-user=" . $this->_user . " --http-passwd=" . escapeshellarg($this->_pass) . " --no-check-certificate 'https://" . $this->_hostname . ":10000/".(
      $this->mode === 'cloudmin' ? 'server-manager' : 'virtual-server'
    )."/remote.cgi?json=1&multiline=&program=";
  }
  */
  private function _get_header_url()
  {
    return "wget -O - --quiet --http-user=".$this->_user
        ." --http-passwd=" . escapeshellarg($this->_pass)
        ." --no-check-certificate "
        ."https://".$this->_hostname.":10000/server-manager/remote.cgi?json=1&multiline=&program=";
  }


  /**
   * Executes the $request using shell_exec
   * @param string $request the command to be excecuted
   * @return array an array with the execution status and message
   */
  private function _call_shell_exec($request)
  {
    //Executing the shell_exec
    //die(var_dump($this->mode, $request));
    if ($result = shell_exec($request)) {
      //Decoding the json result into an array
      $result_array = json_decode($result, true);
      if (isset($result_array['error'])) {
        $this->error = $result_array['error'];
      }

      if ($result_array['status'] === 'success') {
        if (isset($result_array['data'])) {
          if (isset($result_array['data'][0], $result_array['data'][0]['name'])
              && ($result_array['data'][0]['name'] === 'Warning')
          ) {
            $result_array['data'] = \array_slice($result_array['data'], 1);
          }

          return $result_array['data'];
        }
        elseif (isset($result_array['output'])) {
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
  private function process_parameters($param)
  {
    foreach ($param as $key => $val){
      //$val is an array
      if (\is_array($val)) {
        $param[$key] = $this->process_parameters($val);
      }
      else {
        $param[$key] = $this->_sanitize($val);
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
  public function get_args($name)
  {
    if ($this->_checked) {
      $cmd_name = str_replace('_', '-', $name);
      return isset($this->commands[$cmd_name], $this->commands[$cmd_name]['args']) ? $this->commands[$cmd_name]['args'] : [];
    }

  }


  /**
   * Returns an array containing all the commands and their parameters
   * @return array
   */
  public function get_commands()
  {
    if ($this->_checked) {
      return $this->commands;
    }

  }


  /**
   * Gets all the commands directly from the API
   * @param array $param
   * @return array
   */
  public function list_commands($param = [])
  {
    //Prepping, processing and validating the create user parameters
    $param = $this->process_parameters($param);
    //Setting the last action performed
    $this->last_action = "list-commands";

    //Defining  the $url_part and the command to be executed
    $url_part = "list-commands";
    if (isset($param['short'])) {//short parameter is set
      $url_part .= "&short";
    }

    if (isset($param['nameonly'])) {//nameonly parameter is set
      $url_part .= "&nameonly";
    }

    //Concatenating the closing single quote
    $url_part .= "'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->_get_header_url() . $url_part;
    //Calling shell_exec and returning the result array
    return $this->_call_shell_exec($url_part);

  }


  /**
   * @param $command
   * @return array
   */
  public function get_command($command)
  {
    $command = str_replace('_', '-', $command);
    //Setting the last action performed
    $this->last_action = "get-command";

    //Defining  the $url_part and the command to be executed
    $url_part = "get-command&command=".$this->_sanitize($command);
    //Concatenating the closing single quote
    $url_part .= "'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->_get_header_url() . $url_part;
    //Calling shell_exec and returning the result array
    return $this->_call_shell_exec($url_part);

  }


  /**
   * Returns a string of PHP code for executing a given command with all its possible parameters pre-populated
   * @param $command
   * @return bool|string
   */
  public function generate($command)
  {
    $perl_cmd = str_replace('_', '-', $command);
    if (isset($this->commands[$perl_cmd])) {
      $cmd = $this->commands[$perl_cmd];
      $st  = '$vm->'.$command.'(['.PHP_EOL;
      foreach ($cmd['args'] as $k => $v){
        $st .= "'$k' => ".($v['binary'] ? '0' : "''").PHP_EOL;
      }

      $st .= ']);';
      return $st;
    }

    return false;

  }


}
