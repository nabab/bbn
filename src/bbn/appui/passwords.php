<?php

namespace bbn\appui;
use bbn;

class passwords extends bbn\models\cls\db
{
	use
    bbn\models\tts\dbconfig;

  private $_o;

	protected static  
		$_defaults = [
      'table' => 'bbn_passwords',
      'tables' => [
        'passwords' => 'bbn_passwords'
      ],
			'arch' => [
        'passwords' => [
          'id' => 'id',
          'id_option' => 'id_option',
          'id_user_option' => 'id_user_option',
          'password' => 'password'
        ]
			]
		];

  public function __construct(\bbn\db $db) 
  {
    parent::__construct($db);
		$this->_init_class_cfg();
    $this->_o = options::get_instance();
  }

  public function store(string $password, string $id_option): bool
  {
    if ($password && defined('BBN_ENCRYPTION_KEY')) {
      $arch =& $this->class_cfg['arch']['passwords'];
      $to_store = \bbn\util\enc::crypt($password, BBN_ENCRYPTION_KEY);
      //var_dump(base64_encode($to_store));
      return (bool)$this->db->insert_update($this->class_cfg['table'], [
        $arch['id_option'] => $id_option,
        $arch['password'] => base64_encode($to_store)
      ]);
    }
    return false;
  }

  public function user_store(string $password, string $id_pref, bbn\user $user): bool
  {
    if ($password && ($to_store = $user->crypt($password))) {
      $arch =& $this->class_cfg['arch']['passwords'];
      return (bool)$this->db->insert_update($this->class_cfg['table'], [
        $arch['id_user_option'] => $id_pref,
        $arch['password'] => base64_encode($to_store)
      ]);
    }
    return false;
  }

  public function get(string $id_option): ?string
  {
    if (defined('BBN_ENCRYPTION_KEY')) {
      $arch =& $this->class_cfg['arch']['passwords'];
      if ($password = $this->db->select_one($this->class_cfg['table'], $arch['password'], [
        $arch['id_option'] => $id_option
      ])) {
        return \bbn\util\enc::decrypt(base64_decode($password), BBN_ENCRYPTION_KEY);
      }
    }
    return null;
  }

  public function user_get(string $id_pref, bbn\user $user): ?string
  {
    if ($user->is_auth()) {
      $arch =& $this->class_cfg['arch']['passwords'];
      if ($password = $this->db->select_one($this->class_cfg['table'], $arch['password'], [
        $arch['id_user_option'] => $id_pref
      ])) {
        return $user->decrypt(base64_decode($password));
      }
    }
    return null;
  }

  public function delete(string $id_option)
  {
    $arch =& $this->class_cfg['arch']['passwords'];
    return $this->db->delete($this->class_cfg['table'], [
      $arch['id_option'] => $id_option
    ]);
  }

  public function user_delete(string $id_pref, bbn\user $user)
  {
    if ($user->is_auth()) {
      $arch =& $this->class_cfg['arch']['passwords'];
      return $this->db->delete($this->class_cfg['table'], [
        $arch['id_user_option'] => $id_pref
      ]);
    }
  }
}