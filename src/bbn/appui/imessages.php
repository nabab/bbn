<?php
/**
 * Created by BBN Solutions.
 * User: Mirko Argentino
 * Date: 15/03/2018
 * Time: 17:08
 */

namespace bbn\appui;


class imessages extends \bbn\models\cls\db
{
  use
    \bbn\models\tts\references,
    \bbn\models\tts\dbconfig;

  const BBN_APPUI = 'appui',
        BBN_NOTES = 'notes',
        BBN_NOTES_TYPES = 'types',
        BBN_IMESSAGES = 'imessages',
        BBN_DEFAULT_PERM = 'dashboard/home';

  protected static
    /** @var array */
    $_defaults = [
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
          'end' => 'end',
          'active' => 'actif'
        ],
        'users' => [
          'id_imessage' => 'id_imessage',
          'id_user' => 'id_user',
          'saw' => 'saw',
          'moment' => 'moment'
        ]
      ]
    ];

  private static
    $id_type = false;

  private
    $notes,
    $options;


  /**
   * @return bool
   */
  private function _id_type(){
    if ( !self::$id_type ){
      if ( $id = $this->options->from_code(self::BBN_IMESSAGES, self::BBN_NOTES_TYPES, self::BBN_NOTES, self::BBN_APPUI) ){
        self::set_id_type($id);
      }
    }
    return self::$id_type;
  }

  /**
   * @param $id
   */
  private static function set_id_type($id){
    self::$id_type = $id;
  }

  /**
   * Gets internal messages' info from notes archive
   *
   * @param array $messages
   * @param bool $simple
   * @return array
   */
  private function from_notes(array $messages, $simple = true){
    if ( !empty($messages) ){
      foreach ( $messages as $idx => $mess ){
        if ( empty($mess['id_note']) ){
          unset($messages[$idx]);
          continue;
        }
        $note = $this->notes->get($mess['id_note']);
        $note = [
          'id' => $mess['id'],
          'title' => $note['title'],
          'content' => $note['content']
        ];
        if ( $simple ){
          $messages[$idx] = $note;
        }
        else {
          $messages[$idx] = \bbn\x::merge_arrays($messages[$idx], $note);
        }
      }
    }
    return $messages;
  }

  /**
   * imessages constructor.
   * @param \bbn\db $db
   */
  public function __construct(\bbn\db $db){
    parent::__construct($db);
    self::_init_class_cfg(self::$_defaults);
    $this->notes = new \bbn\appui\notes($this->db);
    $this->options = \bbn\appui\options::get_instance();
  }

  /**
   * Inserts a new page's internal message
   *
   * @param $imess
   * @return bool|int
   */
  public function insert($imess){
    $cfg =& $this->class_cfg;
    // Get default page if it isn't set
    if ( empty($imess['id_option']) ){
      $perm = new \bbn\user\permissions();
      $imess['id_option'] = $perm->is(self::BBN_DEFAULT_PERM);
    }
    if (
      !empty($imess['id_option']) &&
      !empty($imess['title']) &&
      !empty($imess['content']) &&
      !empty($this->_id_type()) &&
      // Insert the new note
      ($id_note = $this->notes->insert($imess['title'], $imess['content'], $this->_id_type())) &&
      // Insert the new internal message
      $this->db->insert($cfg['table'], [
        $cfg['arch']['imessages']['id_note'] => $id_note,
        $cfg['arch']['imessages']['id_option'] => $imess['id_option'],
        $cfg['arch']['imessages']['id_user'] => $imess['id_user'] ?: NULL,
        $cfg['arch']['imessages']['id_group'] => $imess['id_group'] ?: NULL,
        $cfg['arch']['imessages']['start'] => $imess['start'] ?: NULL,
        $cfg['arch']['imessages']['end'] => $imess['end'] ?: NULL
      ])
    ){
      return $this->db->last_id();
    }
    return false;
  }

  /**
   * Gets the page's internal messages of an user
   *
   * @param string $id_option
   * @param string $id_user
   * @param bool $simple
   * @return array
   */
  public function get(string $id_option, string $id_user, $simple = true){
    $cfg =& $this->class_cfg;
    // Current datetime
    $now = date('Y-m-d H:i:s');
    // Get the user's group
    $id_group = $this->db->select_one('bbn_users', 'id_group', ['id' => $id_user]);
    // Get the page's internal messages of the user
    $messages = $this->db->get_rows("
      SELECT {$cfg['table']}.*
      FROM {$cfg['tables']['users']}
        RIGHT JOIN {$cfg['table']}
	        ON {$cfg['table']}.{$cfg['arch']['imessages']['id_user']} = {$cfg['tables']['users']}.{$cfg['arch']['users']['id_user']}
	        AND {$cfg['table']}.{$cfg['arch']['imessages']['id']} = {$cfg['tables']['users']}.{$cfg['arch']['users']['id_imessage']}
      WHERE (
        {$cfg['table']}.{$cfg['arch']['imessages']['start']} IS NULL
        OR {$cfg['table']}.{$cfg['arch']['imessages']['start']} >= ?
      )
      AND (
        {$cfg['table']}.{$cfg['arch']['imessages']['end']} IS NULL
        OR {$cfg['table']}.{$cfg['arch']['imessages']['end']} < ?
      )
      AND {$cfg['table']}.{$cfg['arch']['imessages']['active']} = 1
      AND (
        {$cfg['table']}.{$cfg['arch']['imessages']['id_user']} = ?
        OR {$cfg['table']}.{$cfg['arch']['imessages']['id_group']} = ?
        OR (
          {$cfg['table']}.{$cfg['arch']['imessages']['id_user']} IS NULL
          AND {$cfg['table']}.{$cfg['arch']['imessages']['id_group']} IS NULL
        )
      )
      AND {$cfg['table']}.{$cfg['arch']['imessages']['id_option']} = ?
      AND (
        {$cfg['tables']['users']}.{$cfg['arch']['users']['saw']} IS NULL
        OR {$cfg['tables']['users']}.{$cfg['arch']['users']['saw']} = 0
      )",
      $now,
      $now,
      hex2bin($id_user),
      hex2bin($id_group),
      hex2bin($id_option)
    );
    // Get and return the imessage's content|title from notes archive
    return $this->from_notes($messages, $simple);
  }

  /**
   * Sets an user's internal message as saw
   *
   * @param string $id_imess
   * @param string $id_user
   * @return bool
   */
  public function set_saw(string $id_imess, string $id_user){
    $cfg =& $this->class_cfg;
    if ( !empty($id_imess) && !empty($id_user) ){
      return !!$this->db->insert_update($cfg['tables']['users'], [
        $cfg['arch']['users']['id_imessage'] => $id_imess,
        $cfg['arch']['users']['id_user'] => $id_user,
        $cfg['arch']['users']['saw'] => 1,
        $cfg['arch']['users']['moment'] => date('Y-m-d H:i:s'),
      ], [
        $cfg['arch']['users']['id_imessage'] => $id_imess,
        $cfg['arch']['users']['id_user'] => $id_user
      ]);
    }
    return false;
  }


  /**
   * Gets all user's internal messages
   *
   * @param string $id_user
   * @return array
   */
  public function get_by_user(string $id_user){
    $cfg =& $this->class_cfg;
    // Current datetime
    $now = date('Y-m-d H:i:s');
    // Get the user's group
    $id_group = $this->db->select_one('bbn_users', 'id_group', ['id' => $id_user]);
    // Get all user's internal messages
    $messages = $this->db->get_rows("
      SELECT {$cfg['table']}.*
      FROM {$cfg['tables']['users']}
        RIGHT JOIN {$cfg['table']}
	        ON {$cfg['table']}.{$cfg['arch']['imessages']['id_user']} = {$cfg['tables']['users']}.{$cfg['arch']['users']['id_user']}
	        AND {$cfg['table']}.{$cfg['arch']['imessages']['id']} = {$cfg['tables']['users']}.{$cfg['arch']['users']['id_imessage']}
      WHERE (
        {$cfg['table']}.{$cfg['arch']['imessages']['start']} IS NULL
        OR {$cfg['table']}.{$cfg['arch']['imessages']['start']} >= ?
      )
      AND (
        {$cfg['table']}.{$cfg['arch']['imessages']['end']} IS NULL
        OR {$cfg['table']}.{$cfg['arch']['imessages']['end']} < ?
      )
      AND {$cfg['table']}.{$cfg['arch']['imessages']['active']} = 1
      AND (
        (
          {$cfg['table']}.{$cfg['arch']['imessages']['id_user']} = ?
          OR {$cfg['table']}.{$cfg['arch']['imessages']['id_group']} = ?
        )
        OR (
          {$cfg['table']}.{$cfg['arch']['imessages']['id_user']} IS NULL
          OR {$cfg['table']}.{$cfg['arch']['imessages']['id_group']} IS NULL
        )
      )
      AND (
        {$cfg['tables']['users']}.{$cfg['arch']['users']['saw']} IS NULL
        OR {$cfg['tables']['users']}.{$cfg['arch']['users']['saw']} = 0
      )",
      $now,
      $now,
      hex2bin($id_user),
      hex2bin($id_group)
    );
    // Get and return the imessage's info from notes archive
    return $this->from_notes($messages);
  }


}