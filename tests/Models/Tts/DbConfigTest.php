<?php

declare(strict_types=1);

namespace Tests\Models\Tts;

use bbn\Models\Tts\DbConfig;
use PHPUnit\Framework\TestCase;

if (!trait_exists('bbn\Models\Tts\Event')) {
  eval('
    namespace bbn\Models\Tts;
    trait Event {}
  ');
}

if (!class_exists('bbn\X')) {
  eval('
    namespace bbn;
    class X {
      public static function test(...$args): string {
        return vsprintf(array_shift($args), $args);
      }
      public static function filter(array $arr, callable $fn): array {
        return array_values(array_filter($arr, $fn));
      }
    }
  ');
}

if (!class_exists('bbn\Str')) {
  eval('
    namespace bbn;
    class Str {
      public static function startsWith(string $str, string $start): bool {
        return strncmp($str, $start, strlen($start)) === 0;
      }
      public static function encodeFilename(string $str): string {
        return $str;
      }
    }
  ');
}

class DbConfigTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    TestDbConfigChild::resetDbConfigState();
  }

  public function testInitClassCfgBuildsConfiguration(): void
  {
    $obj = new TestDbConfigChild();
    $obj->bootClassCfg();

    $cfg = $obj->getClassCfg();

    $this->assertSame('bbn_test_children', $obj->getClassTable());
    $this->assertSame('child', $obj->getClassTableIndex());

    $this->assertSame(
      [
        'id' => 'id',
        'id_parent' => 'id_parent',
        'label' => 'db_label'
      ],
      $obj->getFields()
    );

    $this->assertArrayHasKey('props', $cfg);
    $this->assertSame(
      ['name' => 'db_label', 'type' => 'varchar'],
      $cfg['props']['child']['label']
    );
  }

  public function testDbConfigCheckThrowsWhenNotInitialized(): void
  {
    $this->expectException(\Exception::class);

    $obj = new TestDbConfigChild();
    $obj->dbConfigCheck();
  }

  public function testDbConfigCheckPassesAfterInitialization(): void
  {
    $obj = new TestDbConfigChild();
    $obj->bootClassCfg();

    $obj->dbConfigCheck();

    $this->assertTrue($obj->isInitClassCfg());
  }
}

class TestDbConfigParent
{
  use DbConfig;

  protected static $default_class_cfg = [
    'table' => 'bbn_test_children',
    'tables' => [
      'child' => 'bbn_test_children'
    ],
    'arch' => [
      'child' => [
        'id' => 'id',
        'id_parent' => 'id_parent'
      ]
    ]
  ];

  public function bootClassCfg(): void
  {
    $this->initClassCfg();
  }

  public static function resetDbConfigState(): void
  {
    self::$_isInitClassCfg = [];
    self::$dbConfigCfg = [];
  }
}

class TestDbConfigChild extends TestDbConfigParent
{
  protected static $default_class_cfg = [
    'arch' => [
      'child' => [
        'id' => 'id',
        'id_parent' => 'id_parent',
        'label' => [
          'name' => 'db_label',
          'type' => 'varchar'
        ]
      ]
    ]
  ];

  public static function resetDbConfigState(): void
  {
    self::$_isInitClassCfg = [];
    self::$dbConfigCfg = [];
  }
}
