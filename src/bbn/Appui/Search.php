<?php

namespace bbn\Appui;

use Exception;
use bbn\Db;
use bbn\X;
use bbn\User;
use bbn\User\Permissions;
use bbn\Tpl;
use bbn\Models\Cls\Basic;
use bbn\Models\Tts\Cache;
use bbn\Models\Tts\Dbconfig;
use bbn\Mvc\Model;
use bbn\Util\Timer;
use Opis\Closure\SerializableClosure;

class Search extends Basic
{
  use Dbconfig, Cache;
  use \bbn\Models\Tts\Optional;

  /**
   * @var Db
   */
  protected Db $db;

  /**
   * @var User
   */
  protected User $user;

  /**
   * @var Permissions
   */
  protected Permissions $perm;

  /**
   * @var Model
   */
  protected Model $model;

  /**
   * @var string
   */
  protected string $cfg_cache_name = 'search_content';

  /**
   * @var string
   */
  protected string $search_cache_name = 'search_%s';

  /**
   * @var array
   */
  protected static $default_class_cfg = [
    'table' => 'bbn_search',
    'tables' => [
      'search' => 'bbn_search',
      'search_results' => 'bbn_search_results'
    ],
    'arch' => [
      'search' => [
        'id' => 'id',
        'id_user' => 'id_user',
        'value' => 'value',
        'num' => 'num',
        'last' => 'last'
      ],
      'search_results' => [
        'id' => 'id',
        'id_search' => 'id_search',
        'num' => 'num',
        'last' => 'last',
        'signature' => 'signature',
        'result' => 'result'
      ]
    ]
  ];

  /**
   * @var array|null
   */
  protected array $search_cfg = [];

  /**
   * @var Timer
   */
  protected Timer $timer;

  /**
   * Time limit in milliseconds for queries.
   *
   * @var int
   */
  protected int $time_limit = 20;

  /**
   * @var array
   */
  protected static array $functions = [];


  public function __construct(Model $model, array $models = [], array $cfg = [])
  {
    //$this->ctrl   = $ctrl;
    // $ctrl->getCustomModelGroup('', 'appui-search'), $ctrl->data['value'], $search->get($ctrl->data['value'])
    $this->db     = Db::getInstance();
    $this->user   = User::getInstance();
    $this->perm   = Permissions::getInstance();

    if (!$this->db) {
      throw new Exception('Db instance cannot be found!');
    }

    if (!$this->user) {
      throw new Exception(X::_('User is not logged in!'));
    }

    $this->_init_class_cfg($cfg);
    $this->cacheInit();
    self::optionalInit();
    $this->timer      = new Timer();
    if (empty($models)
      &&($def = $this->getOption('default'))
      && !empty($def['id_alias'])
    ) {
      $models = \array_map(fn($m) => $m['alias'] ?? [], $this->getOptions($def['id_alias']) ?: []);
    }
    else if (empty($models)) {
      try {
        $model->getCustomModelGroup('', 'appui-search');
      }
      catch (Exception $e) {}

      foreach ($model->getPlugins() as $pi) {
        try {
          $model->getSubpluginModelGroup('', $pi['name'], 'appui-search');
        }
        catch (Exception $e) {}
      }
      return;
    }
    else {
      foreach ($models as $i => $m) {
        if (\bbn\Str::isUid($m)
          && ($o = $this->getOption($m))
        ) {
          $models[$i] = $o;
        }
        else {
          unset($models[$i]);
        }
      }
    }
    if (!empty($models)) {
      foreach ($models as $m) {
        if ($this->perm->has($m['id'], 'options')
          && isset($m['plugin'])
          && !empty($m['filename'])
        ) {
          if (empty($m['plugin'])) {
            try {
              $model->getPluginModel($m['filename'], [], $model->pluginUrl('appui-search'));
            }
            catch (Exception $e) {}
          }
          else {
            try {
              $model->getSubpluginModel($m['filename'], [], $m['plugin'], 'appui-search');
            }
            catch (Exception $e) {}
          }
        }
      }
    }
  }

  /**
   * Serialize and return all saved functions.
   *
   * ```php
   * // (array) ['main' => ['fn' => 'Opis\Closure\SerializableClosure', 'signature' => 'd608a60ab1788be218cc7424bc6e1128', 'file' => 'path/to']]
   * ```
   *
   * @return array
   * @throws Exception
   */
  protected function getSearchCfg(): array
  {
    if ($cached_data = $this->cacheGet($this->cfg_cache_name, __FUNCTION__.'_'.\md5(\json_encode(self::$functions)))) {
      return $cached_data;
    }

    $result = [];

    if (!empty(self::$functions)) {
      foreach (self::$functions as $plugin => $items) {
        if (!is_array($items)) {
          continue;
        }

        foreach ($items as $item) {
          if (!empty($item['fn']) && is_callable($item['fn'])) {
            if (!isset($result[$plugin])) {
              $result[$plugin] = [];
            }

            $result[$plugin][] = array_merge(
              $item, [
                'fn' => $this->serializeFunction($item['fn'])
              ]
            );
          }
        }
      }

      $this->cacheSet($this->cfg_cache_name, __FUNCTION__.'_'.\md5(\json_encode(self::$functions)), $result);
    }

    return $result ?? [];
  }

  /**
   * Executes and returns the content of the search config array.
   *
   * @param string $search_value
   * @return array
   */
  protected function executeFunctions(string $search_value): array
  {
    $result = [];
    $i      = 0;

    foreach ($this->getSearchCfg() as $items) {
      if (!is_array($items)) {
        continue;
      }

      foreach ($items as $item) {
        if (!empty($item['fn'])
            && ($wrapper = unserialize($item['fn']))
            && ($wrapper instanceof SerializableClosure)
        ) {
          // Extract the closure object
          //$closure = $wrapper->getClosure();

          // Invoke the closure with the search string
          $content = $wrapper($search_value);

          if (is_array($content)) {
            if (!empty($content['regex'])) {
              if (!preg_match($content['regex'], $search_value)) {
                continue;
              }
            }

            if (!empty($content['alternates'])) {
              $alts = $content['alternates'];
              unset($content['alternates']);
              foreach ($alts as $i => $alt) {
                $tmp = $content;
                $rep = !empty($alt['replace']);
                if (isset($alt['replace'])) {
                  unset($alt['replace']);
                }
                $tmp['cfg'] = !empty($rep) ? \array_merge($tmp['cfg'], $alt) : X::mergeArrays($tmp['cfg'], $alt);
                if (!empty($alt['score'])) {
                  $tmp['score'] = $alt['score'];
                }
                $result[] = X::mergeArrays($tmp, [
                  'file' => $item['file'] ?? null,
                  'alternative' => $i + 1,
                  'signature' => ($item['signature'] ?? '') . '-' . ($i + 1)
                ]);
              }
            }

            $result[] = X::mergeArrays($content, [
              'file' => $item['file'] ?? null,
              'signature' => $item['signature'] ?? null
            ]);
            $i++;
          }
        }
      }
    }

    X::sortBy($result, 'score', 'DESC');

    return array_map(function ($item, $key) {
      return array_merge($item, ['num' => $key]);
    }, $result, array_keys($result));
  }

  /**
   * Launch the search and return the results.
   *
   * @param string $search_value
   * @param int $step
   * @return array
   * @throws Exception
   */
  public function get(string $search_value, int $step = 0, $start = 0, $limit = 100): array
  {
    $cache_name = sprintf($this->search_cache_name, $search_value);

    // Check if same search is saved for the user
    if (!($config_array = $this->cacheGet($this->user->getId(), $cache_name))) {

      // Execute all functions with the given search string
      $config_array = $this->executeFunctions($search_value);

      // Save it in cache
      $this->cacheSet($this->user->getId(), $cache_name, $config_array);
    }

    $this->timer->start('search');

    $results = [
      'done' => [],
      'data' => []
    ];
    $id_search = $this->getSearchId($search_value);
    // If the search value has been done by the user before
    if (!$step) {
      if ($id_search) {
        $this->updateSearch($search_value);
      }
      else {
        $id_search = $this->saveSearch($search_value);
      }

      if ($previous_search_results = $this->getPreviousSearchResults($id_search)) {
        foreach ($previous_search_results as $r) {
          $item = X::getRow($config_array, ['signature' => $r['signature']]);
          if (!$item) {
            /** @todo isn't there something to delete here ? */
            continue;
          }

          $processed_cfg = $this->db->processCfg($item['cfg']);
          // Get the results saved in the json field `result`
          $ok = true;
          foreach ($processed_cfg['fields'] as $alias => $field) {
            if (!array_key_exists(is_int($alias) ? $this->db->csn($field) : $alias, $r['result'])) {
              $ok = false;
              break;
            }
          }

          if ($ok && ($previous_result = $r['result'])) {
            $cp = $previous_result['component'];
            $hash = $previous_result['hash'];
            $score = $previous_result['score'];
            $signature = $previous_result['signature'];
            $match = $previous_result['match'];
            $url = $previous_result['url'];
            unset(
              $previous_result['component'],
              $previous_result['hash'],
              $previous_result['score'],
              $previous_result['signature'],
              $previous_result['match'],
              $previous_result['url']
            );
            $cfg          = $item['cfg'];
            $cfg['start'] = 0;
            $cfg['where'] = [
              'logic' => 'AND',
              'conditions' => [
                $processed_cfg['filters'],
                [
                  'conditions' => array_map(
                    function ($value, $key) use ($processed_cfg) {
                      $f = [
                        'field' => $processed_cfg['fields'][$key] ?? $key,
                        'operator' => is_null($value) ? 'isnull' : (is_string($value) ? 'LIKE' : '=')
                      ];

                      if (!is_null($value)) {
                        $f['value'] = $value;
                      }

                      return $f;
                    },
                    array_values($previous_result),
                    array_keys($previous_result)
                  )
                ]
              ]
            ];

            if ($add_to_top = $this->db->rselect($cfg)) {
              $add_to_top['component'] = $cp;
              $add_to_top['hash'] = $hash;
              $add_to_top['score'] = $score + ($r['num'] ?: 1) * 50;
              $add_to_top['signature'] = $signature;
              $add_to_top['match'] = $match;
              $add_to_top['url'] = $url;
              $results['data'][] = $add_to_top;
            }
          }
        }
      }
    }

    $results['id'] = $id_search;
    if (!$step && ($this->timer->measure('search') > ($this->time_limit / 1000))) {
      // If time limit has passed then return the result and the index of the next step
      $results['time'] = $this->timer->stop('search');
      $results['next_step'] = -1;
    }
    else {
      if ($step === -1) {
        $step = 0;
      }

      //X::ddump($config_array, "DDDD", $this->executeFunctions($search_value), $search_value, $this->search_cfg);
      $num_cfg = count($config_array);
      if (!$start && !$step) {
        array_walk($config_array, function ($a) {
          $a['cfg']['start'] = 0;
        });
      }

      for ($i = $step; $i < $num_cfg; $i++) {
        if (empty($config_array[$i]['cfg'])) {
          continue;
        }

        $item = $config_array[$i];
        $item['cfg']['limit'] = $limit - count($results['data']);
        if ($search_results = $this->db->rselectAll($item['cfg'])) {
          array_walk($search_results, function (&$a) use ($item) {
            if (!empty($item['component'])) {
              $a['component'] = $item['component'];
            }
            $b = $a;
            unset($b['match']);
            ksort($b);
            $a['hash']      = md5(json_encode($b));
            $a['score']     = $item['score'];
            $a['signature'] = $item['signature'];

            if (!empty($item['options'])) {
              $a['options'] = $item['options'];
            }

            if (!empty($item['url'])) {
              $a['url'] = Tpl::render($item['url'], $a);
            }

            if (!empty($item['action'])) {
              $a['action'] = $item['action'];
            }

          });

          foreach ($search_results as $s) {
            $row = X::find($results['data'], ['hash' => $s['hash']]);
            if (!empty($results['data'][$row])) {
              $results['data'][$row]['score'] += $s['score'];
            }
            else {
              $results['data'][] = $s;
            }
          }

          // There is certainly more
          if (count($search_results) === $item['cfg']['limit']) {
            if (!empty($config_array[$i]['cfg']['start'])) {
              $config_array[$i]['cfg']['start'] += $item['cfg']['limit'];
            }
            else {
              $config_array[$i]['cfg']['start'] = $item['cfg']['limit'];
            }
            // So the loop doesn't go on
            $num_cfg = $i;
          }
        }

        if ($this->timer->measure('search') > ($this->time_limit / 1000)) {
          // If time limit has passed then return the result and the index of the next step
          $results['time'] = $this->timer->stop('search');
          if (isset($config_array[$i + 1])) {
            $results['next_step'] = $i + 1;
          }

          break;
        }
      }
    }

    $this->cacheSet($this->user->getId(), $cache_name, $config_array);
    return $results;
  }


  /**
   * @param string $search_value
   * @return mixed
   */
  public function getSearchId(string $search_value)
  {
    return $this->db->selectOne($this->class_table, $this->fields['id'], [
      $this->fields['id_user'] => $this->user->getId(),
      $this->fields['value'] => $search_value
    ]);
  }


  /**
   * @param string $search_value
   * @return mixed
   */
  public function getSearchRow(string $search_value): ?array
  {
    return $this->db->rselect($this->class_table, [], [
      $this->fields['id_user'] => $this->user->getId(),
      $this->fields['value'] => $search_value
    ]);
  }


  /**
   * @param string $search_value
   * @return mixed|null
   */
  public function saveSearch(string $search_value)
  {
    $insert = $this->db->insert($this->class_table, [
      $this->fields['id_user'] => $this->user->getId(),
      $this->fields['value'] => $search_value,
      $this->fields['num'] => 1,
      $this->fields['last'] => date('Y-m-d H:i:s')
    ]);

    return $insert ? $this->db->lastId() : null;
  }


  /**
   * @param string $search_value
   * @return int
   */
  public function updateSearch(string $search_value): int
  {
    if ($row = $this->getSearchRow($search_value)) {
      return $this->db->update($this->class_table, [
        $this->fields['num'] => $row[$this->fields['num']] + 1,
        $this->fields['last'] => date('Y-m-d H:i:s')
      ], [
        $this->fields['id'] => $row[$this->fields['id']]
      ]);
    }

    return 0;
  }


  /**
   * Adds a result in the table when a result is selected.
   * 
   * @param string $id
   * @param array $data
   * @return int The number of affected rows (1 or 0)
   */
  public function setResult(string $id, array $data): int
  {
    if (!empty($data['signature'])
        && ($row = $this->db->rselect($this->class_table, [], [
          $this->fields['id'] => $id,
          $this->fields['id_user'] => $this->user->getId()
        ]))
    ) {
      $f =& $this->class_cfg['arch']['search_results'];
      $result = $this->db->rselect($this->class_cfg['tables']['search_results'], [$f['id'], $f['num']], [
        $f['id_search'] => $id,
        $f['signature'] => $data['signature']
      ]);
      if ($result) {
        return $this->db->update($this->class_cfg['tables']['search_results'], [
          $f['num'] => $result['num'] + 1,
          $f['last'] => date('Y-m-d H:i:s')
        ], [
          $f['id'] => $result['id']
        ]);
      }
      else {
        return $this->db->insert($this->class_cfg['tables']['search_results'], [
          $f['id_search'] => $id,
          $f['num'] => 1,
          $f['signature'] => $data['signature'],
          $f['result'] => serialize($data)
        ]);
      }
    }

    return 0;
  }


  /**
   * Retrieves the search IDs 
   *
   * @param string $id_search
   * @return array
   */
  public function getSimilarSearches(string $id_search): array
  {
    if ($value = $this->db->selectOne($this->class_cfg['table'], $this->fields['value'], [
      $this->fields['id'] => $id_search
    ])) {
      return $this->db->getColumnValues($this->class_cfg['table'], $this->fields['id'], [
        $this->fields['id_user'] => $this->user->getId(),
        [$this->fields['value'], 'startswith', $value]
      ]);
    }

    throw new Exception(X::_("Impossible to find the requested search ID"));
  }

  /**
   * @param string $id_search
   * @param string $signature
   * @return array|null
   */
  protected function getPreviousSearchResults(string $id_search, string $signature = '')
  {
    $col = $this->class_cfg['arch']['search_results']['id_search'];
    $table = $this->class_cfg['tables']['search_results'];
    $filter = [$col => $id_search];
    if (!empty($signature)) {
      $filter[$this->class_cfg['arch']['search_results']['signature']] = $signature;
    }

    $res = $this->db->rselectAll($table, [], $filter);
    if ($others = $this->getSimilarSearches($id_search)) {
      foreach ($others as $o) {
        $filter[$col] = $o;
        if ($tmp = $this->db->rselectAll($this->class_cfg['tables']['search_results'], [], $filter)) {
          foreach ($tmp as $t) {
            //if (!X::getRow($res, ['hash' => $t['hash']])) {
              $res[] = $t;
            //}
          }
        }
      }
    }

    return array_map(function($a) {
      $a['result'] = unserialize($a['result']);
      return $a;
    }, $res);
  }


  /**
   * @param callable $function
   * @param string $plugin_name
   * @return array
   */
  public static function register(callable $function, string $plugin_name = 'main'): array
  {
    // Get how many parameters the closure has
    try {
      $parameters = (new \ReflectionFunction($function))->getParameters();
    }
    catch (Exception $e) {
      $parameters = ['search'];
    }

    // Add an empty string to every parameter of the closure for the hash
    $args = array_map(function () {
      return '';
    }, $parameters);

    if (!isset(self::$functions[$plugin_name])) {
      self::$functions[$plugin_name] = [];
    }

    $res =  [
      'fn' => $function,
      'signature' => \bbn\Cache::makeHash($function(...$args)),
      'file' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file']
    ];
    // Invoke the closure with the parameters set to empty string and return the results
    self::$functions[$plugin_name][] = $res;
    return $res;
  }


}
