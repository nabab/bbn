<?php

namespace bbn\Shop;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\Dbconfig;
use bbn\Db;


class Cart extends DbCls
{
  use Dbconfig;

  /**
   * @var string
   */
  protected $type_note;

  protected static $default_class_cfg = [
    'errors' => [
    ],
    'table' => 'bbn_shop_cart',
    'tables' => [
      'cart' => 'bbn_shop_cart',
      'cart_products' => 'bbn_shop_cart_products'
    ],
    'arch' => [
      'cart' => [
        'id' => 'id',
        'id_session' => 'id_session',
        'id_client' => 'id_client',
        'creation' => 'creation'
      ],
      'cart_products' => [
        'id' => 'id',
        'id_cart' => 'id_cart',
        'id_product' => 'id_product',
        'quantity' => 'quantity',
        'amount' => 'amount',
        'date_added' => 'date_added'
      ]
    ],
  ];

  public function __construct(Db $db, array $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->_init_class_cfg($cfg);
  }

}