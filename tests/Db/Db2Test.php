<?php

namespace Db;

use bbn\Db2;
use PHPUnit\Framework\TestCase;

class Db2Test extends TestCase
{
  protected $db;

  protected Db2\Engines $mysql;

  /** @test */
  public function constructor_test()
  {
    $cfg = [
      'engine' => 'Mysql'
    ];

    $this->db = new Db2($cfg);
  }
}