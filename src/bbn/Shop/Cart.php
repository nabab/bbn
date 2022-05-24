<?php

namespace bbn\Shop;

use bbn\X;
use bbn\Str;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\Dbconfig;
use bbn\Db;

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
      $this->idSession = $user->getIdSession();
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
    $sales = new \bbn\Shop\Sales($this->db);
    $salesCfg = $sales->getClassCfg();
    $salesFields = $salesCfg['arch']['transactions'];
    if ($idCart = $this->selectOne($this->fields['id'], [
      $this->fields['id_session'] => $this->idSession
    ], [
      $this->fields['creation'] => 'DESC'
    ])) {
      if ($this->db->selectOne($salesCfg['table'], $salesFields['id'], [$salesFields['id_cart'] => $idCart])) {
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
   * Sets the client ID to the current cart
   * @param string $idClient The client ID
   * @return bool
   */
  public function setClient(string $idClient): bool
  {
    if (!Str::isUid($idClient)) {
      throw new \Exception(_('The client ID is an invalid UID'));
    }
    if ($idCart = $this->getCurrentCartID()) {
      return $this->update($idCart, [$this->fields['id_client'] => $idClient]);
    }
    return false;
  }


  public function total()
  {

  }


  public function shippingCost(){

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
    if ($products = $this->getProducts($idCart)) {
      $weightField = $this->productClsCfg['arch']['products']['weight'];
      foreach ($products as $product) {
        $info = $this->getProduct($product[$this->fields['id_product']]);
        if (\is_int($info[$weightField])) {
          $weight += $info[$weightField];
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
    return $this->db->select($pTable, [], [
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
    if ($prod = $this->getProduct($idProduct)) {
      $priceField = $this->productClsCfg['arch']['products']['price'];
      if (\is_null($prod[$priceField])) {
        return (float)0;
      }
      return round((float)$prod[$priceField] * $quantity, 2);
    }
    throw new \Exception(sprintf(_('No product found with the ID %s'), $idProduct));
  }

}