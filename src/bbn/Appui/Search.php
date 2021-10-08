<?php

namespace bbn\Appui;

use bbn\Db;
use bbn\File\Dir;
use bbn\Models\Tts\Cache;
use bbn\Models\Tts\Dbconfig;
use bbn\Mvc;
use bbn\Mvc\Controller;
use bbn\User;
use bbn\Util\Timer;
use bbn\X;
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
   * @var Controller
   */
  protected Controller $ctrl;

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
        'table' => 'table',
        'uid' => 'uid',
        'num' => 'num',
        'last' => 'last'
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
  protected int $time_limit = 300;


  public function __construct(Controller $ctrl, array $cfg = [])
  {
    $this->ctrl   = $ctrl;
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
    $this->search_cfg = $this->getSearchCfg();
    $this->timer      = new Timer();
  }

  /**
   * Parse and return all search config models.
   *
   * @return array|null
   * @throws \Exception
   */
  protected function getSearchCfg(): ?array
  {
    if ($cached_data = $this->cacheGet($this->cfg_cache_name, __FUNCTION__)) {
      return $cached_data;
    }

    $result = [];

    if ($main_cfg = $this->getMainAppSearchCfg()) {
      $result = array_merge($result, $main_cfg);
    }

    if ($plugins_cfg = $this->getPluginsSearchCfg()) {
      $result = array_merge($result, $plugins_cfg);
    }

    if (!empty($result)) {
      $result = array_map(function (callable $function) {
        return $this->serializeFunction($function);
      }, array_filter($result, function ($item) {
        return is_callable($item);
      }));

      $this->cacheSet($this->cfg_cache_name, __FUNCTION__, $result);
    }

    return $result;
  }

  /**
   * Parse and return all search config from main app.
   *
   * @return array|null
   * @throws \Exception
   */
  protected function getMainAppSearchCfg(): ?array
  {
    if (!$dir = Mvc::getPluginPath('appui-search')) {
      return null;
    }

    if (!$files = Dir::getFiles("{$dir}mvc/model")) {
      return null;
    }

    $result = [];

    foreach ($files as $file) {
      if (is_file($file)) {
        $model = Mvc::getPluginUrl('appui-search') . '/' . basename($file, '.php');
        if (($content = $this->ctrl->getModel($model)) && is_array($content) && !empty($content)) {
          $result[$file] = current($content);
        }
      }
    }

    return $result;
  }

  protected function getPluginsSearchCfg(): array
  {
    $result = [];

    foreach ($this->ctrl->getPlugins() as $plugin) {
      if ((strpos('appui-search', $plugin['url'] . '/') === 0) || ($plugin['url'] === 'appui-search')) {
        continue;
      }


    }

    return $result;
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

    foreach ($this->search_cfg as $file => $string) {
      if (($wrapper = @unserialize($string)) && $wrapper instanceof SerializableClosure) {
        // Extract the closure object
        $closure = $wrapper->getClosure();

        // Invoke the closure with the search string
        $content =  $closure($search_value);

        if (is_array($content)) {
          $result[] = array_merge($content, [
            'file' => $file,
            'num' => $i
          ]);
          $i++;
        }
      }
    }

    X::sortBy($result, 'score', 'DESC');

    return $result;
  }

  /**
   * Launch the search and return the results.
   *
   * @param string $search_value
   * @param int $step
   * @return array
   * @throws \Exception
   */
  public function get(string $search_value, int $step = 0): array
  {
    $cache_name = sprintf($this->search_cache_name, $search_value);

    // Check if same search is saved for the user
    if (!$config_array = $this->cacheGet($this->user->getId(), $cache_name)) {

      // Execute all functions with the given search string
      $config_array = $this->executeFunctions($search_value);

      // Save it in cache
      $this->cacheSet($this->user->getId(), $cache_name, $config_array);
    }

    $results = [
      'data' => []
    ];

    $this->timer->start('search');

    for ($i = $step; $i < count($config_array); $i++) {
      $item = $config_array[$i];

      if (empty($item['cfg'])) {
        continue;
      }

      if ($result = $this->db->rselectAll($item['cfg'])) {
        $results['data'] = array_merge($results['data'], $result);
      }

      if ($this->timer->measure('search') > $this->time_limit) {
        // If time limit has passed then return the result and the index of the next step
        $this->timer->stop('search');

        if (isset($config_array[$i + 1])) {
          $results['next_step'] = $i + 1;
        }

        break;
      }
    }

    return $results;

    // Check if the search valus has been done by the user before
    if ($previous_search_id = $this->getPreviousSearchId($search_value)) {
     if ($previous_search_results = $this->getPreviousSearchResults($previous_search_id)) {
       $results_arch = $this->class_cfg['arch']['search_results'];

       foreach ($previous_search_results as $item) {
         // Get the results from the saved table and uid
         $item_result = $this->db->rselect(
           $item[$results_arch['table']], [], [
           'id' => $item[$results_arch['uid']]
         ]);

         if ($item_result) {
          $result[] = $item_result;
         }
       }
     }

     // Update the search num and last columns
      $this->db->update($this->class_table, [
        'num' => 'num + 1',
        'last' => date('Y-m-d H:i:s')
      ], [
        $this->fields['id'] => $previous_search_id
      ]);
    }
    else {
      // If not then save the search
      $id_search = $this->saveSearch($search_value);

      // Execute the query here

      // Then save it in search_results table

      // Add it to the array
    }
  }

  /**
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

  protected function getPreviousSearchResults(string $id_search)
  {
    return $this->db->rselectAll($this->class_cfg, [], [
      $this->class_cfg['arch']['search_results']['id_search'] => $id_search
    ]);
  }
}