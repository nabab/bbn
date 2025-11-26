<?php

namespace bbn\Shop;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbActions;
use bbn\Db;
use bbn\Mail;
use bbn\Appui\Masks;
use bbn\Tpl;
use bbn\Appui\Option;


class Sales extends DbCls
{
  use DbActions;

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
  protected $provider;

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
        'id_shipping_address' => 'id_shipping_address',
        'id_billing_address' => 'id_billing_address',
        'number' => 'number',
        'shipping_cost' => 'shipping_cost',
        'total' => 'total',
        'moment' => 'moment',
        'payment_type' => 'payment_type',
        'reference' => 'reference',
        'url' => 'url',
        'error_message' => 'error_message',
        'error_code' => 'error_code',
        'status' => 'status',
        'test' => 'test'
      ]
    ],
  ];

  public function __construct(Db $db, array|null $cfg = null)
  {
    // The database connection
    $this->db = $db;
    $this->cart = new Cart($this->db);
    $this->client = new Client($this->db);
    $this->provider = new Provider($this->db);
    // Setting up the class configuration
    $this->initClassCfg($cfg);
  }

  public function changeStatus(string $idTransaction, string $status, $errorMessage = null, $errorCode = null): ?bool
  {
    $data = [
      $this->fields['status'] => $status
    ];
    if (!empty($errorMessage)) {
      $data[$this->fields['error_message']] = $errorMessage;
    }
    if (!empty($errorCode)) {
      $data[$this->fields['error_code']] = $errorCode;
    }
    return (bool)$this->dbTraitUpdate($idTransaction, $data);
  }

  public function setStatusPaid(string $idTransaction, $errorMessage = null, $errorCode = null): bool
  {
    return $this->changeStatus($idTransaction, 'paid', $errorMessage,  $errorCode);
  }

  public function setStatusFailed(string $idTransaction, $errorMessage = null, $errorCode = null, bool $fixProductsQuantity = true): bool
  {
    if ($fixProductsQuantity
      && ($idCart = $this->getIdCart($idTransaction))
      && ($products = $this->cart->getProducts($idCart))
    ) {
      $prodCls = new Product($this->db);
      $cartClsCfg = $this->cart->getClassCfg();
      $prodFields = $cartClsCfg['arch']['cart_products'];
      foreach ($products as $product) {
        $currentStock = $prodCls->getStock($product[$prodFields['id_product']]);
        $prodCls->setStock($product[$prodFields['id_product']], $currentStock + $product[$prodFields['quantity']]);
      }
    }
    return $this->changeStatus($idTransaction, 'failed', $errorMessage, $errorCode);
  }

  public function getByProduct(string $id_product, string|null $period = null, string|null $value = null): ?array
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
        $start = Str::sub($value, 0, 10) . ' 00:00:00';
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

  /**
   * Adds a trancation
   * @param array $transacion
   * @return null|string
   */
  public function add(array $transaction): ?string
  {
    $f =& $this->fields;
    if (empty($transaction[$f['id_cart']])) {
      throw new \Exception(X::_('No id_cart found on the given transaction: %s', \json_encode($transaction)));
    }
    if (empty($transaction[$f['id_client']])) {
      throw new \Exception(X::_('No id_client found on the given transaction: %s', \json_encode($transaction)));
    }
    if (empty($transaction[$f['id_shipping_address']])) {
      throw new \Exception(X::_('No id_shipping_address found on the given transaction: %s', \json_encode($transaction)));
    }
    if (empty($transaction[$f['id_billing_address']])) {
      throw new \Exception(X::_('No id_billing_address found on the given transaction: %s', \json_encode($transaction)));
    }
    if (empty($transaction[$f['payment_type']])) {
      throw new \Exception(X::_('No payment_type found on the given transaction: %s', \json_encode($transaction)));
    }
    if (empty($transaction[$f['moment']])) {
      $transaction[$f['moment']] = date('Y-m-d H:i:s');
    }
    if (empty($transaction[$f['total']])) {
      $transaction[$f['total']] = 0;
    }
    if (empty($transaction[$f['shipping_cost']])) {
      $transaction[$f['shipping_cost']] = 0;
    }
    $transaction[$f['number']] = date('Y') . '-' .rand(1000000000, 9999999999);
    while ($this->dbTraitSelect([$f['number'] => $transaction[$f['number']]])) {
      $transaction[$f['number']] = date('Y') . '-' .rand(1000000000, 9999999999);
    }
    $transaction[$f['test']] = !empty($transaction[$f['test']]) ? 1 : 0;
    $this->client->setLastUsedAddress($transaction[$f['id_shipping_address']], $transaction[$f['moment']]);
    $this->client->setLastUsedAddress($transaction[$f['id_billing_address']], $transaction[$f['moment']]);
    return $this->dbTraitInsert($transaction);
  }

  /**
   * Gets a transaction
   * @param string $idTransaction
   * @return null|array
   */
  public function get(string $idTransaction): ?array
  {
    return $this->dbTraitRselect([$this->fields['id'] => $idTransaction]);
  }

  /**
   * Gets the client ID of a transaction
   * @param string $idTransaction
   * @return null|string
   */
  public function getIdClient(string $idTransaction): ?string
  {
    return $this->dbTraitSelectOne($this->fields['id_client'], [$this->fields['id'] => $idTransaction]);
  }

  /**
   * Gets the cart ID of a transaction
   * @param string $idTransaction
   * @return null|string
   */
  public function getIdCart(string $idTransaction): ?string
  {
    return $this->dbTraitSelectOne($this->fields['id_cart'], [$this->fields['id'] => $idTransaction]);
  }

  /**
   * Gets the shipping address ID of a transaction
   * @param string $idTransaction
   * @return null|string
   */
  public function getIdShippingAddress(string $idTransaction): ?string
  {
    return $this->dbTraitSelectOne($this->fields['id_shipping_address'], [$this->fields['id'] => $idTransaction]);
  }

  /**
   * Gets the billing address ID of a transaction
   * @param string $idTransaction
   * @return null|string
   */
  public function getIdBillingAddress(string $idTransaction): ?string
  {
    return $this->dbTraitSelectOne($this->fields['id_billing_address'], [$this->fields['id'] => $idTransaction]);
  }

  /**
   * Gets the shipping address of a transaction
   * @param string $idTransaction
   * @return null|string
   */
  public function getShippingAddress(string $idTransaction): ?array
  {
    if (!($idAddress = $this->getIdShippingAddress($idTransaction))) {
      throw new \Exception(X::_('No shipping address found on transaction %s', $idTransaction));
    }
    return $this->client->getAddress($idAddress);
  }

  /**
   * Gets the billing address of a transaction
   * @param string $idTransaction
   * @return null|string
   */
  public function getBillingAddress(string $idTransaction): ?array
  {
    if (!($idAddress = $this->getIdBillingAddress($idTransaction))) {
      throw new \Exception(X::_('No billing address found on transaction %s', $idTransaction));
    }
    return $this->client->getAddress($idAddress);
  }

  /**
   * Sends the order confirm email to the client
   * @param string $idTransaction
   * @param \bbn\Mail $mailCls
   * @return bool
   */
  public function sendConfirmEmailToClient(string $idTransaction, \bbn\Mail $mailCls = null): bool
  {
    if (($opt = Option::getInstance())
      && ($d = $this->getMailData($idTransaction))
      && ($email = $this->client->getEmail($d[$this->fields['id_client']]))
    ) {
      $masksCls = new Masks($this->db);
      if ($template = $masksCls->getDefault($opt->fromCode('client_order', 'masks', 'appui'))) {
        $title = $masksCls->render($template['title'], $d);
        $content = $masksCls->render($template['content'], $d, 'client_order');
        if(!$mailCls) {
          $mailing = new \bbn\Appui\Mailing($this->db);
          return (bool) $mailing->insertEmail($email, $title, $content);
        }
        return (bool)$mailCls->send([
          'to' => $email,
          'title' => $title,
          'text' => $content
        ]);
      }
    }
    return false;
  }

   /**
   * Sends the order confirm email to the client
   * @param string $idTransaction
   * @param \bbn\Mail $mailCls
   * @return bool
   */
  public function sendConfirmEmailToProviders(string $idTransaction, \bbn\Mail $mailCls = null)
  {
    if ($opt = Option::getInstance())
    {
      $masksCls = new Masks($this->db);
      if ($template = $masksCls->getDefault($opt->fromCode('provider_order_confirm', 'masks', 'appui'))) {
        $productCls = new Product($this->db);
        $providers = [];
        $products = $this->cart->getProducts($this->getIdCart($idTransaction));
        foreach ( $products as $product){ 
          $idProvider = $productCls->getProvider($product['id_product']);
          if (!in_array($idProvider, $providers)) {
            $providers[] = $idProvider;
          }
        }
        if (!empty($providers)) {
          $nr = 0;
          foreach ($providers as $providerId) {
            $providersEmails = $this->provider->getEmails($providerId);
            if (!empty($providersEmails)) {
              foreach ($providersEmails as $email){
                $d = $this->getMailDataProvider($idTransaction, $providerId);
                $title = $masksCls->render($template['title'], $d);
                $content = $masksCls->render($template['content'], $d, 'provider_order_confirm');
                if(!$mailCls) {
                  $mailing = new \bbn\Appui\Mailing($this->db);
                  $mailing->insertEmail($email['email'], $title, $content);
                }
                else {
                  $mailCls->send([
                    'to' => $email['email'],
                    'title' => $title,
                    'text' => $content
                  ]);
                }
              }
            }
          }
        }
      }
    }//aggiungere return
  }

  /**
   * Sends an email to notify a new order
   * @param string $idTransaction The transaction ID
   * @param string $email The email address to send to
   * @param \bbn\Mail $mailCls
   * @return bool
   */
  public function sendNewOrderEmail(string $idTransaction, string $email = '', \bbn\Mail $mailCls = null): bool
  {
    if (($opt = Option::getInstance())
      && ($d = $this->getMailData($idTransaction))
    ) {
      $masksCls = new Masks($this->db);
      if (!Str::isEmail($email)
        && defined('BBN_ADMIN_EMAIL')
      ) {
        $email = BBN_ADMIN_EMAIL;
      }
      if (!empty($email)
        && ($template = $masksCls->getDefault($opt->fromCode('order', 'masks', 'appui')))
      ) {
        $title = $masksCls->render($template['title'], $d);
        $content = $masksCls->render($template['content'], $d, 'order');
        if(!$mailCls) {
          $mailing = new \bbn\Appui\Mailing($this->db);
          return (bool) $mailing->insertEmail($email, $title, $content);
        }
        return (bool)$mailCls->send([
          'to' => $email,
          'title' => $title,
          'text' => $content
        ]);
      }
    }
    return false;
  }

  /**
   * Gets the transaction info to use on an email
   * @param string $idTransaction
   * @return null|array
   */
  private function getMailData(string $idTransaction): ?array
  {
    if (($opt = Option::getInstance())
      && ($transaction = $this->get($idTransaction))
    ) {
      $transaction['products'] = $this->cart->getProductsDetail($transaction[$this->fields['id_cart']]);
      foreach ($transaction['products'] as $i => $p) {
        $transaction['products'][$i]['product']['price'] = '€ ' . (string)number_format(round((float)($p['amount'] / $p['quantity']), 2), 2, ',', '');
        $transaction['products'][$i]['amount'] = '€ ' . (string)number_format(round((float)$p['amount'], 2), 2, ',', '');
      }
      $transaction['total'] = '€ ' . (string)number_format(round((float)$transaction[$this->fields['total']], 2), 2, ',', '');
      $transaction['shippingCost'] = '€ ' . (string)number_format(round((float)$transaction[$this->fields['shipping_cost']], 2), 2, ',', '');
      $transaction['shippingAddress'] = $this->getShippingAddress($idTransaction);
      $transaction['billingAddress'] = $this->getBillingAddress($idTransaction);
      $transaction['shippingAddress']['country'] = $opt->text($transaction['shippingAddress']['country']);
      $transaction['billingAddress']['country'] = $opt->text($transaction['billingAddress']['country']);
      $transaction[$this->fields['payment_type']] = $opt->text($transaction[$this->fields['payment_type']]);
      $transaction['formattedMoment'] = date('d/m/Y', strtotime($transaction[$this->fields['moment']]));
      return $transaction;
    }
    return null;
  }

  /**
   * Gets the transaction info for the provider to use on an email 
   * @param string $idTransaction
   * @return null|array
   */
  private function getMailDataProvider(string $idTransaction, string $idProvider): ?array
  {
    if (($opt = Option::getInstance())
      && ($transaction = $this->get($idTransaction))
    ) {
      $products = $this->cart->getProductsDetail($transaction[$this->fields['id_cart']]);
      $transaction['products'] = X::getRows($products, function($a) use ($idProvider){
        return $a['product']['id_provider'] === $idProvider;
      });
      $total = 0;
      foreach ($transaction['products'] as $i => $p) {
        $transaction['products'][$i]['product']['price'] = '€ ' . (string)number_format(round((float)($p['amount'] / $p['quantity']), 2), 2, ',', '');
        $transaction['products'][$i]['amount'] = '€ ' . (string)number_format(round((float)$p['amount'], 2), 2, ',', '');
        $total += $p['amount'];
      }
      $shippingAddress = $this->getShippingAddress($idTransaction);
      $shippingAddress['email'] = $this->client->getEmail($shippingAddress['id_client']);
      $shippingCost = $this->cart->shippingCostPerProvider($idProvider,$shippingAddress['id_address'], $transaction['id_cart']); 
      $transaction['shippingCost'] = '€ ' . (string)number_format(round((float)$shippingCost, 2), 2, ',', '');
      $transaction['total'] = '€ ' . (string)number_format(round((float) $total + $shippingCost, 2), 2, ',', '');

      $transaction['shippingAddress'] = $shippingAddress;
      $transaction['billingAddress'] = $this->getBillingAddress($idTransaction);
      $transaction['shippingAddress']['country'] = $opt->text($transaction['shippingAddress']['country']);
      $transaction['billingAddress']['country'] = $opt->text($transaction['billingAddress']['country']);
      $transaction[$this->fields['payment_type']] = $opt->text($transaction[$this->fields['payment_type']]);
      $transaction['formattedMoment'] = date('d/m/Y', strtotime($transaction[$this->fields['moment']]));
      X::log([
        'order' => (array)$transaction,
      ], 'transaction');
      return $transaction;
    }
    return null;
  }

}