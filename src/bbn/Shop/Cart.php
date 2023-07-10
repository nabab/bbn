<?php

namespace bbn\Shop;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\Dbconfig;
use bbn\Db;
use bbn\Entities\Address;
use bbn\Appui\Option;

/**
 * Cart class
 * @category Shop
 * @package Shop
 * @author BBN Solutions <info@bbn.solutions>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link https://bbn.io/bbn-php/doc/class/Shop/Cart
 */
class Cart extends DbCls
{
  use Dbconfig;

  /**
   * @var string
   */
  protected $idSession;

  /**
   * @var string
   */
  protected $idUser;

  /**
   * @var \bbn\Shop\Product
   */
  protected $productCls;

  /**
   * @var array
   */
  protected $productClsCfg;

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

  /**
   * Constructor
   * @param \bbn\Db $db
   * @param array $cfg
   */
  public function __construct(Db $db, array $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->_init_class_cfg($cfg);
    if ($user = \bbn\User::getInstance()) {
      $this->idSession = $user->getOsession('id_session');
      $this->idUser = $user->getId();
    }
    $this->productCls = new Product($this->db);
    $this->productClsCfg = $this->productCls->getClassCfg();
  }


  /**
   * Gets the ID of the current cart
   * @return string|null
   */
  public function getCurrentCartID(): ?string
  {
    if (empty($this->idSession)) {
      throw new \Exception(_("No user's session found"));
    }
    $sales = new Sales($this->db);
    $salesCfg = $sales->getClassCfg();
    $salesFields = $salesCfg['arch']['transactions'];
    $where = [
      'logic' => 'OR',
      'conditions' => [[
        'field' => $this->fields['id_session'],
        'value' => $this->idSession
      ]]
    ];
    if (!empty($this->idUser)) {
      $clientCls = new Client($this->db);
      if ($idClient = $clientCls->getIdByUser($this->idUser)) {
        \array_unshift($where['conditions'], [
          'field' => $this->fields['id_client'],
          'value' => $idClient
        ]);
      }
    }
    if ($idCart = $this->selectOne($this->fields['id'], $where, [$this->fields['creation'] => 'DESC'])) {
      if ($this->db->selectOne([
        'table' => $salesCfg['table'],
        'fields' => $salesFields['id'],
        'where' => [[
          'field' => $salesFields['id_cart'],
          'value' => $idCart
        ], [
          'field' => $salesFields['status'],
          'operator' => '!=',
          'value' => 'failed'
        ], [
          'field' => $salesFields['status'],
          'operator' => '!=',
          'value' => 'unpaid'
        ]]
      ])) {
        return null;
      }
      return $idCart;
    }
    return null;
  }


  /**
   * Adds a product to the current (or given) cart
   * It can also be used to add only quantities of product.
   * @param string $idProduct The product ID
   * @param int $quantity The quantity
   * @param string $idCart The cart ID (optional)
   * @return bool
   */
  public function addProduct(string $idProduct, int $quantity = 1, string $idCart = ''): bool
  {
    if (!$this->productCls->isActive($idProduct)) {
      throw new \Exception(sprintf(_('The product with the id %s is not available'), $idProduct));
    }
    if (empty($idCart)
      && !($idCart = $this->getCurrentCartID())
    ) {
      $idCart = $this->createCart();
    }
    if (!Str::isUid($idCart)) {
      throw new \Exception(_('The cart ID is an invalid UID'));
    }
    $pTable = $this->class_cfg['tables']['cart_products'];
    $pFields = $this->class_cfg['arch']['cart_products'];
    if ($p = $this->productExists($idCart, $idProduct)) {
      return $this->setProductQuantity($idCart, $idProduct, $p[$pFields['quantity']] + $quantity);
    }
    else {
      return (bool)$this->db->insert($pTable, [
        $pFields['id_cart'] => $idCart,
        $pFields['id_product'] => $idProduct,
        $pFields['quantity'] => $quantity,
        $pFields['amount'] => $this->getProductAmount($idProduct, $quantity),
        $pFields['date_added'] => date('Y-m-d H:i:s')
      ]);
    }
  }


  /**
   * Removes a product form the current (or given) cart.
   * It can also be used to remove only quantities of product.
   * @param string $idProduct The product ID
   * @param int $quantity The quantity to remove
   * @param string $idCart The cart ID
   */
  public function removeProduct(string $idProduct, int $quantity = 0, string $idCart = ''): bool
  {
    if (empty($idCart)) {
      $idCart = $this->getCurrentCartID();
    }
    if (!Str::isUid($idCart)) {
      throw new \Exception(_('The cart ID is an invalid UID'));
    }
    $pTable = $this->class_cfg['tables']['cart_products'];
    $pFields = $this->class_cfg['arch']['cart_products'];
    if ($p = $this->productExists($idCart, $idProduct)) {
      if (!$quantity || ($quantity >= $p[$pFields['quantity']])) {
        return (bool)$this->db->delete($pTable, [$pFields['id'] => $p[$pFields['id']]]);
      }
      else {
        return $this->setProductQuantity($idCart, $idProduct, $p[$pFields['quantity']] - $quantity);
      }
    }
    return false;
  }


  /**
   * Sets the quantity (and the relative amount) of a product on the cart
   * @param string $idCart The cart ID
   * @param string $idProduct The product ID
   * @param int $quantity The quantity to set
   * @return bool
   */
  public function setProductQuantity(string $idCart, string $idProduct, int $quantity): bool
  {
    $pTable = $this->class_cfg['tables']['cart_products'];
    $pFields = $this->class_cfg['arch']['cart_products'];
    return (bool)$this->db->update($pTable, [
      $pFields['quantity'] => $quantity,
      $pFields['amount'] => $this->getProductAmount($idProduct, $quantity),
      $pFields['date_added'] => date('Y-m-d H:i:s')
    ], [
      $pFields['id_cart'] => $idCart,
      $pFields['id_product'] => $idProduct
    ]);
  }


  /**
   * Gets the products of the current (or given) cart
   * @param string $idCart The cart ID
   * @return array|null
   */
  public function getProducts(string $idCart = ''): ?array
  {
    if (empty($idCart)) {
      $idCart = $this->getCurrentCartID();
    }
    if (!Str::isUid($idCart)) {
      throw new \Exception(_('The cart ID is an invalid UID'));
    }
    $pTable = $this->class_cfg['tables']['cart_products'];
    $pFields = $this->class_cfg['arch']['cart_products'];
    return $this->db->rselectAll($pTable, [], [$pFields['id_cart'] => $idCart]);
  }


  /**
   * Gets the products of the current (or given) cart with the products details
   * @param string $idCart The cart ID
   * @return array|null
   */
  public function getProductsDetail(string $idCart = ''): ?array
  {
    if ($products = $this->getProducts($idCart)) {
      $prodCls = new Product($this->db);
      foreach ($products as $i => $p) {
        $products[$i]['product'] = $prodCls->get($p[$this->class_cfg['arch']['cart_products']['id_product']]);
      }
    }
    return $products;
  }


  /**
   * Gets the products amount of a cart
   * @param string $idCart
   * @return float
   */
  public function getProductsAmount(string $idCart = ''): float
  {
    if (empty($idCart)) {
      $idCart = $this->getCurrentCartID();
    }
    if (!Str::isUid($idCart)) {
      throw new \Exception(_('The cart ID is an invalid UID'));
    }
    $total = 0;
    if ($products = $this->getProducts($idCart)) {
      $pFields = $this->class_cfg['arch']['cart_products'];
      foreach ($products as $product) {
        $total += $product[$pFields['amount']];
      }
    }
    return \round($total, 2);
  }


  /**
   * Gets the products number of a cart
   * @param string $idCart
   * @return int
   */
  public function getProductsQuantity(string $idCart = ''): int
  {
    if (empty($idCart)) {
      $idCart = $this->getCurrentCartID();
    }
    if (!Str::isUid($idCart)) {
      throw new \Exception(_('The cart ID is an invalid UID'));
    }
    if ($prods = $this->getProducts($idCart)) {
      return \count($prods);
    }
    return 0;
  }


  /**
   * Checks the availability of the products in the cart and return an array with "id => quantity"
   * of the products that are no longer available or with less than the requested quantity available
   * @param string $idCart
   * @return null|array
   */
  public function checkProductsStock(string $idCart = ''): ?array
  {
    if ($products = $this->getProducts($idCart)) {
      $pCfg = $this->class_cfg['arch']['cart_products'];
      $res = [];
      foreach ($products as $product) {
        $stock = $this->productCls->getStock($product[$pCfg['id_product']]);
        if (!$stock || ($stock < $product[$pCfg['quantity']])) {
          $res[$product[$pCfg['id_product']]] = $stock;
        }
      }
      return !empty($res) ? $res : null;
    }
    return null;
  }


  /**
   * Gets the client ID of the given cart
   * @param string $idCart
   * @return strin|null
   */
  public function getClient(string $idCart = ''): ?string
  {
    $idCart = empty($idCart) ? $this->getCurrentCartID() : $idCart;
    if (!empty($idCart)) {
      return $this->selectOne($this->fields['id_client'], $idCart);
    }
    return null;
  }

  /**
   * Sets the client ID to the current cart
   * @param string $idClient The client ID
   * @param string $idCart The cart ID
   * @return bool
   */
  public function setClient(string $idClient, string $idCart = ''): bool
  {
    if (!Str::isUid($idClient)) {
      throw new \Exception(_('The client ID is an invalid UID'));
    }
    $idCart = empty($idCart) ? $this->getCurrentCartID() : $idCart;
    if (!empty($idCart)) {
      return $this->update($idCart, [$this->fields['id_client'] => $idClient]);
    }
    return false;
  }


  /**
   * Gets the total of the current (or given) cart for the given address
   * @param string $idAddress The address ID
   * @param string $idCart The cart ID
   * @return float
   */
  public function total(string $idAddress, string $idCart = ''): float
  {
    if (empty($idCart)) {
      $idCart = $this->getCurrentCartID();
    }
    if (!Str::isUid($idCart)) {
      throw new \Exception(_('The cart ID is an invalid UID'));
    }
    $total = $this->getProductsAmount($idCart) + ($this->shippingCost($idAddress, $idCart) ?: 0);
    return \round($total, 2);
  }


  /**
   * Gets the total of the current (or given) cart in detail for the given address
   * @param string $idAddress The address ID
   * @param string $idCart The cart ID
   * @return array
   */
  public function totalDetail(string $idAddress, string $idCart = ''): array
  {
    if (empty($idCart)) {
      $idCart = $this->getCurrentCartID();
    }
    if (!Str::isUid($idCart)) {
      throw new \Exception(_('The cart ID is an invalid UID'));
    }
    $productsAmount = $this->getProductsAmount($idCart);
    $res = [
      'products' => $productsAmount,
      'shipping' => 0,
      'total' => $productsAmount
    ];
    $res['shipping'] = $this->shippingCost($idAddress, $idCart);
    $res['total'] += $res['shipping'];
    foreach ($res as $i => $v) {
      $res[$i] = \round($v, 2);
    }
    return $res;
  }


  /**
   * Gets the shipping cost for the given address
   * @param string $idAddress The address ID
   * @param string $idCart The cart ID
   * @return float
   */
  public function shippingCost(string $idAddress, string $idCart = ''): ?float
  {
    $detail = $this->shippingCostDetail($idAddress, $idCart);
    return \in_array('disabled', \array_values($detail)) ? null : $detail['total'];
  }


  /**
   * Gets the shipping cost for the given address indexed by providers
   * @param string $idAddress The address ID
   * @param string $idCart The cart ID
   * @return array
   */
  public function shippingCostDetail(string $idAddress, string $idCart = ''): array
  { 
    if (empty($idCart)) {
      $idCart = $this->getCurrentCartID();
    }
    if (!Str::isUid($idCart)) {
      throw new \Exception(_('The cart ID is an invalid UID'));
    }
    $costs = [
      'total' => 0
    ];
    if ($products = $this->getProducts($idCart)) {
      $providersCosts = [];
      $providersWeight = [];
      $providersDefaults = [];
      $pFields = $this->class_cfg['arch']['cart_products'];
      foreach ($products as $product) {
        if (!($provider = $this->productCls->getProvider($product[$pFields['id_product']]))) {
          throw new \Exception(sprintf(_('No provider found for the product %s'), $product[$pFields['id_product']]));
        }
        if (!isset($providersCosts[$provider])) {
          $providersCosts[$provider] = $this->getProviderShippingCosts($provider, $idAddress);
        }
        if (!isset($costs[$provider])) {
          $costs[$provider] = 0;
        }
        if (!empty($providersCosts[$provider]['disabled'])) {
          $costs[$provider] = 'disabled';
          continue;
        }
        if ($weight = $this->productCls->getWeight($product[$pFields['id_product']])) {
          if (!isset($providersWeight[$provider])) {
            $providersWeight[$provider] = $weight * $product[$pFields['quantity']];
          }
          else {
            $providersWeight[$provider] += $weight * $product[$pFields['quantity']];
          }
        }
        else if (!isset($providersDefaults[$provider])
          && !empty($providersCosts[$provider]['default'])
        ) {
          $providersDefaults[$provider] = $providersCosts[$provider]['default'];
          $costs[$provider] = \round($costs[$provider] + $providersCosts[$provider]['default'], 2);
          $costs['total'] = \round($costs['total'] + $providersCosts[$provider]['default'], 2);
        }
      }
      foreach ($providersWeight as $p => $pw) {
        $weights = \array_keys($providersCosts[$p]['prices']);
        $tmpw = $weights[0];
        foreach ($weights as $i => $w) {
          if ($w <= $pw) {
            $tmpw = $w;
          }
          if (isset($weights[$i + 1]) && ($pw > $w)) {
            $tmpw = $weights[$i + 1];
          }
        }
        $costs[$p] = \round($costs[$p] + $providersCosts[$p]['prices'][$tmpw], 2);
        $diff = $pw - $tmpw;
        if (($diff > 0) && count($providersCosts[$p]['surcharge'])) {
          $gr = \array_keys($providersCosts[$p]['surcharge'])[0];
          $m = \array_values($providersCosts[$p]['surcharge'])[0];
          $num = $diff / $gr;
          $num = \round($num, 0) + ($num - round($num, 0) > 0 ? 1 : 0);
          $costs[$p] = \round($costs[$p] + ($m * $num), 2);
        }
        $costs['total'] = \round($costs['total'] + $costs[$p], 2);
      }
    }
    return $costs;
  }

  /**
   * Gets the shipping cost for the given address and the given provider
   * @param string $idAddress The address ID
   * @param string $idCart The cart ID
   * @return float
   */
  public function shippingCostPerProvider(string $idProvider, string $idAddress, string $idCart = ''): ?float
  {
    $detail = $this->shippingCostDetailPerProvider($idProvider, $idAddress, $idCart);
    return \in_array('disabled', \array_values($detail)) ? null : $detail['total'];
  }
  /**
   * Gets the shipping cost for the given address and provider
   * @param string $idAddress The address ID
   * @param string $idCart The cart ID
   * @return array
   */
  public function shippingCostDetailPerProvider(string $idProvider, string $idAddress, string $idCart = ''): array
  {     

    if (empty($idCart)) {
      $idCart = $this->getCurrentCartID();
    }
    if (!Str::isUid($idCart)) {
      throw new \Exception(_('The cart ID is an invalid UID'));
    }
    $costs = [
      'total' => 0
    ];
    if ($products = $this->getProducts($idCart)) {
      $providersCosts = [];
      $providersWeight = [];
      $providersDefaults = [];
      $pFields = $this->class_cfg['arch']['cart_products'];
      foreach ($products as $product) {
        if (!($provider = $this->productCls->getProvider($product[$pFields['id_product']]))) {
          throw new \Exception(sprintf(_('No provider found for the product %s'), $product[$pFields['id_product']]));
        }
        else if ( ($provider === $idProvider)) {
          if (!isset($providersCosts[$provider])) {
            $providersCosts[$provider] = $this->getProviderShippingCosts($provider, $idAddress);
          }
          if (!isset($costs[$provider])) {
            $costs[$provider] = 0;
          }
          if (!empty($providersCosts[$provider]['disabled'])) {
            $costs[$provider] = 'disabled';
            continue;
          }
          if ($weight = $this->productCls->getWeight($product[$pFields['id_product']])) {
            if (!isset($providersWeight[$provider])) {
              $providersWeight[$provider] = $weight * $product[$pFields['quantity']];
            }
            else {
              $providersWeight[$provider] += $weight * $product[$pFields['quantity']];
            }
          }
          else if (!isset($providersDefaults[$provider])
            && !empty($providersCosts[$provider]['default'])
          ) {
            $providersDefaults[$provider] = $providersCosts[$provider]['default'];
            $costs[$provider] = \round($costs[$provider] + $providersCosts[$provider]['default'], 2);
            $costs['total'] = \round($costs['total'] + $providersCosts[$provider]['default'], 2);
          }
          foreach ($providersWeight as $p => $pw) {
            $weights = \array_keys($providersCosts[$p]['prices']);
            $tmpw = $weights[0];
            foreach ($weights as $i => $w) {
              if ($w <= $pw) {
                $tmpw = $w;
              }
              if (isset($weights[$i + 1]) && ($pw > $w)) {
                $tmpw = $weights[$i + 1];
              }
            }
            $costs[$p] = \round($costs[$p] + $providersCosts[$p]['prices'][$tmpw], 2);
            $diff = $pw - $tmpw;
            if (($diff > 0) && count($providersCosts[$p]['surcharge'])) {
              $gr = \array_keys($providersCosts[$p]['surcharge'])[0];
              $m = \array_values($providersCosts[$p]['surcharge'])[0];
              $num = $diff / $gr;
              $num = \round($num, 0) + ($num - round($num, 0) > 0 ? 1 : 0);
              $costs[$p] = \round($costs[$p] + ($m * $num), 2);
            }
            $costs['total'] = \round($costs['total'] + $costs[$p], 2);
          }
        }
      }
    }
    return $costs;
  }


  /**
   * Gets the total weight of the shipping
   * @param string $idCart The cart ID
   * @return int
   */
  public function totalShippingWeight(string $idCart = ''): int
  {
    if (empty($idCart)) {
      $idCart = $this->getCurrentCartID();
    }
    if (!Str::isUid($idCart)) {
      throw new \Exception(_('The cart ID is an invalid UID'));
    }
    $weight = 0;
    $pFields = $this->class_cfg['arch']['cart_products'];
    if ($products = $this->getProducts($idCart)) {
      foreach ($products as $product) {
        $prodWeight = $this->productCls->getWeight($product[$pFields['id_product']]);
        if (\is_int($prodWeight)) {
          $weight += $prodWeight * $product[$pFields['quantity']];
        }
      }
    }
    return $weight;
  }


  /**
   * Creates a new cart and returns its ID
   * @return string|null
   */
  protected function createCart(): ?string
  {
    if (empty($this->idSession)) {
      throw new \Exception(_("No user's session found"));
    }
    return $this->insert([
      $this->fields['id_session'] => $this->idSession,
      $this->fields['id_client'] => null,
      $this->fields['creation'] => date('Y-m-d H:i:s')
    ]);
  }


  /**
   * Checks if a products already exists on the given cart
   * @param string $idCart The cart ID
   * @param string $idProduct The product ID
   * @return array|null
   */
  protected function productExists(string $idCart, string $idProduct): ?array
  {
    $pTable = $this->class_cfg['tables']['cart_products'];
    $pFields = $this->class_cfg['arch']['cart_products'];
    return $this->db->rselect($pTable, [], [
      $pFields['id_cart'] => $idCart,
      $pFields['id_product'] => $idProduct
    ]);
  }


  /**
   * Gets the info of a product
   * @param string $idProduct
   * @return array|null
   */
  protected function getProduct(string $idProduct): ?array
  {
    return $this->productCls->get($idProduct);
  }


  /**
   * Get the amount of a product, relative to the quantity
   * @param string $idProduct The product ID
   * @param int $quantity The quantity
   * @return float
   */
  protected function getProductAmount(string $idProduct, int $quantity): float
  {
    if ($this->productCls->isActive($idProduct)) {
      $price = $this->productCls->getPrice($idProduct);
      if (\is_null($price)) {
        return (float)0;
      }
      return round((float)$price * $quantity, 2);
    }
    throw new \Exception(sprintf(_('No product found with the ID %s'), $idProduct));
  }


  protected function getContinentFromAddress(string $idAddress): string
  {
    $idCountry = $this->getCountryFromAddress($idAddress);
    $opt = Option::getInstance();
    if (!($continentCode = $opt->getProp($idCountry, 'continent'))) {
      throw new \Exception(sprintf(_('Continent code not found for the country %s'), $idCountry));
    }
    if (!($continent = $opt->fromCode($continentCode, 'territories'))) {
      throw new \Exception(sprintf(_('Continent not found with the code %s'), $continentCode));
    }
    return $continent;
  }


  protected function getCountryFromAddress(string $idAddress): string
  {
    $addressCls = new Address($this->db);
    $addressClsCfg = $addressCls->getClassCfg();
    $aFields = $addressClsCfg['arch']['addresses'];
    if (!($idCountry = $addressCls->selectOne($aFields['country'], [$aFields['id'] => $idAddress]))) {
      throw new \Exception(sprintf(_('Contry not found for the address %s'), $idAddress));
    }
    return $idCountry;
  }


  protected function getProviderShippingCosts(string $provider, string $idAddress): array
  {
    $providerCls = new Provider($this->db);
    $res = [
      'prices' => [],
      'surcharge' => [],
      'default' => 0
    ];
    $prices = [];
    $pc = $providerCls->getShippingCosts($provider, $this->getCountryFromAddress($idAddress)) ?: $providerCls->getShippingCosts($provider, $this->getContinentFromAddress($idAddress));
    if (\is_array($pc)) {
      if (!empty($pc['disabled'])) {
        $res['disabled'] = true;
      }
      else {
        foreach ($pc as $i => $v) {
          if (($i !== 'territory')
            && ($i !== 'disabled')
          ) {
            if (\substr($i, 0, 2) === 'gm') {
              $res['surcharge'][(int)\substr($i, 2)] = $v;
            }
            else {
              if ($i === 'g3000') {
                $res['default'] = (float)$v;
              }
              $prices[(int)\substr($i, 1)] = (float)$v;
            }
          }
        }
      }
    }
    ksort($prices, SORT_NUMERIC);
    $res['prices'] = $prices;
    return $res;
  }

}
