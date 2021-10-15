<?php

namespace tests;

use bbn\Db\Enums\Errors;

trait MysqlDbSetup
{
  protected static $connection;

  protected static function createTestingDatabase()
  {
    try {
      $db_cfg = self::getDbConfig();

      self::$connection = new \PDO(
        "mysql:host={$db_cfg['host']};port={$db_cfg['port']};}",
        $db_cfg['user'],
        $db_cfg['pass'],
        [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']
      );

      self::$connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

      self::$connection->query("CREATE DATABASE IF NOT EXISTS {$db_cfg['db']}");

      self::$connection = new \PDO(
        "mysql:host={$db_cfg['host']};port={$db_cfg['port']};dbname={$db_cfg['db']}",
        $db_cfg['user'],
        $db_cfg['pass'],
        [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']
      );

      self::$connection->query("SET FOREIGN_KEY_CHECKS=0;");

    } catch (\PDOException $e) {
      throw new \Exception("Unable to establish db connection for testing: " . $e->getMessage());
    }
  }

  protected static function getDbConfig()
  {
    return array(
      'engine'        => 'mysql',
      'host'          => getenv('db_host'),
      'user'          => getenv('db_user'),
      'pass'          => getenv('db_pass'),
      'db'            => getenv('db_name'),
      'port'          => getenv('db_port'),
      'cache_length'  => 3000,
      'on_error'      => Errors::E_STOP,
      'force_host'    => true
    );
  }

  protected static function parseEnvFile()
  {
    $env_file = getcwd() . '/tests/.env.test';

    if (strpos($env_file, '/tests/Db/') !== false) {
      $env_file = str_ireplace('/tests/Db/', '/', $env_file);
    }

    if (!file_exists($env_file)) {
      throw new \Exception(
        'env file does not exist, please create the file in the tests dir, @see .env.test.example'
      );
    }

    $env = file_get_contents($env_file);

    foreach (explode(PHP_EOL, $env) as $item) {
      $res = explode('=', $item);
      $key  = $res[0];
      $value = $res[1] ?? "";
      if (empty($key) || empty($value)) {
        continue;
      }
      @putenv("$key=$value");
    }
  }

  protected function createTable(string $table, callable $callback)
  {
    $this->dropTableIfExists($table);

    $structure = $callback();

    self::$connection->query("CREATE TABLE `$table` (
  $structure
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 collate utf8mb4_unicode_ci");
  }

  protected function dropTableIfExists(string $table)
  {
    self::$connection->query("DROP TABLE IF EXISTS $table");
  }

  protected function dropDatabaseIfExists(string $database)
  {
    self::$connection->query("DROP DATABASE IF EXISTS $database");
  }
}