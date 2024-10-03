<?php

namespace bbn\Appui;

use Exception;
use bbn\Db;
use bbn\Str;
use bbn\X;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use bbn\Models\Tts\Optional;
use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberParseException;
use Brick\PhoneNumber\PhoneNumberFormat;


class Uauth extends DbCls
{
  use DbActions;
  use Optional;

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
    ],
    'uauth_phone_region' => null
  ];


  public function __construct(Db $db, array $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Force the phone format to uppercase
    if (!empty($cfg['uauth_phone_region'])) {
      $cfg['uauth_phone_region'] = strtoupper($cfg['uauth_phone_region']);
    }
    else if (defined(BBN_LOCALE)) {
      $st = explode('.', BBN_LOCALE)[0];
      $cfg['uauth_phone_region'] = strtoupper(substr($st, -2));
    }

    // Setting up the class configuration
    $this->initClassCfg($cfg);
    self::optionalInit();
  }


  public function find(string $value, ?string $type = null): ?array
  {
    $arc = &$this->class_cfg['arch']['uauth'];
    if (in_array($type, ['portable', 'mobile', 'phone'])) {
      $value = $this->checkPhone($value);
    }

    $filter = [$arc['value'] => $value];
    if ($type) {
      $filter[$arc['typology']] = $this->getIdTypology($type);
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
    elseif (in_array($type, ['portable', 'mobile', 'phone'])) {
      $value = $this->checkPhone($value);
    }

    $idType = $this->getIdTypology($type);
    $arc = &$this->class_cfg['arch']['uauth'];
    $data = [
      $arc['value'] => $value,
      $arc['typology'] => $idType
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


  public function getIdTypology(string $type): ?string
  {
    if (Str::isUid($type)) {
      return $type;
    }
    
    if (!($idType = self::getOptionId($type, 'typologies'))) {
      throw new Exception(X::_("The type %s is not valid", $type));
    }

    return $idType;
  }


  public function checkPhone(string $phone)
  {
    try {
      $ph = PhoneNumber::parse($phone, $this->class_cfg['uauth_phone_region']);
      if ($ph) {
        if (!$ph->isPossibleNumber()) {
          throw new Exception(X::_("The value (%s) is not a valid phone number", $phone));
        }

        $phone = $ph->format(PhoneNumberFormat::E164);
      }
    }
    catch (PhoneNumberParseException $e) {
      throw new Exception(X::_("The value (%s) is not a valid phone number: %s", $e->getMessage(), $phone));
    }

    return $phone;
  }


}