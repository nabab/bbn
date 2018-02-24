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

  /**
   * @var string The user's UID.
   */
  private $id_user;

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
    return self::$path.'appui-observer.txt';
  }

  /**
   * Executes a request (kept in the observer) and returns its (single) result.
   *
   * @param string $request
   * @param array|null $params
   * @return mixed
   */
  private function _exec(string $request, array $params = null)
  {
    if ( $this->check() ){
      $res = $params ? $this->db->get_one($request, $params) : $this->db->get_one($request);
      return md5((string)$res);
    }
    return null;
  }

  /**
   * Returns the ID of an observer with public = 1 and with similar request and params.
   *
   * @param string $request
   * @param array|null $params
   * @return null|string
   */
  private function _get_id(string $request, array $params = null):? string
  {
    if ( $this->check() ){
      return $this->db->select_one('bbn_observers', 'id', [
        'id_string' => $this->get_id_string($request, $params),
        'public' => 1
      ]);
    }
    return null;
  }

  /**
   * Returns the ID of an observer for the current user and with similar request and params.
   *
   * @param string $request
   * @param array|null $params
   * @return null|string
   */
  private function _get_user_id(string $request, array $params = null):? string
  {
    if ( $this->id_user && $this->check() ){
      $id_string = $this->get_id_string($request, $params);
      return $this->db->get_one(<<<MYSQL
        SELECT o.id
        FROM bbn_observers AS o
          LEFT JOIN bbn_observers AS ro
            ON o.id_alias = ro.id
        WHERE o.id_user = ?
        AND (
          o.id_string LIKE ?
          OR ro.id_string LIKE ?
        )
MYSQL
        ,
        hex2bin($this->id_user),
        $id_string,
        $id_string);
    }
    return null;
  }

  /**
   * Inserts a new observer in the table.
   *
   * @param string $request
   * @param array|null $params
   * @param int $duration
   * @param bool $public
   * @param string|null $name
   * @return null|string
   */
  private function _insert(string $request, array $params = null, int $duration = 30, bool $public = false, string
  $name = null):? string
  {
    if ( $this->check() ){
      if ( !$public ){
        return null;
      }
      $res = $this->_exec($request, $params);
      if ( $public && $this->db->insert('bbn_observers', [
        'request' => trim($request),
        'params' => $params ? json_encode($params) : null,
        'name' => $name,
        'id_user' => $public ? null : $this->id_user,
        'public' => $public ? 1 : 0,
        'result' => md5($res)
      ]) ){
        $id_alias = $this->db->last_id();
      }
    }
    return null;
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
    parent::__construct($db);
    if ( $usr = bbn\user::get_instance() ){
      $this->id_user = $usr->get_id();
    }

  }

  /**
   * Returns the unique string representing the request + the parameters (md5 of concatenated strings).
   *
   * @param string $request
   * @param array|null $params
   * @return string
   */
  public function get_id_string(string $request, array $params = null): string
  {
    return md5(trim($request).($params ? json_encode($params) : ''));
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
      if ( md5($res) !== $d['result'] ){
        $this->db->update('bbn_observers', [
          'result' => md5($res),
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
  public function add(array $cfg):? string
  {
    if ( $this->id_user  && (null !== $cfg['request']) ){
      $id_alias = false;
      // We perform the check after the query has been executed.
      if ( $this->check() ){
        // If it is a public observer it will be the id_alias and the main observer
        if (
          !empty($cfg['public']) &&
          !($id_alias = $this->_get_id($cfg['request'], $cfg['params'] ?? null))
        ){
          $t = new bbn\util\timer();
          $t->start();
          $res = $this->_exec($cfg['request'], $cfg['params'] ?? null);
          $duration = (int)ceil($t->stop() * 1000);
          if ( $this->db->insert('bbn_observers', [
            'request' => trim($cfg['request']),
            'params' => null === $cfg['params'] ? null : json_encode($cfg['params']),
            'name' => $cfg['name'] ?? null,
            'duration' => $duration,
            'id_user' => null,
            'public' => 1,
            'result' => $res
          ]) ){
            $id_alias = $this->db->last_id();
          }
        }
        if ( $id_obs = $this->_get_user_id($cfg['request'], $cfg['params']?? null) ){
          $this->check_result($id_obs);
          return $id_obs;
        }
        else if ( $id_alias ){
          if ( $this->db->insert('bbn_observers', [
            'id_user' => $this->id_user,
            'public' => 0,
            'id_alias' => $id_alias
          ]) ){
            return $this->db->last_id();
          }
        }
        else{
          $t = new bbn\util\timer();
          $t->start();
          $res = $this->_exec($cfg['request'], $cfg['params'] ?? null);
          $duration = (int)ceil($t->stop() * 1000);
          if ( $this->db->insert('bbn_observers', [
            'request' => trim($cfg['request']),
            'params' => null === $cfg['params'] ? null : json_encode($cfg['params']),
            'name' => $cfg['name'] ?? null,
            'duration' => $duration,
            'id_user' => $this->id_user,
            'public' => 1,
            'result' => $res
          ]) ){
            return $this->db->last_id();
          }
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
  public function get($id):? array
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
    $field = $id_user ? 'id_user' : 'public';
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
AND next < NOW()
MYSQL;
    return $this->db->get_rows($sql, $id_user ?: 1);
  }

  /**
   * Checks the observers, execute their requests every given interval, it will stop when it finds differences in the
   * results, and returns the observers to be updated (meant to be executed from a cron task).
   *
   * @return array
   */
  public function observe()
  {
    self::set_time_limit();
    if ( is_file(self::get_file()) ){
      unlink(self::get_file());
      sleep(5);
      if ( is_file(self::get_file()) ){
        die(bbn\x::dump('Another similar process is already running'));
      }
    }
    file_put_contents(self::get_file(), 'running');
    $sql = <<<MYSQL
SELECT o.id, o.id_user, GROUP_CONCAT(HEX(aliases.id) SEPARATOR ',') AS id_aliases
FROM bbn_observers AS o
  LEFT JOIN bbn_observers AS aliases
    ON aliases.id_alias = o.id
WHERE o.id_alias IS NULL
AND o.next < NOW()
GROUP BY o.id
MYSQL;

    $diff = [];
    while ( file_exists(self::get_file()) ){
      foreach ( $this->db->get_rows($sql) as $d ){
        bbn\x::dump('Really checking');
        if ( !$this->check_result($d['id']) ){
          $res = $this->get_result($d['id']);
          if ( $d['id_user'] ){
            if ( null === $diff[$d['id_user']] ){
              $diff[$d['id_user']] = [];
            }
            $diff[$d['id_user']][] = [
              'id' => $d['id'],
              'result' => $res,
            ];
          }
          if ( $d['id_aliases'] ){
            $aliases = explode(',', $d['id_aliases']);
            foreach ( $aliases as $a ){
              if ( $id_user = $this->db->select_one('bbn_observers', 'id_user', [
                'id' => $a
              ]) ){
                if ( null === $diff[$id_user] ){
                  $diff[$id_user] = [];
                }
                $diff[$id_user][] = [
                  'id' => strtolower($a),
                  'result' => $res,
                  'id_user' => $id_user
                ];
              }
            }
          }
        }
        $this->_update_next($d['id']);
      }
      bbn\x::dump('Sleeping and flushing');
      $this->db->flush();
      @ob_end_flush();
      sleep(1);
      if ( count($diff) ){
        bbn\x::dump('Returning diff!');
        unlink(self::get_file());
        return $diff;
      }
      @ob_end_flush();
    }
    bbn\x::dump('Canceling observer');
  }
}