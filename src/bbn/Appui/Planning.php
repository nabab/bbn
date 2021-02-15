<?php
namespace bbn\Appui;
use bbn;

class Planning {

  use
    bbn\Models\Tts\Dbconfig;

  protected static
    /** @var array */
    $default_class_cfg = [
      'table' => 'bbn_hr_planning',
      'tables' => [
        'planning' => 'bbn_hr_planning',
        'staff_events' => 'bbn_hr_staff_events'
      ],
      'arch' => [
        'planning' => [
          'id' => 'id',
          'id_staff' => 'id_staff',
          'id_event' => 'id_event',
          'id_alias' => 'id_alias',
          'alias' => 'alias'
        ],
        'staff_events' => [
          'id_staff' => 'id_staff',
          'id_event' => 'id_event',
          'note' => 'note',
          'status' => 'status'
        ]
      ]
    ];

  protected 
    $db,
    $events,
    $ecfg,
    $options,
    $ocfg;

  private function _delete(string $id_planning, ?string $id_event = null): bool
  {
    if ( empty($id_event) ){
      $id_event = $this->getIdEvent($id_planning);
    }
    return !!$this->db->delete($this->class_table, [$this->fields['id'] => $id_planning]) && !!$this->events->delete($id_event);
  }

  public function __construct(\bbn\Db $db)
  {
    $this->db = $db;
    $this->_init_class_cfg();
    $this->events = new \bbn\Appui\Events($this->db);
    $this->ecfg = $this->events->getClassCfg();
    $this->options = new \bbn\Appui\Option($this->db);
    $this->ocfg = $this->options->getClassCfg();
  }

  /**
   * Returns the bbn\Appui\Events instance
   * @return \bbn\Appui\Events
   */
  public function getEvents(){
    return $this->events;
  }

  /**
   * Inserts an event for the given staff id
   * @param string $id_staff
   * @param array $event
   * @param string|null $id_alias
   * @param string|null $alias
   * @return string|null
   */
  public function insert(string $id_staff, array $event, ?string $id_alias = null, ?string $alias = null): ?string
  {
    if ( 
      \bbn\Str::isUid($id_staff) &&
      (\bbn\Str::isUid($id_alias) || \is_null($id_alias)) &&
      (\bbn\Str::isDateSql($alias) || \is_null($alias)) &&
      ($id_event = $this->events->insert($event)) &&
      $this->db->insert($this->class_table, [
        $this->fields['id_event'] => $id_event,
        $this->fields['id_staff'] => $id_staff,
        $this->fields['id_alias'] => $id_alias,
        $this->fields['alias'] => \bbn\Str::isDateSql($alias) ? date('Y-m-d', strtotime($alias)) : null
      ])
    ){
      return $this->db->lastId();
    }
    return null;
  }

  /**
   * Updates a planning row and, if necessary, the linked event
   * @param string $id_planning
   * @param string $id_staff
   * @param string|array $event
   * @param string|null $id_alias
   * @param string|null $alias
   * @return bool
   */
  public function update(string $id_planning, string $id_staff, $event, ?string $id_alias = null, ?string $alias = null): bool
  {
    if ( 
      \bbn\Str::isUid($id_planning) &&
      \bbn\Str::isUid($id_staff) &&
      (
        \bbn\Str::isUid($event) || 
        (\is_array($event) && \bbn\Str::isUid($event[$this->ecfg['arch']['events']['id']]))
      ) &&
      (\bbn\Str::isUid($id_alias) || \is_null($id_alias)) &&
      (\bbn\Str::isDateSql($alias) || \is_null($alias))
    ){
      $id_event = \is_array($event) ? $event[$this->ecfg['arch']['events']['id']] : $event;
      $ok = $this->db->update($this->class_table, [
        $this->fields['id_staff'] => $id_staff,
        $this->fields['id_event'] => $id_event,
        $this->fields['id_alias'] => $id_alias,
        $this->fields['alias'] => \bbn\Str::isDateSql($alias) ? date('Y-m-d', strtotime($alias)) : null
      ], [
        $this->fields['id'] => $id_planning
      ]);
      $ok2 = \is_array($event) ? $this->events->edit($id_event, $event) : false;
      return !!$ok || !!$ok2;
    }
    return false;
  }

  /**
   * Deletes a planning row and the linked event
   * @param string $id_planning
   * @param array|null $event
   * @return bool
   */
  public function delete(string $id_planning, ?array $event = null): bool
  {
    if ( 
      \bbn\Str::isUid($id_planning) &&
      // Get the id_event linked to this planning
      ($id_event = $this->getIdEvent($id_planning)) &&
      // Get the old event linked to this planning
      ($old_event = $this->events->getFull($id_event)) &&
      // Events table fields
      ($ef =& $this->ecfg['arch']['events']) &&
      // Exceptions table fields
      ($exf =& $this->ecfg['arch']['exceptions']) &&
      // Events extra fields
      ($extf =& $this->ecfg['extra'])
    ){
      if ( !empty($event) ){
        // Check if the "action" property is set
        if ( empty($event[$extf['action']]) ){
          die('The "'.$extf['action'].'" property is mandatory!');
        }
        switch ( $event[$extf['action']] ){
          case 'this':
            // Check if the event is recurring
            if ( !empty($old_event[$ef['recurring']]) ){
              // Check if the event is a recurrence
              if ( !empty($event[$extf['recurrence']]) ){
                return $this->events->addException($id_event, [
                  $exf['day'] => $event[$ef['start']],
                  $exf['start'] => $event[$ef['start']],
                  $exf['end'] => $event[$ef['end']],
                  $exf['deleted'] => 1
                ]);
              }
              else if ( 
                // Get the first event's recurrence
                ($first_recc = $this->events->getFirstRecurrence($old_event, true, true)) &&
                // Make the recurrences fields structure
                ($event_next = $this->events->makeRecurrencesFields($old_event, [$first_recc]))
              ){
                $event_next = $event_next[0];
                return !!$this->events->edit($id_event, $event_next);
              }
            }
            else {
              return $this->_delete($id_planning, $id_event);
            }
            break;
          case 'all':
            return $this->_delete($id_planning, $id_event);
          case 'future':
            if ( !empty($event[$extf['recurrence']]) ){
              $until = date('Y-m-d', strtotime('-1 day', strtotime($event[$ef['start']])));
              return $this->events->setUntil($id_event, $until);
            }
            else {
              return $this->_delete($id_planning, $id_event);
            }
        }
      }
      else{
        return $this->_delete($id_planning, $id_event);
      }
    }
    return false;
  }

  /**
   * Gets all events of a period.
   * @param string $start
   * @param string $end
   * @return array
   */
  public function getAll(string $start, string $end, string $id_staff = null): array
  {
    $et = $this->ecfg['table'];
    $ef = $this->ecfg['arch']['events'];
    $rt = $this->ecfg['tables']['recurring'];
    $rf = $this->ecfg['arch']['recurring'];
    $where = [
      'logic' => 'OR',
      'conditions' => [[
        'conditions' => [[
          'field' => $this->db->colFullName($ef['start'], $et),
          'operator' => '<=',
          'value' => $end
        ], [
          'logic' => 'OR',
          'conditions' => [[
            'field' => $this->db->colFullName($ef['end'], $et),
            'operator' => '>=',
            'value' => $start
          ], [
            'field' => $this->db->colFullName($ef['end'], $et),
            'operator' => 'isnull'
          ]]
        ]]
      ], [
        'conditions' => [[
          'field' => $this->db->colFullName($ef['start'], $et),
          'operator' => '<=',
          'value' => $start
        ], [
          'field' => $this->db->colFullName($ef['recurring'], $et),
          'value' => 1
        ]]
      ]]
    ];
    if ( \bbn\Str::isUid($id_staff) ){
      $where = [
        'conditions' => [[
          'field' => $this->db->colFullName($this->fields['id_staff'], $this->class_table),
          'value' => $id_staff  
        ], $where]
      ];
    }
    if ( $events = $this->db->rselectAll([
      'table' => $this->class_table,
      'fields' => [
        $this->db->colFullName($this->fields['id'], $this->class_table),
        $this->db->colFullName($this->fields['id_staff'], $this->class_table),
        $this->db->colFullName($this->fields['id_event'], $this->class_table),
        $this->db->colFullName($this->fields['id_alias'], $this->class_table),
        $this->db->colFullName($this->fields['alias'], $this->class_table),
        $this->db->colFullName($ef['id_parent'], $et),
        $ef['id_type'],
        $ef['start'],
        $ef['end'],
        $ef['name'],
        $ef['recurring'],
        $ef['cfg'],
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
        'table' => $et,
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName($ef['id'], $et),
            'exp' => $this->db->colFullName($this->fields['id_event'], $this->class_table)
          ]]
        ]
      ], [
        'table' => $rt,
        'type' => 'left',
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName($ef['id'], $et),
            'exp' => $this->db->colFullName($rf['id_event'], $rt),
          ]]
        ]
      ], [
        'table' => $this->ocfg['table'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName($ef['id_type'], $et),
            'exp' => $this->db->colFullName($this->ocfg['arch']['options']['id'], $this->ocfg['table']),
          ]]
        ]
      ]],
      'where' => $where
    ]) ){
      return $this->analyze($start, $end, $events);
    }
    return [];
  }

  /**
   * Gets all events of a period.
   * @param string $id_alias
   * @param string $start
   * @param string $end
   * @param string|null $id_staff
   * @return array
   */
  public function getAllByAlias(string $id_alias, ?string $start = null, ?string $end = null, ?string $id_staff = null): array
  {
    $et = $this->ecfg['table'];
    $ef = $this->ecfg['arch']['events'];
    $rt = $this->ecfg['tables']['recurring'];
    $rf = $this->ecfg['arch']['recurring'];
    $where = [
      'conditions' => [[
        'field' => $this->db->colFullName($this->fields['id_alias'], $this->class_table),
        'value' => $id_alias
      ]]
    ];
    if ( \bbn\Str::isUid($id_staff) ){
      $where['conditions'][] = [
        'field' => $this->db->colFullName($this->fields['id_staff'], $this->class_table),
        'value' => $id_staff  
      ];
    }
    if ( !empty($start) && !empty($end) ){
      $where['conditions'][] = [
        'logic' => 'OR',
        'conditions' => [[
          'conditions' => [[
            'field' => $this->db->colFullName($ef['start'], $et),
            'operator' => '<=',
            'value' => $end
          ], [
            'logic' => 'OR',
            'conditions' => [[
              'field' => $this->db->colFullName($ef['end'], $et),
              'operator' => '>=',
              'value' => $start
            ], [
              'field' => $this->db->colFullName($ef['end'], $et),
              'operator' => 'isnull'
            ]]
          ]]
        ], [
          'conditions' => [[
            'field' => $this->db->colFullName($ef['start'], $et),
            'operator' => '<=',
            'value' => $start
          ], [
            'field' => $this->db->colFullName($ef['recurring'], $et),
            'value' => 1
          ]]
        ]]
      ];
    }
    if ( $events = $this->db->rselectAll([
      'table' => $this->class_table,
      'fields' => [
        $this->db->colFullName($this->fields['id'], $this->class_table),
        $this->db->colFullName($this->fields['id_staff'], $this->class_table),
        $this->db->colFullName($this->fields['id_event'], $this->class_table),
        $this->db->colFullName($this->fields['id_alias'], $this->class_table),
        $this->db->colFullName($this->fields['alias'], $this->class_table),
        $this->db->colFullName($ef['id_parent'], $et),
        $ef['id_type'],
        $ef['start'],
        $ef['end'],
        $ef['name'],
        $ef['recurring'],
        $ef['cfg'],
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
        'table' => $et,
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName($ef['id'], $et),
            'exp' => $this->db->colFullName($this->fields['id_event'], $this->class_table)
          ]]
        ]
      ], [
        'table' => $rt,
        'type' => 'left',
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName($ef['id'], $et),
            'exp' => $this->db->colFullName($rf['id_event'], $rt),
          ]]
        ]
      ], [
        'table' => $this->ocfg['table'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName($ef['id_type'], $et),
            'exp' => $this->db->colFullName($this->ocfg['arch']['options']['id'], $this->ocfg['table']),
          ]]
        ]
      ]],
      'where' => $where
    ]) ){
      if ( !empty($start) && !empty($end) ){
        return $this->analyze($start, $end, $events);
      }
      return $events;
    }
    return [];
  }

  /**
   * Analyzes an events list and returns it.
   * @param string $start
   * @param string $end
   * @return array
   */
  public function analyze(string $start, string $end, array $events): array
  {
    $ret = [];
    foreach ( $events as $event ){
      // Recurring event
      if ( 
        !empty($event[$this->ecfg['arch']['events']['recurring']]) &&
        ($rec = $this->events->getRecurrences($start, $end, $event))
      ){
        array_push($ret, ...$rec);
      }
      // Normal event
      if ( 
        ($event[$this->ecfg['arch']['events']['start']] >= $start) && 
        ($event[$this->ecfg['arch']['events']['start']] <= $end) 
      ){
        $event['recurrence'] = 0;
        $ret[] = $event;
      }
    }
    return $ret;
  }

  /**
   * Gets the planning's id_event.
   * @param string $id
   * @return string|null
   */
  public function getIdEvent(string $id): ?string
  {
    if ( \bbn\Str::isUid($id) ){
      return $this->db->selectOne($this->class_table, $this->fields['id_event'], [$this->fields['id'] => $id]);
    }
    return null;
  }

/**
 * Gets the planning's id_staff.
 * @param string $id
 * @return string|null
 */
  public function getIdStaff(string $id): ?string
  {
    if ( \bbn\Str::isUid($id) ){
      return $this->db->selectOne($this->class_table, $this->fields['id_staff'], [$this->fields['id'] => $id]);
    }
    return null;
  }

  /**
   * Checks if the staff is available on the given period
   * @param string $id_staff
   * @param string $start
   * @param string $end
   * @return bool
   */
  public function isAvailable(string $id_staff, string $start, string $end): bool
  {
    $set = $this->class_cfg['tables']['staff_events'];
    $sef = $this->class_cfg['arch']['staff_events'];
    $ecfg = $this->getEvents()->getClassCfg();
    $et = $ecfg['tables']['events'];
    $ef = $ecfg['arch']['events'];
    return !$this->db->rselect([
      'table' => $set,
      'fields' => [$this->db->colFullName($sef['id_event'], $set)],
      'join' => [[
        'table' => $et,
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName($ef['id'], $et),
            'exp' => $this->db->colFullName($sef['id_event'], $set)
          ], [
            'field' => $this->db->colFullName($ef['start'], $et),
            'operator' => '<=',
            'value' => $start
          ], [
            'field' => $this->db->colFullName($ef['end'], $et),
            'operator' => '>=',
            'value' => $end
          ]]
        ]
      ]],
      'where' => [
        'conditions' => [[
          'field' => $this->db->colFullName($sef['id_staff'], $set),
          'value' => $id_staff
        ], [
          'field' => $this->db->colFullName($sef['status'], $set),
          'value' => 'accepted'
        ]]
      ]
    ]);

  }

  /**
   * Checks if the staff event is replaced on the given day
   * @param string $id_planning
   * @param string $day
   * @return bool
   */
  public function isReplaced(string $id_planning, string $day): bool
  {
    $t =& $this;
    return !!array_filter($this->getAllByAlias($id_planning), function($a) use($day, $t){
      return $a[$t->fields['alias']] === date('Y-m-d', strtotime($day));
    });
  }
}