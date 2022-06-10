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
    'table' => 'bbn_shop_providers',
    'tables' => [
      'products' => 'bbn_shop_providers'
    ],
    'arch' => [
      'products' => [
        'id' => 'id',
        'name' => 'name',
        'cfg' => 'cfg'
      ],
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
	
	public function add(string $email, bool $newsletter){
		$id_client = false;
		if(!$this->db->selectOne('bbn_shop_clients','id', ['email' => $email])){
			$this->db->insert('bbn_shop_clients', [
			'email' => $email,
			'newsletter' => $newsletter
			]);
		}
		return $this->db->selectOne('bbn_shop_clients','id', ['email' => $email]);
	}

	public function addAddress($id_client, $address): ?array{
		if(!empty($id_client)){
			$opt = Option::getInstance();
		  $entity = new \bbn\Entities\Address($this->db);
			
			$address['email'] = $this->getEmail($id_client);
			$address['address'] = $address['address1'].' '.$address['address2'];
			$country = $opt->option($address['country']);
			
			if($id_address = $entity->insert($address)){
				if($this->db->insert('bbn_shop_clients_addresses', [
					'id_client' => $id_client,
					'id_address' => $id_address,
					'def' => 1,
					'last' =>  date('Y-m-d H:i:s')
				])){
					$newAddress = $this->getAddress($id_address);
					$newAddress['continent'] = $country['continent'];
					return $newAddress;
				}
			}
		}
	}
	
	protected function getEmail($id_client): ?string
	{
		if($email = $this->db->selectOne('bbn_shop_clients','email', ['id' => $id_client])){
			return $email;
		}
		return null;
	}
	
	protected function getAddress($id_address)
	{
		
		return $this->db->rselect('bbn_addresses',[], [
			'id' => $id_address
		]);
	}
	
}