<?php

namespace bbn\Appui;

use bbn\Db;
use bbn\File\Dir;
use bbn\Models\Tts\Cache;
use bbn\Models\Tts\Dbconfig;
use bbn\Mvc;
use bbn\Mvc\Controller;
use bbn\User;
use bbn\X;

class Search
{
  use Dbconfig, Cache;

  protected Db $db;

  protected User $user;

  protected Controller $ctrl;

  protected string $cache_name = 'search_content';

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

  protected array $search_cfg = [];

  protected ?array $class_cfg;


  public function __construct(Controller $ctrl, array $cfg = [])
  {
    $this->ctrl = $ctrl;
    $this->db   = Db::getInstance();
    $this->user = User::getInstance();

    $this->_init_class_cfg($cfg);
    $this->cacheInit();
    $this->search_cfg = $this->getSearchCfg();
  }

  /**
   * @return array|null
   * @throws \Exception
   */
  protected function getSearchCfg(): ?array
  {
    if ($cached_data = $this->cacheGet($this->cache_name, __FUNCTION__)) {
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
      $this->cacheSet($this->cache_name, __FUNCTION__, $result);
    }

    return $result;
  }

  /**
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
        if ($content = $this->ctrl->getModel($model)) {
          $result[] = array_merge(['path' => $file], $content);
        }
      }
    }

    X::sortBy($result, 'score', 'DESC');

    return $result;
  }

  protected function getPluginsSearchCfg()
  {
    $result = [];

    foreach ($this->ctrl->getPlugins() as $plugin) {
      if ((strpos('appui-search', $plugin['url'] . '/') === 0) || ($plugin['url'] === 'appui-search')) {
        continue;
      }


    }

    return $result;
  }

  public function get(string $search_value)
  {
    $result = [];
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