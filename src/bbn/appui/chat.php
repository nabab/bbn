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
          'entrance' => microtime(true),
          'admin' => 1
        ]);
        foreach ( $users as $user ){
          $this->db->insert_ignore('bbn_chats_users', [
            'id_chat' => $id_chat,
            'id_user' => $user,
            'entrance' => microtime(true),
            'admin' => 0
          ]);
        }
        return $id_chat;
      }
    }
    return null;
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
    if ( $this->is_admin($id_chat) ){
      return (bool)$this->db->insert_ignore('bbn_chats_users', [
        'id_chat' => $id_chat,
        'id_user' => $id_user,
        'entrance' => microtime(true),
        'admin' => 0
      ]);
    }
    return false;
  }

  /**
   * Makes the given participant an admin of the given chat provided the current user is admin of this chat.
   *
   * @param string $id_chat
   * @param string $id_user
   * @return bool
   */
  public function make_admin(string $id_chat, string $id_user): bool
  {
    if ( $this->is_admin($id_chat) ){
      return (bool)$this->db->update_ignore('bbn_chats_users', [
        'admin' => 1
      ], [
        'id_chat' => $id_chat,
        'id_user' => $id_user,
      ]);
    }
    return false;
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
    if ( $this->check() ){
      return (bool)$this->db->count('bbn_chats_users', [
        'id_chat' => $id_chat,
        'id_user' => $id_user ?: $this->user->get_id()
      ]);
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

  /**
   * Returns the participants of the given chat as an array of id_user.
   *
   * @param string $id_chat
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
        $time = microtime(true);
        $st = bbn\util\enc::crypt(json_encode(['time' => $time, 'user' => $this->user->get_id(), 'message' => $message]));
        $day = date('Y-m-d');
        foreach ( $users as $user ){
          $dir = BBN_DATA_PATH.'users/'.$user.'/chat/'.$id_chat.'/'.$day;
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
   * Checks whether the current user is an admin of the given chat or not.
   *
   * @param $id_chat
   * @return bool|null
   */
  public function is_admin($id_chat): ?bool
  {
    if ( $this->check() && ($chat = $this->info($id_chat)) && !$chat['blocked'] ){
      return (bool)$this->db->count('bbn_chats_users', [
        'id_chat' => $id_chat,
        'id_user' => $this->user->get_id(),
        'admin' => 1
      ]);
    }
    return null;
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
      $dir = BBN_DATA_PATH.'users/'.$this->user->get_id().'/chat/'.$id_chat.'/'.($day ?: date('Y-m-d'));
      if ( $this->is_participant($id_chat) && is_dir($dir) ){
        $res['success'] = true;
        $files = bbn\file\dir::get_files($dir);
        foreach ( $files as $file ){
          $time = (float)basename($file, '.msg');
          if ( (!$last || x::compare_floats($time, $last, '>')) && ($st = file_get_contents($file)) ){
            $enc = bbn\util\enc::crypt('test');
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

  public function has_old_messages(string $id_chat, $moment): bool
  {
    if ( $this->check() ){
      $cdir = BBN_DATA_PATH.'users/'.$this->user->get_id().'/chat/'.$id_chat.'/';
      if ( $this->is_participant($id_chat) && is_dir($cdir) ){
        $dir = $cdir . date('Y-m-d', $moment);
        $files = \bbn\file\dir::get_files($dir);
        foreach ( $files as $file ){
          $time = (float)basename($file, '.msg');
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

}