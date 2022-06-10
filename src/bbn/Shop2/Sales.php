<?php

namespace bbn\Shop;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\Dbconfig;
use bbn\Db;


class Sales extends DbCls
{
  use Dbconfig;

  /**
   * @var Cart
   */
  protected $cart;

  protected static $default_class_cfg = [
    'errors' => [
    ],
    'table' => 'bbn_shop_transactions',
    'tables' => [
      'transactions' => 'bbn_shop_transactions'
    ],
    'arch' => [
      'transactions' => [
        'id' => 'id',
        'id_cart' => 'id_cart',
        'id_client' => 'id_client',
        'id_main' => 'id_main',
        'total' => 'total',
        'moment' => 'moment',
        'id_address' => 'id_address',
        'payment_type' => 'payment_type',
        'status' => 'status'
      ]
    ],
  ];

  public function __construct(Db $db, array $cfg = null)
  {
    // The database connection
    $this->db = $db;
    $this->cart = new Cart($this->db);
    // Setting up the class configuration
    $this->_init_class_cfg($cfg);
  }


  public function getByProduct(string $id_product, string $period = null, string $value = null): ?array
  {
    $cfg = $this->getClassCfg();
    $cartCfg = $this->cart->getClassCfg();
    $req = [
      'tables' => [$cfg['tables']['transactions']],
      'fields' => [
        'total' => 'IFNULL(SUM(' . $this->db->cfn($cartCfg['arch']['cart_products']['amount'], $cartCfg['tables']['cart_products'], true) . '), 0)',
        'num'   => 'IFNULL(SUM(' . $this->db->cfn($cartCfg['arch']['cart_products']['quantity'], $cartCfg['tables']['cart_products'], true) . '), 0)'
      ],
      'join'  => [
        [
          'table' => $cartCfg['tables']['cart'],
          'on' => [
            [
              'field' => $this->db->cfn($cartCfg['arch']['cart']['id'], $cartCfg['tables']['cart']),
              'exp' => $this->db->cfn($cfg['arch']['transactions']['id_cart'], $cfg['tables']['transactions'], true)
            ]
          ]
        ], [
          'table' => $cartCfg['tables']['cart_products'],
          'on' => [
            [
              'field' => $this->db->cfn($cartCfg['arch']['cart_products']['id_cart'], $cartCfg['tables']['cart_products']),
              'exp' => $this->db->cfn($cartCfg['arch']['cart']['id'], $cartCfg['tables']['cart'], true)
            ]
          ]
        ]
      ],
      'where' => [
        $this->db->cfn($cfg['arch']['transactions']['status'], $cfg['tables']['transactions']) => 1,
        $this->db->cfn($cartCfg['arch']['cart_products']['id_product'], $cartCfg['tables']['cart_products']) => $id_product
      ]
    ];

    if ($period) {
      $hasValue = Str::isDateSql($value);
      if ($hasValue) {
        $start = substr($value, 0, 10) . ' 00:00:00';
      }
      else {
        $end = date('Y-m-d H:i:s', time() + 1);
      }

      switch ($period) {
        case 'd':
          if (!isset($start)) {
            $start = date('Y-m-d H:i:s', mktime(0, 0, 0, date('n', strtotime($end)), date('j', strtotime($end)), date('Y', strtotime($end))));
          }
          else {
            $end = date('Y-m-d H:i:s', mktime(0, 0, 0, date('n', strtotime($start)), date('j', strtotime($start)) + 1, date('Y', strtotime($start))));
          }
          break;
        case 'w':
          if (!isset($start)) {
            $monday = strtotime('Monday this week');
            $start = date('Y-m-d H:i:s', mktime(0, 0, 0, date('n', $monday), date('j', $monday), date('Y', strtotime($end))));
          }
          else {
            $end = date('Y-m-d H:i:s', mktime(0, 0, 0, date('n', strtotime($start)), date('j', strtotime($start)) + 7, date('Y', strtotime($start))));
          }
          break;
        case 'm':
          if (!isset($start)) {
            $start = date('Y-m-d H:i:s', mktime(0, 0, 0, date('n', strtotime($end)), 1, date('Y', strtotime($end))));
          }
          else {
            $end = date('Y-m-d H:i:s', mktime(0, 0, 0, date('n', strtotime($start)) + 1, 1, date('Y', strtotime($start))));
          }
          break;
        case 'y':
          if (!isset($start)) {
            $start = date('Y-m-d H:i:s', mktime(0, 0, 0, 1, 1, date('Y', strtotime($end))));
          }
          else {
            $end = date('Y-m-d H:i:s', mktime(0, 0, 0, 1, 1, date('Y', strtotime($start)) + 1));
          }
          break;
      }
      $req['where'][] = [
        $this->db->cfn($cfg['arch']['transactions']['moment'], $cfg['tables']['transactions']),
        '>=',
        $start
      ];
      $req['where'][] = [
        $this->db->cfn($cfg['arch']['transactions']['moment'], $cfg['tables']['transactions']),
        '<',
        $end
      ];

    }

    return $this->db->rselect($req);
  }

}