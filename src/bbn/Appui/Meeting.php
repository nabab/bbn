<?php
namespace bbn\Appui;

use bbn;
use bbn\Str;

/**
 * Meeting management in Appui
 * @category Appui
 * @package Appui
 * @author Mirko Argentino <mirko@bbn.solutions>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link https://bbn.io/bbn-php/doc/class/Appui/Meeting
 */
class Meeting extends bbn\Models\Cls\Db
{
  use bbn\Models\Tts\Optional;

  private $opt;

  private $optFields;

  private $prefTable;

  private $prefFields;

  private $passCls;

  /**
   * Constructor.
   * @param \bbn\Db $db
   */
  public function __construct(\bbn\Db $db)
  {
    parent::__construct($db);
    self::optionalInit();
    $this->opt = self::getOptionsObject();
    $optCfg = $this->opt->getClassCfg();
    if (!$optCfg) {
      throw new \Error(_('No configuration found for the Option class'));
    }
    $this->optFields = $optCfg['arch']['options'];
    $prefCfg = \bbn\User\Preferences::getInstance()->getClassCfg();
    if (!$prefCfg) {
      throw new \Error(_('No configuration found for the Preferences class'));
    }
    $this->prefTable = $prefCfg['table'];
    $this->prefFields = $prefCfg['arch']['user_options'];
    $this->passCls = new \bbn\Appui\Passwords($this->db);
  }


  public function getRoom(string $idRoom): ?array
  {
    if (!Str::isUid($idRoom)) {
      throw new \Error(_('The room id given is not a uuid'));
    }
    if (($room = $this->db->rselect($this->prefTable, [], [$this->prefFields['id'] => $idRoom]))
      && Str::isJson($room[$this->prefFields['cfg']])
    ) {
      $room = \array_merge($room, \json_decode($room[$this->prefFields['cfg']], true));
      unset($room[$this->prefFields['cfg']]);
    }
    return $room;
  }


  public function getRooms(string $server, string $idUser = null, string $idGroup = null)
  {
    if (!Str::isUid($server)) {
      $server = $this->opt->fromCode($server);
    }
    if (!Str::isUid($server)) {
      throw new \Error(_('The server id given is not a uuid'));
    }
    $where = [
      'conditions' => [[
        'field' => $this->prefFields['id_option'],
        'value' => $server
      ], [
        'logic' => 'OR',
        'conditions' => [[
          'field' => $this->prefFields['public'],
          'value' => 1
        ]]
      ]]
    ];
    if (!empty($idUser)) {
      $where['conditions'][1]['conditions'][] = [
        'field' => $this->prefFields['id_user'],
        'value' => $idUser
      ];
    }
    if (!empty($idGroup)) {
      $where['conditions'][1]['conditions'][] = [
        'field' => $this->prefFields['id_group'],
        'value' => $idGroup
      ];
    }
    $rooms = $this->db->rselectAll([
      'table' => $this->prefTable,
      'fields' => [],
      'where' => $where
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


  public function getUserRooms(string $server, string $idUser)
  {
    $user = \bbn\User::getInstance();
    $userManager = $user->getManager();
    $userData = $userManager->getUser($idUser);
    $userCfg = $user->getClassCfg();
    $idGroup = $userData[$userCfg['arch']['users']['id_group']] ?? false;
    if (empty($idGroup)) {
      throw new \Error(sprintf(_('No group found for the user %s'), $idUser));
    }
    return $this->getRooms($server, $idUser, $idGroup);
  }


  public function addRoom(string $server, string $name, $idUser = null, $idGroup = null, array $moderators = []): ?string
  {
    $idServer = self::getOptionId($server, 'list');
    if (!$idServer) {
      throw new \Error(sprintf(_('Server not found %s'), $server));
    }
    if ($this->db->selectOne($this->prefTable, $this->prefFields['id'], [
      $this->prefFields['text'] => $name,
      $this->prefFields['id_option'] => $idServer
    ])) {
      throw new \Error(sprintf(_('The room %s already exists'), $name));
    }
    if ($this->db->insert($this->prefTable, [
      $this->prefFields['text'] => $name,
      $this->prefFields['id_option'] => $idServer,
      $this->prefFields['id_user'] => $idUser,
      $this->prefFields['id_group'] => $idGroup,
      $this->prefFields['public'] => empty($idUser) && empty($idGroup) ? 1 : 0,
      $this->prefFields['cfg'] => \json_encode([
        'created' => date('Y-m-d H:i:s'),
        'last_use' => '',
        'last_duration' => 0
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
      throw new \Exception(sprintf(_('Room not found %s'), $idRoom));
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
        throw new \Error(sprintf(_('The room %s already exists'), $name));
      }
      $toUpd[$this->prefFields['text']] = $name;
    }
    if (!empty($toUpd)
      && !$this->db->update($this->prefTable, $toUpd, [$this->prefFields['id'] => $idRoom])
    ) {
      return false;
    }
    if (!empty($moderators)) {
      $oldModerators = $this->getModerators($idRoom);
      if (!empty($oldModerators)) {
        foreach ($oldModerators as $m) {
          if (!\in_array($m, $moderators, true)
            && !$this->removeModerator($m, $idRoom)
          ) {
            throw new \Exception(sprintf(_('Error during the elimination of the moderator %s from the room %s'), $m, $idRoom));
          }
        }
      }
      foreach ($moderators as $m) {
        if (!\in_array($m, $oldModerators, true)
            && !$this->addModerator($m, $idRoom)
          ) {
            throw new \Exception(sprintf(_('Error during the insertion of the moderator %s to the room %s'), $m, $idRoom));
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
        throw new \Exception(sprintf(_('No room found with the id %s'), $idRoom));
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
          throw new \Error(sprintf(_('No server found by the room %s'), $idRoom));
        }
        if (!$this->passCls->userStore($this->makeJWT($server[$this->optFields['code']], $r[$this->prefFields['text']]), $idModerator, $u)) {
          throw new \Error(sprintf(_('Error during JWT storing for the user %s for the room %s'), $idUser, $idRoom));
        }
        $t = $this->passCls->userGet($idModerator, $u);
      }
      return !empty($t);
    }
    return false;
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


  private function getModerator(string $idUser, string $idRoom): ?array
  {
    if (!Str::isUid($idUser)) {
      throw new \Error(_('The user id given is not a uuid'));
    }
    if (!Str::isUid($idRoom)) {
      throw new \Error(_('The room id given is not a uuid'));
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
      throw new \Error(sprintf(_('No App ID found for the server %s'), $server));
    }
    if (Str::isUid($room)) {
      $r = $this->getRoom($room);
      $room = $r[$this->prefFields['text']] ?? '';
    }
    if (empty($room)) {
      throw new \Error(sprintf(_('No room name - %s'), $room));
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
      throw new \Error(sprintf(_('No App Secret found for the server %s'), $server));
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