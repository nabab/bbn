<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/06/2016
 * Time: 15:32
 */

namespace bbn\appui;
use bbn;


/**
 * Class observer
 * @package bbn\appui
 */
class observer extends bbn\models\cls\db
{
  /**
   * @var string The path where the observer txt file will be kept, and if deleted
   * the observe function will stop.
   */
  private static $path;

  /**
   * @var bool Indicates if the funciton set_time_limit has been executed.
   */
  private static $time_done = false;

  protected $id_user;

  /**
   * Sets the time limit (to none) once and for all.
   */
  private static function set_time_limit(): void
  {
    if ( !self::$time_done ){
      set_time_limit(0);
      self::$time_done = true;
    }
  }

  /**
   * Returns the observer txt file's full path.
   *
   * @return string
   */
  private static function get_file(): string
  {
    if ( null === self::$path ){
      if ( \defined('BBN_DATA_PATH') ){
        self::$path = BBN_DATA_PATH;
      }
      else{
        self::$path = __DIR__.'/';
      }
    }
    return self::$path.'plugins/appui-cron/appui-observer.txt';
  }

  /**
   * Executes a request (kept in the observer) and returns its (single) result.
   *
   * @param string $request The SQL Query to be executed.
   * @param string|null $params The base64 encoded of a JSON string of the parameters to send with the query.
   * @return mixed
   */
  private function _exec($request, $params = null): ?string
  {
    if ( is_string($request) && bbn\str::is_json($request) ){
      $request = json_decode($request, true);
    }
    if ( is_array($request) ){
      return $this->_exec_array($request);
    }
    return $this->_exec_string($request, $params);
  }

  /**
   * Executes a request (kept in the observer) and returns its (single) result.
   *
   * @param string $request The SQL Query to be executed.
   * @param string|null $params The base64 encoded of a JSON string of the parameters to send with the query.
   * @return mixed
   */
  private function _exec_string(string $request, $params = null): ?string
  {
    if ( $this->check() ){
      $res = !empty($params) ? $this->db->get_one($request, array_map('base64_decode', json_decode($params))) : $this->db->get_one($request);
      return md5((string)$res);
    }
    return null;
  }

  /**
   * Executes a request (kept in the observer as an array) and returns its (single) result.
   *
   * @param string $request The config to be executed.
   * @return mixed
   */
  private function _exec_array(array $request): ?string
  {
    if ( $this->check() ){
      return md5((string)$this->db->select_one($request));
    }
    return null;
  }

  private static function sanitize_params(array $params = null)
  {
    return $params ? json_encode(array_map('base64_encode', $params)) : '';
  }

  /**
   * Returns the ID of an observer with public = 1 and with similar request and params.
   *
   * @param string $request
   * @param string $params
   * @return null|string
   */
  private function _get_id(string $request, $params): ?string
  {
    if ( $this->check() ){
      if ( $params ){
        return $this->db->select_one('bbn_observers', 'id', [
          'id_string' => $this->_get_id_string($request, $params)
        ]);
      }
      return $this->db->select_one('bbn_observers', 'id', [
        'request' => $request,
        'params' => null
      ]);
    }
    return null;
  }

  /**
   * Returns the ID of an observer for the current user and with similar request and params.
   *
   * @param string $request
   * @param string $params
   * @return null|string
   */
  private function _get_id_from_user(string $request, $params): ?string
  {
    if ( $this->id_user && $this->check() ){
      $sql = '
        SELECT `o`.`id`
        FROM bbn_observers AS `o`
          LEFT JOIN bbn_observers AS `ro`
            ON `o`.`id_alias` = `ro`.`id`
        WHERE `o`.`id_user` = ?
        AND (
          (
            `o`.`request` LIKE ?
            AND `o`.`params` '.($params ? 'LIKE ?' : 'IS NULL').'
          )
          OR (
            `ro`.`request` LIKE ?
            AND `ro`.`params` '.($params ? 'LIKE ?' : 'IS NULL').'
          )
        )';
       $args = [hex2bin($this->id_user), $request, $request];
       if ( $params ){
         array_splice($args, 2, 0, $params);
         array_push($args, $params);
       }
       return $this->db->get_one($sql, $args);
    }
    return null;
  }

  /**
   * Returns the unique string representing the request + the parameters (md5 of concatenated strings).
   *
   * @param string $request
   * @param array|null $params
   * @return string
   */
  private function _get_id_string(string $request, string $params = null): string
  {
    return md5($request.($params ?: ''));
  }

  /**
   * Sets the time of next execution in the observer's main row.
   *
   * @param $id
   * @return bbn\db\query|int
   */
  private function _update_next($id): bool
  {
    $id_alias = $this->db->select_one('bbn_observers', 'id_alias', ['id' => $id]);
    return $this->db->query(<<<MYSQL
      UPDATE bbn_observers
      SET next = NOW() + INTERVAL frequency SECOND
      WHERE id = ?
MYSQL
      ,
      hex2bin($id_alias ?: $id)) ? true : false;
  }

  /**
   * observer constructor.
   * @param bbn\db $db
   */
  public function __construct(bbn\db $db)
  {
    $user = \bbn\user::get_instance();
    $this->id_user = $user ? $user->get_id() : null;
    parent::__construct($db);
  }

  /**
   * Confronts the current result with the one kept in database.
   *
   * @param $id
   * @return bool
   */
  public function check_result($id)
  {
    if ( $d = $this->get($id) ){
      $t = new bbn\util\timer();
      $t->start();
      $res = $this->_exec($d['request'], $d['params']);
      $duration = (int)ceil($t->stop() * 1000);
      if ( $res !== $d['result'] ){
        $this->db->update('bbn_observers', [
          'result' => $res,
          'duration' => $duration
        ], [
          'id' => $id
        ]);
        return false;
      }
      return true;
    }
  }

  /**
   * Adds a new observer and returns its id or the id of an existing one.
   *
   * @param array $cfg
   * @return null|string
   */
  public function add(array $cfg): ?string
  {
    if (
      $this->id_user &&
      (null !== $cfg['request']) &&
      $this->check()
    ){
      $t = new bbn\util\timer();
      $t->start();
      if ( is_string($cfg['request']) ){
        $params = self::sanitize_params($cfg['params'] ?? []);
        $request = $cfg['request'];
      }
      else if ( is_array($cfg['request']) ){
        $params = null;
        $request = $cfg['request'];
      }
      else{
        return null;
      }
      $res = $this->_exec($request, $params);
      $duration = (int)ceil($t->stop() * 1000);
      if ( is_array($request) ){
        $request = json_encode($request);
      }
      $id_alias = $this->_get_id($request, $params);
      //die(var_dump($id_alias, $this->db->last(), $request, $params));
      // If it is a public observer it will be the id_alias and the main observer
      if (
        !$id_alias &&
        !empty($cfg['public']) &&
        $this->db->insert('bbn_observers', [
          'request' => $request,
          'params' => $params ?: null,
          'name' => $cfg['name'] ?? null,
          'duration' => $duration,
          'id_user' => null,
          'public' => 1,
          'result' => $res
        ])
      ){
        $id_alias = $this->db->last_id();
      }
      // Getting the ID of the observer corresponding to current user
      if ( $id_obs = $this->_get_id_from_user($request, $params) ){
        //
        $this->check_result($id_obs);
        return $id_obs;
      }
      else if ( $id_alias ){
        if ( $this->db->insert('bbn_observers', [
          'id_user' => $this->id_user,
          'public' => 0,
          'id_alias' => $id_alias,
          'next' => null,
          'result' => $res
        ]) ){
          return $this->db->last_id();
        }
      }
      else{
        if ( $this->db->insert('bbn_observers', [
          'request' => $request,
          'params' => $params ?: null,
          'name' => $cfg['name'] ?? null,
          'duration' => $duration,
          'id_user' => $this->id_user,
          'public' => 0,
          'result' => $res
        ]) ){
          return $this->db->last_id();
        }
      }
    }
    return null;
  }

  /**
   * Returns an observer with its alias properties if there is one (except id and result).
   *
   * @param $id
   * @return array|null
   */
  public function get($id): ?array
  {
    if ( $this->check() ){
      $d = $this->db->rselect('bbn_observers', [], [
        'id' => $id
      ]);
      if ( !$d['id_alias'] ){
        return $d;
      }
      $alias = $this->db->rselect('bbn_observers', [], [
        'id' => $d['id_alias']
      ]);
      $alias['id'] = $d['id'];
      $alias['result'] = $d['result'];
      $alias['id_alias'] = $d['id_alias'];
      return $alias;
    }
    return null;
  }

  /**
   * Returns the result of an observer's request from its UID.
   *
   * @param $id
   * @return false|int|string
   */
  public function get_result($id)
  {
    if ( $this->check() ){
      return $this->db->get_one(<<<MYSQL
SELECT IFNULL(ro.`result`, o.`result`)
FROM bbn_observers AS o
  LEFT JOIN bbn_observers AS ro
    ON ro.id = o.id_alias
WHERE o.id = ?
MYSQL
        ,
        hex2bin($id));
    }
  }

  /**
   *
   *
   * @param string|null $id_user
   * @return array
   */
  public function get_list(string $id_user = null): array
  {
    $field = $id_user ? 'o.id_user' : 'public';
    $sql = <<<MYSQL
SELECT o.id, o.id_alias,
IFNULL(ro.request, o.request) AS request, IFNULL(ro.params, o.params) AS params,
IFNULL(ro.id_user, o.id_user) AS id_user, IFNULL(ro.public, o.public) AS public,
IFNULL(ro.frequency, o.frequency) AS frequency, IFNULL(ro.result, o.result) AS result,
IFNULL(ro.next, o.next) AS next
FROM bbn_observers AS o
  LEFT JOIN bbn_observers AS ro
    ON o.id_alias = ro.id
WHERE $field = ?
AND ((o.next < NOW() AND o.next IS NOT NULL)
OR (ro.next < NOW() AND ro.next IS NOT NULL))
MYSQL;
    return $this->db->get_rows($sql, $id_user ? hex2bin($id_user) : 1);
  }

  /**
   * Deletes the given observer for the current user
   * @param string $id
   * @return int
   */
  public function user_delete($id): int
  {
    if ( property_exists($this, 'user') && $this->check() ){
      return $this->db->delete('bbn_observers', ['id' => $id, 'id_user' => $this->user]);
    }
    return 0;
  }

  /**
   * Checks the observers, execute their requests every given interval, it will stop when it finds differences in the
   * results, and returns the observers to be updated (meant to be executed from a cron task).
   *
   * @return array
   */
  public function observe()
  {
    if ( $this->check() ){
      $sql = <<<MYSQL
  SELECT o.id, o.id_user, o.request, o.params,
  GROUP_CONCAT(HEX(aliases.id) SEPARATOR ',') AS aliases,
  GROUP_CONCAT(HEX(aliases.id_user) SEPARATOR ',') AS users,
  GROUP_CONCAT(aliases.result SEPARATOR ',') AS results
  FROM bbn_observers AS o
    LEFT JOIN bbn_observers AS aliases
      ON aliases.id_alias = o.id
  WHERE o.id_alias IS NULL
  AND o.next < NOW()
  GROUP BY o.id
  HAVING COUNT(aliases.id) > 0
  OR o.id_user IS NOT NULL
MYSQL;

      $diff = [];
      //MAX: 2000
      $this->db->query('SET @@group_concat_max_len = ?', 2000 * 32);
      foreach ( $this->db->get_rows($sql) as $d ){
        $aliases = $d['aliases'] ? array_map('strtolower', explode(',', $d['aliases'])) : [];
        $users = $d['users'] ? array_map('strtolower', explode(',', $d['users'])) : [];
        $results = $d['results'] ? explode(',', $d['results']) : [];
        if ( \bbn\str::is_json($d['request']) ){
          $d['request'] = json_decode($d['request'], true);
          $real_result = $this->_exec_array($d['request']);
        }
        else{
          $real_result = $this->_exec($d['request'], $d['params']);
        }
        $db_result = $this->get_result($d['id']);
        // Only if users are attached to the
        echo '+';
        if ( $real_result !== $db_result ){
          $this->db->update('bbn_observers', ['result' => $real_result], ['id' => $d['id']]);
          if ( $d['id_user'] ){
            if ( !isset($diff[$d['id_user']]) ){
              $diff[$d['id_user']] = [];
            }
            $diff[$d['id_user']][] = [
              'id' => $d['id'],
              'result' => $real_result
            ];
          }
        }
        foreach ( $aliases as $i => $a ){
          if ( $real_result !== $results[$i] ){
            $this->db->update('bbn_observers', ['result' => $real_result], ['id' => $a]);
            $t = $users[$i];
            if ( !isset($diff[$t]) ){
              $diff[$t] = [];
            }
            $diff[$t][] = [
              'id' => $a,
              'result' => $real_result
            ];
          }
        }
        $this->_update_next($d['id']);
      }
      echo '.';
      $this->db->flush();
      if ( count($diff) ){
        bbn\x::dump('Returning diff!', $diff);
        return $diff;
      }
      if ( ob_get_contents() ){
        ob_end_flush();
      }
      return true;
    }
    bbn\x::dump('Canceling observer: '.date('H:i:s Y-m-d'));
    return false;
  }
}