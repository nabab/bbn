<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 11/03/2018
 * Time: 14:43
 */

namespace bbn\Appui;

use bbn;
use bbn\X;

/**
 * Class chat
 * @package bbn\Appui
 */
class Chat extends bbn\Models\Cls\Db
{

  /**
   * @var bbn\User
   */
  private $user;

  /**
   * @var bbn\User\Users
   */
  private $users;


  /**
   * Chat constructor.
   *
   * @param bbn\Db   $db   The database connection object
   * @param bbn\User $user The user object
   */
  public function __construct(bbn\Db $db, bbn\User $user)
  {
    if (defined('BBN_DATA_PATH') && $user->checkSession()) {
      parent::__construct($db);
      $this->user  = $user;
      $this->users = new bbn\User\Users($this->db);
    }
  }


  /**
   * Checks whether the object has been constructed correctly or not.
   *
   * @return bool
   */
  public function check(): bool
  {
    return $this->db && $this->user;
  }


  /**
   * Creates a new chat with the current user and another participant.
   *
   * @param array $users
   * @param int   $public
   * @return null|string
   */
  public function create(array $users, int $public = 0): ?string
  {
    if ($this->check()) {
      $join   = '';
      $where  = '';
      $values = [$this->user->getId(), $public];
      foreach ($users as $i => $u){
        $join    .= "JOIN bbn_chats_users AS u$i ON u$i.id_chat = bbn_chats.id".PHP_EOL;
        $where   .= "AND u$i.id_user = ?".PHP_EOL;
        $values[] = $u;
      }

      $sql = <<<SQL
SELECT id
FROM bbn_chats
  $join
WHERE creator = ?
AND public = ?
$where
SQL;
      if (($id_chat = $this->db->getOne($sql, $values))
          && (count($users) === $this->db->count('bbn_chat_users', ['id_chat' => $id_chat]))
      ) {
        return $id_chat;
      }

      if ($this->db->insert(
        'bbn_chats', [
        'creator' => $this->user->getId(),
        'creation' => date('Y-m-d H:i:s'),
        'public' => $public ? 1 : 0
        ]
      )
      ) {
        $id_chat = $this->db->lastId();
        $this->db->insert(
          'bbn_chats_users', [
          'id_chat' => $id_chat,
          'id_user' => $this->user->getId(),
          'entrance' => X::microtime(),
          'admin' => 1
          ]
        );
        foreach ($users as $user) {
          $this->db->insertIgnore(
            'bbn_chats_users', [
            'id_chat' => $id_chat,
            'id_user' => $user,
            'entrance' => X::microtime(),
            'admin' => 0
            ]
          );
        }

        $this->_set_state_hash($id_chat);
        return $id_chat;
      }
    }

    return null;
  }


  public function createGroup(string $title, array $users, array $admins = []): ?bool
  {
    if (($time = X::microtime())
        && !empty($users)
        && ($id_user = $this->user->getId())
        && ($username = $this->user->getName())
        && $this->db->insert(
          'bbn_chats', [
          'title' => $title,
          'creator' => $id_user,
          'creation' => date('Y-m-d H:i:s', $time)
          ]
        )
        && ($id = $this->db->lastId())
        && $this->db->insert(
          'bbn_chats_users', [
          'id_chat' => $id,
          'id_user' => $id_user,
          'entrance' => $time,
          'admin' => 1
          ]
        )
    ) {
      $users        = array_filter(
        $users, function ($u) use ($id_user) {
          return $u !== $id_user;
        }
      );
      $admins       = array_filter(
        $admins, function ($u) use ($id_user) {
          return $u !== $id_user;
        }
      );
      $users_added  = 0;
      $admins_added = 0;
      foreach ($users as $user) {
        if (bbn\Str::isUid($user)) {
          $users_added += $this->db->insert(
            'bbn_chats_users', [
            'id_chat' => $id,
            'id_user' => $user,
            'entrance' => $time,
            'admin' => 0
            ]
          );
        }
      }

      $this->_add_bot_message(
        $id, [
        $id_user => X::_('You created this group'),
        "$username " . X::_('created this group')
        ]
      );
      foreach ($users as $user) {
        if (bbn\Str::isUid($user)) {
          $name = $this->user->getName($user);
          $this->_add_bot_message(
            $id, [
            $id_user => X::_('You added') . " $name " .  X::_('to the group'),
            $user => $username . ' ' . X::_('added you to the group'),
            "$username " . X::_('added') . " $name " . X::_('to the group')
            ]
          );
          if (\in_array($user, $admins, true)) {
            $admins_added += (int)$this->addAdmin($id, $user);
          }
        }
      }

      $this->_set_state_hash($id);
      return (count($users) === $users_added) && (count($admins) === $admins_added);
    }

    return null;
  }


  /**
   * Destroys the given chat
   * @param string $id_chat
   * @return bool
   */
  public function destroy(string $id_chat): bool
  {
    return $this->check()
      && bbn\Str::isUid($id_chat)
      && $this->isCreator($id_chat)
      && $this->db->update('bbn_chats', ['blocked' => 1], ['id' => $id_chat])
      && $this->db->update('bbn_chats_users', ['active' => 0], ['id_chat' => $id_chat])
      && $this->_set_state_hash($id_chat);
  }


  /**
   * Sends a new message from the current user in the given chat.
   *
   * @param string $id_chat
   * @param string $message
   * @return int|null
   */
  public function talk(string $id_chat, string $message): ?int
  {
    if ($this->check() && ($chat = $this->info($id_chat)) && !$chat['blocked']) {
      $users = $this->getParticipants($id_chat);
      if (\in_array($this->user->getId(), $users, true)) {
        $time = X::microtime();
        $st   = bbn\Util\Enc::crypt(json_encode(['time' => $time, 'user' => $this->user->getId(), 'message' => $message]));
        $day  = date('Y-m-d');
        foreach ($users as $user) {
          $dir = bbn\Mvc::getUserDataPath($user, 'appui-chat').$id_chat.'/'.$day;
          if (bbn\File\Dir::createPath($dir)) {
            file_put_contents($dir.'/'.$time.'.msg', $st);
          }
        }

        $this->setLastActivity($id_chat, $this->user->getId());
        return $this->db->update('bbn_chats', ['last_message' => $time], ['id' => $id_chat]);
      }
    }

    return null;
  }


  /**
   * Gets information about the given chat.
   *
   * @param $id_chat
   * @return array|null
   */
  public function info(string $id_chat): ?array
  {
    if ($this->check() && bbn\Str::isUid($id_chat)) {
      return $this->db->rselect('bbn_chats', [], ['id' => $id_chat]) ?: null;
    }

    return null;
  }


  public function getChatsHash(float $entrance = null): ?string
  {
    $res = '';
    foreach ($this->getChats($entrance) as $c) {
      if ($h = $this->_get_state_hash($c)) {
        $res .= $h;
      }
    }

    return $res ? \md5($res) : null;
  }


  public function setTitle(string $id_chat, string $title = null)
  {
    if (\bbn\Str::isUid($id_chat)
        && $this->isAdmin($id_chat)
        && $this->db->update('bbn_chats', ['title' => $title], ['id' => $id_chat])
    ) {
      $this->_set_state_hash($id_chat);
      return $this->_add_bot_message(
        $id_chat, [
        $this->user->getId() => X::_("You have changed the chat title"),
        $this->user->getName() . ' ' . X::_('has changed the chat title')
        ]
      );
    }
  }


  /**
   * Adds the given user to the given chat (if the current user is admin of this chat).
   *
   * @param string $id_chat
   * @param string $id_user
   * @return bool
   */
  public function addUser(string $id_chat, string $id_user): bool
  {
    if ($this->isAdmin($id_chat)
        && ($name1 = $this->user->getName())
        && ($name2 = $this->user->getName($id_user))
        && $this->db->insertUpdate(
          'bbn_chats_users', [
          'id_chat' => $id_chat,
          'id_user' => $id_user,
          'entrance' => X::microtime(),
          'admin' => 0,
          'active' => 1
          ]
        )
    ) {
      $this->_set_state_hash($id_chat);
      return $this->_add_bot_message(
        $id_chat, [
        $this->user->getId() => X::_('You added') . " $name2",
        $id_user => "$name1 " . X::_('added you'),
        $name1 . ' ' . X::_('added') . ' ' . $name2
        ]
      );
    }

    return false;
  }


  /**
   * Removes the given user to the given chat (if the current user is admin of this chat).
   *
   * @param string $id_chat
   * @param string $id_user
   * @return bool
   */
  public function removeUser(string $id_chat, string $id_user): bool
  {
    if ($this->isAdmin($id_chat)
        && bbn\Str::isUid($id_user)
        && ($name1 = $this->user->getName())
        && ($name2 = $this->user->getName($id_user))
        && $this->db->update(
          'bbn_chats_users', ['active' => 0], [
          'id_chat' => $id_chat,
          'id_user' => $id_user
          ]
        )
    ) {
      $this->_set_state_hash($id_chat);
      return $this->_add_bot_message(
        $id_chat, [
        $this->user->getId() => X::_('You remove') . " $name2",
        $name1 . ' ' . X::_('removed') . ' ' . $name2
        ]
      );
    }

    return false;
  }


  public function addAdmin(string $id_chat, string $id_user): ?bool
  {
    if ($this->check()
        && bbn\Str::isUid($id_chat)
        && bbn\Str::isUid($id_user)
        && $this->isCreator($id_chat)
        && ($name = $this->user->getName())
        && ($name2 = $this->user->getName($id_user))
    ) {
      return $this->_set_admin(
        $id_chat, $id_user, true, [
        $this->user->getId() => X::_('You set') . " $name2 " . X::_('as admin'),
        $id_user => "$name " . X::_('set you as admin'),
        "$name " . X::_('set') . " $name2 " . X::_('as admin')
        ]
      );
    }

    return null;
  }


  public function removeAdmin(string $id_chat, string $id_user): ?bool
  {
    if ($this->check()
        && bbn\Str::isUid($id_chat)
        && bbn\Str::isUid($id_user)
        && $this->isCreator($id_chat)
        && ($name = $this->user->getName())
        && ($name2 = $this->user->getName($id_user))
    ) {
      return $this->_set_admin(
        $id_chat, $id_user, false, [
        $this->user->getId() => X::_('You removed') . " $name2 " . X::_('as admin'),
        $id_user => "$name " . X::_('removed you as admin'),
        "$name " . X::_('removed') . " $name2 " . X::_('as admin')
        ]
      );
    }

    return null;
  }


  /**
   * Returns the participants of the given chat as an array of id_user.
   *
   * @param string $id_chat
   * @param bool   $with_current
   * @return array|null
   */
  public function getParticipants(string $id_chat, bool $with_current = true, bool $last_activity = false): ?array
  {
    if ($this->check()) {
      $ucfg = $this->user->getClassCfg();
      $cfg  = [
        'table' => 'bbn_chats_users',
        'fields' => ['bbn_chats_users.id_user'],
        'join' => [[
          'table' => $ucfg['table'],
          'on' => [
            'conditions' => [[
              'field' => 'bbn_chats_users.id_user',
              'exp' => $ucfg['table'].'.'.$ucfg['arch']['users']['id']
            ], [
              'field' => $ucfg['table'].'.'.$ucfg['arch']['users']['active'],
              'value' => 1
            ]]
          ]
        ]],
        'where' => [
          'conditions' => [[
            'field' => 'id_chat',
            'value' => $id_chat
          ], [
            'field' => 'active',
            'value' => 1
          ]]
        ]
      ];
      if (!$with_current) {
        $cfg['where']['conditions'][] = [
          'field' => 'bbn_chats_users.id_user',
          'operator' => '!=',
          'value' => $this->user->getId()
        ];
      }

      if ($last_activity) {
        $cfg['fields'] = [
          'id' => 'bbn_chats_users.id_user',
          'lastActivity' => 'bbn_chats_users.last_activity',
         // 'lastUserActivity' => 'UNIX_TIMESTAMP(MAX('.$ucfg['tables']['sessions].'.'.$ucfg['arch']['sessions']['last_activity'].'))'
        ];
        /* $cfg['join'][] = [
          'table' => $ucfg['tables']['sessions],
          'type' => 'left',
          'on' => [
            'conditions' => [[
              'field' => $ucfg['tables']['sessions].'.'.$ucfg['arch']['sessions']['id_user'],
              'exp' => 'bbn_chats_users.id_user'
            ]]
          ]
        ];
        $cfg['group_by'] = [$ucfg['tables']['sessions].'.'.$ucfg['arch']['sessions']['id_user']]; */
        return $this->db->rselectAll($cfg);
      }

      return $this->db->getFieldValues($cfg);
    }

    return null;
  }


  /**
   * Returns the admins of the given chat as an array of id_user.
   *
   * @param string $id_chat
   * @return array|null
   */
  public function getAdmins(string $id_chat): ?array
  {
    if ($this->check()
        && bbn\Str::isUid($id_chat)
    ) {
      return $this->db->getFieldValues(
        'bbn_chats_users', 'id_user', [
          'id_chat' => $id_chat,
          'active' => 1,
          'admin' => 1
        ]
      );
    }

    return null;
  }


  /**
   * Checks whether the given user is participant of the given chat.
   *
   * @param string $id_chat
   * @param string $id_user
   * @return bool|null
   */
  public function isParticipant(string $id_chat, string $id_user = null): ?bool
  {
    if ($this->check()
        && bbn\Str::isUid($id_chat)
        && (bbn\Str::isUid($id_user) || \is_null($id_user))
    ) {
      return (bool)$this->db->count(
        'bbn_chats_users', [
        'id_chat' => $id_chat,
        'id_user' => $id_user ?: $this->user->getId()
        ]
      );
    }

    return null;
  }


  /**
   * Checks whether the current user is an admin of the given chat or not.
   *
   * @param string $id_chat
   * @param string $id_user
   * @return bool|null
   */
  public function isAdmin(string $id_chat, string $id_user = null): ?bool
  {
    if ($this->check()
        && bbn\Str::isUid($id_chat)
        && ($chat = $this->info($id_chat))
        && !$chat['blocked']
        && (bbn\Str::isUid($id_user) || \is_null($id_user))
    ) {
      return (bool)$this->db->count(
        'bbn_chats_users', [
        'id_chat' => $id_chat,
        'id_user' => $id_user ?: $this->user->getId(),
        'admin' => 1
        ]
      );
    }

    return null;
  }


  /**
   * Checks whether the current user is the creator of the given chat or not.
   *
   * @param string $id_chat
   * @param string $id_user
   * @return bool|null
   */
  public function isCreator(string $id_chat, string $id_user = null): ?bool
  {
    if ($this->check()
        && bbn\Str::isUid($id_chat)
        && ($chat = $this->info($id_chat))
        && !$chat['blocked']
        && (bbn\Str::isUid($id_user) || \is_null($id_user))
    ) {
      return $chat['creator'] === $id_user ?: $this->user->getId();
    }

    return null;
  }


  public function getChats(float $entrance = null): ?array
  {
    if ($this->check()) {
      $where = [
        'conditions' => [[
          'field' => 'bbn_chats_users.id_user',
          'value' => $this->user->getId()
        ], [
          'field' => 'bbn_chats_users.active',
          'value' => 1
        ]]
      ];
      if (\is_float($entrance)) {
        $where['conditions'][] = [
          'field' => 'bbn_chats_users.entrance',
          'operator' => '<=',
          'value' => $entrance
        ];
      }

      return $this->db->getFieldValues(
        [
        'table' => 'bbn_chats_users',
        'fields' => ['id_chat'],
        'join' => [[
          'table' => 'bbn_chats',
          'on' => [
            'conditions' => [[
              'field' => 'bbn_chats_users.id_chat',
              'exp' => 'bbn_chats.id'
            ]]
          ]
        ]],
        'where' => $where,
        'order' => [[
          'field' => 'bbn_chats.last_message',
          'dir' => 'DESC'
        ]]
        ]
      );
    }
  }


  public function getChatByUsers(array $users): ?string
  {
    if ($this->check() && count($users)) {
      $cfg     = [
        'tables' => ['bbn_chats'],
        'fields' => ['bbn_chats.id'],
        'join' => [],
        'where' => ['blocked' => 0]
      ];
      $users[] = $this->user->getId();
      $users   = array_unique($users);
      foreach ($users as $i => $u) {
        $cfg['join'][]                         = [
          'table' => 'bbn_chats_users',
          'alias' => 'u'.($i + 1),
          'on' => [
            'conditions' => [[
              'field' => 'bbn_chats.id',
              'exp' => 'u'.($i + 1).'.id_chat'
            ]]
          ]
        ];
        $cfg['where']['u'.($i + 1).'.id_user'] = $u;
      }

      $id_chat = false;
      $ids     = $this->db->getColumnValues($cfg);
      if (count($ids)) {
        foreach ($ids as $id) {
          if (count($this->getParticipants($id)) === count($users)) {
            $id_chat = $id;
            break;
          }
        }
      }

      if (!$id_chat) {
        $id_chat = $this->create($users);
      }

      return $id_chat ?: null;
    }

    return null;
  }


  public function hasOldMessages(string $id_chat, $moment): bool
  {
    if ($this->check()) {
      $cdir = bbn\Mvc::getUserDataPath($this->user->getId(), 'appui-chat').$id_chat.'/';
      if ($this->isParticipant($id_chat) && is_dir($cdir)) {
        $dir   = $cdir . date('Y-m-d', $moment);
        $files = \bbn\File\Dir::getFiles($dir);
        foreach ($files as $file){
          $time = round((float)basename($file, '.msg'), 4);
          if (X::compareFloats($time, $moment, '<') && ($st = file_get_contents($file))) {
            return true;
          }
        }

        $dirs = \bbn\File\Dir::getDirs($cdir);
        foreach ($dirs as $d){
          if ((basename($d) < date('Y-m-d', $moment)) && !empty(\bbn\File\Dir::getFiles($d))) {
            return true;
          }
        }
      }
    }

    return false;
  }


  public function getPrevMessages(string $id_chat, float $moment = null, int $num = 50, string $id_user = null): ?array
  {
    return $this->_get_messages($id_chat, $moment ?: X::microtime(), '<', $num, $id_user);
  }


  public function getNextMessages(string $id_chat, float $moment = null, int $num = 0, string $id_user = null)
  {
    return $this->_get_messages($id_chat, $moment ?: X::microtime(), '>', $num, $id_user);
  }


  public function getFromtoMessages(string $id_chat, float $from, float $to, int $num = 0)
  {

  }


  /**
   * Close a chat by setting blocked to 1.
   *
   * @param $id_chat
   * @return bool
   */
  public function block($id_chat): bool
  {
    if ($this->isAdmin($id_chat) && $this->db->update('bbn_chats', ['blocked' => 1], ['id' => $id_chat])) {
      return $this->_set_state_hash($id_chat);
    }

    return false;
  }


  public function leave(string $id_chat, string $id_user = null): ?bool
  {
    if ($this->check()
        && bbn\Str::isUid($id_chat)
        && $this->isParticipant($id_chat)
        && $this->_add_bot_message($id_chat, $this->user->getName($id_user ?: $this->user->getId()) . ' ' . X::_('has left the chat'))
        && $this->db->update(
          'bbn_chats_users', ['active' => 0], [
          'id_chat' => $id_chat,
          'id_user' => $id_user ?: $this->user->getId()
          ]
        )
    ) {
      $ok = true;
      if (($parts = $this->getParticipants($id_chat))
          && (count($parts) === 1)
      ) {
        $ok = !!$this->leave($id_chat, $parts[0]);
      }

      $this->_set_state_hash($id_chat);
      return $ok;
    }

    return null;
  }


  public function getLastActivity(string $id_chat, string $id_user): float
  {
    if (bbn\Str::isUid($id_chat)
        && bbn\Str::isUid($id_user)
        && ($last = $this->db->selectOne(
          'bbn_chats_users', 'last_activity', [
          'id_chat' => $id_chat,
          'id_user' => $id_user
          ]
        ))
    ) {
      return round((float)$last, 4);
    }

    return round((float)0, 4);
  }


  public function setLastActivity(string $id_chat, string $id_user): ?bool
  {
    if (bbn\Str::isUid($id_chat)
        && bbn\Str::isUid($id_user)
        && $this->isParticipant($id_chat, $id_user)
        && $this->db->update(
          'bbn_chats_users', ['last_activity' => X::microtime()], [
          'id_chat' => $id_chat,
          'id_user' => $id_user
          ]
        )
    ) {
      return $this->_set_state_hash($id_chat);
    }

    return null;
  }


  public function getMaxLastActivity(string $id_user = null)
  {
    if ($this->check()
        && (bbn\Str::isUid($id_user)
        || \is_null($id_user))
    ) {
      return $this->db->selectOne(
        'bbn_chats_users', 'MAX(last_activity)', [
        'id_user' => $id_user ?: $this->user->getId(),
        'active' => 1
        ]
      );
    }
  }


  public function setLastNotification(string $id_chat, string $id_user, float $moment = null): bool
  {
    if (bbn\Str::isUid($id_chat) && bbn\Str::isUid($id_user)) {
      if (\is_null($moment)) {
        $moment = bbn\X::microtime();
      }

      return (bool)$this->db->update(
        'bbn_chats_users', ['last_notification' => $moment], [
        'id_chat' => $id_chat,
        'id_user' => $id_user
        ]
      );
    }

    return false;
  }


  /**
   * Sets the current user online
   * @return bool
   */
  public function setOnline(): bool
  {
    return $this->_set_user_status(true);
  }


  /**
   * Sets the current user offline
   * @return bool
   */
  public function setOffline(): bool
  {
    return $this->_set_user_status(false);
  }


  /**
   * Gets the list of online users
   * @return array
   */
  public function getOnlineUsers(): array
  {
    if($this->check()) {
      if ($ids = $this->users->onlineList()) {
        $t = $this;
        return array_values(
          array_filter(
            $ids, function ($id) use ($t) {
              return $t->getUserStatus($id);
            }
          )
        );
      }
    }

    return [];
  }


  /**
   * Gets the status of the current|given user
   * @param string $id
   * @return bool
   */
  public function getUserStatus(string $id = null): bool
  {
    $ucfg = $this->user->getClassCfg();
    $cfg  = json_decode($this->db->selectOne($ucfg['table'], $ucfg['arch']['users']['cfg'], [$ucfg['arch']['users']['id'] => $id ?: $this->user->getId()]), true);
    return !isset($cfg['appui-chat']['online']) || !empty($cfg['appui-chat']['online']);
  }


  public function mute()
  {

  }


  public function unmute()
  {

  }


  /**
   * Deprecated??
   * Returns messages from the given chat sent after $last.
   *
   * @param $id_chat
   * @param null    $last
   * @param null    $day
   * @return array
   */
  public function getMessages($id_chat, $last = null, $day = null): array
  {
    $res = ['success' => false, 'last' => null, 'messages' => []];
    if ($this->check()) {
      $dir = bbn\Mvc::getUserDataPath($this->user->getId(), 'appui-chat').$id_chat.'/'.($day ?: date('Y-m-d'));
      if ($this->isParticipant($id_chat) && is_dir($dir)) {
        $res['success'] = true;
        $files          = bbn\File\Dir::getFiles($dir);
        foreach ($files as $file){
          $time = (float)basename($file, '.msg');
          if ((!$last || X::compareFloats($time, $last, '>')) && ($st = file_get_contents($file))) {
            $res['messages'][] = json_decode(bbn\Util\Enc::decrypt($st), true);
          }
        }

        if (isset($time)) {
          $res['last'] = $time;
        }
      }
    }

    return $res;
  }


  /**
   * Deprecated??
   * Returns messages from the given chat for a specific day.
   *
   * @param $id_chat
   * @param $day
   * @return array
   */
  public function getOldMessages($id_chat, $day)
  {
    return $this->getMessages($id_chat, null, $day);
  }


  /**
   * Deprecated??
   */
  public function getActiveChats()
  {
    if ($chats = $this->getChats()) {
      $t =& $this;
      $d = new \DateTime();
      $d->sub(new \DateInterval('PT20M'));
      if ($chats = array_filter(
        $chats, function ($c) use ($d, $t) {
          return ($m = $t->getMessages($c, $d->getTimestamp())) && !empty($m['messages']);
        }
      )
      ) {
        return array_map(
          function ($c) use ($d, $t) {
            return [
              'id' => $c,
              'messages' => ($m = $t->getMessages($c)) ? $m['messages'] : [],
              'partecipants' => $t->getParticipants($c),
              'has_old' => $t->hasOldMessages($c, $d->getTimestamp() - 1)
            ];
          }, $chats
        );
      }
    }

    return [];
  }


  private function _get_path(string $id_chat, string $id_user = null): ?string
  {
    if (bbn\Str::isUid($id_chat)
        && (bbn\Str::isUid($id_user) || \is_null($id_user))
    ) {
      return bbn\Mvc::getUserDataPath($id_user ?: $this->user->getId(), 'appui-chat') . $id_chat . '/';
    }

    return null;
  }


  private function _scan_files(array $files, string $time, string $comparator, array &$res, int $num = 0)
  {
    foreach ($files as $file) {
      if ($num && (count($res) >= $num)) {
        break;
      }

      $ftime = round((float)basename($file, '.msg'), 4);
      if (X::compareFloats($ftime, $time, $comparator)
          && ($st = file_get_contents($file))
      ) {
        $res[] = json_decode(bbn\Util\Enc::decrypt($st), true);
      }
    }

    return $res;
  }


  private function _get_messages(string $id_chat, float $moment, string $comparator, int $num = 0, string $id_user = null)
  {
    if ($this->check() && bbn\Str::isUid($id_chat)) {
      $res    = [];
      $dir    = $this->_get_path($id_chat, $id_user);
      $moment = round($moment, 4);
      if ($this->isParticipant($id_chat, $id_user) && is_dir($dir)) {
        $files = bbn\File\Dir::getFiles($dir . date('Y-m-d', $moment));
        $this->_scan_files($files ?: [], $moment, $comparator, $res, $num);
        if (!$num || (count($res) < $num)) {
          $dirs = array_reverse(bbn\File\Dir::getDirs($dir));
          foreach ($dirs as $d){
            if ($num && (count($res) >= $num)) {
              break;
            }

            if (((($comparator === '<')
                && (basename($d) < date('Y-m-d', $moment)))
                || (($comparator === '>')
                && (basename($d) > date('Y-m-d', $moment))))
                && ($files = bbn\File\Dir::getFiles($d))
            ) {
              $this->_scan_files($files, $moment, $comparator, $res, $num);
            }
          }
        }
      }

      X::sortBy($res, 'time');
      $id_user  = bbn\Str::isUid($id_user) ? $id_user : $this->user->getId();
      $last_act = $this->getLastActivity($id_chat, $id_user);
      return array_map(
        function ($r) use ($last_act, $id_user) {
          if (!empty($r['user']) && ($id_user !== $r['user'])) {
            $r['unread'] = X::compareFloats($r['time'], $last_act, '>');
          }

          return $r;
        }, $res
      );
    }

    return null;
  }


  private function _add_bot_message(string $id_chat, $message): ?bool
  {
    if ($this->check() && bbn\Str::isUid($id_chat)) {
      $users = $this->getParticipants($id_chat);
      $added = 0;
      foreach ($users as $user){
        $mess = \is_string($message) ? $message : (\is_array($message) ? ($message[$user] ?? $message[0]) : false);
        if ($mess) {
          $time = X::microtime();
          $st   = bbn\Util\Enc::crypt(
            json_encode(
              [
              'time' => $time,
              'message' => $mess
              ]
            )
          );
          $day  = date('Y-m-d', $time);
          $dir  = $this->_get_path($id_chat, $user) . $day;
          if (bbn\File\Dir::createPath($dir)) {
            file_put_contents($dir.'/'.$time.'.msg', $st);
            $added += $this->db->update('bbn_chats', ['last_message' => $time], ['id' => $id_chat]);
          }
        }
      }

      return (bool)$added;
    }

    return null;
  }


  private function _set_admin(string $id_chat, string $id_user, bool $admin, array $bot): ?bool
  {
    if ($this->isParticipant($id_chat, $id_user)
        && $this->db->update(
          'bbn_chats_users', ['admin' => (int)$admin], [
          'id_chat' => $id_chat,
          'id_user' => $id_user
          ]
        )
    ) {
      $this->_set_state_hash($id_chat);
      return $this->_add_bot_message($id_chat, $bot);
    }

    return null;
  }


  private function _get_state_hash(string $id_chat): ?string
  {
    if ($this->check() && bbn\Str::isUid($id_chat)) {
      return $this->db->selectOne('bbn_chats', 'state_hash', ['id' => $id_chat]);
    }

    return null;
  }


  private function _set_state_hash(string $id_chat): bool
  {
    if (bbn\Str::isUid($id_chat)) {
      $info = $this->info($id_chat);
      $hash = \md5(
        \json_encode(
          [
          'title' => $info['title'],
          'blocked' => $info['blocked'],
          'admins' => $this->getAdmins($id_chat),
          'participants' => $this->getParticipants($id_chat, true, true)
          ]
        )
      );
      return (bool)$this->db->update('bbn_chats', ['state_hash' => $hash], ['id' => $id_chat]);
    }

    return false;
  }


  /**
   * Sets the status of the chat system of the current user
   * @param bool $is_online
   * @return bool
   */
  private function _set_user_status(bool $is_online): bool
  {
    if ($this->check()) {
      $ucfg = $this->user->getClassCfg();
      $cfg  = $this->db->selectOne($ucfg['table'], $ucfg['arch']['users']['cfg'], [$ucfg['arch']['users']['id'] => $this->user->getId()]);
      if (!empty($cfg) && ($c = json_decode($cfg, true))) {
        if (!isset($c['appui-chat'])) {
          $c['appui-chat'] = [];
        }

        $c['appui-chat']['online'] = $is_online;
      }
      else {
        $c = [
          'appui-chat' => [
            'online' => $is_online
          ]
        ];
      }

      if (isset($c['appui-chat']['online'])) {
        return (bool)$this->db->update($ucfg['table'], [$ucfg['arch']['users']['cfg'] => json_encode($c)], [$ucfg['arch']['users']['id'] => $this->user->getId()]);
      }
    }

    return false;
  }


}
