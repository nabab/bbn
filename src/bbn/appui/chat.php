<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 11/03/2018
 * Time: 14:43
 */

namespace bbn\appui;
use bbn;
use bbn\x;

/**
 * Class chat
 * @package bbn\appui
 */
class chat
{

  /**
   * @var bbn\db
   */
  /**
   * @var bbn\db|bbn\user
   */
  private
    $db,
    $user;

  private function _get_path(string $id_chat, string $id_user = null): ?string
  {
    if (
      bbn\str::is_uid($id_chat) &&
      (bbn\str::is_uid($id_user) || \is_null($id_user))
    ){
      return bbn\mvc::get_user_data_path($id_user ?: $this->user->get_id(), 'appui-chat') . $id_chat . '/';
    }
    return null;
  }

  private function _scan_files(array $files, string $time, string $comparator, array &$res, int $num = 0){
    foreach ( $files as $file ){
      if ( $num && (count($res) >= $num) ){
        break;
      }
      $ftime = round((float)basename($file, '.msg'), 4);
      if (
        x::compare_floats($ftime, $time, $comparator) &&
        ($st = file_get_contents($file))
      ){
        $res[] = json_decode(bbn\util\enc::decrypt($st), true);
      }
    }
    return $res;
  }

  private function _get_messages(string $id_chat, float $moment, string $comparator, int $num = 0){
    if ( $this->check() && bbn\str::is_uid($id_chat) ){
      $res = [];
      $dir = $this->_get_path($id_chat);
      $moment = round($moment, 4);
      if ( $this->is_participant($id_chat) && is_dir($dir) ){
        $files = \bbn\file\dir::get_files($dir . date('Y-m-d', $moment));
        $this->_scan_files($files ?: [], $moment, $comparator, $res, $num);
        if ( !$num || (count($res) < $num) ){
          $dirs = array_reverse(\bbn\file\dir::get_dirs($dir));
          foreach ( $dirs as $d ){
            if ( $num && (count($res) >= $num) ){
              break;
            }
            if (
              (
                (
                  ($comparator === '<') &&
                  (basename($d) < date('Y-m-d', $moment))
                ) ||
                (
                  ($comparator === '>') &&
                  (basename($d) > date('Y-m-d', $moment))
                )
              ) &&
              ($files = \bbn\file\dir::get_files($d))
            ){
              $this->_scan_files($files, $moment, $comparator, $res, $num);
            }
          }
        }
      }
      x::sort_by($res, 'time');
      return $res;
    }
    return null;
  }

  private function _add_bot_message(string $id_chat, $message): ?bool
  {
    if ( $this->check() && bbn\str::is_uid($id_chat) ){
      $users = $this->get_participants($id_chat);
      $added = 0;
      foreach ( $users as $user ){
        $mess = \is_string($message) ? $message : (\is_array($message) ? ($message[$user] ?? $message[0]) : false);
        if ( $mess ){
          $time = x::microtime();
          $st = bbn\util\enc::crypt(json_encode([
            'time' => $time,
            'message' => $mess
            ]));
          $day = date('Y-m-d', $time);
          $dir = $this->_get_path($id_chat, $user) . $day;
          if ( bbn\file\dir::create_path($dir) ){
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
    if (
      $this->is_participant($id_chat, $id_user) &&
      $this->db->update('bbn_chats_users', ['admin' => (int)$admin], [
        'id_chat' => $id_chat,
        'id_user' => $id_user
      ])
    ){
      return $this->_add_bot_message($id_chat, $bot);
    }
    return null;
  }

  /**
   * chat constructor.
   *
   * @param bbn\db $db
   * @param bbn\user $user
   */
  public function __construct(bbn\db $db, bbn\user $user)
  {
    if ( defined('BBN_DATA_PATH') && $user->check_session() ){
      $this->db = $db;
      $this->user = $user;
    }
  }

  /**
   * @return bool
   */
  public function check(): bool
  {
    return $this->db && $this->user;
  }

  /**
   * Creates a new chat with the current userr and another participant.
   *
   * @param array $users
   * @param int $public
   * @return null|string
   */
  public function create(array $users, int $public = 0): ?string
  {
    if ( $this->check() ){
      $join = '';
      $where = '';
      $values = [$this->user->get_id(), $public];
      foreach ( $users as $i => $u ){
        $join .= "JOIN bbn_chats_users AS u$i ON u$i.id_chat = bbn_chats.id".PHP_EOL;
        $where .= "AND u$i.id_user = ?".PHP_EOL;
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
      if (
        ($id_chat = $this->db->get_one($sql, $values)) &&
        (count($users) === $this->db->count('bbn_chat_users', ['id_chat' => $id_chat]))
      ){
        return $id_chat;
      }
      if ( $this->db->insert('bbn_chats', [
        'creator' => $this->user->get_id(),
        'creation' => date('Y-m-d H:i:s'),
        'public' => $public ? 1 : 0
      ]) ){
        $id_chat = $this->db->last_id();
        $this->db->insert('bbn_chats_users', [
          'id_chat' => $id_chat,
          'id_user' => $this->user->get_id(),
          'entrance' => x::microtime(),
          'admin' => 1
        ]);
        foreach ( $users as $user ){
          $this->db->insert_ignore('bbn_chats_users', [
            'id_chat' => $id_chat,
            'id_user' => $user,
            'entrance' => x::microtime(),
            'admin' => 0
          ]);
        }
        return $id_chat;
      }
    }
    return null;
  }

  public function create_group(string $title, array $users, array $admins = []): ?bool
  {
    if (
      ($time = x::microtime()) &&
      !empty($users) &&
      ($id_user = $this->user->get_id()) &&
      ($username = $this->user->get_name()) &&
      $this->db->insert('bbn_chats', [
        'title' => $title,
        'creator' => $id_user,
        'creation' => date('Y-m-d H:i:s', $time)
      ]) &&
      ($id = $this->db->last_id()) &&
      $this->db->insert('bbn_chats_users', [
        'id_chat' => $id,
        'id_user' => $id_user,
        'entrance' => $time,
        'admin' => 1
      ])
    ){
      ;
      $users = array_filter($users, function($u) use($id_user){
        return $u !== $id_user;
      });
      $admins = array_filter($admins, function($u) use($id_user){
        return $u !== $id_user;
      });
      $users_added = 0;
      $admins_added = 0;
      foreach ( $users as $user ){
        if ( bbn\str::is_uid($user) ){
          $users_added += $this->db->insert('bbn_chats_users', [
            'id_chat' => $id,
            'id_user' => $user,
            'entrance' => $time,
            'admin' => 0
          ]);
        }
      }
      $this->_add_bot_message($id, [
        $id_user => _('You created this group'),
        "$username " . _('created this group')
      ]);
      foreach ( $users as $user ){
        if ( bbn\str::is_uid($user) ){
          $name = $this->user->get_name($user);
          $this->_add_bot_message($id, [
            $id_user => _('You added') . " $name " .  _('to the group'),
            $user => $username . ' ' . _('added you to the group'),
            "$username " . _('added') . " $name " . _('to the group')
          ]);
          if ( \in_array($user, $admins, true) ){
            $admins_added += (int)$this->add_admin($id, $user);
          }
        }
      }
      return (count($users) === $users_added) && (count($admins) === $admins_added);
    }
    return null;
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
    if ( $this->check() && ($chat = $this->info($id_chat)) && !$chat['blocked'] ){
      $users = $this->get_participants($id_chat);
      if ( \in_array($this->user->get_id(), $users, true) ){
        $time = x::microtime();
        $st = bbn\util\enc::crypt(json_encode(['time' => $time, 'user' => $this->user->get_id(), 'message' => $message]));
        $day = date('Y-m-d');
        foreach ( $users as $user ){
          $dir = bbn\mvc::get_user_data_path($user, 'appui-chat').$id_chat.'/'.$day;
          if ( bbn\file\dir::create_path($dir) ){
            file_put_contents($dir.'/'.$time.'.msg', $st);
          }
        }
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
  public function info($id_chat): ?array
  {
    if ( $this->check() ){
      return $this->db->rselect('bbn_chats', [], ['id' => $id_chat]) ?: null;
    }
    return null;
  }

  public function set_title(string $id_chat, string $title = null){
    if (
      \bbn\str::is_uid($id_chat) &&
      $this->is_admin($id_chat) &&
      $this->db->update('bbn_chats', ['title' => $title], ['id' => $id_chat])
    ){
      return $this->_add_bot_message($id_chat, [
        $this->user->get_id() => _("You have changed the chat title"),
        $this->user->get_name() . ' ' . _('has changed the chat title')
      ]);
    }
  }

  /**
   * Adds the given user to the given chat (if the current user is admin of this chat).
   *
   * @param string $id_chat
   * @param string $id_user
   * @return bool
   */
  public function add_user(string $id_chat, string $id_user): bool
  {
    if (
      $this->is_admin($id_chat) &&
      ($name1 = $this->user->get_name()) &&
      ($name2 = $this->user->get_name($id_user)) &&
      $this->db->insert_update('bbn_chats_users', [
        'id_chat' => $id_chat,
        'id_user' => $id_user,
        'entrance' => x::microtime(),
        'admin' => 0,
        'active' => 1
      ])
    ){
      return $this->_add_bot_message($id_chat, [
        $this->user->get_id() => _('You added') . " $name2",
        $id_user => "$name1 " . _('added you'),
        $name1 . ' ' . _('added') . ' ' . $name2
      ]);
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
  public function remove_user(string $id_chat, string $id_user): bool
  {
    if (
      $this->is_admin($id_chat) &&
      bbn\str::is_uid($id_user) &&
      ($name1 = $this->user->get_name()) &&
      ($name2 = $this->user->get_name($id_user)) &&
      $this->db->update('bbn_chats_users', ['active' => 0], [
        'id_chat' => $id_chat,
        'id_user' => $id_user
      ])
    ){
      return $this->_add_bot_message($id_chat, [
        $this->user->get_id() => _('You remove') . " $name2",
        $name1 . ' ' . _('removed') . ' ' . $name2
      ]);
    }
    return false;
  }

  public function add_admin(string $id_chat, string $id_user): ?bool
  {
    if (
      $this->check() &&
      bbn\str::is_uid($id_chat) &&
      bbn\str::is_uid($id_user) &&
      $this->is_creator($id_chat) &&
      ($name = $this->user->get_name()) &&
      ($name2 = $this->user->get_name($id_user))
    ){
      return $this->_set_admin($id_chat, $id_user, true, [
        $this->user->get_id() => _('You set') . " $name2 " . _('as admin'),
        $id_user => "$name " . _('set you as admin'),
        "$name " . _('set') . " $name2 " . _('as admin')
      ]);
    }
    return null;
  }

  public function remove_admin(string $id_chat, string $id_user): ?bool
  {
    if (
      $this->check() &&
      bbn\str::is_uid($id_chat) &&
      bbn\str::is_uid($id_user) &&
      $this->is_creator($id_chat) &&
      ($name = $this->user->get_name()) &&
      ($name2 = $this->user->get_name($id_user))
    ){
      return $this->_set_admin($id_chat, $id_user, false, [
        $this->user->get_id() => _('You removed') . " $name2 " . _('as admin'),
        $id_user => "$name " . _('removed you as admin'),
        "$name " . _('removed') . " $name2 " . _('as admin')
      ]);
    }
    return null;
  }

  /**
   * Returns the participants of the given chat as an array of id_user.
   *
   * @param string $id_chat
   * @param bool $with_current
   * @return array|null
   */
  public function get_participants(string $id_chat, bool $with_current = true): ?array
  {
    if ( $this->check() ){
      $where = [['id_chat', '=', $id_chat], ['active', '=', 1]];
      if ( !$with_current ){
        $where[] = ['id_user', '!=', $this->user->get_id()];
      }
      return $this->db->get_field_values('bbn_chats_users', 'id_user', $where);
    }
    return null;
  }

  /**
   * Returns the admins of the given chat as an array of id_user.
   *
   * @param string $id_chat
   * @return array|null
   */
  public function get_admins(string $id_chat): ?array
  {
    if (
      $this->check() &&
      bbn\str::is_uid($id_chat)
    ){
      return $this->db->get_field_values('bbn_chats_users', 'id_user', [
        'id_chat' => $id_chat,
        'active' =>  1,
        'admin' => 1
      ]);
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
  public function is_participant(string $id_chat, string $id_user = null): ?bool
  {
    if (
      $this->check() &&
      bbn\str::is_uid($id_chat) &&
      (bbn\str::is_uid($id_user) || \is_null($id_user))
    ){
      return (bool)$this->db->count('bbn_chats_users', [
        'id_chat' => $id_chat,
        'id_user' => $id_user ?: $this->user->get_id()
      ]);
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
  public function is_admin(string $id_chat, string $id_user = null): ?bool
  {
    if (
      $this->check() &&
      bbn\str::is_uid($id_chat) &&
      ($chat = $this->info($id_chat)) &&
      !$chat['blocked'] &&
      (bbn\str::is_uid($id_user) || \is_null($id_user))
    ){
      return (bool)$this->db->count('bbn_chats_users', [
        'id_chat' => $id_chat,
        'id_user' => $id_user ?: $this->user->get_id(),
        'admin' => 1
      ]);
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
  public function is_creator(string $id_chat, string $id_user = null): ?bool
  {
    if (
      $this->check() &&
      bbn\str::is_uid($id_chat) &&
      ($chat = $this->info($id_chat)) &&
      !$chat['blocked'] &&
      (bbn\str::is_uid($id_user) || \is_null($id_user))
    ){
      return $chat['creator'] === $id_user ?: $this->user->get_id();
    }
    return null;
  }

  public function get_chats(): ?array
  {
    if ( $this->check() ){
      return $this->db->get_field_values('bbn_chats_users', 'id_chat', [
        'id_user' => $this->user->get_id(),
        'active' => 1
      ], [
        'last_message' => 'DESC'
      ]);
    }
  }

  public function get_chat_by_users(array $users): ?string
  {
    if ( $this->check() && count($users) ){
      $cfg = [
        'tables' => ['bbn_chats'],
        'fields' => ['bbn_chats.id'],
        'join' => [],
        'where' => ['blocked' => 1]
      ];
      foreach ( $users as $i => $u ){
        $cfg['join'][] = [
          'table' => 'bbn_chats_users',
          'alias' => 'u'.($i+1),
          'on' => [
            'conditions' => [
              [
                'field' => 'bbn_chats.id',
                'exp' => 'u'.($i+1).'.id_chat'
              ]
            ]
          ]
        ];
        $cfg['where']['u'.($i+1).'.id_user'] = $u;
      }
      $id_chat = false;
      $ids = $this->db->get_column_values($cfg);
      if ( count($ids) ){
        foreach ( $ids as $id ){
          if ( count($this->get_participants($id)) === (count($users) + 1) ){
            $id_chat = $id;
            break;
          }
        }
      }
      if ( !$id_chat ){
        $id_chat = $this->create($users);
      }
      return $id_chat ?: null;
    }
    return null;
  }

  public function has_old_messages(string $id_chat, $moment): bool
  {
    if ( $this->check() ){
      $cdir = bbn\mvc::get_user_data_path($this->user->get_id(), 'appui-chat').$id_chat.'/';
      if ( $this->is_participant($id_chat) && is_dir($cdir) ){
        $dir = $cdir . date('Y-m-d', $moment);
        $files = \bbn\file\dir::get_files($dir);
        foreach ( $files as $file ){
          $time = round((float)basename($file, '.msg'), 4);
          if ( x::compare_floats($time, $moment, '<') && ($st = file_get_contents($file)) ){
            return true;
          }
        }
        $dirs = \bbn\file\dir::get_dirs($cdir);
        foreach ( $dirs as $d ){
          if ( (basename($d) < date('Y-m-d', $moment)) && !empty(\bbn\file\dir::get_files($d)) ){
            return true;
          }
        }
      }
    }
    return false;
  }

  public function get_prev_messages(string $id_chat, float $moment = null, int $num = 50): ?array
  {
    return $this->_get_messages($id_chat, $moment ?: x::microtime(), '<', $num);
  }

  public function get_next_messages(string $id_chat, float $moment = null, int $num = 0){
    return $this->_get_messages($id_chat, $moment ?: x::microtime(), '>', $num);
  }

  public function get_fromto_messages(string $id_chat, float $from, float $to, int $num = 0){

  }

  /**
   * Close a chat by setting blocked to 1.
   *
   * @param $id_chat
   * @return bool
   */
  public function block($id_chat): bool
  {
    if ( $this->is_admin($id_chat) ){
      return (bool)$this->db->update('bbn_chats', ['blocked' => 1], ['id' => $id_chat]);
    }
    return false;
  }

  public function leave(string $id_chat, string $id_user = null): ?bool
  {
    if (
      $this->check() &&
      bbn\str::is_uid($id_chat) &&
      $this->is_participant($id_chat) &&
      $this->_add_bot_message($id_chat, $this->user->get_name($id_user ?: $this->user->get_id()) . ' ' . _('has left the chat')) &&
      $this->db->update('bbn_chats_users', ['active' => 0], [
        'id_chat' => $id_chat,
        'id_user' => $id_user ?: $this->user->get_id()
      ])
    ){
      $ok = true;
      if (
        ($parts = $this->get_participants($id_chat)) &&
        (count($parts) === 1 )
      ){
        $ok = !!$this->leave($id_chat, $parts[0]);
      }
      return $ok;
    }
    return null;
  }

  public function set_last_activity(string $id_chat, string $id_user){
    if (
      bbn\str::is_uid($id_chat) &&
      bbn\str::is_uid($id_user) &&
      $this->is_participant($id_chat, $id_user)
    ){
      return $this->db->update('bbn_chats_users', ['last_activity' => x::microtime()], [
        'id_chat' => $id_chat,
        'id_user' => $id_user
      ]);
    }
  }

  public function mute(){

  }

  public function unmute(){

  }

  /**
   * Returns messages from the given chat sent after $last.
   *
   * @param $id_chat
   * @param null $last
   * @param null $day
   * @return array
   */
  public function get_messages($id_chat, $last = null, $day = null): array
  {
    $res = ['success' => false, 'last' => null, 'messages' => []];
    if ( $this->check() ){
      $dir = bbn\mvc::get_user_data_path($this->user->get_id(), 'appui-chat').$id_chat.'/'.($day ?: date('Y-m-d'));
      if ( $this->is_participant($id_chat) && is_dir($dir) ){
        $res['success'] = true;
        $files = bbn\file\dir::get_files($dir);
        foreach ( $files as $file ){
          $time = (float)basename($file, '.msg');
          if ( (!$last || x::compare_floats($time, $last, '>')) && ($st = file_get_contents($file)) ){
            $res['messages'][] = json_decode(bbn\util\enc::decrypt($st), true);
          }
        }
        if ( isset($time) ){
          $res['last'] = $time;
        }
      }
    }
    return $res;
  }

  /**
   * Returns messages from the given chat for a specific day.
   *
   * @param $id_chat
   * @param $day
   * @return array
   */
  public function get_old_messages($id_chat, $day)
  {
    return $this->get_messages($id_chat, null, $day);
  }

  /**
   * 
   */
  public function get_active_chats(){
    if ( $chats = $this->get_chats() ){
      $t =& $this;
      $d = new \DateTime();
      $d->sub(new \DateInterval('PT20M'));
      if ( $chats = array_filter($chats, function($c) use($d, $t){
        return ($m = $t->get_messages($c, $d->getTimestamp())) && !empty($m['messages']);
      }) ){
        return array_map(function($c) use($d, $t){
          return [
            'id' => $c,
            'messages' => ($m = $t->get_messages($c)) ? $m['messages'] : [],
            'partecipants' => $t->get_participants($c),
            'has_old' => $t->has_old_messages($c, $d->getTimestamp()-1)
          ];
        }, $chats);
      }
    }
    return [];
  }

}