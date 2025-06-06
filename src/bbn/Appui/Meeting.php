<?php
namespace bbn\Appui;

use Exception;
use bbn\Str;
use bbn\X;
use bbn\User\Preferences;
use bbn\Models\Tts\Optional;
use bbn\Models\Tts\DbActions;
use bbn\Models\Cls\Db as DbCls;
use bbn\Appui\Passwords;

/**
 * Meeting management in Appui
 * @category Appui
 * @package Appui
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link https://bbn.io/bbn-php/doc/class/Appui/Meeting
 */
class Meeting extends DbCls
{
  use Optional;
  use DbActions;

  private $opt;

  private $optFields;

  private $prefTable;

  private $prefFields;

  private $passCls;

  /** @var array Database architecture schema */
  protected static $default_class_cfg = [
    'table' => 'bbn_meetings',
    'tables' => [
      'meetings' => 'bbn_meetings',
      'participants' => 'bbn_meetings_participants'
    ],
    'arch' => [
      'meetings' => [
        'id' => 'id',
        'id_room' => 'id_room',
        'id_tmp' => 'id_tmp',
        'started' => 'started',
        'ended' => 'ended'
      ],
      'participants' => [
        'id' => 'id',
        'id_meeting' => 'id_meeting',
        'id_tmp' => 'id_tmp',
        'id_user' => 'id_user',
        'name' => 'name',
        'invited' => 'invited',
        'joined' => 'joined',
        'leaved' => 'leaved'
      ]
    ]
  ];

  /**
   * Constructor.
   * @param \bbn\Db $db
   */
  public function __construct(\bbn\Db $db)
  {
    parent::__construct($db);
    $this->initClassCfg();
    self::optionalInit();
    $this->opt = self::getOptionsObject();
    $optCfg = $this->opt->getClassCfg();
    if (!$optCfg) {
      throw new Exception(_('No configuration found for the Option class'));
    }
    $this->optFields = $optCfg['arch']['options'];
    $pref = Preferences::getInstance();
    if (!empty($pref)) {
      $prefCfg = $pref->getClassCfg();
      if (!$prefCfg) {
        throw new Exception(_('No configuration found for the Preferences class'));
      }
      $this->prefTable = $prefCfg['table'];
      $this->prefFields = $prefCfg['arch']['user_options'];
      $this->passCls = new Passwords($this->db);
    }
  }


  public function getRoom(string $idRoom): ?array
  {
    if (!Str::isUid($idRoom)) {
      throw new Exception(_('The room id given is not a uuid'));
    }
    if (($room = $this->db->rselect($this->prefTable, [], [$this->prefFields['id'] => $idRoom]))
      && Str::isJson($room[$this->prefFields['cfg']])
    ) {
      $room = \array_merge($room, \json_decode($room[$this->prefFields['cfg']], true));
      unset($room[$this->prefFields['cfg']]);
    }
    return $room;
  }


  public function getRooms(string $server, string|null $idUser = null, string|null $idGroup = null)
  {
    if (!Str::isUid($server)) {
      $server = $this->getOptionId($server);
    }
    if (!Str::isUid($server)) {
      throw new Exception(_('The server id given is not a uuid'));
    }
    $where = [
      'conditions' => [[
        'field' => $this->prefFields['id_option'],
        'value' => $server
      ], [
        'field' => $this->prefFields['id_alias'],
        'operator' => 'isnull'
      ], [
        'logic' => 'OR',
        'conditions' => [[
          'field' => $this->prefFields['public'],
          'value' => 1
        ]]
      ]]
    ];
    if (!empty($idUser)) {
      $where['conditions'][2]['conditions'][] = [
        'field' => $this->prefFields['id_user'],
        'value' => $idUser
      ];
    }
    if (!empty($idGroup)) {
      $where['conditions'][2]['conditions'][] = [
        'field' => $this->prefFields['id_group'],
        'value' => $idGroup
      ];
    }
    $rooms = $this->db->rselectAll([
      'table' => $this->prefTable,
      'fields' => [],
      'where' => $where,
      'order' => [[
        'field' => $this->prefFields['text'],
        'dir' => 'ASC'
      ]]
    ]);
    if (!empty($rooms)) {
      foreach ($rooms as $i => $r) {
        $rooms[$i]['moderators'] = $this->getModerators($r[$this->prefFields['id']]);
        if (Str::isJson($r[$this->prefFields['cfg']])) {
          $rooms[$i] = \array_merge($rooms[$i], \json_decode($r[$this->prefFields['cfg']], true));
          unset($rooms[$i][$this->prefFields['cfg']]);
        }
      }
    }
    return $rooms;
  }


  public function getAllRooms(string|null $idUser = null, string|null $idGroup = null): ?array
  {
    $servers = $this->getOptionsIds('list');
    if (!empty($servers)) {
      $servers = \array_values($servers);
      $serverWhere = [];
      if (\count($servers) > 1) {
        $serverWhere['logic'] = 'OR';
        $serverWhere['conditions'] = [];
        foreach ($servers as $s) {
          $serverWhere['conditions'][] = [
            'field' => $this->prefFields['id_option'],
            'value' => $s
          ];
        }
      }
      else {
        $serverWhere['field'] = $this->prefFields['id_option'];
        $serverWhere['value'] = $servers[0];
      }

      $where = [
        'conditions' => [[
          'field' => $this->prefFields['id_alias'],
          'operator' => 'isnull'
        ], $serverWhere, [
          'logic' => 'OR',
          'conditions' => [[
            'field' => $this->prefFields['public'],
            'value' => 1
          ]]
        ]]
      ];
      if (!empty($idUser)) {
        $where['conditions'][2]['conditions'][] = [
          'field' => $this->prefFields['id_user'],
          'value' => $idUser
        ];
      }
      if (!empty($idGroup)) {
        $where['conditions'][2]['conditions'][] = [
          'field' => $this->prefFields['id_group'],
          'value' => $idGroup
        ];
      }
      $rooms = $this->db->rselectAll([
        'table' => $this->prefTable,
        'fields' => [],
        'where' => $where,
        'order' => [[
          'field' => $this->prefFields['text'],
          'dir' => 'ASC'
        ]]
      ]);
      if (!empty($idUser)) {
        $meetingsTable = $this->class_cfg['tables']['meetings'];
        $meetingsFields = $this->class_cfg['arch']['meetings'];
        $partsTable = $this->class_cfg['tables']['participants'];
        $partsFields = $this->class_cfg['arch']['participants'];
        $t = $this;
        if ($roomsInvited = $this->db->rselectAll([
          'table' => $this->prefTable,
          'fields' => \array_map(function($f) use ($t){
            return $t->db->colFullName($f, $t->prefTable);
          }, $this->prefFields),
          'join' => [[
            'table' => $meetingsTable,
            'on' => [
              'conditions' => [[
                'field' => $this->db->colFullName($meetingsFields['id_room'], $meetingsTable),
                'exp' => $this->db->colFullName($this->prefFields['id'], $this->prefTable)
              ]]
            ]
          ], [
            'table' => $partsTable,
            'on' => [
              'conditions' => [[
                'field' => $this->db->colFullName($meetingsFields['id'], $meetingsTable),
                'exp' => $this->db->colFullName($partsFields['id_meeting'], $partsTable)
              ]]
            ]
          ]],
          'where' => [
            'conditions' => [[
              'field' => $this->db->colFullName($partsFields['id_user'], $partsTable),
              'value' => $idUser
            ], [
              'field' => $this->db->colFullName($partsFields['invited'], $partsTable),
              'value' => 1
            ]]
          ],
          'group_by' => [$this->db->colFullName($meetingsFields['id_room'], $meetingsTable)]
        ])) {
          foreach ($roomsInvited as $r) {
            if (X::search($rooms, [$this->prefFields['id'] => $r[$this->prefFields['id']]]) === null) {
              $rooms[] = $r;
            }
          }
        }
      }
      X::sortBy($rooms, $this->prefFields['text'], 'asc');
      if (!empty($rooms)) {
        foreach ($rooms as $i => $r) {
          $r['moderators'] = $this->getModerators($r[$this->prefFields['id']]);
          if (Str::isJson($r[$this->prefFields['cfg']])) {
            $r = \array_merge($r, \json_decode($r[$this->prefFields['cfg']], true));
            unset($r[$this->prefFields['cfg']]);
          }
          if ($idMeeting = $this->getStartedMeeting($r[$this->prefFields['id']])) {
            $r['participants'] = $this->getParticipants($idMeeting);
            $r['invited'] = $this->getInvited($idMeeting);
            $r['liveMeeting'] = $idMeeting;
          }
          else {
            $r['participants'] = [];
            $r['invited'] = [];
            $r['liveMeeting'] = null;
          }
          $r['live'] = !empty($idMeeting);
          $last = $this->getLastMeeting($r[$this->prefFields['id']]);
          $r['last'] = !empty($last) ? $last[$this->class_cfg['arch']['meetings']['started']] : '';
          $rooms[$i] = $r;
        }
      }

      return $rooms;
    }

    return null;
  }


  public function getUserRooms(string $server, string $idUser)
  {
    $user = \bbn\User::getInstance();
    $userManager = $user->getManager();
    $userData = $userManager->getUser($idUser);
    $userCfg = $user->getClassCfg();
    $idGroup = $userData[$userCfg['arch']['users']['id_group']] ?? false;
    if (empty($idGroup)) {
      throw new Exception(sprintf(_('No group found for the user %s'), $idUser));
    }
    return $this->getRooms($server, $idUser, $idGroup);
  }


  public function getAllUserRooms(string $idUser)
  {
    $user = \bbn\User::getInstance();
    $userManager = $user->getManager();
    $userData = $userManager->getUser($idUser);
    $userCfg = $user->getClassCfg();
    $idGroup = $userData[$userCfg['arch']['users']['id_group']] ?? false;
    if (empty($idGroup)) {
      throw new Exception(sprintf(_('No group found for the user %s'), $idUser));
    }
    return $this->getAllRooms($idUser, $idGroup);
  }


  public function addRoom(string $server, string $name, $idUser = null, $idGroup = null, array $moderators = []): ?string
  {
    $idServer = self::getOptionId($server, 'list');
    if (!$idServer) {
      throw new Exception(sprintf(_('Server not found %s'), $server));
    }
    if ($this->db->selectOne($this->prefTable, $this->prefFields['id'], [
      $this->prefFields['text'] => $name,
      $this->prefFields['id_option'] => $idServer
    ])) {
      throw new Exception(sprintf(_('The room %s already exists'), $name));
    }
    if ($this->db->insert($this->prefTable, [
      $this->prefFields['text'] => $name,
      $this->prefFields['id_option'] => $idServer,
      $this->prefFields['id_user'] => $idUser,
      $this->prefFields['id_group'] => $idGroup,
      $this->prefFields['public'] => empty($idUser) && empty($idGroup) ? 1 : 0,
      $this->prefFields['cfg'] => \json_encode([
        'created' => date('Y-m-d H:i:s')
      ])
    ])) {
      $idRoom = $this->db->lastId();
      if (!empty($moderators)) {
        foreach ($moderators as $mod) {
          $this->addModerator($mod, $idRoom);
        }
      }
      return $idRoom;
    }
    return null;
  }


  public function editRoom(string $idRoom, string $name, $idUser = null, $idGroup = null, array $moderators = []): bool
  {
    $old = $this->getRoom($idRoom);
    if (!$old) {
      throw new Exception(sprintf(_('Room not found %s'), $idRoom));
    }
    $toUpd = [];
    if (($old[$this->prefFields['id_user']] !== $idUser)){
      $toUpd[$this->prefFields['id_user']] = $idUser;
    }
    if (($old[$this->prefFields['id_group']] !== $idGroup)){
      $toUpd[$this->prefFields['id_group']] = $idGroup;
    }
    $public = !empty($idUser) || !empty($idGroup) ? 0 : 1;
    if (($old[$this->prefFields['public']] !== $public)){
      $toUpd[$this->prefFields['public']] = $public;
    }
    if (($old[$this->prefFields['text']] !== $name)){
      if ($this->db->selectOne($this->prefTable, $this->prefFields['id'], [
        [$this->prefFields['text'], '=', $name],
        [$this->prefFields['id_option'], '=', $old[$this->prefFields['id_option']]],
        [$this->prefFields['id'], '!=', $idRoom]
      ])) {
        throw new Exception(sprintf(_('The room %s already exists'), $name));
      }
      $toUpd[$this->prefFields['text']] = $name;
    }
    if (!empty($toUpd)
      && !$this->db->update($this->prefTable, $toUpd, [$this->prefFields['id'] => $idRoom])
    ) {
      return false;
    }
    // Force re-create the moderator's token if the room's name has changed
    if (\array_key_exists($this->prefFields['text'], $toUpd)) {
      $oldModerators = $this->getModerators($idRoom);
      foreach ($oldModerators as $m) {
        if (!$this->removeModerator($m, $idRoom)) {
          throw new Exception(sprintf(_('Error during the elimination of the moderator %s from the room %s'), $m, $idRoom));
        }
        if (!$this->addModerator($m, $idRoom)) {
          throw new Exception(sprintf(_('Error during the insertion of the moderator %s to the room %s'), $m, $idRoom));
        }
      }
    }
    if (!empty($moderators)) {
      $oldModerators = $this->getModerators($idRoom);
      if (!empty($oldModerators)) {
        foreach ($oldModerators as $m) {
          if (!\in_array($m, $moderators, true)
            && !$this->removeModerator($m, $idRoom)
          ) {
            throw new Exception(sprintf(_('Error during the elimination of the moderator %s from the room %s'), $m, $idRoom));
          }
        }
      }
      foreach ($moderators as $m) {
        if (!\in_array($m, $oldModerators, true)
          && !$this->addModerator($m, $idRoom)
        ) {
          throw new Exception(sprintf(_('Error during the insertion of the moderator %s to the room %s'), $m, $idRoom));
        }
      }
    }
    return true;
  }


  public function removeRoom(string $idRoom): ?int
  {
    if (Str::isUid($idRoom)) {
      return $this->db->delete($this->prefTable, [$this->prefFields['id'] => $idRoom]);
    }
    return null;
  }


  public function addModerator(string $idUser, string $idRoom): bool
  {
    if (Str::isUid($idUser) && Str::isUid($idRoom)) {
      if (!($room = $this->db->rselect($this->prefTable, [], [$this->prefFields['id'] => $idRoom]))) {
        throw new Exception(sprintf(_('No room found with the id %s'), $idRoom));
      }
      if ($this->isModerator($idUser, $idRoom)) {
        return true;
      }
      if ($this->db->insert($this->prefTable, [
        $this->prefFields['id_option'] => $room[$this->prefFields['id_option']],
        $this->prefFields['id_alias'] => $idRoom,
        $this->prefFields['id_user'] => $idUser
      ])) {
        return true;
      }
    }
    return false;
  }


  public function removeModerator(string $idUser, string $idRoom): bool
  {
    if ($mod = $this->getModerator($idUser, $idRoom)) {
      $idModerator = $mod[$this->prefFields['id']];
      return (bool)$this->db->delete($this->prefTable, [$this->prefFields['id'] => $idModerator]);
    }
    return false;
  }


  public function checkModeratorToken(string $idUser, string $idRoom): bool
  {
    if (($r = $this->getRoom($idRoom))
      && ($m = $this->getModerator($idUser, $idRoom))
      && ($u = \bbn\User::getInstance())
    ) {
      $idModerator = $m[$this->prefFields['id']];
      $t = $this->passCls->userGet($idModerator, $u);
      if (empty($t)) {
        if (!($server = $this->getServerByRoom($idRoom))) {
          throw new Exception(sprintf(_('No server found by the room %s'), $idRoom));
        }
        if (!$this->passCls->userStore($this->makeJWT($server[$this->optFields['code']], $r[$this->prefFields['text']]), $idModerator, $u)) {
          throw new Exception(sprintf(_('Error during JWT storing for the user %s for the room %s'), $idUser, $idRoom));
        }
        $t = $this->passCls->userGet($idModerator, $u);
      }
      return !empty($t);
    }
    return false;
  }


  public function getModeratorToken(string $idUser, string $idRoom): ?string
  {
    if (($m = $this->getModerator($idUser, $idRoom))
      && ($u = \bbn\User::getInstance())
    ) {
      $idModerator = $m[$this->prefFields['id']];
      if ($t = $this->passCls->userGet($idModerator, $u)) {
        return $t;
      }
    }
    return null;
  }


  public function isModerator(string $idUser, string $idRoom): bool
  {
    return (bool)$this->getModerator($idUser, $idRoom);
  }


  public function getModerators(string $idRoom, bool $full = false): ?array
  {
    $userCfg = \bbn\User::getInstance()->getClassCfg();
    $userFields = $userCfg['arch']['users'];
    $fields = [
      $this->db->colFullName($userFields['id'], $userCfg['table'])
    ];
    $method = 'getColumnValues';
    if (!empty($full)) {
      $method = 'rselectAll';
      $fields = \array_merge($fields, [
        $this->db->colFullName($userFields['id_group'], $userCfg['table']),
          $this->db->colFullName($userFields['email'], $userCfg['table']),
          $this->db->colFullName($userFields['username'], $userCfg['table']),
          $this->db->colFullName($userFields['login'], $userCfg['table'])
      ]);
    }
    return $this->db->{$method}([
      'table' => $this->prefTable,
      'fields' => $fields,
      'join' => [[
        'table' => $userCfg['table'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName($this->prefFields['id_user'], $this->prefTable),
            'exp' => $this->db->colFullName($userFields['id'], $userCfg['table'])
          ], [
            'field' => $this->db->colFullName($userFields['active'], $userCfg['table']),
            'value' => 1
          ]]
        ]
      ]],
      'where' => [
        'conditions' => [[
          'field' => $this->db->colFullName($this->prefFields['id_alias'], $this->prefTable),
          'value' => $idRoom
        ]]
      ]
    ]);
  }


  public function setJoined(string $idRoom, string $idTmp, string|null $idUser = null): ?string
  {
    $idMeeting = $this->getStartedMeeting($idRoom);
    if (empty($idMeeting)
      && !empty($idUser)
      && $this->isModerator($idUser, $idRoom)
    ) {
      $idMeeting = $this->startMeeting($idRoom);
    }
    if (!empty($idMeeting)) {
      $fields = $this->class_cfg['arch']['participants'];
      $table = $this->class_cfg['tables']['participants'];
      if (!empty($idUser) && ($invited = $this->db->selectOne([
        'table' => $table,
        'fields' => [$fields['id']],
        'where' => [
          'conditions' => [[
            'field' => $fields['id_meeting'],
            'value' => $idMeeting
          ], [
            'field' => $fields['id_user'],
            'value' => $idUser
          ], [
            'field' => $fields['invited'],
            'value' => 1
          ], [
            'field' => $fields['joined'],
            'operator' => 'isnull'
          ]]
        ]
      ]))) {
        if ($this->db->update($table, [
          $fields['id_tmp'] => $idTmp,
          $fields['joined'] => date('Y-m-d H:i:s')
        ], [
          $fields['id'] => $invited
        ])) {
          return $invited;
        }
      }
      else {
        $this->setLeaved($idMeeting, $idTmp, $idUser, false);
        if ($this->db->insert($table, [
          $fields['id_meeting'] => $idMeeting,
          $fields['id_user'] => $idUser,
          $fields['id_tmp'] => $idTmp,
          $fields['joined'] => date('Y-m-d H:i:s')
        ])) {
          return $this->db->lastId();
        }
      }
    }
    return null;
  }


  public function setLeaved(string $idMeeting, string $idTmp, string|null $idUser = null, bool $close = true): bool
  {
    $fields = $this->class_cfg['arch']['participants'];
    $where = [
      $fields['id_user'] => $idUser,
      $fields['id_meeting'] => $idMeeting,
      $fields['leaved'] => null
    ];
    if (empty($idUser)) {
      $where[$fields['id_tmp']] = $idTmp;
    }
    if ($this->db->update($this->class_cfg['tables']['participants'], [
      $fields['leaved'] => date('Y-m-d H:i:s')
    ], $where)) {
      if (!empty($close) && !$this->getParticipants($idMeeting)) {
        $this->stopMeeting($idMeeting);
      }
      return true;
    }
    return false;
  }


  public function setParticipantName(string $idMeeting, string $idTmp, string $name): bool
  {
    $tableParts = $this->class_cfg['tables']['participants'];
    $fieldsParts = $this->class_cfg['arch']['participants'];
    $fieldsMeetings = $this->class_cfg['arch']['meetings'];
    if ($meeting = $this->getMeeting($idMeeting)) {
      $old = $this->db->rselect($tableParts, [], [
        $fieldsParts['id_meeting'] => $idMeeting,
        $fieldsParts['id_tmp'] => $idTmp
      ]);
      if (empty($old)
        && ($idRoom = $meeting[$fieldsMeetings['id_room']])
      ) {
        $old = $this->setJoined($idRoom, $idTmp);
      }
      $idPart = \is_array($old) ? $old[$fieldsParts['id']] : $old;
      if (!empty($old) && !empty($name)) {
        return (bool)$this->db->update($tableParts, [
          $fieldsParts['name'] => $name
        ], [
          $fieldsParts['id'] => $idPart
        ]);
      }
    }
    return false;
  }


  public function getParticipants(string $idMeeting): array
  {
    $fields = $this->class_cfg['arch']['participants'];
    return $this->db->rselectAll([
      'table' => $this->class_cfg['tables']['participants'],
      'fields' => [],
      'where' => [
        'conditions' => [[
          'field' => $fields['id_meeting'],
          'value' => $idMeeting
        ], [
          'field' => $fields['leaved'],
          'operator' => 'isnull'
        ], [
          'field' => $fields['joined'],
          'operator' => 'isnotnull'
        ]]
      ]
    ]);
  }


  public function getInvited(string $idMeeting): array
  {
    $fields = $this->class_cfg['arch']['participants'];
    return $this->db->getColumnValues([
      'table' => $this->class_cfg['tables']['participants'],
      'fields' => [$fields['id_user']],
      'where' => [
        'conditions' => [[
          'field' => $fields['id_meeting'],
          'value' => $idMeeting
        ], [
          'field' => $fields['invited'],
          'value' => 1
        ]]
      ],
      'group_by' => [$fields['id_user']]
    ]);
  }


  public function startMeeting(string $idRoom): string
  {
    if ($idMeeting = $this->getStartedMeeting($idRoom)) {
      return $idMeeting;
    }
    $fields = $this->class_cfg['arch']['meetings'];
    if (!$this->db->insert($this->class_cfg['table'], [
      $fields['id_room'] => $idRoom,
      $fields['started'] => date('Y-m-d H:i:s')
    ])) {
      throw new Exception(sprintf(_('Error starting the meeting for the room %s'), $idRoom));
    }
    return $this->db->lastId();
  }


  public function stopMeeting(string $idMeeting): bool
  {
    $m = $this->getMeeting($idMeeting);
    if (empty($m)) {
      throw new Exception(sprintf(_('Meeting not found with the id %s'), $idMeeting));
    }
    $date = date('Y-m-d H:i:s');
    $fields = $this->class_cfg['arch']['meetings'];
    if (\is_null($m[$fields['ended']])
      && !$this->db->update($this->class_cfg['table'], [
        $fields['ended'] => $date
      ], [
        $fields['id'] => $idMeeting
      ])
    ) {
      throw new Exception(sprintf(_('Error ending the meeting with the id %s'), $idMeeting));
    }
    $fields = $this->class_cfg['arch']['participants'];
    $this->db->update($this->class_cfg['tables']['participants'], [
      $fields['leaved'] => $date
    ], [
      $fields['id_meeting'] => $idMeeting
    ]);
    return true;
  }


  public function getStartedMeeting(string $idRoom): ?string
  {
    $fields = $this->class_cfg['arch']['meetings'];
    return $this->db->selectOne([
      'table' => $this->class_cfg['table'],
      'fields' => [$fields['id']],
      'where' => [
        'conditions' => [[
          'field' => $fields['id_room'],
          'value' => $idRoom
        ], [
          'field' => $fields['ended'],
          'operator' => 'isnull'
        ]]
      ]
    ]);
  }


  public function getLastMeeting(string $idRoom): ?array
  {
    return $this->db->rselect(
      $this->class_cfg['table'],
      [],
      [
        $this->class_cfg['arch']['meetings']['id_room'] => $idRoom
      ],
      [
        $this->class_cfg['arch']['meetings']['started'] => 'DESC'
      ]
    );
  }


  public function getMeeting(string $idMeeting): ?array
  {
    return $this->db->rselect(
      $this->class_cfg['table'],
      [],
      [
        $this->class_cfg['arch']['meetings']['id'] => $idMeeting
      ]
    );
  }


  public function getMeetingByTmp(string $idTmp, array $where = []): ?array
  {
    $w = [
      'conditions' => [[
        'field' => $this->class_cfg['arch']['meetings']['id_tmp'],
        'value' => $idTmp
      ]]
    ];
    if (!empty($where)) {
      $w['conditions'][] = $where;
    }
    return $this->db->rselect([
      'table' => $this->class_cfg['table'],
      'fields' => [],
      'where' => $w
    ]);
  }


  public function inviteUser(string $idMeeting, string $idUser): bool
  {
    $fields = $this->class_cfg['arch']['participants'];
    $table = $this->class_cfg['tables']['participants'];
    $exists = $this->db->selectOne([
      'table' => $table,
      'fields' => [$fields['id']],
      'where' => [
        'conditions' => [[
          'field' => $fields['id_meeting'],
          'value' => $idMeeting
        ], [
          'field' => $fields['id_user'],
          'value' => $idUser
        ], [
          'field' => $fields['invited'],
          'value' => 1
        ]]
      ]
    ]);
    if (!empty($exists)) {
      return true;
    }
    return (bool)$this->db->insert($table, [
      $fields['id_meeting'] => $idMeeting,
      $fields['id_user'] => $idUser,
      $fields['invited'] => 1
    ]);
  }


  public function isMeeting(string $idMeeting): bool
  {
    $fields = $this->class_cfg['arch']['meetings'];;
    return (bool)$this->db->selectOne([
      'table' => $this->class_cfg['tables']['meetings'],
      'fields' => [$fields['id']],
      'where' => [
        'conditions' => [[
          'field' => $fields['id'],
          'value' => $idMeeting
        ], [
          'field' => $fields['ended'],
          'operator' => 'isnull'
        ]]
      ]
    ]);
  }


  public function getParticipantsLogs(string $idMeeting): array
  {
    $logs = [];
    $partsFields = $this->class_cfg['arch']['participants'];
    $user = \bbn\User::getInstance();
    if ($parts = $this->db->rselectAll([
      'table' => $this->class_cfg['tables']['participants'],
      'fields' => [],
      'where' => [
        'conditions' => [[
          'field' => $partsFields['id_meeting'],
          'value' => $idMeeting
        ], [
          'field' => $partsFields['joined'],
          'operator' => 'isnotnull'
        ], [
          'logic' => 'OR',
          'conditions' => [[
            'field' => $partsFields['id_user'],
            'operator' => 'isnotnull'
          ], [
            'field' => $partsFields['name'],
            'operator' => 'isnotnull'
          ]]
        ]]
      ]
    ])) {
      foreach ($parts as $part) {
        $name = !empty($part[$partsFields['id_user']]) ?
          $user->getName($part[$partsFields['id_user']]) :
          (!empty($part[$partsFields['name']]) ? sprintf('%s (%s)', $part[$partsFields['name']], _('External')) : _('Unknown external user'));
        $logs[] = [
          'moment' => $part[$partsFields['joined']],
          'text' => $name . ' ' . _('joined the meeting')
        ];
        if (!empty($part[$partsFields['leaved']])) {
          $logs[] = [
            'moment' => $part[$partsFields['leaved']],
            'text' => $name . ' ' . _('left the meeting')
          ];
        }
      }
      X::sortBy($logs, 'moment', 'asc');
    }
    return $logs;
  }


  public function getMeetingsFromServer(string $server): array
  {
    $meetings = [];
    $url = 'http://' . $server . ':8080/colibri/conferences';
    if ($tmpMeetings = X::curl($url, null, [])) {
      $tmpMeetings = \json_decode($tmpMeetings, true);
      if (!empty($tmpMeetings)) {
        foreach ($tmpMeetings as $meet) {
          if ($m = X::curl($url . '/' . $meet['id'], null, [])) {
            $m = \json_decode($m, true);
            $parts = [];
            if (!empty($m['contents'])) {
              $conn = \array_values(\array_filter($m['contents'], function($c){
                return \array_key_exists('sctpconnections', $c);
              }));
              $parts = !empty($conn) && !empty($conn[0]['sctpconnections']) ?
                \array_map(function($p){
                  return $p['endpoint'];
                }, $conn[0]['sctpconnections']) :
                [];
            }
            $meetings[$m['id']] = [
              'id' => $m['id'],
              'participants' => $parts
            ];
          }
        }
      }
    }
    return $meetings;
  }


  private function getModerator(string $idUser, string $idRoom): ?array
  {
    if (!Str::isUid($idUser)) {
      throw new Exception(_('The user id given is not a uuid'));
    }
    if (!Str::isUid($idRoom)) {
      throw new Exception(_('The room id given is not a uuid'));
    }
    return $this->db->rselect($this->prefTable, [], [
      $this->prefFields['id_alias'] => $idRoom,
      $this->prefFields['id_user'] => $idUser
    ]);
  }


  private function getServerByRoom(string $idRoom): ?array
  {
    if ($room = $this->getRoom($idRoom)) {
      return $this->opt->option($room[$this->prefFields['id_option']]);
    }
    return null;
  }


  private function makeJWT(string $server, string $room)
  {
    // Create token header as a JSON string
    $header = json_encode([
      'typ' => 'JWT',
      'alg' => 'HS256'
    ]);
    if (!($appId = $this->getAppId($server))) {
      throw new Exception(sprintf(_('No App ID found for the server %s'), $server));
    }
    if (Str::isUid($room)) {
      $r = $this->getRoom($room);
      $room = $r[$this->prefFields['text']] ?? '';
    }
    if (empty($room)) {
      throw new Exception(sprintf(_('No room name - %s'), $room));
    }
    // Create token payload as a JSON string
    $payload = json_encode([
      'aud' => $appId,
      'iss' => $appId,
      'sub' => $server,
      'room' => $room
    ]);
    // Encode header to base64 string
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    // Encode payload to base64 string
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    if (!($appSecret = $this->getAppSecret($server))) {
      throw new Exception(sprintf(_('No App Secret found for the server %s'), $server));
    }
    // Create signature hash
    $signature = hash_hmac(
      'sha256',
      $base64UrlHeader . "." . $base64UrlPayload,
      $appSecret,
      true
    );
    // Encode signature to base64 string
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    // Return JWT
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
  }


  private function getAppId(string $server): ?string
  {
    if ($opt = self::getOption($server, 'list')) {
      return !empty($opt['appID']) ? $opt['appID'] : null;
    }
    return null;
  }


  private function getAppSecret(string $server): ?string
  {
    if ($optID = self::getOptionId($server, 'list')) {
      return $this->passCls->get($optID);
    }
    return null;
  }
}