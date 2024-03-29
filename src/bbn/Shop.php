<?php

/**
 * PHP version 8
 *
 * @category Shop
 * @package  BBN
 * @author   Theomas Nabet <thomas.nabet@gmail.com>
 * @license  https://opensource.org/licenses/mit-license.php MIT
 * @version  "GIT: <git_id>"
 * @link     https://www.bbn.io/bbn-php
 */

namespace bbn;

use Exception;
use bbn\Appui\Grid;
use bbn\Appui\Medias;
use bbn\Appui\Note;
use bbn\Appui\Cms;
use bbn\Appui\Option;
use bbn\Shop\Product;
use bbn\Shop\Provider;
use bbn\Shop\Sales;
use bbn\Shop\Cart;
use bbn\Shop\Client;
use bbn\Models\Tts\DbConfig;
use bbn\Models\Cls\Db as DbCls;


/**
 * Shopping system main class.
 *
 * ### Generates in a cache directory a javascript or CSS file based on the request received.
 *
 * The cdn class will be using all the classes in bbn\Cdn in order to
 * treat a request URL, and return the appropriate content.
 *
 * - First it will parse the URL and make a first configuration array out of it,
 * from which a hash will be calculated
 * * Then it will serve a cache file if it exists and create one otherwise by:
 * * Making a full configuration array using libraries database with all the needed file(s)
 * * Then it will compile these files into a single file that will be put in cache
 * * This file should be of type js or css
 * * If files are both types the content returned will be JS which will call the css files
 *
 *
 *
 *
 * ### Request can have the following forms:
 * * https://mycdn.net/lib=bbn-vue,jquery
 * * https://mycdn.net/lib=bbnjs|1.0.1|dark,bbn-vue|2.0.2
 * * https://mycdn.net/lib/my_library/?dir=true
 * * https://mycdn.net/lib/my_library/?f=file1.js,file2.js,file3.css
 *
 * ```php
 * $cdn = new \bbn\Cdn($_SERVER['REQUEST_URI']);
 * $cdn->process();
 * if ( $cdn->check() ){
 *   $cdn->output();
 * }
 * ```
 *
 * @category CDN
 * @package  BBN
 * @author   Theomas Nabet <thomas.nabet@gmail.com>
 * @license  https://opensource.org/licenses/mit-license.php MIT
 * @link     https://bbnio2.thomas.lan/bbn-php/doc/class/cdn
 */
class Shop extends DbCls
{
  use DbConfig;

  /**
   * @var Medias
   */
  private $medias;

  /**
   * @var Cms
   */
  private $cms;

  /**
   * @var Product
   */
  private $product;

  /**
   * @var Sales
   */
  private $sales;

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

  /**
   * @var Cart
   */
  protected $cart;

  /**
   * @var Client
   */
  protected $client;

  /**
   * @var Provider
   */
  protected $providers;

  /** 
   * @var array Database structure
   */
  protected static $default_class_cfg = [];
  /**
   * Constructor.
   *
   * Generates a configuration based on the given request and instantiate
   * a compiler for the response.
   * If *$db* is not not given the current instance if any will be used.
   *
   * @param string  $request The original request sent to the server
   * @param db|null $db      The DB connection with the libraries tables
   */
  public function __construct(Db $db, array $cfg = [])
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->opt       = Option::getInstance();
    $this->note      = new Note($this->db);
    $this->cms       = new Cms($this->db, $this->note);
    $this->medias    = new Medias($this->db);
    $this->product   = new Product($this->db, $cfg);
    $this->sales     = new Sales($this->db);
    $this->cart     = new Cart($this->db);
    $this->client     = new Client($this->db);

    $this->providers = new Provider($this->db, $cfg['providers'] ?? []);
    //$this->medias->setImageRoot('/image/');
  }


  /**
   * Returns the product type used in the notes.
   *
   * @return string
   */
  public function getProductTypeNote(): string
  {
    return $this->product->getTypeNote();
  }


  /**
   * Returns the list of the providers in an array
   *
   * @param array $params
   * @return array
   */
  public function getProvidersList(array $params = []): array
  {
    $cfg  = $this->providers->getClassCfg();
    $fields = array_merge($cfg['arch']['providers'], ['emails' => "GROUP_CONCAT(`email`)"]);
    $grid = new \bbn\Appui\Grid($this->db, $params, [
      'tables' => $cfg['table'],
      'fields' => $fields,
      'join' => [[
        'table' => 'bbn_shop_providers_emails',
        'type' => 'left',
        'on' => [
          'conditions' => [[
            'field' => $cfg['arch']['emails']['id_provider'],
            'exp' => $cfg['arch']['providers']['id']
          ]]
        ]
      ]],
      'group_by' =>  $cfg['arch']['providers']['id'],
      'limit' => 100
    ]);
    if ($grid->check()) {
      $res = $grid->getDatatable();
      foreach ($res['data'] as &$d) {
        $d['cfg'] = $d['cfg'] ? json_decode($d['cfg'], true) : [];
      }

      unset($d);
      return $res;
    }
  }
  /**
   * Returns the list of the providers in an array
   *
   * @param array $params
   * @return array
   */
  public function getTransactionsList(array $params = []): array
  {
    if (!empty($params['excel'])) {
      unset($params['fields']);
      if (
        !empty($params['filters'])
        && !empty($params['filters']['logic'])
        && empty($params['filters']['conditions'])
      ) {
        unset($params['filters']);
      }
    }
    $cfg  = $this->sales->getClassCfg();
    $transFields = $cfg['arch']['transactions'];
    $grid = new \bbn\Appui\Grid($this->db, $params, [
      'tables' => $cfg['table'],
      'fields' => \array_values($transFields)
    ]);
    if ($grid->check()) {
      $res = $grid->getDatatable();
      foreach ($res['data'] as &$d) {
        $d['shipping_address'] = $this->sales->getShippingAddress($d[$transFields['id']]);
        $d['billing_address'] = $d['shipping_address'];
        if ($d[$transFields['id_billing_address']] !==  $d[$transFields['id_shipping_address']]) {
          $d['billing_address'] = $this->sales->getBillingAddress($d[$transFields['id']]);
        }
        if (
          empty($params['excel'])
          && ($d['products'] = $this->cart->getProducts($d[$transFields['id_cart']]))
        ) {
          foreach ($d['products'] as $i => $p) {
            $prod = $this->product->get($p['id_product']);
            $d['products'][$i]['product'] = $prod;
            $d['products'][$i]['product']['provider'] = '';
            if (!empty($prod)) {
              $provider = $this->providers->get($prod['id_provider']);
              $d['products'][$i]['product']['provider'] = !empty($provider) ? $provider['name'] : '';
            }
          }
        }
        $d['client'] = $this->client->get($d[$transFields['id_client']]);
        if (
          !empty($params['excel'])
          && !empty($params['excel']['fields'])
        ) {
          $tmp = [];
          foreach ($params['excel']['fields'] as $f) {
            if (empty($f['hidden'])) {
              if ($f['field'] === 'shipping_address') {
                $tmp[$f['field']] = $d['shipping_address']['fulladdress'];
              } elseif ($f['field'] === 'billing_address') {
                $tmp[$f['field']] = $d['billing_address']['fulladdress'];
              } elseif ($f['field'] === 'client.name') {
                $tmp[$f['field']] = $d['client']['first_name'] . ' ' . $d['client']['last_name'];
              } elseif ($f['field'] === 'payment_type') {
                $tmp[$f['field']] = $this->opt->text($d['payment_type']);
              } else {
                $tmp[$f['field']] = $d[$f['field']];
              }
            }
          }
          $d = $tmp;
        }
      }
      unset($d);
      if (!empty($params['excel'])) {
        $filename = BBN_USER_PATH . 'tmp/transactions_' . date('d-m-Y_H-i-s') . '.xlsx';
        if (\bbn\X::toExcel($res['data'], $filename, true, $params['excel'])) {
          return ['file' => $filename];
        }
      }
      return $res;
    }
  }

  /**
   * Returns a list of the products for the shop (public)
   *
   * @param array $params
   * @return array|null
   */
  public function getList(array $params = []): ?array
  {
    if (empty($params['limit'])) {
      $params['limit'] = 10;
    }
    if (empty($params['order'])) {
      $params['order'] = ['start' => 'DESC'];
    }

    $data = null;
    $cfg     = $this->product->getClassCfg();
    $noteCfg = $this->note->getClassCfg();
    $fields  = $cfg['arch']['products'];
    unset($fields['id_note']);

    $dbCfg = $this->cms->getLastVersionCfg();
    $dbCfg['where']['conditions'][] = [
      'field' => $this->db->cfn($cfg['arch']['products']['id_main'], $cfg['table']),
      'operator' => 'isnull'
    ];
    $dbCfg['where']['conditions'][] = [
      'field' => $noteCfg['arch']['notes']['id_type'],
      'value' => $this->product->getTypeNote()
    ];
    $dbCfg['where']['conditions'][] = [
      'field' => 'active',
      'value' => 1
    ];
    $dbCfg['join'][] = [
      'table' => $cfg['table'],
      'on' => [
        [
          'field' => $this->db->cfn($noteCfg['arch']['notes']['id'], $noteCfg['table']),
          'exp' => $this->db->cfn($cfg['arch']['products']['id_note'], $cfg['table'], true)
        ], [
          'field' => $this->db->cfn($cfg['arch']['products']['active'], $cfg['table']),
          'value' => 1
        ]
      ]
    ];
    $dbCfg['fields'] = array_merge($dbCfg['fields'], $fields);
    $grid = new Grid($this->db, $params, $dbCfg);
    $data = $grid->getDatatable();

    if ($data && $data['total']) {
      $editions = $this->product->getEditions();
      $types = $this->product->getTypes();
      foreach ($data['data'] as &$d) {
        $media = $d['front_img'] ? $this->medias->getMedia($d['front_img'], true) : [];
        $d['image'] = $media['path'];
        $d['edition'] = $editions[$d['id_edition']];
        $d['type'] = $types[$d['product_type']];
      }
      unset($d);
    }

    return $data;
  }


  /**
   * Returns a list of the products for management.
   *
   * @param [type] $tableCfg
   * @return array
   */
  public function getAdminList($tableCfg): array
  {
    $cfg      = $this->product->getClassCfg();
    $dbCfg    = $this->cms->getLastVersionCfg(false, false);
    $notesCfg = $this->note->getClassCfg();

    $dbCfg['where']['conditions'][] = [
      'field' => $this->db->cfn($cfg['arch']['products']['id_main'], $cfg['table']),
      'operator' => 'isnull'
    ];

    $dbCfg['tables'] = [$cfg['table']];
    array_unshift(
      $dbCfg['join'],
      [
        'table' => $notesCfg['table'],
        'on' => [
          [
            'field' => $this->db->cfn($notesCfg['arch']['notes']['id'], $notesCfg['table']),
            'exp' => $this->db->cfn($cfg['arch']['products']['id_note'], $cfg['table'], true)
          ]
        ]
      ],
      [
        'type' => 'left',
        'table' => $notesCfg['tables']['notes_tags'],
        'on' => [
          [
            'field' => $this->db->cfn($cfg['arch']['products']['id_note'], $cfg['table']),
            'exp' => $this->db->cfn($notesCfg['arch']['notes_tags']['id_note'], $notesCfg['tables']['notes_tags'], true),
          ]
        ]
      ]
    );

    array_unshift(
      $dbCfg['fields'],
      ...$this->db->getFieldsList($cfg['table'])
    );

    $dbCfg['fields']['num_tags'] = 'COUNT(DISTINCT ' . $this->db->cfn($notesCfg['arch']['notes_tags']['id_tag'], $notesCfg['tables']['notes_tags'], true) . ')';
    $dbCfg['group_by'] = [$this->db->cfn($cfg['arch']['products']['id'], $cfg['table'])];
    $grid = new \bbn\Appui\Grid($this->db, $tableCfg, $dbCfg);
    if ($grid->check()) {
      $tmp_grid = $grid->getDatatable();

      $cms   = &$this->cms;
      $notes = &$this->note;
      $tmp_grid['data'] = array_map(function ($a) use (&$cms, &$notes) {
        $a['medias'] = $notes->getMedias($a['id_note']);
        $a['id_media'] = $cms->getDefaultMedia($a['id_note']);
        $a['num_medias'] = count($a['medias']);
        $a['tags'] = []; //$a['num_tags'] ? $notes->getTags($a['id_note']) : [];
        return $a;
      }, $tmp_grid['data']);

      return $tmp_grid;
    }
  }


  /**
   * Returns all the informations about the given product
   *
   * @param string $id
   * @return array|null
   * 
   */
  public function getFullProduct(string $id): ?array
  {
    $prod = $this->product->get($id);
    if ($prod && $prod['id_note']) {
      $note = $this->cms->get($prod['id_note'], true);
      if (!empty($prod['front_img'])) {
        $prod['front_img'] = $this->medias->getMedia($prod['front_img'], true);
        $prod['source'] = $prod['front_img']['path'];
      }

      if ($note) {
        $cfg      = $this->product->getClassCfg();
        if (empty($prod['id_main'])) {
          $prod['variants'] = [];
          if ($variants = $this->db->getColumnValues($cfg['table'], $cfg['arch']['products']['id'], [
            $cfg['arch']['products']['id_main'] => $id
          ]));
          foreach ($variants as $v) {
            $prod['variants'][] = $this->getFullProduct($v);
          }
        }
        $cartCfg = $this->cart->getClassCfg();
        $prod['cart_number'] = $this->db->count($cartCfg['tables']['cart_products'], [$cartCfg['arch']['cart_products']['id_product'] => $id]);
        $prod['sales'] = [
          'total' => $this->sales->getByProduct($id),
          'y'     => ['total' => 0, 'num' => 0],
          'm'     => ['total' => 0, 'num' => 0],
          'w'     => ['total' => 0, 'num' => 0],
          'd'     => ['total' => 0, 'num' => 0],
        ];

        $keys = array_keys($prod['sales']);
        for ($i = 0; $i < count($keys) - 1; $i++) {
          if ($prod['sales'][$keys[$i]]['total']) {
            $prod['sales'][$keys[$i + 1]] = $this->sales->getByProduct($id, $keys[$i + 1]);
          }
        }

        return X::mergeArrays($note, $prod);
      }
    }

    return null;
  }
  /**
   * 
   */
  public function getAbandonedCarts(int $days): array
  {
    $salesCfg  = $this->sales->getClassCfg();
    $cartCfg  = $this->cart->getClassCfg();
    $today = new \DateTime();
    $my_date = date_sub(($today), date_interval_create_from_date_string(strval($days) . ' days'));
    $date = $my_date->format('Y-m-d');
    unset($salesCfg['arch']['transactions']['id']);
    $salesCfg['arch']['transactions']['id_transaction'] = $salesCfg['table'] . '.' . 'id';


    $grid = new \bbn\Appui\Grid($this->db, ['limit' => 0], [
      'table' => $cartCfg['table'],
      'fields' => [
        "id" => "bbn_shop_cart.id",
        "id_session" => "id_session",
        "id_client" => "id_client",
        "creation" => "creation",
        "id_cart" => "id_cart",
        "id_shipping_address" => "id_shipping_address",
        "id_billing_address" => "id_billing_address",
        "number" => "number",
        "shipping_cost" => "shipping_cost",
        "total" => "total",
        "moment" => "moment",
        "payment_type" => "payment_type",
        "reference" => "reference",
        "url" => "url",
        "error_message" => "error_message",
        "error_code" => "error_code",
        "status" => "status",
        "test" => "test",
        "id_transaction" => "bbn_shop_transactions.id"
      ],
      'join' => [
        [
          'table' => $salesCfg['table'],
          'type' => 'left',
          'on' => [
            'conditions' => [
              [
                'field' => $salesCfg['arch']['transactions']['id_cart'],
                'operator' => 'eq',
                'exp' => $cartCfg['table'] . '.' . $cartCfg['arch']['cart']['id']
              ]
            ]
          ]
        ], [
          'table' => $cartCfg['table'],
          'alias' => 'cart2',
          'type' => 'left',
          'on' => [
            'conditions' => [
              [
                'field' => 'cart2.id_client',
                'exp' => $cartCfg['table'] . '.id_client',
              ], [
                'field' => 'cart2.creation',
                'operator' => '>',
                'exp' => $cartCfg['table'] . '. creation',
              ]
            ]
          ]
        ]
      ],
      'where' => [
        'logic' => 'AND',
        'conditions' => [
          [
            'field' => $cartCfg['arch']['cart']['id_client'],
            'operator' => 'isnotnull'
          ], [
            'field' => $cartCfg['arch']['cart']['creation'],
            'operator' => '>=',
            'value' => $date
          ], [
            'field' => 'cart2.id',
            'operator' => 'isnull'
          ], [
            'logic' => 'OR',
            'conditions' => [
              [
                'field' => $salesCfg['arch']['transactions']['id_cart'],
                'operator' => 'isnull'
              ], [
                'field' => $salesCfg['arch']['transactions']['status'],
                'operator' => '!=',
                'value' => 'paid'
              ]
            ]
          ]
        ]
      ],
      'group_by' => [
        $cartCfg['table'] . '.' . $cartCfg['arch']['cart']['id']
      ]
    ]);
    if ($data = $grid->getDatatable()['data']) {
      return array_filter($data, function ($a) {
        return !$this->cart->isPaid($a['id']);
      });
    }
    return [];
  }
}
