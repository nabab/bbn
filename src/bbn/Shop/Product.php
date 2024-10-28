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
use bbn\Db;
use bbn\Shop\Sales;
use bbn\Shop\Cart;


class Product extends DbCls
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
    $this->initClassCfg($cfg);
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
      && ($id = $this->db->selectOne($cfg['table'], $cfg['arch']['products']['id'], [
        $cfg['arch']['products']['id_note'] => $id_note,
        ]))
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
    if ($this->dbTraitExists($id)) {
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
        $data['id_media'] ?: null,
        $data['id_option']
      );
      //media upload end
      $res2 = $this->db->update('bbn_shop_products', [
        
        $a['front_img']      => $data['id_media'] ?: null,
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


  /**
   * Gets the price of the given product
   * @param string $id The product ID
   * @return float|null
   */
  public function getPrice(string $id): ?float
  {
    return $this->db->selectOne($this->class_table, $this->fields['price'], [$this->fields['id'] => $id]);
  }


  /**
   * Gets the provider of the given product
   * @param string $id The product ID
   * @return string|null
   */
  public function getProvider(string $id): ?string
  {
    return $this->db->selectOne($this->class_table, $this->fields['id_provider'], [$this->fields['id'] => $id]);
  }


  /**
   * Gets the weight of the given product
   * @param string $id The product ID
   * @return int|null
   */
  public function getWeight(string $id): ?int
  {
    return $this->db->selectOne($this->class_table, $this->fields['weight'], [$this->fields['id'] => $id]);
  }


  /**
   * Checks if the given product is active
   * @param string $id The product ID
   * @return bool
   */
  public function isActive(string $id): bool
  {
    return (bool)$this->db->selectOne($this->class_table, $this->fields['active'], [$this->fields['id'] => $id]);
  }


  public function getVariants(string $id): array
  {
    $cfg  = $this->getClassCfg();
    $a = $cfg['arch']['products'];
    return $this->db->getColumnValues($cfg['table'], $a['id'], [
      $a['id_main'] => $id,
      $a['active'] => 1
    ]);
  }


  /**
   * Gets the stock quantity of the given product
   * @param string $id
   * @return int
   */
  public function getStock(string $id): int
  {
    return $this->dbTraitSelectOne($this->fields['stock'], $id);
  }


  /**
   * Sets the stock quantity of the given product
   * @param string $id
   * @param int $quantity
   * @return bool
   */
  public function setStock(string $id, int $quantity): bool
  {
    return (bool)$this->dbTraitUpdate($id, [$this->fields['stock'] => $quantity]);
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
      $cols['id_main'] => $product['id_main'] ?: $product['id'],
      $cols['active'] => 1

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
    $toDelete = true;
    if ($total['num']) {
      throw new Exception(_("Impossible to delete a product which has already been sold"));
      $toDelete = false;
    }

    if ($id_note = $this->db->selectOne($this->class_table, $this->fields['id_note'], [$this->fields['id'] => $id])) {
      foreach ($this->getVariants($id) as $v) {;
        $this->remove($v);
      }

      $cart = new Cart($this->db);
      $cartCfg = $cart->getClassCfg();
      $carts = $this->db->rselectAll([
        'table' => $cartCfg['tables']['cart_products'],
        'fields' => [$cartCfg['arch']['cart_products']['id']],
        'join' => [[
          'table' => 'bbn_history_uids',
          'on' => [[
            'field' => 'id',
            'exp' => $cartCfg['arch']['cart_products']['id']
          ]]
        ]],
        'where' => [
          $cartCfg['arch']['cart_products']['id_product'] => $id
        ],
        'group_by' => $cartCfg['arch']['cart_products']['id']
      ]) ?: [];
      foreach ($carts as $c) {
        if ($toDelete) {
          $this->db->delete('bbn_history_uids', ['bbn_uid' => $c[$cartCfg['arch']['cart_products']['id']]]);
        }
        else {
          $this->db->delete(
            $cartCfg['tables']['cart_products'],
            [
              $cartCfg['arch']['cart_products']['id'] => $c[$cartCfg['arch']['cart_products']['id']]
            ]
          );
        }
      }

      return ($toDelete ? $this->db->delete($this->class_table, [$this->fields['id'] => $id]) : $this->deactivate($id))
        && $this->cms->delete($id_note);
    }

    return 0;
  }

}
