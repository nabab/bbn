<?php
/*
 * Copyright (C) 2014 BBN
 *
 */

namespace bbn\Cron;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Db;
use bbn\Mvc;
use bbn\Models\Cls\Basic;
use bbn\Appui\Notification;
use bbn\Db\Enums\Errors;
use function in_array;
use function is_array;
use function defined;
/**
 * Class cron
 * @package bbn\Appui
 */
class Manager extends Basic
{
  use Config;
  use Filesystem;

  protected ?string $table = null;

  /**
   * Manager constructor.
   *
   * @param Db $db
   * @param array $cfg
   */
  public function __construct(
    private Db $db,
    array $cfg = []
  ) {
    if (Mvc::getDataPath() && $this->db->check()) {
      // It must be called from a plugin (appui-cron actually)
      //$this->path = BBN_DATA_PATH.'plugins/appui-cron/';
      $this->path  = Mvc::getDataPath('appui-cron');
      $this->table = "{$this->prefix}cron";
    }
  }


  /**
   * @return bool
   */
  public function check(): bool
  {
    return (bool)$this->db && $this->db->check();
  }


  /**
   * Returns the full row as an indexed array for the given CRON ID.
   *
   * @param string $id
   * @return null|array
   */
  public function getCron(string $id): ?array
  {
    if ($this->check() && ($data = $this->db->rselect($this->table, [], ['id' => $id]))) {
      $data['cfg'] = json_decode($data['cfg'], 1);
      return $data;
    }

    return null;
  }


  /**
   * @param $id_cron
   * @return bool
   */
  public function isTimeout($id_cron): bool
  {
    if ($this->check()
        && ($cron = $this->getCron($id_cron))
        && ($path = $this->getLogPath($cron))
        && is_file($path)
    ) {
      [$pid, $time] = X::split(file_get_contents($path), '|');
      return $time + $cron['cfg']['timeout'] < time();
    }

    return false;
  }


  /**
   * Writes in the given CRON row the next start time, the current as previous, and the new running status.
   *
   * @param string $id_cron
   * @return bool
   */
  public function start(string $id_cron): bool
  {
    $res = false;
    if ($this->check()
        && ($cron = $this->getCron($id_cron))
    ) {
      $enable = false;
      if ($this->db->isTriggerEnabled()) {
        $this->db->disableTrigger();
        $enable = true;
      }

      if ($this->db->update(
        $this->table, [
        'prev' => date('Y-m-d H:i:s'),
        'pid' => getmypid()
        ], [
        'id' => $id_cron,
        'pid' => null,
        'active' => 1
        ]
      )
      ) {
        $res = true;
      }

      if ($enable) {
        $this->db->enableTrigger();
      }
    }

    return $res;
  }


  /**
   * Writes in the given CRON row the duration and the new finished status.
   *
   * @param string $id_cron
   * @param string $res
   * @return bool
   */
  public function finish(string $id_cron, $res = '')
  {
    $res = false;
    if ($cron = $this->getCron($id_cron)) {
      if (!empty($cron['cfg']['frequency'])) {
        $time  = time();
        $start = date('Y-m-d H:i:s', $time);
        $next  = $this->getNextDate($cron['cfg']['frequency'], strtotime($cron['next'] ?: $start));
      }

      $enable   = false;
      $err_mode = $this->db->getErrorMode();
      $this->db->setErrorMode(Errors::E_CONTINUE);
      if ($this->db->isTriggerEnabled()) {
        $this->db->disableTrigger();
        $enable = true;
      }

      if ($this->db->update(
        $this->table, [
        'next' => $next ?? null,
        'pid' => null,
        'active' => isset($next) ? 1 : 0
        ], [
        'id' => $id_cron,
        'pid' => getmypid()
        ]
      )
      ) {
        $res = true;
      }

      if ($err_mode !== Errors::E_CONTINUE) {
        $this->db->setErrorMode($err_mode);
      }

      if ($enable) {
        $this->db->enableTrigger();
      }
    }

    return $res;
  }


  /**
   * Returns a SQL datetime for the next event given a frequency and a base timestamp.
   *
   * Frequency syntax:
   *   - 1 letter (y, m, w, d, h, i, s) + positive integer
   *   - Examples: "h1", "d2", "w1", "m3", "y1"
   *
   * @param string $frequency A string like "h1", "d2", etc.
   * @param int    $fromTime  Unix timestamp to count from (0 = now).
   * @return string|null      The next execution datetime in "Y-m-d H:i:s" or null on invalid input.
   */
  public function getNextDate(string $frequency, int $fromTime = 0): ?string
  {
    $frequency = trim($frequency);

    // Minimum is 2 chars: one letter + digits
    if ($frequency === '' || Str::len($frequency) < 2) {
      return null;
    }

    // First char is the unit, rest is the amount
    $unitChar = Str::changeCase(Str::sub($frequency, 0, 1), 'lower');
    $value    = Str::sub($frequency, 1);

    if (!Str::isNumber($value)) {
      // Keep old behavior: log but do not throw
      X::log($value, 'next_date');
      return null;
    }

    $interval = (int)$value;
    $unit = FrequencyUnit::tryFromCode($unitChar);

    if ($unit === null || $interval <= 0) {
      return null;
    }

    $now = time();
    if ($fromTime <= 0) {
      $fromTime = $now;
    }

    $year   = (int)date('Y', $fromTime);
    $month  = (int)date('n', $fromTime);
    $day    = (int)date('j', $fromTime);
    $hour   = (int)date('G', $fromTime);
    $minute = (int)date('i', $fromTime);
    $second = (int)date('s', $fromTime);

    // First candidate (one interval ahead)
    $test = match ($unit) {
      FrequencyUnit::Year   => mktime($hour, $minute, $second, $month,          $day,          $year + $interval),
      FrequencyUnit::Month  => mktime($hour, $minute, $second, $month + $interval, $day,       $year),
      FrequencyUnit::Week   => mktime($hour, $minute, $second, $month,          $day + 7*$interval, $year),
      FrequencyUnit::Day    => mktime($hour, $minute, $second, $month,          $day + $interval,   $year),
      FrequencyUnit::Hour   => mktime($hour + $interval, $minute, $second, $month, $day,       $year),
      FrequencyUnit::Minute => mktime($hour, $minute + $interval, $second, $month, $day,       $year),
      FrequencyUnit::Second => mktime($hour, $minute, $second + $interval, $month, $day,       $year),
    };
    $length = $test - $fromTime;

    // If somehow the test time is equal or before the base, don't continue
    if ($length <= 0) {
      return null;
    }

    // How many intervals do we need to skip to reach the future?
    $step = 0;
    if ($test < $now) {
      $diff = $now - $test;
      $step = (int)floor($diff / $length);
    }

    $candidate = $test;

    // Move forward in steps until we are in the future
    while ($candidate <= $now) {
      $step++;
      $candidate = match ($unit) {
        FrequencyUnit::Year   => mktime($hour, $minute, $second, $month,               $day,               $year + ($interval * $step)),
        FrequencyUnit::Month  => mktime($hour, $minute, $second, $month + ($interval*$step), $day,         $year),
        FrequencyUnit::Week   => mktime($hour, $minute, $second, $month,               $day + 7*($interval*$step), $year),
        FrequencyUnit::Day    => mktime($hour, $minute, $second, $month,               $day + ($interval*$step),   $year),
        FrequencyUnit::Hour   => mktime($hour + ($interval*$step), $minute, $second,   $month, $day,       $year),
        FrequencyUnit::Minute => mktime($hour, $minute + ($interval*$step), $second,   $month, $day,       $year),
        FrequencyUnit::Second => mktime($hour, $minute, $second + ($interval*$step),   $month, $day,       $year),
      };
    }

    return date('Y-m-d H:i:s', $candidate);
  }


  /**
   * Returns the whole row for the next CRON to be executed from now if there is any.
   *
   * @param null|string $id_cron
   * @return null|array
   */
  public function getNext(?string $id_cron = null): ?array
  {
    $conditions = [[
      'field' => 'next',
      'operator' => '<',
      'exp' => 'NOW()'
    ], [
      'field' => 'next',
      'operator' => 'isnotnull'
    ], [
      'field' => 'active',
      'value' => 1
    ]];
    if (Str::isUid($id_cron)) {
      $conditions[] = [
        'field' => 'id',
        'value' => $id_cron
      ];
    }

    if ($this->check()
        && ($data = $this->db->rselect(
          [
          'table' => $this->table,
          'fields' => [],
          'where' => [
          'conditions' => $conditions
          ],
          'order' => [[
          'field' => 'priority',
          'dir' => 'ASC'
          ], [
          'field' => 'next',
          'dir' => 'ASC'
          ]]
          ]
        ))
    ) {
      // Dans cfg: timeout, et soit: latency, minute, hour, day of month, day of week, Date
      $data['cfg'] = json_decode($data['cfg'], 1);
      return $data;
    }

    return null;
  }


  /**
   * @return array|null
   * @throws Exception
   */
  public function getRunningRows(): ?array
  {
    if ($this->check()) {
      return array_map(
        function ($a): array {
          $cfg = $a['cfg'] ? json_decode($a['cfg'], true) : [];
          unset($a['cfg']);
          return X::mergeArrays($a, $cfg);
        }, $this->db->rselectAll(
          [
          'table' => $this->table,
          'fields' => [],
          'where' => [
          'conditions' => [[
            'field' => 'active',
            'value' => 1
          ], [
            'field' => 'pid',
            'operator' => 'isnotnull'
          ]]
          ],
          'order' => [[
          'field' => 'prev',
          'dir' => 'ASC'
          ]]
          ]
        )
      );
    }

    return null;
  }


  /**
   * @param int $limit
   * @param int $sec
   * @return array|null
   * @throws Exception
   */
  public function getNextRows(int $limit = 10, int $sec = 0): ?array
  {
    if ($limit === 0) {
      $limit = 1000;
    }

    if ($this->check()) {
      return array_map(
        function ($a): array {
          $cfg = $a['cfg'] ? json_decode($a['cfg'], true) : [];
          unset($a['cfg']);
          return X::mergeArrays($a, $cfg);
        }, $this->db->rselectAll(
          [
          'table' => $this->table,
          'fields' => [],
          'where' => [
          'conditions' => [[
            'field' => 'active',
            'value' => 1
          ], [
            'field' => 'pid',
            'operator' => 'isnull'
          ], [
            'field' => 'next',
            'operator' => 'isnotnull'
          ], [
            'field' => 'next',
            'operator' => '<',
            'exp' => $sec ? "DATE_ADD(NOW(), INTERVAL $sec SECOND)" : 'NOW()'
          ]]
          ],
          'order' => [[
          'field' => 'priority',
          'dir' => 'ASC'
          ], [
          'field' => 'next',
          'dir' => 'ASC'
          ]],
          'limit' => $limit
          ]
        )
      );
    }

    return null;
  }


  /**
   * @return array|null
   * @throws Exception
   */
  public function getFailed(): ?array
  {
    if ($this->check()) {
      return array_map(
        function ($a): array {
          $cfg = $a['cfg'] ? json_decode($a['cfg'], true) : [];
          unset($a['cfg']);
          return X::mergeArrays($a, $cfg);
        }, $this->db->rselectAll(
          [
          'table' => $this->table,
          'fields' => [],
          'where' => [
          'conditions' => [[
            'field' => 'active',
            'value' => 1
          ], [
            'field' => 'pid',
            'operator' => 'isnotnull'
          ], [
            'field' => 'next',
            'operator' => 'isnotnull'
          ], [
            'field' => 'NOW()',
            'operator' => '>',
            'exp' => "DATE_ADD(prev, INTERVAL JSON_EXTRACT(cfg, '$.timeout') SECOND)"
          ]]
          ],
          'order' => [[
          'field' => 'priority',
          'dir' => 'ASC'
          ], [
          'field' => 'next',
          'dir' => 'ASC'
          ]]
          ]
        )
      );
    }

    return null;
  }


  /**
   * @param Notification|null $notification
   * @throws Exception
   */
  public function notifyFailed(?Notification $notification = null): void
  {
    $notifications = $notification ?? new Notification($this->db);
    if ($failed = $this->getFailed()) {
      foreach ($failed as $f) {
        $content = X::_('The task')." $f[file] ".X::_('failed.');
        if (empty($f['notification'])
            && $notifications->insertByOption(X::_('CRON task failed'), $content, 'cron/task_failed', true)
        ) {
          $this->db->update($this->table, ['notification' => X::microtime()], ['id' => $f['id']]);
        }
      }
    }
  }


  /**
   * @param string $id_cron
   * @return bool
   */
  public function isRunning(string $id_cron): bool
  {
    return (bool)( $this->check() && $this->db->count(
      $this->table, [
      ['id' => $id_cron],
      ['pid', 'isnotnull']
      ]
    ));
  }


  /**
   * Sets the active column to 1 for the given CRON ID.
   *
   * @param $id_cron
   * @return int|null
   */
  public function activate($id_cron): int
  {
    return $this->db->update($this->table, ['active' => 1], ['id' => $id_cron]);
  }


  /**
   * Sets the active column to 0 for the given CRON ID.
   *
   * @param $id_cron
   * @return int|null
   */
  public function deactivate($id_cron): int
  {
    return $this->db->update($this->table, ['active' => 0], ['id' => $id_cron]);
  }


  /**
   * Sets the pid' column to the given value for the given CRON ID.
   *
   * @param $id_cron
   * @return int|null
   */
  public function setPid($id_cron, $pid): int
  {
    return $this->db->update($this->table, ['pid' => $pid], ['id' => $id_cron]);
  }


  /**
   * Sets the pid and notification columns to null for the given CRON ID.
   *
   * @param $id_cron
   * @return int|null
   */
  public function unsetPid($id_cron): int
  {
    return $this->db->update(
      $this->table, [
      'pid' => null,
      'notification' => null
      ], ['id' => $id_cron]
    );
  }


  /**
   * @param array $cfg
   * @return array|null
   */
  public function add(array $cfg): ?array
  {
    if ($this->check()
        && X::hasProps($cfg, ['file', 'priority', 'frequency', 'timeout'], true)
    ) {
      $d = [
        'file' => $cfg['file'],
        'description' => $cfg['description'] ?? '',
        'next' => $cfg['next'] ?? date('Y-m-d H:i:s'),
        'priority' => $cfg['priority'],
        'cfg' => json_encode(
          [
          'frequency' => $cfg['frequency'],
          'timeout' => $cfg['timeout']
          ]
        ),
        'active' => 1
      ];
      if ($this->db->insert($this->table, $d)) {
        $d['id'] = $this->db->lastId();
        return $d;
      }
    }

    return null;
  }


  public function addSingle(string $file, string $variant, int $priority = 5, int $timeout = 360)
  {
    if ($this->check()) {
      if (!defined('BBN_PROJECT')) {
        throw new Exception('BBN_PROJECT is not defined');
      }

      $d = [
        'file' => $file,
        'description' => X::_('One shot action'),
        'next' => date('Y-m-d H:i:s'),
        'priority' => $priority,
        'cfg' => json_encode(
          [
            'frequency' => null,
            'timeout' => $timeout
          ]
        ),
        'project' => constant('BBN_PROJECT'),
        'active' => 1
      ];
      if ($this->db->insertUpdate($this->table, $d)) {
        $d['id'] = $this->db->lastId();
        return $d;
      }
    }

    return null;
  }


  /**
   * Edits an existing CRON row.
   *
   * @param string $id   The cron ID.
   * @param array  $cfg  New values: file, description, next, priority, frequency, timeout.
   * @return array|null  The updated row (id + fields) or null on failure.
   */
  public function edit(string $id, array $cfg): ?array
  {
    if (!$this->check()) {
      return null;
    }

    // Existing cron row with decoded cfg (see getCron())
    $cron = $this->getCron($id);
    if (!$cron) {
      return null;
    }

    // Current configuration stored as JSON in the DB
    $currentCfg = is_array($cron['cfg']) ? $cron['cfg'] : [];

    // Fallbacks for frequency and timeout
    $currentFrequency = $currentCfg['frequency'] ?? null;
    $currentTimeout   = $currentCfg['timeout']   ?? self::$cron_timeout;

    // New configuration, falling back to existing values
    $newFrequency = $cfg['frequency'] ?? $currentFrequency;
    $newTimeout   = $cfg['timeout']   ?? $currentTimeout;

    $data = [
      'file'        => $cfg['file']        ?? $cron['file'],
      'description' => $cfg['description'] ?? $cron['description'],
      'next'        => $cfg['next']        ?? $cron['next'],
      'priority'    => $cfg['priority']    ?? $cron['priority'],
      'cfg'         => json_encode(
        [
          'frequency' => $newFrequency,
          'timeout'   => $newTimeout,
        ],
        JSON_THROW_ON_ERROR
      ),
      // Editing always (re)activates the cron
      'active'      => 1,
    ];

    if ($this->db->update($this->table, $data, ['id' => $id])) {
      $data['id']  = $id;
      // Be nice and return the decoded cfg as well:
      $data['cfg'] = [
        'frequency' => $newFrequency,
        'timeout'   => $newTimeout,
      ];

      return $data;
    }

    return null;
  }

}