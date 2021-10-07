<?php

namespace Cron;

use bbn\Cron;
use bbn\Db;
use bbn\Mvc;
use bbn\Mvc\Controller;
use bbn\Util\Timer;
use PHPUnit\Framework\TestCase;
use tests\Files;
use tests\Reflectable;

class CronTest extends TestCase
{
  use Reflectable, Files;

  protected Cron $cron;

  protected $db_mock;

  protected $ctrl_mock;

  protected $cfg = [
    'data_path' => BBN_APP_PATH . BBN_DATA_PATH .'path/to/data/',
    'prefix' => 'prefix_',
    'log_file' => 'path/to/log_file/',
  ];

  protected function setUp(): void
  {
    $this->db_mock  = \Mockery::mock(Db::class);
    $this->ctrl_mock = \Mockery::mock(Controller::class);
    $this->cleanTestingDir();
  }

  protected function tearDown(): void
  {
    \Mockery::close();
    $this->cleanTestingDir();
  }


  public function getInstance()
  {
    return $this->cron;
  }

  protected function init(?array $cfg = null)
  {
    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->ctrl_mock->shouldReceive('pluginUrl')
      ->once()
      ->with('appui-cron')
      ->andReturn('path/to/cron');

    $this->cron = new Cron($this->db_mock, $this->ctrl_mock, $cfg ?? $this->cfg);
  }

  /** @test */
  public function constructor_test_with_controller()
  {
    $this->init();

    $this->assertSame(
      $this->cfg['data_path'],
      $this->getNonPublicProperty('path')
    );

    $this->assertInstanceOf(
      Db::class,
      $this->getNonPublicProperty('db')
    );

    $this->assertInstanceOf(
      Timer::class,
      $this->getNonPublicProperty('timer')
    );

    $this->assertSame(
      "{$this->cfg['prefix']}cron",
      $this->getNonPublicProperty('table')
    );

    $this->assertSame(
      'path/to/cron/run',
      $this->getNonPublicProperty('exe_path')
    );

    $this->assertInstanceOf(
      Controller::class,
      $this->getNonPublicProperty('controller')
    );
  }

  /** @test */
  public function constructor_test_without_controller()
  {
    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->cron = new Cron($this->db_mock, null, [
      'exe_path' => 'path/to/cron'
    ]);


    $this->assertSame(
      Mvc::getDataPath('appui-cron'),
      $this->getNonPublicProperty('path')
    );

    $this->assertInstanceOf(
      Db::class,
      $this->getNonPublicProperty('db')
    );

    $this->assertInstanceOf(
      Timer::class,
      $this->getNonPublicProperty('timer')
    );

    $this->assertSame(
      $this->getNonPublicProperty('prefix') . 'cron',
      $this->getNonPublicProperty('table')
    );

    $this->assertSame(
      'path/to/cron',
      $this->getNonPublicProperty('exe_path')
    );

    $this->assertNull(
      $this->getNonPublicProperty('log_file')
    );

    $this->assertNull(
      $this->getNonPublicProperty('ctrl')
    );
  }

  /** @test */
  public function constructor_test_when_db_check_returns_false()
  {
    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->cron = new Cron($this->db_mock, $this->ctrl_mock, $this->cfg);

    $this->assertNull(
      $this->getNonPublicProperty('path')
    );

    $this->assertNull(
      $this->getNonPublicProperty('db')
    );

    $this->assertNull(
      $this->getNonPublicProperty('timer')
    );

    $this->assertNull(
      $this->getNonPublicProperty('table')
    );

    $this->assertNull(
      $this->getNonPublicProperty('exe_path')
    );

    $this->assertNull(
      $this->getNonPublicProperty('log_file')
    );

    $this->assertNull(
      $this->getNonPublicProperty('controller')
    );
  }

  /** @test */
  public function getLauncher_method_creates_and_returns_an_instance_of_launcher_class()
  {
    $this->db_mock->shouldReceive('check')
      ->times(2)
      ->andReturnTrue();

    $this->init();

    $this->assertInstanceOf(
      Cron\Launcher::class,
      $this->cron->getLauncher()
    );
  }

  /** @test */
  public function getLauncher_method_returns_an_existing_launcher_class_when_exists()
  {
    $this->init();

    $this->setNonPublicPropertyValue(
      '_launcher',
      \Mockery::mock(Cron\Launcher::class)
    );

    $this->assertInstanceOf(
      Cron\Launcher::class,
      $this->cron->getLauncher()
    );
  }

  /** @test */
  public function getLauncher_method_returns_null_when_controller_is_null()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->setNonPublicPropertyValue('controller', null);

    $this->assertNull(
      $this->cron->getLauncher()
    );

    $this->assertNull(
      $this->getNonPublicProperty('_launcher')
    );
  }

  /** @test */
  public function getLauncher_method_returns_null_when_exe_path_is_null()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->setNonPublicPropertyValue('exe_path', null);

    $this->assertNull(
      $this->cron->getLauncher()
    );

    $this->assertNull(
      $this->getNonPublicProperty('_launcher')
    );
  }

  /** @test */
  public function getLauncher_method_returns_null_when_db_check_returns_false()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->cron->getLauncher()
    );

    $this->assertNull(
      $this->getNonPublicProperty('_launcher')
    );
  }

  /** @test */
  public function getRunner_method_creates_and_returns_an_instance_of_runner_class()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->assertInstanceOf(
      Cron\Runner::class,
      $this->cron->getRunner()
    );
  }

  /** @test */
  public function getRunner_method_returns_null_when_controller_is_null()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->setNonPublicPropertyValue('controller', null);

    $this->assertNull(
      $this->cron->getRunner()
    );
  }

  /** @test */
  public function getRunner_method_returns_null_when_check_method_returns_false()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->cron->getRunner()
    );
  }

  /** @test */
  public function getController_method_returns_the_controller_instance()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->assertSame(
      $this->getNonPublicProperty('controller'),
      $this->cron->getController()
    );
  }

  /** @test */
  public function getController_method_returns_null_when_controller_is_null()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->setNonPublicPropertyValue('controller', null);

    $this->assertNull(
      $this->cron->getController()
    );
  }

  /** @test */
  public function getController_method_returns_null_when_check_method_returns_false()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->cron->getController()
    );
  }

  /** @test */
  public function getManager_method_creates_returns_an_instance_of_manager_class()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->twice()
      ->andReturnTrue();

    $this->assertInstanceOf(
      Cron\Manager::class,
      $this->cron->getManager()
    );

    $this->assertInstanceOf(
      Cron\Manager::class,
      $this->getNonPublicProperty('_manager')
    );
  }

  /** @test */
  public function getManager_method_returns_null_when_controller_is_null()
  {
    $this->init();

    $this->setNonPublicPropertyValue('controller', null);

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->assertNull(
      $this->cron->getManager()
    );
  }

  /** @test */
  public function getManager_method_returns_null_when_check_method_returns_false()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->cron->getManager()
    );
  }

  /** @test */
  public function getManager_method_returns_the_existing_manager_instance()
  {
    $this->init();

    $this->setNonPublicPropertyValue(
      '_manager',
      \Mockery::mock(Cron\Manager::class)
    );

    $this->assertInstanceOf(
      Cron\Manager::class,
      $this->cron->getManager()
    );
  }

  /** @test */
  public function check_method_checks_if_the_database_is_ready_to_perform_a_query()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->assertTrue(
      $this->cron->check()
    );
  }

  /** @test */
  public function getExePath_method_reruns_exe_path()
  {
    $this->init();

    $this->assertSame(
      $this->getNonPublicProperty('exe_path'),
      $this->cron->getExePath()
    );
  }

  /** @test */
  public function getLogFile_method_returns_log_file_path()
  {
    $this->init();

    $this->assertSame(
      $this->getNonPublicProperty('log_file'),
      $this->cron->getLogFile()
    );
  }

  /** @test */
  public function getPath_method_returns_the_path_of_the_plugin()
  {
    $this->init();

    $this->assertSame(
      $this->getNonPublicProperty('path'),
      $this->cron->getPath()
    );
  }

  /** @test */
  public function launchPoll_method_launches_a_parallel_poll_process()
  {
    $this->init();

    $this->setNonPublicPropertyValue(
      '_launcher',
      $launcher_mock = \Mockery::mock(Cron\Launcher::class));

    $launcher_mock->shouldReceive('launch')
      ->once()
      ->with(['type' => 'poll'])
      ->andReturn($expected = '2021-09-24-14-19-10.txt');

    $this->assertSame($expected, $this->cron->launchPoll());
  }

  /** @test */
  public function launchPoll_method_returns_null_when_launcher_instance_could_not_be_created()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->cron->launchPoll()
    );
  }

  /** @test */
  public function launchTaskSystem_method_launches_a_parallel_cron_process()
  {
    $this->init();

    $this->setNonPublicPropertyValue(
      '_launcher',
      $launcher = \Mockery::mock(Cron\Launcher::class)
    );

    $launcher->shouldReceive('launch')
      ->once()
      ->with(['type' => 'cron'])
      ->andReturn($expected = '2021-09-24-14-19-10');

    $this->assertSame($expected, $this->cron->launchTaskSystem());
  }

  /** @test */
  public function launchTaskSystem_method_returns_null_when_launcher_instance_could_not_be_created()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->cron->launchTaskSystem()
    );
  }

  /** @test */
  public function getStatusPath_method_returns_status_path()
  {
    $this->init();

    $this->assertSame(
      "{$this->cfg['data_path']}status/.cron",
      $this->cron->getStatusPath('cron')
    );
  }

  /** @test */
  public function getStatusPath_method_returns_null_when_the_provided_type_is_empty_or_path_is_not_set()
  {
    $this->init();

    $this->assertNull(
      $this->cron->getStatusPath('')
    );

    $this->setNonPublicPropertyValue('path', null);

    $this->assertNull(
      $this->cron->getStatusPath('cron')
    );
  }

  /** @test */
  public function getPidPath_method_returns_pid_path()
  {
    $this->init();

    $this->assertSame(
      "{$this->cfg['data_path']}pid/.cron",
      $this->cron->getPidPath(['type' => 'cron'])
    );

    $this->assertSame(
      "{$this->cfg['data_path']}pid/.1234",
      $this->cron->getPidPath(['id' => 1234])
    );
  }

  /** @test */
  public function getPidPath_method_returns_null_when_no_id_or_type_is_provided()
  {
    $this->init();

    $this->assertNull(
      $this->cron->getPidPath(['foo' => 'bar'])
    );

    $this->setNonPublicPropertyValue('path', null);

    $this->assertNull(
      $this->cron->getPidPath(['type' => 'poll'])
    );
  }

  /** @test */
  public function getLogPath_method_creates_and_returns_log_path_when_type_is_provided_having_error_and_no_path_as_false()
  {
    $this->init();

    $this->assertStringContainsString(
      $this->cfg['data_path'],
      $result = $this->cron->getLogPath(['type' => 'cron'])
    );

    $this->assertFileExists($result);
    $this->cleanTestingDir();
  }

  /** @test */
  public function getLogPath_method_returns_log_path_when_type_is_provided_having_error_and_no_path_as_true()
  {
    $this->init();

    $this->assertSame(
      $this->cfg['data_path'] . 'error/cron/',
      $result = $this->cron->getLogPath(['type' => 'cron'],true, true)
    );

    $this->assertFileDoesNotExist($result);

    $this->assertSame(
      $this->cfg['data_path'] . 'error/tasks/1234/',
      $result2 = $this->cron->getLogPath(['id' => 1234],true, true)
    );

    $this->assertFileDoesNotExist($result2);

    $this->assertSame(
      $this->cfg['data_path'] . 'error/cron/',
      $result3 = $this->cron->getLogPath(['type' => 'cron'],true, true)
    );

    $this->assertFileDoesNotExist($result3);

    $this->assertSame(
      $this->cfg['data_path'] . 'error/tasks/1234/',
      $result4 = $this->cron->getLogPath(['id' => 1234],true, true)
    );

    $this->assertFileDoesNotExist($result4);
  }

  /** @test */
  public function getLogPath_method_returns_log_path_when_type_is_provided_having_error_as_false_and_no_path_equal_as_true()
  {
    $this->init();

    $this->assertSame(
      $this->cfg['data_path'] . 'log/cron/',
      $result = $this->cron->getLogPath(['type' => 'cron'],false, true)
    );

    $this->assertFileDoesNotExist($result);

    $this->assertSame(
      $this->cfg['data_path'] . 'log/tasks/1234/',
      $result2 = $this->cron->getLogPath(['id' => 1234],false, true)
    );

    $this->assertFileDoesNotExist($result2);

    $this->assertSame(
      $this->cfg['data_path'] . 'log/cron/',
      $result3 = $this->cron->getLogPath(['type' => 'cron'],false, true)
    );

    $this->assertFileDoesNotExist($result3);

    $this->assertSame(
      $this->cfg['data_path'] . 'log/tasks/1234/',
      $result4 = $this->cron->getLogPath(['id' => 1234],false, true)
    );

    $this->assertFileDoesNotExist($result4);
  }

  /** @test */
  public function getLogPath_method_returns_null_when_the_given_cfg_missing_type_and_id()
  {
    $this->init();

    $this->assertNull(
      $this->cron->getLogPath(['foo' => 'bar'])
    );
  }

  /** @test */
  public function getLogPath_method_returns_null_when_path_is_not_set()
  {
    $this->init();

    $this->setNonPublicPropertyValue('path', null);

    $this->assertNull(
      $this->cron->getLogPath(['type' => 'cron'])
    );
  }

  /** @test */
  public function isActive_method_returns_true_if_the_active_file_exists_and_false_otherwise()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->twice()
      ->andReturnTrue();

    $this->createDir($dir = "path/to/data/status");

    $this->createFile('.active', '', $dir);

    $this->assertTrue($this->cron->isActive());

    $this->cleanTestingDir();

    $this->assertFalse($this->cron->isActive());
  }

  /** @test */
  public function isCronActive_method_returns_true_if_the_cron_file_exists_and_false_otherwise()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->twice()
      ->andReturnTrue();

    $this->createDir($dir = "path/to/data/status");

    $this->createFile('.cron', '', $dir);

    $this->assertTrue($this->cron->isCronActive());

    $this->cleanTestingDir();

    $this->assertFalse($this->cron->isCronActive());
  }

  /** @test */
  public function isPollActive_method_returns_true_if_the_poll_file_exists_and_false_otherwise()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->twice()
      ->andReturnTrue();

    $this->createDir($dir = "path/to/data/status");

    $this->createFile('.poll', '', $dir);

    $this->assertTrue($this->cron->isPollActive());

    $this->cleanTestingDir();

    $this->assertFalse($this->cron->isPollActive());
  }
}