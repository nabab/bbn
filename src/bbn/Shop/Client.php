<?php

namespace bbn\Shop;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\Dbconfig;
use bbn\Appui\Grid;
use bbn\Appui\Medias;
use bbn\Appui\Note;
use bbn\Appui\Cms;
use bbn\Appui\Option;
use bbn\Db;


class Client extends DbCls
{
  use Dbconfig;

  /**
   * @var Medias
   */
  private $medias;

  /**
   * @var Cms
   */
  private $cms;

  /**
   * @var Note
   */
  private $note;

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
  protected $type_note;

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
        'name' => 'name',
        'email' => 'email',
        'newsletter' => 'newsletter',
        'active' => 'active'
      ],
      'clients_addresses' => [
        'id' => 'id',
        'id_client' => 'id_client',
        'id_address' => 'id_address',
        'def' => 'def',
        'last' => 'last',
        'active' => 'active'
      ]
    ],
  ];

  public function __construct(Db $db, array $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->_init_class_cfg($cfg);
    $this->opt   = Option::getInstance();
    $this->note  = new Note($this->db);
    $this->cms   = new Cms($this->db, $this->note);
    $this->media = new Medias($this->db);
    $this->media->setImageRoot('/image/');
  }

  public function add(string $email, bool $newsletter = false): ?string
  {
		if (!($idClient = $this->db->selectOne($this->class_table, $this->fields['id'], [$this->fields['email'] => $email]))
      && $this->db->insert($this->class_table, [
        $this->fields['email'] => $email,
        $this->fields['newsletter'] => empty($newsletter) ? 0 : 1
			])
    ) {
			$idClient = $this->db->lastId();
		}
		return $idClient;
	}
	public function addClientName(string $idClient, string $name, string $lastname){
		if($this->db->rselect($this->class_table, [], [$this->fields['id'] => $idClient])){
			if($this->db->update($this->class_table, [$this->fields['name'] => $name.' '.$lastname],[$this->fields['id'] => $idClient])){
				return $name.' '.$lastname;
			}
		}
	}
	
	public function addAddress(string $idClient, array $address): ?array
  {
    $opt = Option::getInstance();
    $entity = new \bbn\Entities\Address($this->db);
    $cfg = $entity->getClassCfg();
    $address[$cfg['arch']['addresses']['email']] = $this->getEmail($idClient);
		if(($name = $address['name']) && ($lastname = $address['lastname'])){
			$fullName = $this->addClientName($idClient, $name, $lastname);
			unset($address['name'], $address['lastname']);
		}
    $address[$cfg['arch']['addresses']['address']] = $address['address1'] . ' ' . $address['address2'];
    $country = $opt->option($address['country']);
    if ($idAddress = $entity->insert($address)) {
      if ($this->db->insert($this->class_cfg['tables']['clients_addresses'], [
        $this->class_cfg['arch']['clients_addresses']['id_client'] => $idClient,
        $this->class_cfg['arch']['clients_addresses']['id_address'] => $idAddress,
        $this->class_cfg['arch']['clients_addresses']['def'] => 1,
        $this->class_cfg['arch']['clients_addresses']['last'] =>  date('Y-m-d H:i:s')
      ])) {
        $newAddress = $this->getAddress($idAddress);
        $newAddress['continent'] = $country['continent'];
				$newAddress['fullName'] = $fullName;
        return $newAddress;
      }
    }
    return null;
	}

	protected function getEmail(STRING $idClient): ?string
	{
		return $this->db->selectOne($this->class_table, $this->fields['email'], [$this->fields['id'] => $idClient]);
	}

	protected function getAddress(string $idAddress): ?array
	{
    $entity = new \bbn\Entities\Address($this->db);
    $cfg = $entity->getClassCfg();
		return $entity->rselect([$cfg['arch']['addresses']['id'] => $idAddress]);
	}

}