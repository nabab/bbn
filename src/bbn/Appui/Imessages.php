<?php
/**
 * Created by BBN Solutions.
 * User: Mirko Argentino
 * Date: 15/03/2018
 * Time: 17:08
 */

namespace bbn\Appui;

use bbn;

class Imessages extends \bbn\Models\Cls\Db
{
  use bbn\Models\Tts\References;
  use bbn\Models\Tts\Dbconfig;

  const BBN_APPUI = 'appui';
  const BBN_NOTES = 'note';
  const BBN_NOTES_TYPES = 'types';
  const BBN_IMESSAGES = 'imessages';
  const BBN_DEFAULT_PERM = 'dashboard/home';

  /** @var array */
  protected static $default_class_cfg = [
    'table' => 'bbn_imessages',
    'tables' => [
      'imessages' => 'bbn_imessages',
      'users' => 'bbn_imessages_users'
    ],
    'arch' => [
      'imessages' => [
        'id' => 'id',
        'id_note' => 'id_note',
        'id_option' => 'id_option',
        'id_user' => 'id_user',
        'id_group' => 'id_group',
        'start' => 'start',
        'end' => 'end'
      ],
      'users' => [
        'id_imessage' => 'id_imessage',
        'id_user' => 'id_user',
        'hidden' => 'hidden',
        'moment' => 'moment'
      ]
    ]
  ];

  private static $id_type = null;

  private $notes;
  private $options;


  /**
   * @return bool
   */
  private function _id_type(): ?string
  {
    if (!self::$id_type) {
      if ($id = $this->options->fromCode(self::BBN_IMESSAGES, self::BBN_NOTES_TYPES, self::BBN_NOTES, self::BBN_APPUI)) {
        self::setIdType($id);
      }
    }
    return self::$id_type;
  }

  /**
   * @param $id
   */
  private static function setIdType(string $id): void
  {
    self::$id_type = $id;
  }

  /**
   * Gets internal messages' info from notes archive
   *
   * @param array $messages
   * @param bool  $simple
   * @return array
   */
  private function fromNotes(array $messages, bool $simple = true): array
  {
    if (!empty($messages)) {
      foreach ($messages as $idx => $mess){
        if (empty($mess['id_note'])) {
          unset($messages[$idx]);
          continue;
        }
        $note = $this->notes->get($mess['id_note']);
        $note = [
          'id' => $mess['id'],
          'title' => $note['title'],
          'content' => $note['content']
        ];
        if ($simple) {
          $messages[$idx] = $note;
        }
        else {
          $messages[$idx] = \bbn\X::mergeArrays($messages[$idx], $note);
        }
      }
    }
    return $messages;
  }

  /**
   * imessages constructor.
   * @param \bbn\Db $db
   */
  public function __construct(bbn\Db $db, $cfg = [])
  {
    parent::__construct($db);
    $this->_init_class_cfg($cfg);
    $this->notes = new \bbn\Appui\Note($this->db);
    $this->options = \bbn\Appui\Option::getInstance();
  }

  /**
   * Inserts a new page's internal message
   *
   * @param $imess
   * @return bool|int
   */
  public function insert($imess): ?string
  {
    $res = null;
    $cfg =& $this->class_cfg;
    // Get default page if it isn't set
    if (empty($imess['id_option'])) {
      $perm = new \bbn\User\Permissions();
      $imess['id_option'] = $perm->is(self::BBN_DEFAULT_PERM);
    }
    if (!empty($imess['id_option']) 
        && !empty($imess['title']) 
        && !empty($imess['content']) 
        && !empty($this->_id_type()) 
        // Insert the new note
        && ($id_note = $this->notes->insert($imess['title'], $imess['content'], $this->_id_type())) 
        // Insert the new internal message
        && $this->db->insert(
          $cfg['table'],
          [
            $cfg['arch']['imessages']['id_note'] => $id_note,
            $cfg['arch']['imessages']['id_option'] => $imess['id_option'],
            $cfg['arch']['imessages']['id_user'] => $imess['id_user'] ?: null,
            $cfg['arch']['imessages']['id_group'] => $imess['id_group'] ?: null,
            $cfg['arch']['imessages']['start'] => $imess['start'] ?: null,
            $cfg['arch']['imessages']['end'] => $imess['end'] ?: null
          ]
        )
    ) {
      $res = $this->db->lastId();
    }
    return $res;
  }

  /**
   * Gets the page's internal messages of an user
   *
   * @param string $id_option
   * @param string $id_user
   * @param bool   $simple
   * @return array
   */
  public function get(string $id_option, string $id_user, $simple = true)
  {
    $cfg =& $this->class_cfg;
    // Current datetime
    $now = date('Y-m-d H:i:s');
    // Get the user's group
    $id_group = $this->db->selectOne('bbn_users', 'id_group', ['id' => $id_user]);
    $db =& $this->db;
    $fields = array_map(
      function ($a) use ($db, $cfg) {
        return $db->cfn($a, $cfg['table']);
      },
      array_keys($this->db->getColumns($cfg['table']))
    );
    // Get the page's internal messages of the user
    $messages = $this->db->rselectAll(
      [
        'tables' => [$cfg['tables']['users']],
        'fields' => $fields,
        'join' => [
          [
            'table' => $cfg['table'],
            'on' => [
              [
                'field' => $this->db->cfn($cfg['arch']['imessages']['id'], $cfg['table']),
                'exp' => $this->db->cfn($cfg['arch']['users']['id_imessage'], $cfg['tables']['users'])
              ]
            ]
          ]
        ],
        'where' => [
          'conditions' => [
            [
              'field' => $this->db->cfn($cfg['arch']['imessages']['id_option'], $cfg['table']),
              'value' => $id_option
            ], [
              'logic' => 'OR',
              'conditions' => [
                [
                  'field' => $this->db->cfn($cfg['arch']['imessages']['start'], $cfg['table']),
                  'operator' => 'isnull'
                ], [
                  'field' => $this->db->cfn($cfg['arch']['imessages']['start'], $cfg['table']),
                  'operator' => '<=',
                  'exp' => 'NOW()'
                ]
              ]
            ], [
              'logic' => 'OR',
              'conditions' => [
                [
                  'field' => $this->db->cfn($cfg['arch']['imessages']['end'], $cfg['table']),
                  'operator' => 'isnull'
                ], [
                  'field' => $this->db->cfn($cfg['arch']['imessages']['end'], $cfg['table']),
                  'operator' => '>',
                  'exp' => 'NOW()'
                ]
              ]
            ], [
              'logic' => 'OR',
              'conditions' => [
                [
                  'field' => $this->db->cfn($cfg['arch']['imessages']['id_user'], $cfg['table']),
                  'value' => $id_user
                ], [
                  'field' => $this->db->cfn($cfg['arch']['imessages']['id_group'], $cfg['table']),
                  'value' => $id_group
                ], [
                  'logic' => 'OR',
                  'conditions' => [
                    [
                      'field' => $this->db->cfn($cfg['arch']['imessages']['id_user'], $cfg['table']),
                      'operator' => 'isnull'
                    ], [
                      'field' => $this->db->cfn($cfg['arch']['imessages']['id_group'], $cfg['table']),
                      'operator' => 'isnull'
                    ]
                  ]
                ]
              ]
            ], [
              'logic' => 'OR',
              'conditions' => [
                [
                  'field' => $this->db->cfn($cfg['arch']['users']['hidden'], $cfg['tables']['users']),
                  'operator' => 'isnull'
                ], [
                  'field' => $this->db->cfn($cfg['arch']['users']['hidden'], $cfg['tables']['users']),
                  'operator' => '=',
                  'value' => 0
                ]
              ]
            ]
          ]
        ]
      ]
    );
    if ($res = $this->fromNotes($messages, $simple)) {
      // Get and return the imessage's content|title from notes archive
      return $res;
    }
    return null;
  }

  /**
   * Sets an user's internal message as visible
   *
   * @param string $id_imess
   * @param string $id_user
   * @return bool
   */
  public function setHidden(string $id_imess, string $id_user)
  {
    $cfg =& $this->class_cfg;
    if (!empty($id_imess) && !empty($id_user)) {
      return (bool)$this->db->insertUpdate(
        $cfg['tables']['users'], [
        $cfg['arch']['users']['id_imessage'] => $id_imess,
        $cfg['arch']['users']['id_user'] => $id_user,
        $cfg['arch']['users']['hidden'] => 1,
        $cfg['arch']['users']['moment'] => date('Y-m-d H:i:s'),
        ], [
        $cfg['arch']['users']['id_imessage'] => $id_imess,
        $cfg['arch']['users']['id_user'] => $id_user
        ]
      );
    }
    return false;
  }

  /**
   * Sets an user's internal message as not visible
   *
   * @param string $id_imess
   * @param string $id_user
   * @return bool
   */
  public function unsetHidden(string $id_imess, string $id_user)
  {
    $cfg =& $this->class_cfg;
    if (!empty($id_imess) && !empty($id_user)) {
      return (bool)$this->db->updateIgnore(
        $cfg['tables']['users'], [
        $cfg['arch']['users']['id_imessage'] => $id_imess,
        $cfg['arch']['users']['id_user'] => $id_user,
        $cfg['arch']['users']['hidden'] => 0,
        $cfg['arch']['users']['moment'] => date('Y-m-d H:i:s'),
        ], [
        $cfg['arch']['users']['id_imessage'] => $id_imess,
        $cfg['arch']['users']['id_user'] => $id_user
        ]
      );
    }
    return false;
  }

  public function getByPerm(string $id_option, $simple = true)
  {
    $cfg =& $this->class_cfg;
    $messages = $this->db->rselectAll(
      $cfg['table'],
      [],
      [$cfg['arch']['imessages']['id_option'] => $id_option]
    );
    // Get and return the imessage's content|title from notes archive
    return $this->fromNotes($messages, $simple);
  }


  /**
   * Gets all user's internal messages
   *
   * @param string $id_user
   * @return array
   */
  public function getByUser(string $id_user)
  {
    $cfg =& $this->class_cfg;
    // Current datetime
    $now = date('Y-m-d H:i:s');
    // Get the user's group
    $id_group = $this->db->selectOne('bbn_users', 'id_group', ['id' => $id_user]);
    $fields = array_map(
      function ($a) use ($db, $cfg) {
        return $db->cfn($a, $cfg['table']);
      },
      array_keys($this->db->getColumns($cfg['table']))
    );
    // Get all user's internal messages
    $messages = $this->db->rselectAll(
      [
        'tables' => [$cfg['tables']['users']],
        'fields' => $fields,
        'join' => [
          [
            'table' => $cfg['table'],
            'on' => [
              [
                'field' => $this->db->cfn($cfg['arch']['imessages']['id_user'], $cfg['table']),
                'exp' => $this->db->cfn($cfg['arch']['users']['id_user'], $cfg['tables']['users'])
              ], [
                'field' => $this->db->cfn($cfg['arch']['imessages']['id'], $cfg['table']),
                'exp' => $this->db->cfn($cfg['arch']['users']['id_imessage'], $cfg['tables']['users'])
              ]
            ]
          ]
        ],
        'where' => [
          'conditions' => [
            [
              'logic' => 'OR',
              'conditions' => [
                [
                  'field' => $this->db->cfn($cfg['arch']['imessages']['start'], $cfg['table']),
                  'operator' => 'isnull'
                ], [
                  'field' => $this->db->cfn($cfg['arch']['imessages']['start'], $cfg['table']),
                  'operator' => '<=',
                  'exp' => 'NOW()'
                ]
              ]
            ], [
              'logic' => 'OR',
              'conditions' => [
                [
                  'field' => $this->db->cfn($cfg['arch']['imessages']['end'], $cfg['table']),
                  'operator' => 'isnull'
                ], [
                  'field' => $this->db->cfn($cfg['arch']['imessages']['end'], $cfg['table']),
                  'operator' => '>',
                  'exp' => 'NOW()'
                ]
              ]
            ], [
              'logic' => 'OR',
              'conditions' => [
                [
                  'field' => $this->db->cfn($cfg['arch']['imessages']['id_user'], $cfg['table']),
                  'value' => $id_user
                ], [
                  'field' => $this->db->cfn($cfg['arch']['imessages']['id_group'], $cfg['table']),
                  'value' => $id_group
                ], [
                  'logic' => 'OR',
                  'conditions' => [
                    [
                      'field' => $this->db->cfn($cfg['arch']['imessages']['id_user'], $cfg['table']),
                      'operator' => 'isnull'
                    ], [
                      'field' => $this->db->cfn($cfg['arch']['imessages']['id_group'], $cfg['table']),
                      'operator' => 'isnull'
                    ]
                  ]
                ]
              ]
            ], [
              'logic' => 'OR',
              'conditions' => [
                [
                  'field' => $this->db->cfn($cfg['arch']['users']['hidden'], $cfg['tables']['users']),
                  'operator' => 'isnull'
                ], [
                  'field' => $this->db->cfn($cfg['arch']['users']['hidden'], $cfg['tables']['users']),
                  'operator' => '=',
                  'value' => 0
                ]
              ]
            ]
          ]
        ]
      ]
    );
    // Get and return the imessage's info from notes archive
    return $this->fromNotes($messages);
  }
}
