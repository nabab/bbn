<?php
namespace bbn\Appui;

use Exception;
use DateTime;
use bbn\X;
use bbn\Str;
use bbn\Db;
use bbn\User;
use bbn\Date;
use bbn\Models\Tts\DbActions;
use bbn\Models\Tts\Optional;
use bbn\Models\Cls\Db as modelDb;
use bbn\Appui\Option;
class Event extends modelDb
{

  use DbActions;
  use Optional;

  /** @var array $default_class_cfg */
  protected static $default_class_cfg = [
    'table' => 'bbn_events',
    'tables' => [
      'events' => 'bbn_events',
      'recurring' => 'bbn_events_recurring',
      'exceptions' => 'bbn_events_exceptions',
      'options' => 'bbn_options',
    ],
    'arch' => [
      'events' => [
        'id' => 'id',
        'id_parent' => 'id_parent',
        'id_type' => 'id_type',
        'start' => 'start',
        'end' => 'end',
        'name' => 'name',
        'recurring' => 'recurring',
        'cfg' => 'cfg'
      ],
      'recurring' => [
        'id' => 'id',
        'id_event' => 'id_event',
        'type' => 'type',
        'interval' => 'interval',
        'occurrences' => 'occurrences',
        'until' => 'until',
        'wd' => 'wd',
        'mw' => 'mw',
        'md' => 'md',
        'ym' => 'ym'  
      ],
      'exceptions' => [
        'id' => 'id',
        'id_event' => 'id_event',
        'id_user' => 'id_user',
        'creation' => 'creation',
        'day' => 'day',
        'start' => 'start',
        'end' => 'end',
        'rescheduled' => 'rescheduled',
        'deleted' => 'deleted'
      ],
      'options' => [
        'id' => 'id'
      ]
    ],
    'extra' => [
      'action' => 'action',
      'recurrence' => 'recurrence',
      'exception' =>'exception',
      'old_start' => 'old_start',
      'old_end' => 'old_end',
      'exceptions' => 'exceptions'
    ]
  ];

  /** @var Option $opt */
  private $opt;
  /** @var User $user */
  private $usr;
  /** @var string $id_opt */
  private $opt_id;
  /** @var array $recurrences */
  private $recurrences = [
    'daily,',
    'weekly',
    'monthly',
    'yearly'
  ];
  private $weekdays = [
    1 => 'monday',
    2 => 'tuesday',
    3 => 'wednesday',
    4 => 'thursday',
    5 => 'friday',
    6 => 'saturday',
    7 => 'sunday'
  ];

  /** @var array $class_cfg */
  protected $class_cfg;

  /**
   * Filters the recurrences of an event by the month's week.
   * @param array $recurrences
   * @param int $monthweek
   * @return array
   */
  protected function filterRecurrencesByMonthWeek(array $recurrences, int $monthweek): array
  {
    $t =& $this;
    // Recurring table fields
    $rf =& $this->class_cfg['arch']['recurring'];
    // Events table fields
    $ef =& $this->class_cfg['arch']['events'];
    return array_filter($recurrences, function($o) use($ef, $rf, $t, $monthweek){
      $tstart = strtotime(date('Y-m-d', strtotime($o[$ef['start']])));
      // Get the last day of the month
      $lastday = strtotime('last day of this month', $tstart);
      // Get the first day of the month
      $firstday = strtotime('first day of this month', $tstart);
      // Get the week number of the recurrence
      $week = Date::getMonthWeek(date('Y-m-d', $tstart));
      //From the end
      if ( $monthweek < 0 ){
        // Get the day in the last week of the month
        $lastwd = strtotime($t->weekdays[$o[$rf['wd']]] . ' this week', $lastday);
        if ( $lastwd > $lastday ){
          // If the day is outside the month to decrease the week
          $lastwd = strtotime('-1 week', $lastwd);
        }
        // Get the corrected week number
        $lwd = Date::getMonthWeek(date('Y-m-d', strtotime('-' . abs($monthweek + 1) .' week', $lastwd)));
        return $lwd === $week;
      }
      // From the start
      else {
        // Get the day in the first week of the month
        $firstwd = strtotime($t->weekdays[$o[$rf['wd']]] . ' this week', $firstday);
        // If the day is outside the month to increase the week
        if ( $firstwd < $firstday ){
          $firstwd = strtotime('+1 week', $firstwd);
        }
        // Get the corrected week number
        $fwd = Date::getMonthWeek(date('Y-m-d', strtotime('+' . ($monthweek - 1) .' week', $firstwd)));
        return $fwd === $week;
      }
    });
  }

  public function __construct(Db $db){
    parent::__construct($db);
    $this->_init_class_cfg();
    $this->opt = Option::getInstance();
    $this->usr = User::getInstance();
    //$this->opt_id = $this->opt->fromCode('event', 'appui');
  }

  /**
   * @param array $event
   * @return string|null
   * @throws Exception
   */
  public function insert(array $event): ?string
  {
    $f =& $this->fields;
    if ( (X::hasProps($event, [$f['id_type']], true) ) && (array_key_exists($this->fields['start'], $event))){
      if ( 
        !empty($event[$f['cfg']]) &&
        !Str::isJson($event[$f['cfg']])
      ){
        $event[$f['cfg']] = json_encode($event[$f['cfg']]);
      }
      // Check if the event is recurring
      if ( $is_rec = !empty($event[$f['recurring']]) ){
        $rf =& $this->class_cfg['arch']['recurring'];
        // If the event's start date is different of its first recurrence to change the start date
        if (
          ($first = $this->getFirstRecurrence($event, false)) &&
          ($event[$f['start']] !== $first)
        ){
          // Check if the event has an end date and to change it
          if ( !empty($event[$f['end']]) ){
            $diff = date_diff(new DateTime($event[$f['start']]), new DateTime($event[$f['end']]));
            $end = new DateTime($first);
            $event[$f['end']] = $end->add($diff)->format('Y-m-d H:i:s');  
          }
          $event[$f['start']] = $first;
        }
      }
      if ( 
        $this->db->insert($this->class_table, [
          $f['id_parent'] => $event[$f['id_parent']] ?? null,
          $f['id_type'] => $event[$f['id_type']],
          $f['start'] => $event[$f['start']],
          $f['end'] => empty($event[$f['end']]) ? null : $event[$f['end']],
          $f['name'] => empty($event[$f['name']]) ? null : $event[$f['name']],
          $f['recurring'] => (int)$is_rec,
          $f['cfg'] => $event[$f['cfg']] ?? null
        ]) &&
        ($id = $this->db->lastId())
      ){
        if ( $is_rec ){
          $this->db->insert($this->class_cfg['tables']['recurring'], [
            $rf['id_event'] => $id,
            $rf['type'] => $event[$rf['type']],
            $rf['interval'] => $event[$rf['interval']] ?? null,
            $rf['occurrences'] => $event[$rf['occurrences']] ?? null,
            $rf['until'] => $event[$rf['until']] ?? null,
            $rf['wd'] => !empty($event[$rf['wd']]) ? (Str::isJson($event[$rf['wd']]) ? $event[$rf['wd']] : json_encode($event[$rf['wd']])) : null,
            $rf['mw'] => !empty($event[$rf['mw']]) ? (Str::isJson($event[$rf['mw']]) ? $event[$rf['mw']] : json_encode($event[$rf['mw']])) : null,
            $rf['md'] => !empty($event[$rf['md']]) ? (Str::isJson($event[$rf['md']]) ? $event[$rf['md']] : json_encode($event[$rf['md']])) : null,
            $rf['ym'] => !empty($event[$rf['ym']]) ? (Str::isJson($event[$rf['ym']]) ? $event[$rf['ym']] : json_encode($event[$rf['ym']])) : null
          ]);
        }
        return $id; 
      }
    }
    return null;
  }

  /**
   * @param string $id
   * @param array $event
   * @return int|null
   */
  public function edit(string $id, array $event): ?int
  {
    if ( Str::isUid($id) ){
      $f =& $this->fields;
      $rf =& $this->class_cfg['arch']['recurring'];
      $ok = 0;
      $old_is_rec = $this->db->selectOne($this->class_table, $this->fields['recurring'], [
        $this->fields['id'] => $id
      ]);
      if ( array_key_exists($f['id'], $event) ){
        unset($event[$f['id']]);
      }
      if ( 
        !empty($event[$f['cfg']]) &&
        !Str::isJson($event[$f['cfg']])
      ){
        $event[$f['cfg']] = json_encode($event[$f['cfg']]);
      }

      $ok2 = $this->db->update($this->class_table, [
        $f['id_type'] => $event[$f['id_type']],
        $f['start'] => $event[$f['start']],
        $f['end'] => $event[$f['end']] ?? null,
        $f['name'] => $event[$f['name']] ?? null,
        $f['recurring'] => $event[$f['recurring']] ?? 0,
        $f['cfg'] => $event[$f['cfg']] ?? null
      ], [
	      $f['id'] => $id
      ]);
      if ( !empty($event[$f['recurring']]) ){
        $ok = $this->db->insertUpdate($this->class_cfg['tables']['recurring'], [
          $rf['id_event'] => $id,
          $rf['type'] => $event[$rf['type']],
          $rf['interval'] => $event[$rf['interval']] ?? null,
          $rf['occurrences'] => $event[$rf['occurrences']] ?? null,
          $rf['until'] => $event[$rf['until']] ?? null,
          $rf['wd'] => !empty($event[$rf['wd']]) ? (Str::isJson($event[$rf['wd']]) ? $event[$rf['wd']] : json_encode($event[$rf['wd']])) : null,
          $rf['mw'] => !empty($event[$rf['mw']]) ? (Str::isJson($event[$rf['mw']]) ? $event[$rf['mw']] : json_encode($event[$rf['mw']])) : null,
          $rf['md'] => !empty($event[$rf['md']]) ? (Str::isJson($event[$rf['md']]) ? $event[$rf['md']] : json_encode($event[$rf['md']])) : null,
          $rf['ym'] => !empty($event[$rf['ym']]) ? (Str::isJson($event[$rf['ym']]) ? $event[$rf['ym']] : json_encode($event[$rf['ym']])) : null
        ]);
      }
      else if ( !empty($old_is_rec) ){
        $this->deleteRecurrences($id);
      }

      return $ok || $ok2;
    }

    return null;
  }

  /**
   * @param string $id
   * @return bool
   */
  public function delete(string $id): bool
  {
    if ( Str::isUid($id) ){
      return (bool)$this->db->delete($this->class_table, [$this->fields['id'] => $id]);
    }

    return false;
  }

  /**
   * @param string $id
   * @return array|null
   */
  public function get(string $id): ?array
  {
    if ( Str::isUid($id) ){
      $t =& $this;

      return $this->db->rselect([
        'table' => $this->class_table,
        'fields' => array_map(function($f) use($t){
          return $this->db->colFullName($f, $t->class_table);
        }, $this->fields),
        'join' => [[
          'table' => $this->class_cfg['tables']['options'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->colFullName($this->class_cfg['arch']['options']['id'], $this->class_cfg['tables']['options']),
              'exp' => $this->fields['id_type']
            ]]
          ]
        ]],
        'where' => [
          $this->db->colFullName($this->fields['id'], $this->class_table) => $id
        ]
      ]);
    }

    return null;
  }


  private function getIds(string $mode = 'next', array $filter = [], string $from = null, int $num = 1): array
  {
    $timeFilter = [
      'logic' => 'AND',
      'conditions' => [
        [
          'field' => $this->fields['start'],
          'operator' => $mode === 'next' ? '>' : '<',
          'value' => Str::isDateSQL($from) ?: date('Y-m-d H:i:s')
        ]
      ]
    ];
    if (!empty($filter)) {
      $tmp = $filter;
      if (!isset($tmp['conditions'])) {
        $tmp = [
          'logic' => 'AND',
          'conditions' => $tmp
        ];
      }
      $filter = $timeFilter;
      $filter['conditions'][] = $tmp;
    }
    else {
      $filter = $timeFilter;
    }

    $args = [
      $this->class_table,
      $this->fields['id'],
      $filter,
      [$this->fields['start'] => 'DESC'],
      $num
    ];
    return $this->db->getColumnValues(...$args);
  }


  private function getOnes(string $mode = 'next', array $filter = [], string $from = null, int $num = 1): ?array
  {
    $ids = $this->getIds($mode, $filter, $from, $num);
    if ($num === 1) {
      if (!$ids) {
        return null;
      }

      return $this->get($ids[0]);
    }

    $res = [];
    foreach ($ids as $id) {
      $res[] = $this->get($id);
    }

    return $res;
  }
  
  public function getLastIds(array $filter = [], string $from = null, int $num = 1): array
  {
    return $this->getIds('last', $filter, $from, $num);
  }


  public function getNextIds(array $filter = [], string $from = null, int $num = 1): array
  {
    return $this->getIds('next', $filter, $from, $num);
  }


  public function getLast(array $filter = [], string $from = null, int $num = 1): ?array
  {
    return $this->getOnes('last', $filter, $from, $num);
  }
  

  public function getNext(array $filter = [], string $from = null, int $num = 1): ?array
  {
    return $this->getOnes('next', $filter, $from, $num);
  }
  

  /**
   * Gets an event with the recurring details.
   * @param string $id
   * @return array|null
   */
  public function getFull(string $id): ?array
  {
    if ( Str::isUid($id) ){
      $rt =& $this->class_cfg['tables']['recurring'];
      $rf =& $this->class_cfg['arch']['recurring'];
      $ot =& $this->class_cfg['tables']['options'];
      return $this->db->rselect([
        'table' => $this->class_table,
        'fields' => [
          $this->db->colFullName($this->fields['id'], $this->class_table),
          $this->db->colFullName($this->fields['id_parent'], $this->class_table),
          $this->fields['id_type'],
          $this->fields['start'],
          $this->fields['end'],
          $this->fields['name'],
          $this->fields['recurring'],
          $this->fields['cfg'],
          $rf['type'],
          $rf['interval'],
          $rf['occurrences'],
          $rf['until'],
          $rf['wd'],
          $rf['mw'],
          $rf['md'],
          $rf['ym']
        ],
        'join' => [[
          'table' => $rt,
          'type' => 'left',
          'on' => [
            'conditions' => [[
              'field' => $this->db->colFullName($this->fields['id'], $this->class_table),
              'exp' => $this->db->colFullName($rf['id_event'], $rt),
            ]]
          ]
        ], [
          'table' => $ot,
          'on' => [
            'conditions' => [[
              'field' => $this->fields['id_type'],
              'exp' => $this->db->colFullName($this->class_cfg['arch']['options']['id'], $ot),
            ]]
          ]
        ]],
        'where' => [
          $this->db->colFullName($this->fields['id'], $this->class_table) => $id
        ]
      ]);
    }

    return null;
  }

  /**
   * Returns an array of all event's recurrences in a period
   * @param string $start
   * @param string $end
   * @param array $event
   * @return array
   */
  public function getRecurrences(string $start, string $end, array &$event): array
  {
    // Recurring table fields
    $rf =& $this->class_cfg['arch']['recurring'];
    // Events table fields
    $ef =& $this->class_cfg['arch']['events'];
    // When object instance
    $when = $this->getWhenObject($event);
    // Get occurrences
    $occ = $this->makeRecurrencesFields($event, $when->getOccurrencesBetween(new \DateTime($start), new \DateTime($end)));
    // Specific month's week
    if ( !empty($event[$rf['mw']]) ){
      $occ = $this->filterRecurrencesByMonthWeek($occ, $event[$rf['mw']]);
    }

    return $this->filterRecurrencesByExceptions($occ);
  }

  /**
   * Makes the fields structure on the given event recurrences
   * @param array $event 
   * @param array $recurrences
   * @return array
   */
  public function makeRecurrencesFields(array $event, array $recurrences): array
  {
    $ef =& $this->class_cfg['arch']['events'];
    // Calculate the diff between the event start and the event end
    $diff = !empty($event[$ef['end']]) ? date_diff(new DateTime($event[$ef['start']]), new DateTime($event[$ef['end']])) : false;
    // Fix fields
    return array_map(function($d) use($event, $ef, $diff){
      $d = \is_string($d) ? $d : $d->format('Y-m-d H:i:s');
      $e = null;
      if ( $diff ){
        $e = new DateTime($d);
        $e = $e->add($diff)->format('Y-m-d H:i:s');
      }
      return array_merge($event, [
        $ef['start'] => $d,
        $ef['end'] => $e,
        'recurrence' => (int)($event[$ef['start']] !== $d)
      ]);
    }, $recurrences);
  }

  /**
   * Returns the date of the first recurrence of a recurring event.
   * @param array $event
   * @param bool $omitstart Default: true
   * @param bool $exceptions Default: false
   * @return string|null
   */
  public function getFirstRecurrence(array $event, $omitstart = true, $exceptions = false): ?string 
  {
    if ( 
      $exceptions &&
      ($excs = $this->getExceptions($event[$this->fields['id']]))
    ){
      $t =& $this;
      $event[$this->class_cfg['extra']['exceptions']] = array_map(function($e) use($t){
        return $e[$t->class_cfg['arch']['exceptions']['day']].' '.$e[$t->class_cfg['arch']['exceptions']['start']];
      }, $excs);
    }
    $when = $this->getWhenObject($event);
    if ( $r = $when->getNextOccurrence($when->startDate, $omitstart) ){
      return $r->format('Y-m-d H:i:s');
    }

    return null;
  }

  /**
   * Deletes the recurrences of the given event
   * @param string $id
   * @return int
   */
  public function deleteRecurrences(string $id): bool
  {
    if ( Str::isUid($id) ){
      $todelete = $this->db->count($this->class_cfg['tables']['recurring'], [$this->class_cfg['arch']['recurring']['id_event'] => $id]);
      return $this->db->delete($this->class_cfg['tables']['recurring'], [$this->class_cfg['arch']['recurring']['id_event'] => $id]) === $todelete;
    }

    return false;
  }

  /**
   * Makes a When object by an event.
   * @param array $event
   * @return \When\When
   */
  public function getWhenObject(array &$event): \When\When
  {
    // Recurring table fields
    $rf =& $this->class_cfg['arch']['recurring'];
    // Events table fields
    $ef =& $this->class_cfg['arch']['events'];
    // Extra fields
    $extf =& $this->class_cfg['arch']['extra'];
    // When object instance
    $when = new \When\When($event[$ef['start']]);
    // Trick to have the possibility to set the start date different to the first occurrence
    $when->RFC5545_COMPLIANT = 2;
    // Set the frequency
    $when->freq($event[$rf['type']]);
    // Remove the original event from the occurrences
    $excs = $event[$ef['start']];
    // If the exceptions are present add them to exclusions list
    if ( !empty($event[$extf['exceptions']]) ){
      if ( \is_array($event[$extf['exceptions']]) ){
        $excs .= implode(',', $event[$extf['exceptions']]);
      }
      else if ( \is_string($event[$extf['exceptions']]) ){
        $excs .= $event[$extf['exceptions']];
      }
    }
    $when->exclusions($excs);
    // Interval
    if ( !empty($event[$rf['interval']]) ){
      $when->interval($event[$rf['interval']] + 1);
    }
    // Number of occurrences
    if ( !empty($event[$rf['occurrences']]) ){
      $when->count($event[$rf['occurrences']]);
    }
    // Until
    if ( !empty($event[$rf['until']]) ){
      $until = new \DateTime($event[$rf['until']]);
      $until->add(new \DateInterval('P1D'));
      $when->until($until);
    }
    // Specific week's day
    if ( \is_null($event[$rf['wd']]) ){
      $event[$rf['wd']] = [];
    }
    else if (Str::isJson($event[$rf['wd']]) ){
      $event[$rf['wd']] = json_decode($event[$rf['wd']], true);
    }
    if ( !empty($event[$rf['wd']]) ){
      $wds =& $this->weekdays;
      $days = array_map(function($d) use($wds){
        return substr($wds[$d], 0, 2);
      }, $event[$rf['wd']]);
      $when->byday($days);
    }
    // Specific month's day
    if ( \is_null($event[$rf['md']]) ){
      $event[$rf['md']] = [];
    }
    else if (Str::isJson($event[$rf['md']]) ){
      $event[$rf['md']] = json_decode($event[$rf['md']], true);
    }
    if ( !empty($event[$rf['md']]) ){
      $when->bymonthday($event[$rf['md']]);
    }
    // Specific year's month
    if ( \is_null($event[$rf['ym']]) ){
      $event[$rf['ym']] = [];
    }
    else if (Str::isJson($event[$rf['ym']]) ){
      $event[$rf['ym']] = json_decode($event[$rf['ym']], true);
    }
    if ( !empty($event[$rf['ym']]) ){
      $when->bymonth($event[$rf['ym']]);
    }
    // Specific month's week
    if ( \is_null($event[$rf['mw']]) ){
      $event[$rf['mw']] = [];
    }
    else if (Str::isJson($event[$rf['mw']]) ){
      $event[$rf['mw']] = json_decode($event[$rf['mw']], true);
    }
    return $when;
  }

  /**
   * Filters the event's recurrences by exceptions.
   * @param array $recurrences
   * @return array
   */
  public function filterRecurrencesByExceptions(array $recurrences): array
  {
    if ( 
      !empty($recurrences) &&
      // Recurring table fields
      ($rf =& $this->class_cfg['arch']['recurring']) &&
      // Exception table fields
      ($ef =& $this->class_cfg['arch']['events']) &&
      // Events table fields
      ($rt =& $this->class_cfg['tables']['exceptions']) &&
      // Exception table fields
      ($exf =& $this->class_cfg['arch']['exceptions']) &&
      // Get exceptions
      ($ex = $this->getExceptions($recurrences[0][$rf['id_event']])) 
    ){
      return array_filter($recurrences, function($r) use($ex, $ef, $exf){
        return X::find($ex, [$exf['day'] => date('Y-m-d', strtotime($r[$ef['start']]))]) === null;
      });    
    }
    return $recurrences;
  }

  /**
   * Setz the event's until property
   * @param string $id
   * @param string $until
   * @return bool
   */
  public function setUntil(string $id, string $until = null): bool
  {
    if ( 
      Str::isUid($id) &&
      (
        \is_null($until) ||
        Str::isDateSql($until)
      )
    ){
      return (bool)$this->db->update($this->class_cfg['tables']['recurring'], [
        $this->class_cfg['arch']['recurring']['until'] => $until
      ], [
        $this->class_cfg['arch']['recurring']['id_event'] => $id
      ]);
    }
    return false;
  }

  /** Sets the event's until property to null
   * @param string $id
   * @return bool
   */
  public function unsetUntil(string $id): bool
  {
    return $this->setUntil($id);
  }

  /**
   * Adds an event recurring exception
   * @param string $id_event
   * @param array $exc
   * @return bool
   */
  public function addException(string $id_event, array $exc): bool
  {
    if ( 
      Str::isUid($id_event) &&
      ($ext =& $this->class_cfg['tables']['exceptions']) &&
      ($exf =& $this->class_cfg['arch']['exceptions']) &&
      $this->get($id_event) && 
      !empty($exc[$exf['day']]) &&
      !empty($exc[$exf['start']]) &&
      !empty($exc[$exf['end']]) &&
      (
        !empty($exc[$exf['deleted']]) ||
        !empty($exc[$exf['rescheduled']])
      )
    ){
      if ( empty($exc[$exf['id_event']]) ){
        $exc[$exf['id_event']] = $id_event;
      }
      $exc[$exf['day']] = date('Y-m-d', strtotime($exc[$exf['day']]));
      $exc[$exf['start']] = date('H:i:s', strtotime($exc[$exf['start']]));
      $exc[$exf['end']] = date('H:i:s', strtotime($exc[$exf['end']]));
      $exc[$exf['id_user']] = !empty($exc[$exf['id_user']]) ? $exc[$exf['id_user']] : User::getInstance()->getId();
      $exc[$exf['creation']] = Str::isDateSql($exc[$exf['creation']]) ?
        $exc[$exf['creation']] : date('Y-m-d H:i:s');
      return (bool)$this->db->insert($ext, $exc);
    }
    return false;
  }

  /**
   * Copies the event's exceptions to an other one.
   * @param string $from_event
   * @param string $to_event
   * @param bool
   */
  public function copyExceptions(string $from_event, string $to_event): bool
  {
    if (
      Str::isUid($from_event) &&
      Str::isUid($to_event) &&
      ($table =& $this->class_cfg['tables']['exceptions']) &&
      ($fields =& $this->class_cfg['arch']['exceptions'])
    ){
      $exc = array_map(function($e) use($fields, $to_event){
        unset($e[$fields['id']]);
        $e[$fields['id_event']] = $to_event;
        return $e;
      }, $this->db->rselectAll($table, [], [$fields['id_event'] => $from_event]));
      $inserted = 0;
      foreach ( $exc as $e ){
        $inserted += $this->db->insert($table, $e);
      }
      return count($exc) === $inserted;
    }
    return false;
  }

  /**
   * Gets the event's exceptions.
   * @param string $id
   * @return array|null
   */
  public function getExceptions(string $id): ?array
  {
    if ( Str::isUid($id) ){
      return $this->db->rselectAll($this->class_cfg['tables']['exceptions'], [], [
        $this->class_cfg['arch']['exceptions']['id_event'] => $id
      ]);
    }
    return null;
  }

}
