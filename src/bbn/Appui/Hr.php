<?php

namespace bbn\Appui;

use bbn\Db;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use bbn\Models\Tts\Optional;

/**
 * HR class
 * @category Appui
 * @package Appui
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link https://bbn.io/bbn-php/doc/class/Appui/Hr
 */
class Hr extends DbCls
{
  use DbActions;
  use Optional;

  protected static $default_class_cfg = [
    'errors' => [],
    'table' => 'bbn_hr_staff',
    'tables' => [
      'staff' => 'bbn_hr_staff',
      'staff_planning' => 'bbn_hr_planning',
      'staff_events' => 'bbn_hr_staff_events'
    ],
    'arch' => [
      'staff' => [
        'id' => 'id',
        'id_user' => 'id_user'
      ],
      'staff_planning' => [
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
    ],
  ];

  /**
   * Constructor
   * @param \bbn\Db $db
   * @param array $cfg
   */
  public function __construct(Db $db, array $cfg = null)
  {
    parent::__construct($db);
    $this->_init_class_cfg($cfg);
    self::optionalInit();
  }


  public function getStaff(bool $onlyActive = false): ?array
  {
    $join =[[
      'table' => 'bbn_people',
      'on' => [
        'conditions' => [[
          'field' => $this->db->cfn($this->fields['id'], $this->class_table),
          'exp' => 'bbn_people.id'
        ]]
      ]
    ]];

    if (!$onlyActive) {
      $join[] = [
        'table' => 'bbn_history_uids',
        'on' => [
          'conditions' => [[
            'field' => 'bbn_people.id',
            'exp' => 'bbn_history_uids.bbn_uid'
          ]]
        ]
      ];
    }

    return $this->db->rselectAll([
      'table' => $this->class_table,
      'fields' => [
        'value' => 'bbn_people.id',
        'text' => 'bbn_people.fullname',
        $this->fields['id_user']
      ],
      'join' => $join,
      'order' => [
        'text' => 'ASC',
        $this->fields['id'] => 'ASC'
      ]
    ]);
  }


  public function getActiveStaff(): ?array
  {
    return $this->getStaff(true);
  }
}