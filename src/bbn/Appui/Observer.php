<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/06/2016
 * Time: 15:32
 */

namespace bbn\Appui;
use bbn;
use bbn\X;


/**
 * Class observer
 * @package bbn\Appui
 */
class Observer extends bbn\Models\Cls\Db
{
  /**
   * @var string The path where the observer txt file will be kept, and if deleted
   * the observe function will stop.
   */
  private static $path;

  protected static $default_frequency = 60;

  /**
   * @var bool Indicates if the funciton set_time_limit has been executed.
   */
  private static $time_done = false;

  protected $id_user;

  /**
   * Sets the time limit (to none) once and for all.
   */
  private static function setTimeLimit(): void
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
  private static function getFile(): string
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
    if ( is_string($request) && bbn\Str::isJson($request) ){
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
      $res = !empty($params) ? $this->db->getOne($request, array_map('base64_decode', Json_decode($params))) : $this->db->getOne($request);
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
      return md5((string)$this->db->selectOne($request));
    }
    return null;
  }

  private static function sanitizeParams(array $params = null)
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
        return $this->db->selectOne('bbn_observers', 'id', [
          'id_string' => $this->_get_id_string($request, $params)
        ]);
      }
      return $this->db->selectOne('bbn_observers', 'id', [
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
  private function _get_id_from_user(string $request, string $params = null): ?string
  {
    $r = null;
    if ($this->id_user && $this->check()) {
      $cfg = [
        'field' => 'o.id',
        'tables' => [
          'o' => 'bbn_observers'
        ],
        'join' => [
          [
            'table' => 'bbn_observers',
            'type' => 'left',
            'alias' => 'ro',
            'on' => [
              [
                'field' => 'o.id_alias',
                'operator' => 'eq',
                'exp' => 'ro.id'
              ]
            ]
          ]
        ],
        'where' => [
          [
            'field' => 'o.id_user',
            'value' => $this->id_user
          ], [
            'logic' => 'OR',
            'conditions' => [
              [
                'logic' => 'AND',
                'conditions' => [
                  [
                    'field' => 'o.request',
                    'value' => $request
                  ], [
                    'field' => 'o.params',
                    'operator' => $params ? 'like' : 'isnull'
                  ]
                ]
              ], [
                'logic' => 'AND',
                'conditions' => [
                  [
                    'field' => 'ro.request',
                    'value' => $request
                  ], [
                    'field' => 'ro.params',
                    'operator' => $params ? 'like' : 'isnull'
                  ]
                ]
              ]
            ]
          ]
        ]
      ];
      if ($params) {
        $cfg['where'][1]['conditions'][0]['conditions'][1]['value'] = $params;
        $cfg['where'][1]['conditions'][1]['conditions'][1]['value'] = $params;
      }
      $r = $this->db->selectOne($cfg);
    }
    return $r;
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
   * @todo Add the possibility for expression in db update/insert
   * 
   * @param $id
   * @return bbn\Db\Query|int
   */
  private function _update_next(string $id, int $frequency): bool
  {
    $next = date('Y-m-d H:i:s', Time() + $frequency);
    $r = (bool)$this->db->update(
      'bbn_observers', 
      ['next' => $next],
      [
        'id' => $id,
        'id_alias' => null
      ]
    );
    return $r;
  }

  /**
   * observer constructor.
   * @param bbn\Db $db
   */
  public function __construct(bbn\Db $db)
  {
    $user = \bbn\User::getInstance();
    $this->id_user = $user ? $user->getId() : null;
    if (defined('BBN_EXTERNAL_USER_ID') && ($this->id_user === BBN_EXTERNAL_USER_ID)) {
      $this->id_user = null;
    }
    parent::__construct($db);
  }

  /**
   * Confronts the current result with the one kept in database.
   *
   * @param $id
   * @return bool
   */
  public function checkResult($id)
  {
    if ( $d = $this->get($id) ){
      $t = new bbn\Util\Timer();
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
  public function add(array $cfg, $check_result = true): ?string
  {
    if (
      $this->id_user &&
      (null !== $cfg['request']) &&
      $this->check()
    ){
      X::log($cfg, 'observers');
      $t = new bbn\Util\Timer();
      $t->start();
      if ( is_string($cfg['request']) ){
        $params = self::sanitizeParams($cfg['params'] ?? []);
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
      X::log([$id_alias, $this->db->last(), $request, $params], 'observers');
      //die(var_dump($id_alias, $this->db->last(), $request, $params));
      // If it is a public observer it will be the id_alias and the main observer
      if (
        !$id_alias &&
        !empty($cfg['public']) &&
        $this->db->insertIgnore('bbn_observers', [
          'request' => $request,
          'params' => $params ?: null,
          'name' => $cfg['name'] ?? null,
          'frequency' => empty($cfg['frequency']) ? self::$default_frequency : $cfg['frequency'],
          'duration' => $duration,
          'id_user' => null,
          'public' => 1,
          'result' => $res
        ])
      ){
        $id_alias = $this->db->lastId();
      }
      // Getting the ID of the observer corresponding to current user
      if ( $id_obs = $this->_get_id_from_user($request, $params) ){
        if ($check_result) {
          $this->checkResult($id_obs);
        }
        return $id_obs;
      }
      else if ( $id_alias ){
        if ( $this->db->insertIgnore('bbn_observers', [
          'id_user' => $this->id_user,
          'public' => 0,
          'id_alias' => $id_alias,
          'next' => null,
          'result' => $res
        ]) ){
          return $this->db->lastId();
        }
      }
      else{
        if ( $this->db->insertIgnore('bbn_observers', [
          'request' => $request,
          'params' => $params ?: null,
          'name' => $cfg['name'] ?? null,
          'duration' => $duration,
          'id_user' => $this->id_user,
          'public' => 0,
          'result' => $res
        ]) ){
          return $this->db->lastId();
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
    if ($this->check() &&
        ($d = $this->db->rselect('bbn_observers', [], [
          'id' => $id
        ]))
    ) {
      if ( !$d['id_alias'] ){
        return $d;
      }
      if ($alias = $this->db->rselect('bbn_observers', [], [
        'id' => $d['id_alias']
      ])) {
        $alias['id'] = $d['id'];
        $alias['result'] = $d['result'];
        $alias['id_alias'] = $d['id_alias'];
        return $alias;
      }
    }

    return null;
  }

  /**
   * Returns the result of an observer's request from its UID.
   *
   * @param $id
   * @return false|int|string
   */
  public function getResult($id, bool $now = false): ?string
  {
    $r = null;
    if ($this->check()) {
      if ($now && ($o = $this->get($id))) {
        return $this->_exec($o['request'], $o['params']);
      }
      $r = $this->db->selectOne(
        [
          'tables' => ['o' => 'bbn_observers'],
          'field' => 'IFNULL(ro.`result`, o.`result`)',
          'join' => [
            [
              'table' => 'bbn_observers',
              'alias' => 'ro',
              'type' => 'LEFT',
              'on' => [
                [
                  'field' => 'o.id_alias',
                  'operator' => '=',
                  'exp' => 'ro.id'
                ]
              ],
              'where' => [
                'o.id' => $id
              ]
            ]
          ]
        ]
      );
    }
    return $r;
  }

  /**
   *
   *
   * @param string|null $id_user
   * @return array
   */
  public function getList(string $id_user = null): array
  {
    $field = $id_user ? 'o.id_user' : 'public';
    $now = date('Y-m-d H:i:s');
    return $this->db->rselectAll([
      'tables' => ['o' => 'bbn_observers'],
      'fields' => [
        'o.id', 'o.id_alias',
        'request' => 'IFNULL(ro.request, o.request)',
        'params' => 'IFNULL(ro.params, o.params)',
        'frequency' => 'IFNULL(ro.frequency, o.frequency)',
        'result' => 'IFNULL(ro.result, o.result)',
        'next' => 'IFNULL(ro.next, o.next)'
      ],
      'join' => [
        [
          'table' => 'bbn_observers',
          'type' => 'left',
          'alias' => 'ro',
          'on' => [
            [
              'field' => 'o.id_alias',
              'exp' => 'ro.id'
            ]
          ]
        ]
      ],
      'where' => [
        [
          'field' => $id_user ? 'o.id_user' : 'o.public',
          'operator' => '=',
          'value' => $id_user ?: 1
        ], [
          'logic' => 'OR',
          'conditions' => [
            [
              'logic' => 'AND',
              'conditions' => [
                [
                  'field' => 'o.next',
                  'operator' => '<',
                  'value' => $now
                ], [
                  'field' => 'o.next',
                  'operator' => 'isnotnull'
                ]
              ]
            ], [
              'logic' => 'AND',
              'conditions' => [
                [
                  'field' => 'ro.next',
                  'operator' => '<',
                  'value' => $now
                ], [
                  'field' => 'ro.next',
                  'operator' => 'isnotnull'
                ]
              ]
            ]
          ]
        ]
      ]
    ]);
  }

  /**
   * Deletes the given observer for the current user
   * @param string $id
   * @return int
   */
  public function userDelete($id): int
  {
    if ( property_exists($this, 'user') && $this->check() ){
      return $this->db->delete('bbn_observers', ['id' => $id, 'id_user' => $this->user]);
    }
    return 0;
  }

  protected function isUserActive($id_user, $delay = 120): bool
  {
    $max = $this->db->selectOne('bbn_users_sessions', 'MAX(bbn_users_sessions.last_activity)', ['id_user' => $id_user]);
    return $max && (strtotime($max) > (time() - $delay));
  }

  protected function checkObserver($row): bool
  {
    if ($row['id_user'] && (!defined('BBN_USER_EXTERNAL_ID') || ($row['id_user'] !== BBN_EXTERNAL_USER_ID)) && !$this->isUserActive($row['id_user'])) {
      $this->db->delete('bbn_observers', ['id' => $row['id']]);
      return false;
    }
    else if ($tmp = $this->db->rselectAll('bbn_observers', ['id', 'id_user'], ['id_alias' => $row['id']])) {
      $aliases = [];
      foreach ($tmp as $t){
        if (!$this->isUserActive($t['id_user'])) {
          $this->db->delete('bbn_observers', ['id' => $t['id']]);
        }
        else{
          $aliases[] = $t;
        }
      }
      if (!count($aliases)) {
        $this->db->delete('bbn_observers', ['id' => $row['id']]);
        return false;
      }
    }
    return true;
  }

  protected function deleteOld()
  {
    $sql = <<<SQL
DELETE o1
FROM bbn_observers AS o1
  LEFT JOIN bbn_observers AS o2
    ON o2.id_alias = o1.id
WHERE o2.id IS NULL
AND o1.id_user IS NULL
SQL;
    $r = $this->db->query($sql);
    return $r;
  }

  /**
   * Checks the observers, execute their requests if interval is reached, it will stop when it finds differences in the
   * results, and returns the observers to be updated (meant to be executed from a cron task), indexed by id_user.
   *
   * @return array
   */
  public function observe()
  {
    if ( $this->check() ){
      $now = date('Y-m-d H:i:s');
      $rows = $this->db->rselectAll([
        'table' => 'bbn_observers',
        'fields' => ['id', 'id_user', 'request', 'params', 'result', 'frequency'],
        'where' => [
          'conditions' => [
            [
              'field' => 'id_alias',
              'operator' => 'isnull'
            ], [
              'field' => 'next',
              'operator' => '<',
              'value' => $now
            ]
          ]
        ]
      ]);
      $diff = [];
      $timer = new \bbn\Util\Timer();
      foreach ( $rows as $d ){
        if ($this->checkObserver($d)) {
          // Aliases are the IDs of the observers aliases of the current row
          $timer->start('exec');
          $aliases = $this->db->rselectAll('bbn_observers', ['id', 'id_user', 'request', 'params', 'result'], ['id_alias' => $d['id']]);
          if ( \bbn\Str::isJson($d['request']) ){
            $d['request'] = json_decode($d['request'], true);
            $real_result = $this->_exec_array($d['request']);
          }
          else{
            $real_result = $this->_exec($d['request'], $d['params']);
          }
          X::log([$timer->stop('exec'), $this->db->last()], 'obs_request');
          // If the result is different we update the table
          if ( $real_result !== $d['result'] ){
            echo '+';
            $this->db->update('bbn_observers', ['result' => $real_result], ['id' => $d['id']]);
            // And if a user is attached to the observer we add it to the result
            if ( $d['id_user'] ){
              if ( !isset($diff[$d['id_user']]) ){
                $diff[$d['id_user']] = [];
              }
              $diff[$d['id_user']][] = [
                'id' => $d['id'],
                'result' => $real_result
              ];
            }
            // For each alias we add the entry for the corresponding user...
            foreach ( $aliases as $a ){
              // ...If the result differs
              if ( $real_result !== $a['result'] ){
                $this->db->update('bbn_observers', ['result' => $real_result], ['id' => $a['id']]);
                if ( !isset($diff[$a['id_user']]) ){
                  $diff[$a['id_user']] = [];
                }
                $diff[$a['id_user']][] = [
                  'id' => $a['id'],
                  'result' => $real_result
                ];
              }
            }
          }
        }
        // And we update the next time of execution
        $this->_update_next($d['id'], $d['frequency']);
      }
      echo '.';
      $this->deleteOld();
      $this->db->flush();
      if ( count($diff) ){
        bbn\X::dump('Returning diff!', $diff);
        return $diff;
      }
      if ( ob_get_contents() ){
        ob_end_flush();
      }
      return true;
    }
    bbn\X::dump('Canceling observer: '.date('H:i:s Y-m-d'));
    return false;
  }
}