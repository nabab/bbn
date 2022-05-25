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


class Provider extends DbCls
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
      'providers' => 'bbn_shop_providers'
    ],
    'arch' => [
      'providers' => [
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


  public function add($name, array $cfg = null): ?string
  {
    $dbcfg = $this->getClassCfg();
    if ($this->insert([
      $dbcfg['arch']['providers']['name'] => $name,
      $dbcfg['arch']['providers']['cfg']  => $cfg ? json_encode($cfg) : null
    ])) {
      return $this->db->lastId();
    }

    return null;
  }


  public function edit($id, array $data): ?string
  {
    $dbcfg = $this->getClassCfg();
    if (X::hasProp($data, 'name', true)) {
      return $this->db->update(
        $dbcfg['table'],
        [
          $dbcfg['arch']['providers']['name'] => $data['name'],
          $dbcfg['arch']['providers']['cfg']  => $data['cfg'] ? json_encode($data['cfg']) : null
        ],
        [$dbcfg['arch']['providers']['id'] => $id]
      );
    }

    return null;
  }


  public function get(string $id): ?array
  {
    if ($res = $this->rselect($id)) {
      $res['cfg'] = $res['cfg'] ? json_decode($res['cfg'], true) : [];
      return $res;
    }

    return null;
  }


  /**
   * Gets the shipping costs list of the given provider and territory
   * @param string $id The provider ID
   * @param string $territory The territory ID
   * @return array
   */
  public function getShippingCosts(string $id, string $territory): ?array
  {
    if (($cfg = $this->selectOne($this->fields['cfg'], [$this->fields['id'] => $id]))
      && ($cfg = json_decode($cfg, true))
    ) {
      return X::getRow($cfg, ['territory' => $territory]);
    }
    return null;
  }

}