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
class Shop extends Models\Cls\Db
{
  use Models\Tts\Dbconfig;

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
    $this->providers = new Provider($this->db, $cfg['providers'] ?? []);
    //$this->medias->setImageRoot('/image/');
  }


  public function getProvidersList(array $params = []): array
  {
    $cfg  = $this->providers->getClassCfg();
    $grid = new \bbn\Appui\Grid($this->db, $params, [
      'tables' => $cfg['table'],
      'fields' => $cfg['arch']['providers'],
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


  public function getAdminList($tableCfg)
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
  
    $dbCfg['fields']['num_tags'] = 'COUNT(DISTINCT '.$this->db->cfn($notesCfg['arch']['notes_tags']['id_tag'], $notesCfg['tables']['notes_tags'], true).')';
    $dbCfg['group_by'] = [$this->db->cfn($cfg['arch']['products']['id'], $cfg['table'])];
    $grid = new \bbn\Appui\Grid($this->db, $tableCfg, $dbCfg);
    if ($grid->check()) {
      $tmp_grid = $grid->getDatatable();
  
      $db     =& $this->db;
      $medias =& $this->medias;
      $tmp_grid['data'] = array_map(function($a) use (&$db, &$medias) {
        $a['media'] = [];
        if($product_medias = $db->rselectAll('bbn_notes_medias', [], ['id_note' => $a['id_note']])) {
          foreach($product_medias as $p){
            $a['media'][] = $medias->getMedia($p['id_media'], true);
          }
        }

        $url = Mvc::getPluginUrl('appui-note').'/media/image/';

        if($a['front_img'] ){
          $a['front_img'] = $medias->getMedia($a['front_img'], true);
        }

        $a['media'] = array_map(function($d)use(&$medias, $url) {
          $d['is_image'] = false;
          if ($d['content']) {
            $full_path = $medias->getMediaPath($d['id']);
            $d['full_path'] = $medias->getThumbs($full_path);
            $d['path'] = $url.$d['id'];
            $d['content'] = json_decode($d['content'], true);
            $d['is_image'] = $medias->isImage($full_path);
            if ($d['is_image']) {
              $d['thumbs'] = $medias->getThumbsSizes($d['id']);
            }
          }
          return $d;
        }, $a['media']);
        //die(var_dump($a));
  
        $a['num_media'] = count($a['media']) ? count($a['media']) : 0;
        $a['tags'] = [];//$a['num_tags'] ? $notes->getTags($a['id_note']) : [];
        return $a;
      }, $tmp_grid['data']);

      return $tmp_grid;
    }
  }


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


}