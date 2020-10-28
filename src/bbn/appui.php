<?php

namespace bbn;

class appui
{

  protected static $vars = [
    "env",
    "is_ssl",
    "admin_email",
    "db_engine",
    "db_host",
    "database",
    "db_user",
    "db_pass",
    "server_name",
    "hostname",
    "public",
    "cur_path",
    "app_path",
    "lib_path",
    "data_path",
    "log_path",
    "user",
    "preferences",
    "permissions",
    "options",
    "history",
    "encryption_key",
    "fingerprint",
    "app_name",
    "app_prefix",
    "site_title",
    "client_name"
  ];

  private $_checked = false;

  private $_settings_file;

  private $_environment_file;

  private $_settings;

  private $_environment;


  /**
   * Constructor
   */
  public function __construct()
  {
    $ok = true;
    foreach (self::$vars as $v) {
      if (!defined('BBN_'.strtoupper($v))) {
        x::log(_("$v is not defined"));
        throw new \Exception(_("$v is not defined"));
        $ok = false;
      }
    }

    if ($ok) {
      $this->_checked = true;
    }
  }


  /**
   * Checks just once whether or not all needed constant have been defined.
   *
   * @return bool
   */
  public function check(): bool
  {
    return $this->_checked;
  }


  /**
   * Gets the settings of the current project.
   *
   * @return array|null
   */
  public function get_settings(): ?array
  {
    if ($this->check()) {
      if (empty($this->_settings)
          && ($file = $this->get_settings_file())
          && ($content = file_get_contents($file))
      ) {
        if (substr($file, -4) === '.yml') {
          $this->_settings = yaml_parse($content);
        }
        else {
          $this->_settings = json_decode($content, true);
        }
      }

      return $this->_settings;
    }

    return null;
  }


  /**
   * Gets the index for the given environment.
   *
   * @param string $hostname The hostname of the environment.
   * @param string $servname The URL of the environment.
   *
   * @return string|null
   */
  public function get_environment_index(string $hostname = null, string $servname = null): ?string
  {
    if ($this->check()) {
      if (empty($hostname) && empty($servname)) {
        $hostname = BBN_HOSTNAME;
        $servname = BBN_SERVER_NAME;
      }

      return md5($hostname.$servname);
    }

    return null;

  }


  /**
   * Returns the environment variables of an app from the current project.
   *
   * @param string $hostname The hostname of the environment.
   * @param string $servname The URL of the environment.
   *
   * @return array
   */
  public function get_environment($hostname = null, string $servname = null): ?array
  {
    if ($this->check()) {
      if ($hostname !== true) {
        $idx = $this->get_environment_index($hostname, $servname);
      }
      if (empty($this->_environment)
          && ($file = $this->get_environment_file())
          && ($content = file_get_contents($file))
      ) {
        if (substr($file, -4) === '.yml') {
          $envs = yaml_parse($content);
        }
        else {
          $envs = json_decode($content, true);
        }

        foreach ($envs as $i => $env) {
          $md5                      = $this->get_environment_index($env['hostname'], $env['server_name']);
          $this->_environment[$md5] = [
            'index' => $i,
            'data' => $env
          ];
        }
      }

      return $hostname === true ? array_values(
        array_map(
          function ($a) {
            return $a['data'];
          },
          $this->_environment
        )
      ) : (
        isset($this->_environment[$idx]) ?
            $this->_environment[$idx]['data'] :
            null
      );
    }

    return null;
  }


  /**
   * Get the settings file location.
   *
   * @return string|null
   */
  public function get_settings_file(): ?string
  {
    if ($this->check()) {
      if (empty($this->_settings_file)) {
        if (function_exists('\\yaml_parse') && is_file(BBN_APP_PATH.'src/cfg/settings.yml')) {
          $this->_settings_file = BBN_APP_PATH.'src/cfg/settings.yml';
        }
        elseif (is_file(BBN_APP_PATH.'src/cfg/settings.json')) {
          $this->_settings_file = BBN_APP_PATH.'src/cfg/settings.json';
        }
      }

      return $this->_settings_file;
    }

    return null;
  }


  /**
   * Get the environment file location.
   *
   * @return string|null
   */
  public function get_environment_file(): ?string
  {
    if ($this->check()) {
      if (empty($this->_environment_file)) {
        if (function_exists('\\yaml_parse') && is_file(BBN_APP_PATH.'src/cfg/settings.yml')) {
          $this->_environment_file = BBN_APP_PATH.'src/cfg/environment.yml';
        }
        elseif (is_file(BBN_APP_PATH.'src/cfg/settings.json')) {
          $this->_environment_file = BBN_APP_PATH.'src/cfg/environment.json';
        }
      }

      return $this->_environment_file;
    }

    return null;
  }


  /**
   * Set environment vars
   *
   * @param array  $update   The new values to be added
   * @param string $hostname The environment's hostname
   * @param string $servname The environment's server name
   * @param bool   $replace  True if the whole value should be replaced by the new one
   *
   * @return bool
   */
  public function set_environment(
      array $update,
      string $hostname = null,
      string $servname = null,
      bool $replace = false
  ) : bool
  {
    if ($cfg = $this->get_environment(true)) {
      $idx = $this->get_environment_index($hostname, $servname);
      if (isset($this->_environment[$idx])) {
        if ($replace) {
          $this->_environment[$idx]['data'] = $update;
        }
        else {
          $this->_environment[$idx]['data'] = array_merge(
            $this->_environment[$idx]['data'],
            $update
          );
        }

        $file = $this->get_environment_file();
        $envs = $this->get_environment(true);
        if (substr($file, -4) === '.yml') {
          unlink($file);
          return (bool)\yaml_emit_file($file, $envs);
        }
        else {
          return (bool)\file_put_contents($file, json_encode($envs, JSON_PRETTY_PRINT));
        }
      }
    }

    return false;
  }


  /**
   * Set settings vars
   *
   * @param array $update  The new values to be added
   * @param bool  $replace True if the whole value should be replaced by the new one
   *
   * @return bool
   */
  public function set_settings(array $update, $replace = false) : bool
  {
    if ($this->get_settings()) {
      if ($replace) {
        $this->_settings = $update;
      }
      else {
        $this->_settings = array_merge(
          $this->_settings,
          $update
        );
      }

      $file = $this->get_settings_file();
      if (substr($file, -4) === '.yml') {
        return (bool)\yaml_emit_file($file, $this->_settings);
      }
      else {
        return (bool)\file_put_contents($file, json_encode($this->_settings, JSON_PRETTY_PRINT));
      }
    }
    else {
      throw new \Exception(_("Impossible to get the settings"));
    }

    return false;
  }


  public function replace_environment(array $update, string $hostname = null, string $servname = null): bool
  {
    return $this->set_environment($update, $hostname, $servname, true);
  }


  public function replace_settings(array $update): bool
  {
    return $this->set_settings($update, true);
  }

}
