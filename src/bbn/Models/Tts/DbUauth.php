<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:47
 */

namespace bbn\Models\Tts;

use Exception;
use bbn\X;
use bbn\Db;
use bbn\Appui\Uauth;

trait DbUauth
{

  /** @var bool */
  private $_dbUauthIsInit = false;

  protected $dbUauthSystem;

  protected $dbUauthErrorCodes = [

  ];

  protected $dbUauthSystems = [
    // One user can have one unique auth
    'one-to-one',
    // One user can have multiple auths but they can't be shared
    'one-to-many',
    // Multiple users can have one unique auth
    'many-to-one',
    // Multiple users can have multiple auths
    'many-to-many'
  ];

  /**
   * 
   * @var Uauth
   */
  protected static $dbUauth;

  private static function dbUauthSetup(Db $db, array $cfg = []) : void
  {
    if (!self::$dbUauth) {
      self::$dbUauth = new Uauth($db, $cfg);
    }
  }

  protected static function dbUauthGetClass(): Uauth
  {
    return self::$dbUauth;
  }

  protected function dbUauthInit(): void
  {
    if (!$this->_dbUauthIsInit) {
      $this->_dbUauthIsInit = true;
      if (!$this->db || !isset($this->class_cfg['uauth_system']) || !isset($this->class_cfg['arch']['uauth'])) {
        throw new Exception(X::_("The uauth system is not defined"));
      }

      if (!in_array($this->class_cfg['uauth_system'], $this->dbUauthSystems)) {
        throw new Exception(X::_("The uauth system is not valid"));
      }

      $this->dbUauthSystem = $this->class_cfg['uauth_system'];
      $cfg = [];
      if (!empty($this->class_cfg['uauth_phone_region'])) {
        $cfg['uauth_phone_region'] = $this->class_cfg['uauth_phone_region'];
      }

      self::dbUauthSetup($this->db, $cfg);
    }
  }

  protected function dbUauthIsInit(): bool
  {
    return $this->_dbUauthIsInit;
  }

  protected function dbUauthInitCheck(): void
  {
    if (!$this->_dbUauthIsInit) {
      throw new Exception(X::_("The uauth system is not initialized"));
    }
  }

  protected function dbUauthHas(string $id, string $value, string $type): bool
  {
    $this->dbUauthInitCheck();
    $arch = $this->class_cfg['arch']['uauth'];
    $uauthCfg = self::$dbUauth->getClassCfg();
    $uauthArch = $uauthCfg['arch']['uauth'];
    if ($existing = self::$dbUauth->find($value, $type)) {
      $idUauth = $existing ? $existing['id'] : self::$dbUauth->insert($value, $type);
      if ($this->db->count([
        'tables' => [$this->class_cfg['tables']['uauth']],
        'join' => [[
          'table' => $uauthCfg['table'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->cfn($uauthArch['id'], $uauthCfg['table']),
              'exp' => $arch['id_uauth']
            ]]
          ]
        ]],
        'where' => [
          $arch['id_associate'] => $id,
          $arch['id_uauth'] => $idUauth,
          $uauthArch['typology'] => self::$dbUauth->getIdTypology($type)
        ]
      ])) {
        return true;
      }
    }

    return false;
  }

  protected function dbUauthSet(string $id, string $value, string $type, array|null $cfg = null): bool
  {
    $this->dbUauthInitCheck();
    $arch = $this->class_cfg['arch']['uauth'];
    $uauthCfg = self::$dbUauth->getClassCfg();
    $uauthArch = $uauthCfg['arch']['uauth'];
    if (!($id_type = self::$dbUauth->getIdTypology($type))) {
      throw new Exception(X::_("The typology is not valid"));
    }

    $existing = self::$dbUauth->find($value, $id_type);
    $idUauth = $existing ? $existing['id'] : self::$dbUauth->insert($value, $id_type);
    if ($existing && $this->db->count($this->class_cfg['tables']['uauth'], [
        $arch['id_associate'] => $id,
        $arch['id_uauth'] => $idUauth,
        $arch['typology'] => $id_type
    ])) {
      return false;
    }

    if (in_array($this->dbUauthSystem, ['one-to-one', 'one-to-many'])) {
      if ($this->db->getOne([
        'tables' => [$this->class_cfg['tables']['uauth']],
        'join' => [[
          'table' => $uauthCfg['table'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->cfn($uauthArch['id'], $uauthCfg['table']),
              'exp' => $arch['id_uauth']
            ]]
          ]
        ]],
        'where' => [
          $arch['id_uauth'] => $idUauth,
          $uauthArch['typology'] => $id_type
        ]
      ])) {
        $this->dbUauthRemoveAll($id, $type);
      }
    }

    if (in_array($this->dbUauthSystem, ['one-to-one', 'many-to-one'])) {
      if ($this->db->count([
        'tables' => [$this->class_cfg['tables']['uauth']],
        'join' => [[
          'table' => $uauthCfg['table'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->cfn($uauthArch['id'], $uauthCfg['table']),
              'exp' => $arch['id_uauth']
            ]]
          ]
        ]],
        'where' => [
          $arch['id_associate'] => $id,
          $uauthArch['typology'] => $id_type
        ]
      ])) {
        $this->dbUauthRemoveAll($id, $id_type);
      }
    }

    $data = [
      $arch['id_associate'] => $id,
      $arch['id_uauth'] => $idUauth
    ];
    if ($cfg) {
      $data[$arch['cfg']] = json_encode($cfg);
    }

    if ($this->db->insert($this->class_cfg['tables']['uauth'], $data)) {
      return true;
    }

    return false;

  }

  /**
   * Adds a new uauth to the associate, creating it if needed.
   * 
   * @param string $id
   * @param string $value
   * @param string $type
   * @param array $cfg
   * @throws \Exception
   * @return mixed
   */
  public function dbUauthAdd(string $id, string $value, string $type, array|null $cfg = null): ?string
  {
    $this->dbUauthInitCheck();
    $arch = $this->class_cfg['arch']['uauth'];
    $uauthCfg = self::$dbUauth->getClassCfg();
    $uauthArch = $uauthCfg['arch']['uauth'];
    if (!($id_type = self::$dbUauth->getIdTypology($type))) {
      throw new Exception(X::_("The typology is not valid"));
    }

    $existing = self::$dbUauth->find($value, $id_type);
    $idUauth = $existing ? $existing['id'] : self::$dbUauth->insert($value, $type);
    
    if ($existing && $this->db->count($this->class_cfg['tables']['uauth'], [
        $arch['id_associate'] => $id,
        $arch['id_uauth'] => $idUauth
    ])) {
      throw new Exception(X::_("The association already exists"));
    }

    if (in_array($this->dbUauthSystem, ['one-to-one', 'one-to-many'])) {
      if ($this->db->count([
        'tables' => [$this->class_cfg['tables']['uauth']],
        'join' => [[
          'table' => $uauthCfg['table'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->cfn($uauthArch['id'], $uauthCfg['table']),
              'exp' => $arch['id_uauth']
            ]]
          ]
        ]],
        'where' => [
          $arch['id_uauth'] => $idUauth,
          $uauthArch['typology'] => $id_type
        ]
      ])) {
        throw new Exception(X::_("%s is already used", $value));
      }
    }
    else if (in_array($this->dbUauthSystem, ['one-to-one', 'many-to-one'])) {
      if ($this->db->count([
        'tables' => [$this->class_cfg['tables']['uauth']],
        'join' => [[
          'table' => $uauthCfg['table'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->cfn($uauthArch['id'], $uauthCfg['table']),
              'exp' => $arch['id_uauth']
            ]]
          ]
        ]],
        'where' => [
          $arch['id_associate'] => $id,
          $uauthArch['typology'] => $id_type
        ]
      ])) {
        throw new Exception(X::_("%s is already used", $value));
      }
    }

    $data = [
      $arch['id_associate'] => $id,
      $arch['id_uauth'] => $idUauth
    ];
    if ($cfg) {
      $data[$arch['cfg']] = json_encode($cfg);
    }

    if ($this->db->insert($this->class_cfg['tables']['uauth'], $data)) {
      return $idUauth;
    }

    return null;
  }

  protected function dbUauthRemove(string $id, string $value, string $type): ?string
  {
    $this->dbUauthInitCheck();
    $existing = self::$dbUauth->find($value, $type);
    if (!$existing) {
      throw new Exception(X::_("The uauth does not exist"));
    }
    
    $arch = $this->class_cfg['arch']['uauth'];
    return $this->db->delete($this->class_cfg['tables']['uauth'], [
      $arch['id_associate'] => $id,
      $arch['id_uauth'] => $existing['id']
    ]);
  }

  protected function dbUauthRemoveAll(string $id, string $type): int
  {
    $this->dbUauthInitCheck();
    $id_type = self::$dbUauth->getIdTypology($type);
    $arch = $this->class_cfg['arch']['uauth'];
    $uauthCfg = self::$dbUauth->getClassCfg();
    $uauthArch = $uauthCfg['arch']['uauth'];
    $all = $this->db->getColumnValues([
      'table' => $this->class_cfg['tables']['uauth'],
      'fields' => $arch['id'],
      'join' => [
        [
          'table' => $uauthCfg['table'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->cfn($uauthArch['id'], $uauthCfg['table']),
              'exp' => $arch['id_uauth']
            ]]
          ]
        ]
      ],
      'where' => [
        $arch['id_associate'] => $id,
        $uauthArch['typology'] => $id_type
      ]
    ]);

    $res = 0;
    foreach ($all as $id_uauth) {
      $res += $this->db->delete($this->class_cfg['tables']['uauth'], [
        $arch['id'] => $id_uauth
      ]);
    }

    return $res;
  }


  /*
  protected function dbUauthUpdate(string $id, string $value, string $type, string $prev, array $cfg = []): ?string
  {
    $this->dbUauthInitCheck();
    $uauthCfg = $this->class_cfg['arch']['uauth'];
    $existing = self::$dbUauth->find($value, $type);
    if (!$existing) {

    }
    $idUauth = $existing ? $existing['id'] : self::$dbUauth->insert($value, $type);
    
    if ($existing && $this->db->count($this->class_cfg['tables']['uauth'], [
        $uauthCfg['id_associate'] => $id,
        $uauthCfg['id_uauth'] => $idUauth
    ])) {
      throw new Exception(X::_("The association already exists"));
    }

    if (in_array($this->dbUauthSystem, ['one-to-one', 'one-to-many'])) {
      if ($this->db->count($this->class_cfg['tables']['uauth'], [
        $uauthCfg['id_uauth'] => $existing['id']
      ])) {
        throw new Exception(X::_("%s is already used", $value));
      }
    }
    else if (in_array($this->dbUauthSystem, ['one-to-one', 'many-to-one'])) {
      if ($this->db->count($this->class_cfg['tables']['uauth'], [
        $uauthCfg['id_associate'] => $id,
      ])) {
        throw new Exception(X::_("A record is already associated"));
      }
    }

    $data = [
      $uauthCfg['id_associate'] => $id,
      $uauthCfg['id_uauth'] => $idUauth
    ];
    if ($cfg) {
      $data[$uauthCfg['cfg']] = json_encode($cfg);
    }

    if ($this->db->insert($this->class_cfg['tables']['uauth'], $data)) {
      return $idUauth;
    }

    return null;
  }
    */

  protected function dbUauthRetrieve(string $id_associate, string $type): ?array
  {
    $this->dbUauthInitCheck();
    $arch = $this->class_cfg['arch']['uauth'];
    $uauthCfg = self::$dbUauth->getClassCfg();

    $res = $this->db->rselectAll([
      'tables' => $this->class_cfg['tables']['uauth'],
      'fields' => [$arch['id_uauth'], $arch['id_associate'], $type => $uauthCfg['arch']['uauth']['value']],
      'join' => [[
        'table' => $uauthCfg['table'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->cfn($uauthCfg['arch']['uauth']['id'], $uauthCfg['table']),
            'exp' => $arch['id_uauth']
          ]]
        ]
      ]],
      'where' => [
        $arch['id_associate'] => $id_associate,
        $uauthCfg['arch']['uauth']['typology'] => self::$dbUauth->getIdTypology($type)
      ]
    ]);

    if (in_array($this->dbUauthSystem, ['one-to-one', 'many-to-one'])) {
      if (count($res) > 1) {
        throw new Exception(X::_("The record is associated to more than one uauth"));
      }

      return $res[0] ?? null;
    }
    else {
      return $res;
    }
  }

  protected function dbUauthGet(string $id_auth): ?array
  {
    $this->dbUauthInitCheck();
    return self::$dbUauth->get($id_auth);
  }


  protected function dbUauthGetValue($id_auth): ?string
  {
    $this->dbUauthInitCheck();
    
    return self::$dbUauth->getValue($id_auth);
  }


  protected function dbUauthFind(string $value, string|null $type = null): ?array
  {
    $this->dbUauthInitCheck();
    return self::$dbUauth->find($value, $type);
  }

  protected function dbUauthGetByUauth(string $id_uauth): ?array
  {
    $this->dbUauthInitCheck();
    if (self::$dbUauth->exists($id_uauth)) {
      return $this->db->getColumnValues(
        $this->class_cfg['tables']['uauth'],
        $this->class_cfg['arch']['uauth']['id_associate'],
        [$this->class_cfg['arch']['uauth']['id_uauth'] => $id_uauth]
      );
    }
  }



}

