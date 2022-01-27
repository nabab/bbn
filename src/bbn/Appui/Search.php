<?php

namespace bbn\Appui;

use bbn\Db;
use bbn\X;
use bbn\User;
use bbn\Tpl;
use bbn\Models\Tts\Cache;
use bbn\Models\Tts\Dbconfig;
use bbn\Mvc\Model;
use bbn\Util\Timer;
use Opis\Closure\SerializableClosure;

class Search
{
  use Dbconfig, Cache;

  /**
   * @var Db
   */
  protected Db $db;

  /**
   * @var User
   */
  protected User $user;

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
  protected int $time_limit = 50;

  /**
   * @var array
   */
  protected static array $functions = [];


  public function __construct(Model $model, array $cfg = [])
  {
    //$this->ctrl   = $ctrl;
    // $ctrl->getCustomModelGroup('', 'appui-search'), $ctrl->data['value'], $search->get($ctrl->data['value'])
    $this->db     = Db::getInstance();
    $this->user   = User::getInstance();

    if (!$this->db) {
      throw new \Exception('Db instance cannot be found!');
    }

    if (!$this->user) {
      throw new \Exception(X::_('User is not logged in!'));
    }

    $this->_init_class_cfg($cfg);
    $this->cacheInit();
    $this->timer      = new Timer();
    $model->getCustomModelGroup('', 'appui-search');
  }

  /**
   * Serialize and return all saved functions.
   *
   * ```php
   * // (array) ['main' => ['fn' => 'Opis\Closure\SerializableClosure', 'signature' => 'd608a60ab1788be218cc7424bc6e1128', 'file' => 'path/to']]
   * ```
   *
   * @return array
   * @throws \Exception
   */
  protected function getSearchCfg(): array
  {
    if ($cached_data = $this->cacheGet($this->cfg_cache_name, __FUNCTION__)) {
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

      $this->cacheSet($this->cfg_cache_name, __FUNCTION__, $result);
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
                $tmp['cfg'] = X::mergeArrays($tmp['cfg'], $alt);
                if (!empty($alt['score'])) {
                  $tmp['score'] = $alt['score'];
                }

                $result[] = X::mergeArrays($tmp, [
                  'file' => $item['file'] ?? null,
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
   * @throws \Exception
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

    $results = [
      'done' => [],
      'data' => []
    ];

    $this->timer->start('search');

    // If the search value has been done by the user before
    if (($previous_search_id = $this->getPreviousSearchId($search_value))
        && ($previous_search_results = $this->getPreviousSearchResults($previous_search_id))
    ) {
      $results_arch  = $this->class_cfg['arch']['search_results'];
      foreach ($previous_search_results as $r) {
        $item          = X::getRow($config_array, ['signature' => $r['signature']]);
        $processed_cfg = $this->db->processCfg($item['cfg']);
        // Get the results saved in the json field `result`
        if (($previous_result = json_decode($r[$results_arch['result']], true))
            && (X::hasProps($previous_result, $processed_cfg['fields']))
        ) {
          $cfg          = $item['cfg'];
          $cfg['where'] = [
            'logic' => 'AND',
            'conditions' => [
              $processed_cfg['filters'],
              [
                'conditions' => array_map(
                  function ($value, $key) {
                    return [
                      'field' => $key,
                      'operator' => '=',
                      'value' => $value
                    ];
                  },
                  $previous_result, array_keys($previous_result)
                )
              ]
            ]
          ];

          if ($add_to_top = $this->db->rselect($cfg)) {
            $results['data'] = array_merge($results['data'], [$add_to_top]);
          }
        }
      }
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
      X::log($item, 'search');
      $item['cfg']['limit'] = $limit - count($results['data']);
      if ($search_results = $this->db->rselectAll($item['cfg'])) {
        array_walk($search_results, function (&$a) use ($item) {
          $a['score'] = $item['score'];
          if (!empty($item['component'])) {
            $a['component'] = $item['component'];
          }

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
        $results['data'] = array_merge($results['data'], $search_results);

        if (count($search_results) === $item['cfg']['limit']) {
          $config_array[$i]['cfg']['start'] += $item['cfg']['limit'];
          // So the loop doesn't go on
          $num_cfg = $i;
        }
      }

      if ($this->timer->measure('search') > ($this->time_limit / 1000)) {
        // If time limit has passed then return the result and the index of the next step
        $this->timer->stop('search');
        if (isset($config_array[$i + 1])) {
          $results['next_step'] = $i + 1;
        }

        break;
      }
    }

    $this->cacheSet($this->user->getId(), $cache_name, $config_array);
    return $results;
  }


  /**
   * @param string $search_value
   * @return mixed
   */
  protected function getPreviousSearchId(string $search_value)
  {
    return $this->db->selectOne([
      'table' => $this->class_table,
      'fields' => [$this->fields['id']],
      'where' => [
        [
          'field' => $this->fields['id_user'],
          'operator' => '=',
          'value' => $this->user->getId()
        ],
        [
          'field' => $this->fields['value'],
          'operator' => '=',
          'value' => $search_value
        ]
      ]
    ]);
  }

  /**
   * @param string $search_value
   * @return mixed|null
   */
  protected function saveSearch(string $search_value)
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
   * @param string $id_search
   * @param string $signature
   * @return array|null
   */
  protected function getPreviousSearchResults(string $id_search, string $signature = '')
  {
    $filter = [
      $this->class_cfg['arch']['search_results']['id_search'] => $id_search
    ];
    if (!empty($signature)) {
      $filter[$this->class_cfg['arch']['search_results']['signature']] = $signature;
    }

    return $this->db->rselectAll($this->class_cfg['tables']['search_results'], [], $filter);
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
    catch (\Exception $e) {
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
