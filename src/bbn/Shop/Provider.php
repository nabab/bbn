<?php

namespace bbn\Shop;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use bbn\Appui\Grid;
use bbn\Appui\Medias;
use bbn\Appui\Note;
use bbn\Appui\Cms;
use bbn\Appui\Option;
use bbn\Appui\Masks;
use bbn\Db;
use bbn\Tpl;



class Provider extends DbCls
{
  use DbActions;

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
      'providers' => 'bbn_shop_providers',
      'emails' => 'bbn_shop_providers_emails'
    ],
    'arch' => [
      'providers' => [
        'id' => 'id',
        'name' => 'name',
        'cfg' => 'cfg'
      ],
      'emails' => [
        'id_provider' => 'id_provider',
        'email' => 'email'
      ]
    ]
  ];

  public function getEmails(string $id_provider) :?array
  { 
    $cfg = $this->getClassCfg();
    $emails = $this->db->rselectAll($cfg['tables']['emails'], 'email', [
      $cfg['arch']['emails']['id_provider'] => $id_provider
    ]);
    return $emails ?: [];
  }

  protected function providerEmailExists(string $id_provider, string $email) :?bool
  { 
    $cfg = $this->getClassCfg();
    if ( $this->db->rselect($cfg['tables']['emails'], 'email', [
      $cfg['arch']['emails']['id_provider'] => $id_provider,
      $cfg['arch']['emails']['email'] => $email
    ])) {
      return true;
    }
    return false;
  }

  public function addProviderEmail(string $id_provider, string $email) 
  { 
    $cfg = $this->getClassCfg();
    if (!static::providerEmailExists($id_provider, $email)) {
      return $this->db->insert($cfg['tables']['emails'], [
        $cfg['arch']['emails']['email'] => $email,
        $cfg['arch']['emails']['id_provider'] => $id_provider
      ]);
    }
  }

  public function deleteProviderEmail(string $id_provider, string $email) 
  { 
    $cfg = $this->getClassCfg();
    if (static::providerEmailExists($id_provider, $email)) {
      
      return $this->db->delete($cfg['tables']['emails'], [
        $cfg['arch']['emails']['email'] => $email,
        $cfg['arch']['emails']['id_provider'] => $id_provider
      ]);
    }
  }
  
  public function sendSoldOutEmail(string $id_provider, array $product)
  {
    $masksCls = new Masks($this->db);
    $mailCls = new \bbn\Appui\Mailing($this->db);
    if( ($emails = $this->getEmails($id_provider)) &&
     ($template = $masksCls->getDefault($this->opt->fromCode('product_sold_out', 'masks', 'appui')))
     ) 
    {
      $title = Tpl::render($template['title'], $product['product']);
      $content = Tpl::render($template['content'], $product['product']);
      foreach($emails as $email) {
        $mailCls->insertEmail($email, $title, $content);

      }
    }
  }

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
    if ($this->dbTraitInsert([
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
      if (X::hasProp($data, 'email', true) && !static::providerEmailExists($id, $data['email'])) {
        $this->addProviderEmail($id, $data['email']);
      }
      return $this->dbTraitUpdate(
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
    if ($res = $this->dbTraitRselect($id)) {
      $dbcfg = $this->getClassCfg();
      $res['emails'] = $this->db->rselectAll($dbcfg['tables']['emails'], 'email', [
        $dbcfg['arch']['emails']['id_provider'] => $id
      ]);
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
    if (($cfg = $this->dbTraitSelectOne($this->fields['cfg'], [$this->fields['id'] => $id]))
      && ($cfg = json_decode($cfg, true))
    ) {
      return X::getRow($cfg, ['territory' => $territory]);
    }
    return null;
  }

}
