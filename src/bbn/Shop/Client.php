<?php

namespace bbn\Shop;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\Dbconfig;
use bbn\Appui\Option;
use bbn\Db;


class Client extends DbCls
{
  use Dbconfig;

  /**
   * @var Option
   */
  private $opt;

  /**
   * @var string
   */
  protected $lang;

  /**
   * @var string
   */
  protected $id;

  /**
   * @var array
   */
  protected static $default_class_cfg = [
    'errors' => [
    ],
    'table' => 'bbn_shop_clients',
    'tables' => [
      'clients' => 'bbn_shop_clients',
      'clients_addresses' => 'bbn_shop_clients_addresses'
    ],
    'arch' => [
      'clients' => [
        'id' => 'id',
        'id_user' => 'id_user',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'email' => 'email',
        'newsletter' => 'newsletter',
        'active' => 'active'
      ],
      'clients_addresses' => [
        'id' => 'id',
        'id_client' => 'id_client',
        'id_address' => 'id_address',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'def' => 'def',
        'last' => 'last',
        'active' => 'active'
      ]
    ],
  ];

  /**
   * Construct
   * @param \bbn\Db $db,
   * @param array $cfg
   */
  public function __construct(Db $db, array $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->_init_class_cfg($cfg);
    $this->opt = Option::getInstance();
  }

  /**
   * Returns the current client ID
   */
  public function getId(){
    return $this->id;
  }

  /**
   * Gets the client ID by the given user ID
   * @param string $idUser
   * @return null|string
   */
  public function getIdByUser(string $idUser): ?string
  {
    return $this->db->selectOne($this->class_table, $this->fields['id'], [
      $this->fields['id_user'] => $idUser,
      $this->fields['active'] => 1
    ]);
  }

  /**
   * Gets a client
   * @param strin $id
   * @return null|array
   */
  public function get(string $id): ?array
  {
    return $this->rselect([
      $this->fields['id'] => $id,
      $this->fields['active'] => 1
    ]);
  }

  /**
   * Adds a client
   * @param string $name The client name
   * @param string $email The client email
   * @param bool $newsletter
   * @param null|string $idUser The user ID
   * @return null|string The client ID
   */
  public function add(string $firstName, string $lastName, string $email, bool $newsletter = false, $idUser = null): ?string
  {
		if (!$this->selectOne($this->fields['id'], [$this->fields['email'] => $email])
      && $this->insert([
        $this->fields['id_user'] => $idUser,
        $this->fields['first_name'] => $firstName,
        $this->fields['last_name'] => $lastName ?: '',
        $this->fields['email'] => $email,
        $this->fields['newsletter'] => empty($newsletter) ? 0 : 1
      ])
    ) {
      return $this->db->lastId();
    }
		return null;
	}

	public function addClientName(string $idClient, string $name, string $lastName){
		if ($this->rselect([$this->fields['id'] => $idClient])) {
			if ($this->update($idClient, [
        $this->fields['first_name'] => $name,
        $this->fields['last_name'] => $lastName
      ])) {
				return $name . ' ' . $lastName;
			}
		}
	}

  /**
   * Adds a client address
   * @param string $idClient,
   * @param array $address
   * @return null|string
   */
	public function addAddress(string $idClient, array $address): ?string
  {
    $opt = Option::getInstance();
    $addressCls = new \bbn\Entities\Address($this->db);
    $addressCfg = $addressCls->getClassCfg();
    if (empty($address[$addressCfg['arch']['addresses']['country']])) {
      throw new \Exception(_('The address country is mandatory'));
    }
    if (!$opt->option($address[$addressCfg['arch']['addresses']['country']])) {
      throw new \Exception(X::_('Country not found: %s', $address[$addressCfg['arch']['addresses']['country']]));
    }
    $idAddress = $address[$this->class_cfg['arch']['clients_addresses']['id_address']] ?? null;
    if (isset($address['fulladdress'])) {
      unset($address['fulladdress']);
    }
    if (empty($idAddress)) {
      $toAddress = \array_filter($address, function($k) use($addressCfg){
        return \in_array($k, \array_values($addressCfg['arch']['addresses']), true);
      }, ARRAY_FILTER_USE_KEY);
      if (!empty($address['region'])) {
        $toAddress['region'] = $address['region'];
      }
      $idAddress = $addressCls->insert($toAddress);
    }
    if (!empty($idAddress)) {
      if (!empty($address[$this->class_cfg['arch']['clients_addresses']['def']])) {
        $this->db->update($this->class_cfg['tables']['clients_addresses'], [
          'def' => 0
        ], [
          'id_client' => $idClient,
          'def' => $address[$this->class_cfg['arch']['clients_addresses']['def']]
        ]);
      }
      if ($this->db->insert($this->class_cfg['tables']['clients_addresses'], [
        $this->class_cfg['arch']['clients_addresses']['id_client'] => $idClient,
        $this->class_cfg['arch']['clients_addresses']['id_address'] => $idAddress,
        $this->class_cfg['arch']['clients_addresses']['first_name'] => $address[$this->class_cfg['arch']['clients_addresses']['first_name']],
        $this->class_cfg['arch']['clients_addresses']['last_name'] => $address[$this->class_cfg['arch']['clients_addresses']['last_name']],
        $this->class_cfg['arch']['clients_addresses']['def'] => $address[$this->class_cfg['arch']['clients_addresses']['def']],
        $this->class_cfg['arch']['clients_addresses']['last'] =>  date('Y-m-d H:i:s')
      ])) {
        return $this->db->lastId();
      }
    }
    return null;
	}

	public function getEmail(string $idClient): ?string
	{
		return $this->selectOne($this->fields['email'], [$this->fields['id'] => $idClient]);
	}

  /**
   * Gets the addresses list of a client
   * @param string $idClient
   * @return array
   */
  public function getAddresses(string $idClient): array
  {
    $res = [];
    if ($addresses = $this->db->getColumnValues($this->class_cfg['tables']['clients_addresses'], $this->class_cfg['arch']['clients_addresses']['id'], [
      $this->class_cfg['arch']['clients_addresses']['id_client'] => $idClient
    ], [
      $this->class_cfg['arch']['clients_addresses']['id_client'] => 'desc'
    ])) {
      foreach ($addresses as $a) {
        if ($ad = $this->getAddress($a)) {
          $res[] = $ad;
        }
      }
    }
    return $res;
  }

  /**
   * Gets a client address
   * @param string $idClientAddress
   * @return null|array
   */
	public function getAddress(string $idClientAddress): ?array
	{
    if ($clientAddress = $this->db->rselect($this->class_cfg['tables']['clients_addresses'], [], [
      $this->class_cfg['arch']['clients_addresses']['id'] => $idClientAddress
    ])) {
      $addressCls = new \bbn\Entities\Address($this->db);
      $addressCfg = $addressCls->getClassCfg();
      $addressFields = $addressCfg['arch']['addresses'];
      if ($addr = $addressCls->rselect($clientAddress[$this->class_cfg['arch']['clients_addresses']['id_address']])) {
        $ad = explode("\n", $addr[$addressFields['address']]);
        $clientAddress = X::mergeArrays($clientAddress, [
          'address1' => $ad[0],
          'address2' => $ad[1] ?? '',
          'postcode' => $addr[$addressFields['postcode']],
          'city' => $addr[$addressFields['city']],
          'country' => $addr[$addressFields['country']],
          'phone' => $addr[$addressFields['phone']],
          'region' => !empty($addr['region']) ? $addr['region'] : '',
          'fulladdress'=> $addr[$addressFields['fulladdress']]
        ]);
      }
      else {
        throw new \Exception(X::_('Address not found: %s', $clientAddress[$this->class_cfg['arch']['clients_addresses']['id_address']]));
      }
    }
    return $clientAddress;
	}

  /**
   * Gets the default shipping address of the give client ID
   * @param string $id The client ID
   * @return null|array
   */
  public function getDefaultShippingAddress(string $id): ?array
  {
    $table = $this->class_cfg['tables']['clients_addresses'];
    $fields = $this->class_cfg['arch']['clients_addresses'];
    if ($idClientAddress = $this->db->selectOne($table, $fields['id'], [
      $fields['id_client'] => $id,
      $fields['def'] => 1
    ])) {
      return $this->getAddress($idClientAddress);
    }
    if ($idClientAddress = $this->db->selectOne($table, $fields['id'], [
      $fields['id_client'] => $id,
      $fields['def'] => 2
    ])) {
      return $this->getAddress($idClientAddress);
    }
    return null;
  }

  /**
   * Gets the default shipping address of the give client ID
   * @param string $id The client ID
   * @return null|array
   */
  public function getDefaultBillingAddress(string $id): ?array
  {
    $table = $this->class_cfg['tables']['clients_addresses'];
    $fields = $this->class_cfg['arch']['clients_addresses'];
    if ($idClientAddress = $this->db->selectOne($table, $fields['id'], [
      $fields['id_client'] => $id,
      $fields['def'] => 2
    ])) {
      return $this->getAddress($idClientAddress);
    }
    return null;
  }

  /**
   * Sets the 'last' field for the given client address
   * @param string $idClientAddress
   * @return bool
   */
  public function setLastUsedAddress(string $idClientAddress, string $moment = '')
  {
    $table = $this->class_cfg['tables']['clients_addresses'];
    $fields = $this->class_cfg['arch']['clients_addresses'];
    $moment = date('Y-m-d H:i:s', !empty($moment) ? strtotime($moment) : time());
    return (bool)$this->db->update($table, [$fields['last'] => $moment], [$fields['id'] => $idClientAddress]);
  }

}