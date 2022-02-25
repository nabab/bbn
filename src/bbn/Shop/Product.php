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
    'table' => 'bbn_shop_products',
    'tables' => [
      'products' => 'bbn_shop_products'
    ],
    'arch' => [
      'products' => [
        'id' => 'id',
        'id_provider' => 'id_provider',
        'id_note' => 'id_note',
        'id_main' => 'id_main',
        'price' => 'price',
        'price_purchase' => 'price_purchase',
        'dimensions' => 'dimensions',
        'weight' => 'weight',
        'product_type' => 'product_type',
        'id_edition' => 'id_edition',
        'stock' => 'stock',
        'front_img' => 'front_img',
        'active' => 'active',
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


  public function getTypeNote()
  {
    if (!$this->type_note) {
      $o = Option::getInstance();
      $this->type_note = $o->fromCode('products', 'types', 'note', 'appui');
    }

    return $this->type_note;
  }


  public function getTypes()
  {
    return $this->opt->options('product_types');
  }


  public function getEditions()
  {
    return $this->opt->options('editions');
  }


  public function exists(string $id)
  {
    $cfg = $this->getClassCfg();
    return (bool)$this->db->count($cfg['table'], [$cfg['arch']['products']['id'] => $id]);
  }


  public function getByUrl(string $url, $public = true)
  {
    $cfg = $this->getClassCfg();
    if (
      ($id_note = $this->note->urlToId($url))
      && ($id = $this->db->selectOne($cfg['table'], $cfg['arch']['products']['id'], [$cfg['arch']['products']['id_note'] => $id_note]))
    ) {
      $all = $this->get($id);
      if ($public) {
        unset($all['price_purchase']);
        $all['stock'] = (bool)$all['stock'];
      }

      return $all;
    }
  }


  public function get(string $id): ?array
  {
    if ($this->exists($id)) {
      $cfg  = $this->getClassCfg();
      $res  = $this->db->rselect($cfg['table'], [], [$cfg['arch']['products']['id'] => $id]);
      $note = $this->cms->get($res['id_note'], true);
      return array_merge($note, $res);
    }

    return null;
  }


  public function add($data)
  {
    if (!X::hasProps($data, ['product_type', 'id_provider', 'url', 'title'])) {
      throw new \Exception(_("Some mandatory fields are missing"));
    }
    if (!($type = $this->opt->fromCode('products', 'types', 'note', 'appui'))) {
      throw new \Exception(_("Impossible to retrieve the product type"));
    }

    if ($id_note = $this->note->insert(
      $data['title'],
      '[]',
      $type,
      false,
      false,
      $data['id_parent'] ?? null,
      $data['id_alias'] ?? null,
      'json/bbn-cms',
      BBN_LANG
    )) {
      $this->cms->setUrl($id_note, $data['url']);

      if ($this->db->insert('bbn_shop_products', [
        'id_provider'    => $data['id_provider'],
        'id_note'        => $id_note,
        'id_main'        => $data['id_main'] ?? null,
        'price_purchase' => $data['price_purchase'] ?? null,
        'price'     => $data['price'] ?? null,
        'dimensions'     => $data['dimensions'] ?? null,
        'weight'         => $data['weight'] ?? null,
        'product_type'   => $data['product_type'],
        'id_edition'     => $data['id_edition'],
        'stock'          => $data['stock'] ?? null,
        'active'         => $data['active'] ?? 0
      ])) {
        if (!empty($data['tags'])) {
          $this->note->setTags($id_note, $data['tags']);
        }

        $id_product = $this->db->lastId();
        $product    = $this->db->rselect('bbn_shop_products', [], ['id' => $id_product]);
        if ($product && $product['id_note']) {
          $note = $this->cms->get($product['id_note'], true);
          if ($note) {
            $product = X::mergeArrays($note, $product);
          }
        }

        return $product;
      }
    }

    return null;
  }


  public function edit(array $data): int
  {
    if ($this->db->selectOne('bbn_shop_products', 'id_note', ['id' => $data['id']])) {
      $content = empty($data['items']) ? '[]' : json_encode($data['items']);
      $res = $this->cms->set($data['url'], $data['title'], $content, $data['excerpt'], $data['start'] ?? null, $data['end'] ?? null, $data['tags']);
      if (!empty($data['front_img'])) {
        $file     = $data['front_img'];
        $id_media = $file['id'];
      }
      else {
        $id_media = null;
      }

      //media upload end
      $res2 = $this->db->update('bbn_shop_products', [
        'id_provider'    => $data['id_provider'],
        'price_purchase' => $data['price_purchase'],
        'price'     => $data['price'],
        'dimensions'     => $data['dimensions'],
        'weight'         => $data['weight'],
        'product_type'        => $data['product_type'],
        'id_edition'     => $data['id_edition'],
        'stock'          => $data['stock'],
        'active'         => $data['active'],
        'front_img'      => $id_media,
      ], ['id'           => $data['id']]);
      return $res || $res2 ? 1 : 0;
    }

    return 0;
  }


  public function remove(string $id): int
  {
    if ($id_note = $this->db->selectOne('bbn_shop_products', 'id_note', ['id' => $id])) {
      $this->note->removeTags($id_note);
      return $this->db->delete('bbn_shop_products', ['id' => $id]) && $this->cms->delete($id_note);
    }

    return 0;
  }

}