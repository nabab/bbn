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
        'id_main' => 'id_main',
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
      $this->type_note = $o->fromCode('products', 'types', 'appui-note', 'plugins', 'shop', 'appui');
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
        unset($all[$cfg['arch']['products']['price_purchase']]);
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
      $final = array_merge($note, $res);
      $final['variants'] = $this->getVariantsList($final);
      return $final;
    }

    return null;
  }


  public function add($data)
  {
    $cfg  = $this->getClassCfg();
    $a = $cfg['arch']['products'];
    $noteCfg = $this->note->getClassCfg();
    if (!X::hasProps($data, [$a['product_type'], $a['id_provider'], 'url', $noteCfg['arch']['versions']['title']])) {
      throw new \Exception(_("Some mandatory fields are missing"));
    }

    if (!($type = $this->getTypeNote())) {
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

      if ($this->db->insert($cfg['table'], [
        $a['id_provider']    => $data['id_provider'],
        $a['id_note']        => $id_note,
        $a['id_main']        => $data['id_main'] ?? null,
        $a['price_purchase'] => $data['price_purchase'] ?? null,
        $a['price']          => $data['price'] ?? null,
        $a['dimensions']     => $data['dimensions'] ?? null,
        $a['weight']         => $data['weight'] ?? null,
        $a['product_type']   => $data['product_type'],
        $a['id_edition']     => $data['id_edition'],
        $a['stock']          => $data['stock'] ?? null,
        $a['active']         => $data['active'] ?? 0
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
    $cfg  = $this->getClassCfg();
    $a = $cfg['arch']['products'];
    if ($this->db->selectOne($cfg['table'], $a['id_note'], [$a['id'] => $data['id']])) {
      $content = empty($data['items']) ? '[]' : json_encode($data['items']);
      $res = $this->cms->set(
        $data['url'],
        $data['title'],
        $content,
        $data['excerpt'],
        $data['start'] ?? null,
        $data['end'] ?? null,
        $data['tags'],
        $data['id_type'],
        $data['id_media'],
        $data['id_option']
      );

      //media upload end
      $res2 = $this->db->update('bbn_shop_products', [
        $a['id_provider']    => $data['id_provider'],
        $a['price_purchase'] => $data['price_purchase'],
        $a['price']          => $data['price'],
        $a['dimensions']     => $data['dimensions'],
        $a['weight']         => $data['weight'],
        $a['product_type']   => $data['product_type'],
        $a['id_edition']     => $data['id_edition'],
        $a['stock']          => $data['stock'],
        $a['active']         => $data['active']
      ], [
        $a['id']           => $data['id']]);
      return $res || $res2 ? 1 : 0;
    }

    return 0;
  }


  public function getVariants(string $id): array
  {
    $cfg  = $this->getClassCfg();
    $a = $cfg['arch']['products'];
    return $this->db->getColumnValues($cfg['table'], $a['id'], [
      $a['id_main'] => $id
    ]);
  }



  private function getVariantsList(array $product): array
  {
    $cfg  = $this->getClassCfg();
    $cols = $cfg['arch']['products'];
    $res  = [];
    if ($product['id_main']) {
      $id_note = $this->db->selectOne($cfg['table'], $cols['id_note'], [
        $cols['id'] => $product['id_main']
      ]);
      $res[] = [
        'value' => $product['id_main'],
        'text'  => $this->note->getTitle($id_note),
        'url'   => $this->note->getUrl($id_note)
      ];
    }

    $all = $this->db->rselectAll($cfg['table'], [$cols['id'], $cols['id_note']], [
      $cols['id_main'] => $product['id_main'] ?: $product['id']
    ]);
    foreach ($all as $a) {
      if ($a['id'] !== $product['id']) {
        $res[] = [
          'value' => $a['id'],
          'text'  => $this->note->getTitle($a['id_note']),
          'url'   => $this->note->getUrl($a['id_note'])
        ];
      }
    }

    return $res;
  }



  public function remove(string $id): int
  {
    $sales = new Sales($this->db);
    $total = $sales->getByProduct($id);
    if ($total['num']) {
      throw new Exception(_("Impossible to delete a product which has already been sold"));
    }

    $cfg  = $this->getClassCfg();
    $a = $cfg['arch']['products'];
    if ($id_note = $this->db->selectOne($cfg['table'], $a['id_note'], [$a['id'] => $id])) {
      foreach ($this->getVariants($id) as $v) {
        $this->remove($id);
      }

      return $this->db->delete('bbn_shop_products', ['id' => $id]) && $this->cms->delete($id_note);
    }

    return 0;
  }

}