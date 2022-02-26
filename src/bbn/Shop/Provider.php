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


class Product extends DbCls
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


  public function add($name, array $cfg = null): ?string
  {
    $dbcfg = $this->getClassCfg();
    if ($this->insert($cfg['table'], [
      $dbcfg['arch']['providers']['name'] => $name,
      $dbcfg['arch']['providers']['cfg']  => $cfg ? json_encode($cfg) : null
    ])) {
      return $this->db->lastId();
    }

    return null;
  }

}