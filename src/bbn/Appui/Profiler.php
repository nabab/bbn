<?php
namespace bbn\Appui;

/**
 * Undocumented class
 */
use bbn\X;
use bbn\Mvc;
use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use bbn\Util\Timer;
use bbn\Appui\Grid;


/**
 * Class chat
 * @package bbn\Appui
 */
class Profiler extends DbCls
{
  use DbActions;

  protected $is_started = false;

  protected $chrono;

  protected static $delay = 2;

  /** @var array */
  protected static $default_class_cfg = [
    'table' => 'bbn_profiler',
    'tables' => [
      'bbn_profiler' => 'bbn_profiler'
    ],
    'arch' => [
      'bbn_profiler' => [
        'id' => 'id',
        'id_user' => 'id_user',
        'url' => 'url',
        'time' => 'time',
        'length' => 'length',
        'content' => 'content'
      ]
    ]
  ];

  public static function setDelay(int $delay)
  {
    self::$delay = $delay;
  }

  /**
   * Constructor.
   *
   * @param Db $db The db connection
   */
  public function __construct(Db $db)
  {
    parent::__construct($db);
    $this->chrono = new Timer();
    $this->initClassCfg(self::$default_class_cfg);
  }


  /**
   * Starting the profiling.
   *
   * @return bool
   */
  public function start(bool $force = false): bool
  {
    if ($this->check() && !$this->is_started) {
      $c = &$this->class_cfg['arch']['bbn_profiler'];
      $last = $this->db->selectOne(
        $this->class_table,
        "MAX(`$c[time]`)"
      );
      if ($force || !$last || (time() - strtotime($last) > self::$delay)) {
        $this->chrono->start();
        if (function_exists('tideways_xhprof_enable')) {
          tideways_xhprof_enable(
            TIDEWAYS_XHPROF_FLAGS_MEMORY | 
            TIDEWAYS_XHPROF_FLAGS_CPU
          );
          $this->is_started = true;
          return true;
        }
        elseif (function_exists('xhprof_enable')) {
          xhprof_enable(
            XHPROF_FLAGS_MEMORY | 
            XHPROF_FLAGS_CPU
          );
          $this->is_started = true;
          return true;
        }
      }
    }

    return false;
  }


  /**
   * Finishing the profiling and inserting profile in DB.
   *
   * @param Mvc $mvc
   *
   * @return bool
   */
  public function finish(Mvc $mvc): bool
  {
    if ($this->is_started && $this->check()) {
      $this->is_started = false;
      $data = function_exists('tideways_xhprof_disable') ? tideways_xhprof_disable() : xhprof_disable();
      $c = &$this->class_cfg['arch']['bbn_profiler'];
      return (bool)$this->db->insert(
        $this->class_table,
        [
          $c['id_user'] => $mvc->inc->user->getId(),
          $c['url'] => $mvc->getRequest(),
          $c['time'] => date('Y-m-d-H:i:s'),
          $c['length'] => $this->chrono->stop(),
          $c['content'] => serialize($data)
        ]
      );
    }

    return false;
  }


  public function get(string $id): ?array
  {
    if ($this->check()) {
      $c   = &$this->class_cfg['arch']['bbn_profiler'];
      $row = $this->db->rselect($this->class_table, [], [$c['id'] => $id]);
      if ($row) {
        $content       = unserialize($row['content']);
        unset($row['content']);

        $res         = $row;
        $res['data'] = [];
        foreach ($content as $fn => $data) {
          if (str_contains($fn, '==>')) {
            [
              $data['parent'],
              $data['child']
            ] = X::split($fn, '==>');
          }
          else {
            $data['parent'] = $fn;
            $data['child']  = '';
          }
          $data['mem_na'] = $data['mem.na'] ?? '';
          $data['mem_nf'] = $data['mem.nf'] ?? '';
          $data['mem_aa'] = $data['mem.aa'] ?? '';
          if (isset($data['mem.na'])) {
            unset($data['mem.na']);
          }

          if (isset($data['mem.nf'])) {
            unset($data['mem.nf']);
          }

          if (isset($data['mem.aa'])) {
            unset($data['mem.aa']);
}
          $res['data'][] = $data;
        }

        return $res;
      }
    }

    return null;
  }


  public function getUrls(): ?array
  {
    if ($this->check()) {
      $c = &$this->class_cfg['arch']['bbn_profiler'];
      return $this->db->getColumnValues($this->class_table, $c['url']);
    }

    return null;
  }


  public function getList(array $data): ?array
  {
    if ($this->check()) {
      $c = $this->class_cfg['arch']['bbn_profiler'];
      unset($c['content']);
      $data['limit'] = isset($data['limit']) && is_int($data['limit']) ? $data['limit'] : 50;
      $data['start'] = isset($data['start']) && is_int($data['start']) ? $data['start'] : 0;
      $grid = new Grid(
        $this->db,
        $data,
        [
          'table' => $this->class_table,
          'fields' => $c,
          'order' => [[
            'field' => 'time',
            'dir' => 'DESC'
          ]]
        ]
      );

      if ($grid->check()) {
        return $grid->getDatatable(true);
      }
    }
    return null;
  }


}
