<?php

namespace bbn\User;

use Exception;
use bbn\Db;
use bbn\Str;
use bbn\X;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberParseException;
use Brick\PhoneNumber\PhoneNumberFormat;


class Uauth extends DbCls
{
  use DbActions;

  protected static $default_class_cfg = [
    'table' => 'bbn_uauth',
    'tables' => [
      'uauth' => 'bbn_uauth'
    ],
    'arch' => [
      'uauth' => [
        'id' => 'id',
        'typology' => 'typology',
        'value' => 'value'
      ]
    ]
  ];

  public function __construct(Db $db, array $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->initClassCfg($cfg);
  }


  public function find(string $value, string $type = null): ?array
  {
    $arc = &$this->class_cfg['arch']['uauth'];
    $filter = [$arc['value'] => $value];
    if ($type) {
      $filter[$arc['typology']] = $type;
    }

    return $this->dbTraitRselect($filter);
  }


  public function get(string $id): ?array
  {
    return $this->dbTraitRselect($id);
  }


  public function getIndexed(string $id): ?array
  {
    $res = $this->get($id);
    $arc = &$this->class_cfg['arch']['uauth'];
    if ($res) {
      $res[$res[$arc['typology']]] = $res[$arc['value']];
      unset($res[$arc['typology']], $res[$arc['value']]);
    }

    return $res;
  }


  public function getValue(string $id): ?array
  {
    $arc = &$this->class_cfg['arch']['uauth'];
    return $this->dbTraitSelectOne($arc['value'], $id);
  }

  public function insert(string $value, string $type): ?string
  {
    if ($type === 'email') {
      if (!Str::isEmail($value)) {
        throw new Exception(X::_("The value is not a valid email"));
      }
    }
    elseif ($type === 'phone') {
      try {
        $ph = PhoneNumber::parse($value);
        if ($ph) {
          $value = $ph->format(PhoneNumberFormat::E164);
        }
      }
      catch (PhoneNumberParseException $e) {
        throw new Exception(X::_("The value is not a valid phone number"));
      }
    }
    else {
      throw new Exception(X::_("The type is not valid"));
    }

    $arc = &$this->class_cfg['arch']['uauth'];
    $data = [
      $arc['value'] => $value,
      $arc['typology'] => $type
    ];

    return $this->dbTraitInsert($data);
  }

  public function delete(string $id): bool
  {
    return $this->dbTraitDelete($id);
  }


  public function getRelations(): array 
  {
    return $this->dbTraitGetTableRelations();
  }



}