<?php

namespace Mvc;

use bbn\Mvc;
use bbn\Mvc\Controller;
use PHPUnit\Framework\TestCase;
use tests\Reflectable;
use tests\ReflectionHelpers;

class ControllerTest extends TestCase
{

  use Reflectable;

  protected Controller $controller;

  protected $mvc;


  public function getInstance()
  {
    return $this->controller;
  }


  protected function setUp(): void
  {
    $this->mvc = \Mockery::mock(Mvc::class);
    $info      = [
      'mode'      => 'js',
      'path'      => 'form',
      'file'      => 'foo/bar/baz/src/components/form/form.j',
      'request'   => 'get',
      'root'      => './src/',
      'plugin'    => 'plugin',
      'args'      => [
        'foo' => 'bar'
      ],
      'checkers'  => ''
    ];

    $this->controller = new Controller($this->mvc, []);
  }


  /** @test */
  public function constructor_test()
  {
    $this->assertInstanceOf(
      Mvc::class,
      $this->getNonPublicProperty('_mvc')
    );
  }


}
