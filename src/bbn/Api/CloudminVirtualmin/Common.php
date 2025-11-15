<?php

namespace bbn\Api\CloudminVirtualmin;

use bbn\Str;

/**
 * Cloudmin Virtualmin Common trait
 */
trait Common
{

  /** @var string The username */
  private $user;

  /** @var string The password */
  private $pass;

  /** @var bool Check instance existence */
  private $checked = false;

  /** @var array Array of all commands */
  private $commands = [];

  /** @var bbn\Cache cache */
  private $cacher;

  /** @var bool */
  private $asJson = true;

  /** @var string The last action to have been performed */
  public $lastAction = false;

  /** @var */
  public $error = false;

  /**
   * Virtualmin constructor.
   * @param array $cfg
   */
  public function __construct(array $cfg)
  {
    if (isset($cfg['user'], $cfg['pass'])) {
      $this->cacheInit();
      $this->user     = $cfg['user'];
      $this->pass     = $cfg['pass'];
      $this->hostname = isset($cfg['host']) ? $cfg['host'] : 'localhost';
      $this->checked  = true;
      if (class_exists('\\bbn\\Cache')) {
        if (!$this->cacheHas('list_commands')) {
          $this->fetchCommands();
        }

        $this->commands = $this->cacheGet('list_commands');
      }
      else {
        $this->commands = $this->fetchCommands();
      }
    }
  }


  /**
   * @param $name
   * @param $arguments
   * @return array|bool
   */
  public function __call($name, $arguments)
  {
    if ($this->checked) {
      $cmdName = str_replace('_', '-', $name);
      if (\is_array($this->commands) && isset($this->commands[$cmdName])) {
        //Setting the last action performed
        $this->lastAction = $cmdName;
        //Defining  the $url_part and the command to be executed
        $url_part = $cmdName;
        $cmd      = $this->commands[$cmdName];
        if (!empty($arguments[0])) {
          //Prepping, processing and validating the create user parameters
          $args = $this->processParameters($arguments[0]);
          if (!empty($cmd['args'])) {
            foreach ($cmd['args'] as $k => $v) {
              // We can't use this system to check the mandatory properties:
              // Virtualmin also marks properties that are actually a choice between
              // several "mandatory" properties as "mandatory"
              /*
              if (!empty($v['mandatory']) && !isset($args[$k])) {
                if (
                    (Str::pos($k, 'pass') === false)
                    && (!isset($args['pass']) && !isset($args['encpass'])
                    && !isset($args['passfile']))
                ) {
                  var_dump("Parameter $k mandatory for $name!");
                  return false;
                }
              }
              */
              if (isset($args[$k])) {
                if ($v['binary'] && ($args[$k] || $v['mandatory'])) {
                  $url_part .= "&$k=";
                }
                elseif (\is_array($v) && $v['multiple'] && \is_array($args[$k])) {
                  foreach ($args[$k] as $w) {
                    $url_part .= "&$k=$w";
                  }
                }
                else {
                  $url_part .= "&$k=" . $args[$k];
                }
              }
            }
          }
        }

        //Concatenating the header url and $url_part to create the full url to be executed
        $url_part = $this->getHeaderUrl() . $url_part . "'";
        //Calling shell_exec and returning the result array
        return $this->callShellExec($url_part);
      }
      // We force even if we don't have the command in the list
      else {
        if (!empty($arguments)) {
          $args = $this->processParameters($arguments[0]);
        }
        $url_part = $cmdName;
        if (!empty($args)) {
          foreach ($args as $k => $v) {
            if (\is_array($v)) {
              foreach ($v as $w) {
                $url_part .= "&$k=$w";
              }
            }
            elseif ($v == 1) {
              $url_part .= "&$k=";
            }
            else {
              $url_part .= "&$k=$v";
            }
          }
        }

        //Concatenating the header url and $url_part to create the full url to be executed
        $url_part = $this->getHeaderUrl() . $url_part . "'";
        //Calling shell_exec and returning the result array
        return $this->callShellExec($url_part);
      }
    }
    return false;
  }


  /**
   * Returns an array containing all the commands and their parameters
   * @return array
   */
  public function getCommands()
  {
    if ($this->checked) {
      return $this->commands;
    }
  }


  /**
   * Returns the arguments description of a given command
   * @param $name The command name
   * @return array
   */
  public function getArgs($name)
  {
    if ($this->checked) {
      $cmdName = str_replace('_', '-', $name);
      return isset($this->commands[$cmdName], $this->commands[$cmdName]['args']) ? $this->commands[$cmdName]['args'] : [];
    }
  }


  /**
   * @param $command
   * @return array
   */
  public function getCommand($command)
  {
    $command = str_replace('_', '-', $command);
    //Setting the last action performed
    $this->lastAction = "get-command";
    //Defining  the $url_part and the command to be executed
    $url_part = "get-command&command=" . $this->sanitize($command);
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->getHeaderUrl() . $url_part . "'";
    //Calling shell_exec and returning the result array
    return $this->callShellExec($url_part);
  }


  /**
   * Returns a string of PHP code for executing a given command with all its possible parameters pre-populated
   * @param $command
   * @return string|false
   */
  public function generate($command)
  {
    $perlCmd = str_replace('_', '-', $command);
    if (isset($this->commands[$perlCmd])) {
      $cmd = $this->commands[$perlCmd];
      $st  = '$vm->' . $command . '([' . PHP_EOL;
      foreach ($cmd['args'] as $k => $v) {
        $st .= "'$k' => " . ($v['binary'] ? '0' : "''") . PHP_EOL;
      }

      $st .= ']);';
      return $st;
    }

    return false;
  }


  /**
   * Sets the 'asJson' property as true
   * @return self
   */
  public function setJson()
  {
    $this->asJson = true;
    return $this;
  }


  /**
   * Sets the 'asJson' property as false
   * @return self
   */
  public function unsetJson()
  {
    $this->asJson = false;
    return $this;
  }


  /**
   * Test server connection
   * @return bool
   */
  public function testConnection(): bool
  {
    return (bool)shell_exec($this->getHeaderUrl() . "'");
  }


  /**
   * This function is used to sanitize the strings which are given as parameters
   * @param string $st
   * @return string The the header url part to be executed
   */
  private function sanitize($st)
  {
    $st = trim((string)$st);
    if (
        (Str::pos($st, ';') !== false)
        || (Str::pos($st, '<') !== false)
        || (Str::pos($st, '"') !== false)
        || (Str::pos($st, "'") !== false)
    ) {
      return '';
    }
    return $st;
  }


  /**
   * This function is used to get the header url part to be executed
   * @return string The the header url part to be executed
   */
  private function getHeaderUrl()
  {
    $environment = strtolower(get_class($this));
    return "wget -O - --quiet --http-user='" . $this->user . "' --http-passwd=" .
      escapeshellarg($this->pass) . " --no-check-certificate 'https://" . $this->hostname . ":10000/" . (
        $environment === 'cloudmin' ? 'server-manager' : 'virtual-server'
      ) . "/remote.cgi?" . ($this->asJson ? "json=1&multiline=&" : "") . "program=";
  }


  /**
   * Executes the $request using shell_exec
   * @param string $request the command to be excecuted
   * @return array an array with the execution status and message
   */
  private function callShellExec($request)
  {
    //\bbn\X::log($request, 'virtualminCloudmin');
    //Executing the shell_exec
    $result = shell_exec($request);
    //\bbn\X::log($result, 'virtualminCloudmin');
    if ($result && $this->asJson && \bbn\Str::isJson($result)) {
      //Decoding the json result into an array
      $result = json_decode($result, true);
      if (isset($result['error'])) {
        $this->error = $result['error'];
      }

      if (isset($result['status']) && ($result['status'] === 'success')) {
        if (isset($result['data'])) {
          if (
              isset($result['data'][0], $result['data'][0]['name'])
              && ($result['data'][0]['name'] === 'Warning')
          ) {
            $result['data'] = \array_slice($result['data'], 1);
          }

          return $result['data'];
        }
        elseif (isset($result['output'])) {
          return $result['output'];
        }
      }
    }
    elseif ($result && !$this->asJson) {
      return $result;
    }

    return false;
  }


  /**
   * Sanitize each parameter
   * @param array $param the raw parameters
   * @return array the processed parameters
   */
  private function processParameters(array $param)
  {
    foreach ($param as $key => $val) {
      if (\is_array($val)) {
        $param[$key] = $this->processParameters($val);
      }
      else {
        $param[$key] = $this->sanitize($val);
      }
    }

    return $param;
  }


  /**
   * @return array
   */
  private function fetchCommands()
  {
    if ($this->checked) {
      $raw_commands = $this->listCommands();
      $commands     = [];
      foreach ($raw_commands as $com) {
        if ($cmd = $this->getCommand($com['name'])) {
          array_shift($cmd)['value'];
          $args = [];
          foreach ($cmd as $cm) {
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
      $this->cacheSet('list_commands', '', $commands);
      return $commands;
    }
  }


  /**
   * Gets all the commands directly from the API
   * @param array $param
   * @return array
   */
  public function listCommands(array $param = [])
  {
    //Prepping, processing and validating the create user parameters
    $param = $this->processParameters($param);
    //Setting the last action performed
    $this->lastAction = "list-commands";

    //Defining  the $url_part and the command to be executed
    $url_part = "list-commands";
    if (isset($param['short'])) {//short parameter is set
      $url_part .= "&short";
    }

    if (isset($param['nameonly'])) {//nameonly parameter is set
      $url_part .= "&nameonly";
    }

    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->getHeaderUrl() . $url_part . "'";

    //test
    $uid = $this->hostname;
    if (!empty($param)) {
      $uid .= md5(json_encode($param));
    }

    if ($this->cacheHas($uid, 'list_commands')) {
      $result_call = $this->cacheGet($uid, 'list_commands');
    }
    else {
      $result_call = $this->callShellExec($url_part);
      $this->cacheSet($uid, 'list_commands', $result_call);
    }

    return $result_call;
  }
}
