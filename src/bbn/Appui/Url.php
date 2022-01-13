<?php
namespace bbn\Appui;

use bbn;
use bbn\X;
use bbn\Str;

class Url extends bbn\Models\Cls\Db
{
  use bbn\Models\Tts\Dbconfig;

  /** @var array */
  protected static $default_class_cfg = [
    'table' => 'bbn_url',
    'tables' => [
      'url' => 'bbn_url'
    ],
    'arch' => [
      'url' => [
        'id' => 'id',
        'url' => 'url',
        'num_display' => 'num_display',
        'type_url' => 'type_url',
        'redirect' => 'redirect'
      ]
    ]
  ];


  public function __construct(bbn\Db $db)
  {
    parent::__construct($db);
    $this->_init_class_cfg();
  }


  public static function sanitize(string $url, string $prefix = ''): string
  {
    $url    = trim($url, '/ ');
    $prefix = trim($prefix, '/ ');
    while (strpos($url, '//')) {
      $url = str_replace('//', '/', $url);
    }

    return $url . ($prefix ? '/' . $prefix : '');
  }


  public function add(string $url, string $type_url, string $prefix = ''): ?string
  {
    if ($url = $this->sanitize($url, $prefix)) {
      return $this->insert([
        $this->fields['url'] => $url,
        $this->fields['type_url'] => $type_url
      ]);
    }

    return null;
  }


  public function addRedirect(string $url, string $id_url): ?string
  {
    if ($url = $this->sanitize($url)
        && !$this->select($url)
        && ($cfg = $this->select($id_url))
    ) {
      return $this->insert([
        $this->fields['url'] => $url,
        $this->fields['type_url'] => $cfg['type_url']
      ]);
    }

    return null;
  }


  public function urlExists(string $url): bool
  {
    if ($url = $this->sanitize($url)) {
      return $this->exists([$this->fields['url'] => $url]);
    }

    return false;
  }

}
