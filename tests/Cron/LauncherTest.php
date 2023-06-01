<?php

namespace bbn\Cron;

use bbn\Cron;
use bbn\Cron\Launcher;
use PHPUnit\Framework\TestCase;
use bbn\tests\Reflectable;

class LauncherTest extends TestCase
{
  use Reflectable;

  protected Launcher $launcher;

  protected $cron_mock;

  protected function setUp(): void
  {
    $this->cron_mock = \Mockery::mock(Cron::class);
  }

  protected function tearDown(): void
  {
    \Mockery::close();
  }


  protected function init()
  {
    $this->cron_mock->shouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturnTrue();

    $this->cron_mock->shouldReceive('getExePath')
      ->once()
      ->withNoArgs()
      ->andReturn('path\to\exe');

    $this->launcher = new Launcher($this->cron_mock);
  }

  public function getInstance()
  {
    return $this->launcher;
  }

  /** @test */
  public function constructor_test()
  {
    $this->init();

    $this->assertSame(
      $this->cron_mock,
      $this->getNonPublicProperty('cron')
    );

    $this->assertSame(
      'path\to\exe',
      $this->getNonPublicProperty('exe_path')
    );
  }

  /** @test */
  public function constructor_test_when_check_method_returns_false()
  {
    $this->cron_mock->shouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturnFalse();

    $this->launcher = new Launcher($this->cron_mock);

    $this->assertNull(
      $this->getNonPublicProperty('cron')
    );

    $this->assertNull(
      $this->getNonPublicProperty('exe_path')
    );
  }

  /** @test */
  public function launch_method_launches_a_parallel_process()
  {
    $this->init();

    $this->cron_mock->shouldReceive('getLogPath')
      ->once()
      ->with([
        'type' => 'cron',
        'exe_path' => $this->getNonPublicProperty('exe_path')
      ])
      ->andReturn($log_path = 'path/to/log/');

    $this->assertStringContainsString(
      $log_path,
      $this->launcher->launch(['type' => 'cron'])
    );
  }

  /** @test */
  public function launch_method_returns_null_when_exe_path_is_not_set()
  {
    $this->init();

    $this->setNonPublicPropertyValue('exe_path', null);

    $this->assertNull(
      $this->launcher->launch(['type' => 'cron'])
    );
  }

  /** @test */
  public function launchPoll_method_launches_a_poll_type_process()
  {
    $this->launcher = \Mockery::mock(Launcher::class)->makePartial();

    $this->launcher->shouldReceive('isPollActive')
      ->once()
      ->andReturnTrue();

    $this->launcher->shouldReceive('launch')
      ->once()
      ->with(['type' => 'poll'])
      ->andReturn($log = 'path/to/log');

    $this->assertSame($log, $this->launcher->launchPoll());
  }

  /** @test */
  public function launchPoll_method_returns_null_if_the_poll_file_does_not_exist()
  {
    $this->launcher = \Mockery::mock(Launcher::class)->makePartial();

    $this->launcher->shouldReceive('isPollActive')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->launcher->launchPoll()
    );
  }

  /** @test */
  public function launchTaskSystem_method_launches_a_cron_type_process()
  {
    $this->launcher = \Mockery::mock(Launcher::class)->makePartial();

    $this->launcher->shouldReceive('isCronActive')
      ->once()
      ->andReturnTrue();

    $this->launcher->shouldReceive('launch')
      ->once()
      ->with(['type' => 'cron'])
      ->andReturn($log = 'path/to/log');

    $this->assertSame($log, $this->launcher->launchTaskSystem());
  }

  /** @test */
  public function launchTaskSystem_method_returns_null_when_cron_file_does_not_exist()
  {
    $this->launcher = \Mockery::mock(Launcher::class)->makePartial();

    $this->assertNull(
      $this->launcher->launchTaskSystem()
    );
  }
}