<?php

namespace bbn\tests\Cron;

use bbn\Appui\Observer;
use bbn\Cron;
use bbn\Cron\Runner;
use bbn\Db;
use bbn\Mvc\Controller;
use bbn\User;
use bbn\Util\Timer;
use PHPUnit\Framework\TestCase;
use bbn\tests\Files;
use bbn\tests\Reflectable;

class RunnerTest extends TestCase
{
  use Reflectable, Files;

  protected Runner $runner;

  protected $cron_mock;

  protected $controller_mock;

  protected $db_mock;

  protected $timer_mock;

  protected $manager_mock;

  protected $observer_mock;

  protected $cfg = [
    'type' => 'cron'
  ];

  protected $data_path;

  protected $plugin_path = 'plugins/appui-cron/';

  protected function setUp(): void
  {
    $this->cron_mock       = \Mockery::mock(Cron::class);
    $this->controller_mock = \Mockery::mock(Controller::class);
    $this->db_mock         = \Mockery::mock(Db::class);
    $this->timer_mock      = \Mockery::mock(Timer::class);
    $this->observer_mock   = \Mockery::mock(Observer::class);

    $this->controller_mock->db = $this->db_mock;
    $this->cleanTestingDir();

    $this->data_path = BBN_APP_PATH . BBN_DATA_PATH . $this->plugin_path;

    $this->cron_mock->shouldReceive('getManager')
      ->andReturn($this->manager_mock = \Mockery::mock(Cron\Manager::class));
  }

  protected function tearDown(): void
  {
    \Mockery::close();
    $this->cleanTestingDir();
  }

  public function getInstance()
  {
    return $this->runner;
  }

  protected function setConstructorExpectations()
  {
    $this->cron_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->cron_mock->shouldReceive('getController')
      ->once()
      ->andReturn($this->controller_mock);

    $this->cron_mock->shouldReceive('getLogFile')
      ->once()
      ->andReturn('path/to/log_file');

    $this->controller_mock->shouldReceive('dataPath')
      ->once()
      ->with('appui-cron')
      ->andReturn($this->data_path);
  }

  protected function init(?array $cfg = null)
  {
    $this->setConstructorExpectations();

    $this->runner = new Runner($this->cron_mock, $cfg ?? $this->cfg);

    if ($this->getNonPublicProperty('timer')) {
      $this->setNonPublicPropertyValue('timer', $this->timer_mock);
    }
  }

  protected function mockRunnerClass(?array $cfg = null)
  {
    $this->runner = \Mockery::mock(Runner::class)->makePartial();

    $this->setConstructorExpectations();

    $this->runner->__construct($this->cron_mock, $cfg ?? $this->cfg);

    if ($this->getNonPublicProperty('timer')) {
      $this->setNonPublicPropertyValue('timer', $this->timer_mock);
    }
  }

  protected function mockUserClassAndGetConfig(): array
  {
    $user_mock = \Mockery::mock(User::class);

    $this->setNonPublicPropertyValue('retriever_instance', $user_mock, $user_mock);

    $user_mock->shouldReceive('getClassCfg')
      ->andReturn(
        $cfg = $this->getNonPublicProperty('default_class_cfg', $user_mock)
      );

    return $cfg;
  }

  /** @test */
  public function constructor_test()
  {
    $this->init();

    $this->assertSame(
      $this->controller_mock,
      $this->getNonPublicProperty('controller')
    );

    $this->assertSame(
      $this->cron_mock,
      $this->getNonPublicProperty('cron')
    );

    $this->assertSame(
      'path/to/log_file',
      $this->getNonPublicProperty('log_file')
    );

    $this->assertSame(
      $this->db_mock,
      $this->getNonPublicProperty('db')
    );

    $this->assertSame(
      $this->data_path,
      $this->getNonPublicProperty('path')
    );

    $this->assertSame(
      $this->cfg,
      $this->getNonPublicProperty('data')
    );

    $this->assertSame(
      $this->cfg['type'],
      $this->getNonPublicProperty('type')
    );

    $this->assertInstanceOf(
      Timer::class,
      $this->getNonPublicProperty('timer')
    );
  }

  /** @test */
  public function constructor_test_when_check_method_returns_false()
  {
    $this->cron_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->runner = new Runner($this->cron_mock, $this->cfg);

    $this->assertNull(
      $this->getNonPublicProperty('controller')
    );

    $this->assertNull(
      $this->getNonPublicProperty('log_file')
    );

    $this->assertNull(
      $this->getNonPublicProperty('db')
    );

    $this->assertNull(
      $this->getNonPublicProperty('path')
    );

    $this->assertNull(
      $this->getNonPublicProperty('data')
    );

    $this->assertNull(
      $this->getNonPublicProperty('type')
    );

    $this->assertNull(
      $this->getNonPublicProperty('timer')
    );
  }

  /** @test */
  public function constructor_test_when_no_type_provided()
  {
    $this->runner = new Runner($this->cron_mock, ['a' => 'b']);

    $this->assertNull(
      $this->getNonPublicProperty('controller')
    );

    $this->assertNull(
      $this->getNonPublicProperty('log_file')
    );

    $this->assertNull(
      $this->getNonPublicProperty('db')
    );

    $this->assertNull(
      $this->getNonPublicProperty('path')
    );

    $this->assertNull(
      $this->getNonPublicProperty('data')
    );

    $this->assertNull(
      $this->getNonPublicProperty('type')
    );

    $this->assertNull(
      $this->getNonPublicProperty('timer')
    );
  }

  /** @test */
  public function output_method_test_when_log_is_boolean()
  {
    $this->init();

    $this->expectOutputString(<<<OUTPUT
  "foo": true,

OUTPUT
);

    $this->runner->output('foo', true);
  }

  /** @test */
  public function output_method_test_when_log_is_number()
  {
    $this->init();

    $this->expectOutputString(<<<OUTPUT
  "foo": 22,

OUTPUT
    );

    $this->runner->output('foo', 22);
  }

  /** @test */
  public function output_method_test_when_log_is_string()
  {
    $this->init();

    $this->expectOutputString(<<<OUTPUT
  "foo": "bar\"",

OUTPUT
    );

    $this->runner->output('foo', 'bar"');
  }

  /** @test */
  public function output_method_test_when_log_is_array()
  {
    $this->init();

    $this->expectOutputString(<<<OUTPUT
  "foo": 
{
    "a": "b\"c",
}
,

OUTPUT
    );

    $this->runner->output('foo', ['a' => 'b"c']);
  }

  /** @test */
  public function output_method_test_when_log_is_object()
  {
    $this->init();

    $this->expectOutputString(<<<OUTPUT
  "foo": 
{
    "a": "b\"c",
}
,

OUTPUT
    );

    $this->runner->output('foo', (object)['a' => 'b"c']);
  }

  /** @test */
  public function output_method_test_when_name_is_false()
  {
    $this->init();

    $this->expectOutputString(<<<OUTPUT
}

OUTPUT
);

    $this->runner->output(false);
  }

  public function output_method_test_when_name_is_true()
  {
    $this->init();

    $this->expectOutputString(<<<OUTPUT
{

OUTPUT
    );

    $this->runner->output(true);
  }

  /** @test */
  public function output_method_multiple_tests()
  {
    $this->init();

    $this->expectOutputString(<<<OUTPUT
{
  "foo": true,
  "bar": 123,
}

OUTPUT
);

    $this->runner->output(true);

    $this->runner->output('foo', true);
    $this->runner->output('bar', 123);

    $this->runner->output(false);
  }

  /** @test */
  public function output_method_does_not_produce_output_when_name_is_empty()
  {
    $this->expectOutputString('');

    $this->init();
    $this->runner->output('');
  }

  /** @test */
  public function shutdown_method_test_when_file_exists_and_type_is_cron_and_id_exists()
  {
    if (!defined('BBN_PID')) {
      define('BBN_PID', 'test');
    }

    $this->init();

    $this->setNonPublicPropertyValue('data', [
      'type' => $this->cfg['type'],
      'id' => $id = '634a2c70aaaaa2aaa47652540000cfaa'
    ]);

    $this->createDir($dir = "{$this->plugin_path}pid");

    $file = $this->createFile(".{$this->cfg['type']}", 'test|foo', $dir);

    $this->manager_mock->shouldReceive('finish')
      ->once()
      ->with($id)
      ->andReturnTrue();

    $this->runner->shutdown();

    $this->assertFileExists($file);
  }

  /** @test */
  public function shutdown_method_test_when_file_exists_and_content_does_not_match_bbn_pid_constant()
  {
    if (!defined('BBN_PID')) {
      define('BBN_PID', 'test');
    }

    $this->expectOutputString(<<<OUTPUT
  "Different processes": "foo/test",

OUTPUT
);

    $this->init();

    $this->createDir($dir = "{$this->plugin_path}pid");

    $file = $this->createFile(".{$this->cfg['type']}", 'foo|bar', $dir);

    $this->runner->shutdown();

    $this->assertFileExists($file);
  }

  /** @test */
  public function shutdown_method_test_when_type_is_poll()
  {
    $this->init();

    $this->setNonPublicPropertyValue('data', [
      'type' => 'poll'
    ]);

    $this->cron_mock->shouldReceive('launchPoll')
      ->once()
      ->andReturn('path/to/log/');

    $this->runner->shutdown();

    $this->assertTrue(true);
  }

  /** @test */
  public function shutdown_method_test_when_type_is_cron_and_id_is_not_uid()
  {
    $this->init();

    $this->setNonPublicPropertyValue('data', [
      'type' => 'cron',
      'id' => '1234'
    ]);

    $this->cron_mock->shouldReceive('launchTaskSystem')
      ->once()
      ->andReturn('path/to/log');

    $this->runner->shutdown();

    $this->assertTrue(true);
  }

  /** @test */
  public function shutdown_method_test_when_type_is_cron_and_id_key_does_not_exist()
  {
    $this->init();

    $this->cron_mock->shouldReceive('launchTaskSystem')
      ->once()
      ->andReturn('path/to/log');

    $this->runner->shutdown();

    $this->assertTrue(true);
  }

  /** @test */
  public function getData_method_returns_the_data()
  {
    $this->init();

    $this->assertSame(
      $this->getNonPublicProperty('data'),
      $this->runner->getData()
    );
  }

  /** @test */
  public function check_method_checks_whether_the_cron_type_is_set_or_not()
  {
    $this->init();

    $this->assertTrue(
      $this->runner->check()
    );

    $this->setNonPublicPropertyValue('type', null);

    $this->assertFalse(
      $this->runner->check()
    );
  }

  /** @test */
  public function poll_method_test()
  {
    $this->mockRunnerClass();
    $user_cfg = $this->mockUserClassAndGetConfig();

    $this->timer_mock->shouldReceive('start')
      ->once()
      ->with('timeout')
      ->andReturnTrue();

    $this->timer_mock->shouldReceive('start')
      ->once()
      ->with('users')
      ->andReturnTrue();

    $this->timer_mock->shouldReceive('start')
      ->once()
      ->with('cron_check')
      ->andReturnTrue();

    // user cfg variables
    $sessions_table = $user_cfg['tables']['sessions'];
    $id_field       = $user_cfg['arch']['sessions']['id'];
    $sess_id_field  = $user_cfg['arch']['sessions']['sess_id'];
    $id_user_field  = $user_cfg['arch']['sessions']['id_user'];
    $opened_field   = $user_cfg['arch']['sessions']['opened'];

    $time           = time();
    $observer_files = [];

    // First loop isPollActive is true
    $this->runner->shouldReceive('isPollActive')
      ->once()
      ->andReturnTrue();

    $this->observer_mock->shouldReceive('observe')
      ->once()
      ->andReturn($users = [
        'id_user_1' => [
          ['id' =>  'id_1', 'result' => 'result_1'],
          ['id' =>  'id_2', 'result' => 'result_2']
        ],
        'id_user_2' => [
          ['id' => 'id_3', 'result' => 'result_3']
        ]
      ]);

    foreach ($users as $user_id => $user_data) {
      $this->db_mock->shouldReceive('selectAll')
        ->once()
        ->with(
          $sessions_table, // table
          [$id_field, $sess_id_field], // fields
          [$id_user_field => $user_id, $opened_field => 1] // where
        )
        ->andReturn($sessions = [
          (object)[$id_field => 'id_1', $sess_id_field => 'sess_1'],
          (object)[$id_field => 'id_2', $sess_id_field => 'sess_2'],
        ]);

      foreach ($sessions as $session) {
        $this->controller_mock->shouldReceive('userDataPath')
          ->once()
          ->with($user_id, 'appui-core')
          ->andReturn($user_data_path = "{$this->data_path}users/$user_id/data/appui-core/");

        $observer_files[] = [
          'file' => "{$user_data_path}poller/queue/{$session->id}/observer-$time.json",
          'data' => ['observers' => $user_data]
        ];
      }
    }

    // First measure method call for users which will be greater than user_timeout
    $this->timer_mock->shouldReceive('measure')
      ->once()
      ->with('users')
      ->andReturn($this->getNonPublicProperty('user_timeout') + 1);

    $this->timer_mock->shouldReceive('stop')
      ->once()
      ->with('users')
      ->andReturn(1.5);

    $this->timer_mock->shouldReceive('start')
      ->once()
      ->with('users')
      ->andReturnTrue();

    // Second measure method call for timeout which will be greater than poll_timeout
    $this->timer_mock->shouldReceive('measure')
      ->once()
      ->with('timeout')
      ->andReturn($this->getNonPublicProperty('poll_timeout') + 1);

    // Third measure method call for cron_check which will be greater than cron_check_timeout
    $this->timer_mock->shouldReceive('measure')
      ->once()
      ->with('cron_check')
      ->andReturn($this->getNonPublicProperty('cron_check_timeout') + 1);

    // notifyFailed method should be called in this case
    $this->manager_mock->shouldReceive('notifyFailed')
      ->once();

    // Then stop method should be called with cron_check
    $this->timer_mock->shouldReceive('stop')
      ->once()
      ->with('cron_check')
      ->andReturn(2.6);

    // And start it again
    $this->timer_mock->shouldReceive('start')
      ->once()
      ->with('cron_check')
      ->andReturnTrue();

    // Second loop isPollActive
    $this->runner->shouldReceive('isPollActive')
      ->once()
      ->andReturnFalse();

    $date = date('Y-m-d H:i:s');

    $this->expectOutputString(<<<OUTPUT
?.  "Ending poll process": "$date",

OUTPUT
);

    $this->runner->poll($this->observer_mock);

    // Observer files should be created
    foreach ($observer_files as $observer) {
      $this->assertFileExists($observer['file']);
      $this->assertSame(
        $observer['data'],
        json_decode(file_get_contents($observer['file']), true)
      );
    }
  }

  /** @test */
  public function poll_method_does_not_process_when_check_method_returns_false()
  {
    $this->init();

    $this->setNonPublicPropertyValue('type', null);

    $this->expectOutputString('');

    $this->runner->poll();
  }

  /** @test */
  /*public function runTaskSystem_method_test_when_isActive_returns_false()
  {
    $this->mockRunnerClass();

    $this->timer_mock->shouldReceive('start')
      ->once()
      ->with('timeout')
      ->andReturnTrue();

    $this->runner->shouldReceive('isActive')
      ->once()
      ->andReturnFalse();

    $this->runner->shouldNotReceive('isCronActive');

    $this->manager_mock->shouldReceive('getRunningRows')
      ->once()
      ->withNoArgs()
      ->andReturn([
        $row_1 = ['id' => '123', 'pid' => 'pid_1', 'cfg' => json_encode(['a' => 'b'])],
        $row_2 = ['id' => '12345', 'pid' => 'pid_2', 'cfg' => json_encode(['c' => 'd'])]
      ]);

    // The first row has file
    $this->createDir($dir = "{$this->plugin_path}pid");
    $file_1 = $this->createFile(".{$row_1['id']}", 'foo|bar', $dir);

    // Manager::unsetPid method should be called for every row
    $this->manager_mock->shouldReceive('unsetPid')
      ->once()
      ->with($row_1['id'])
      ->andReturn(1);

    $this->manager_mock->shouldReceive('unsetPid')
      ->once()
      ->with($row_2['id'])
      ->andReturn(1);

    $this->runner->runTaskSystem();

    // File of the first row should be deleted
    $this->assertFileDoesNotExist($file_1);
  }*/

  /** @test */
  /*public function runTaskSystem_method_test_when_isCronActive_returns_false()
  {
    $this->mockRunnerClass();

    $this->timer_mock->shouldReceive('start')
      ->once()
      ->with('timeout')
      ->andReturnTrue();

    $this->runner->shouldReceive('isActive')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isCronActive')
      ->once()
      ->andReturnFalse();

    $this->manager_mock->shouldReceive('getRunningRows')
      ->once()
      ->withNoArgs()
      ->andReturn([
        $row_1 = ['id' => '123', 'pid' => 'pid_1', 'cfg' => json_encode(['a' => 'b'])],
        $row_2 = ['id' => '12345', 'pid' => 'pid_2', 'cfg' => json_encode(['c' => 'd'])]
      ]);

    // The first row has file
    $this->createDir($dir = "{$this->plugin_path}pid");
    $file_1 = $this->createFile(".{$row_1['id']}", 'foo|bar', $dir);

    // Manager::unsetPid method should be called for every row
    $this->manager_mock->shouldReceive('unsetPid')
      ->once()
      ->with($row_1['id'])
      ->andReturn(1);

    $this->manager_mock->shouldReceive('unsetPid')
      ->once()
      ->with($row_2['id'])
      ->andReturn(1);

    $this->runner->runTaskSystem();

    // File of the first row should be deleted
    $this->assertFileDoesNotExist($file_1);
  }*/

  /** @test */
  /*public function runTaskSystem_method_test_when_isActive_and_isCronActive_returns_true()
  {
    $this->mockRunnerClass();

    $this->timer_mock->shouldReceive('start')
      ->once()
      ->with('timeout')
      ->andReturnTrue();

    $this->runner->shouldReceive('isActive')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isCronActive')
      ->once()
      ->andReturnTrue();

    $this->manager_mock->shouldReceive('getNextRows')
      ->once()
      ->with(0)
      ->andReturn($rows = [
        ['id' => '123', 'file' => 'file_1', 'cfg' => json_encode(['a' => 'b'])],
        ['id' => '321', 'file' => 'file_2', 'cfg' => null]
      ]);

    // Launcher::launch method should be called for all rows
    $launcher_mock = \Mockery::mock(Cron\Launcher::class);

    $this->cron_mock->shouldReceive('getLauncher')
      ->times(3)
      ->andReturn($launcher_mock);

    foreach ($rows as $row) {
      $launcher_mock->shouldReceive('launch')
        ->once()
        ->with([
          'type' => 'cron',
          'id' => $row['id'],
          'file' => $row['file']
        ])
        ->andReturn('path/to/log');
    }

    // Second iterate in the while loop
    $this->runner->shouldReceive('isActive')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isCronActive')
      ->once()
      ->andReturnTrue();

    $this->manager_mock->shouldReceive('getNextRows')
      ->once()
      ->with(0)
      ->andReturn([
        $row_3 = ['id' => '12334', 'file' => 'file_3', 'cfg' => json_encode(['a' => 'b'])],
      ]);

    $launcher_mock->shouldReceive('launch')
      ->once()
      ->with([
        'type' => 'cron',
        'id' => $row_3['id'],
        'file' => $row_3['file']
      ])
      ->andReturn('path/to/log');

    // Break the loop
    $this->runner->shouldReceive('isActive')
      ->once()
      ->andReturnFalse();

    $this->manager_mock->shouldReceive('getRunningRows')
      ->once()
      ->andReturn([]);

    $this->runner->runTaskSystem();

    $this->assertTrue(true);
  }*/

  /** @test */
  public function runTaskSystem_method_does_not_run_the_tas_if_check_method_returns_false()
  {
    $this->mockRunnerClass();

    $this->setNonPublicPropertyValue('type', null);

    $this->timer_mock->shouldNotReceive('start');
    $this->runner->shouldNotReceive('isActive');
    $this->runner->shouldNotReceive('isCronActive');

    $this->runner->runTaskSystem();

    $this->assertTrue(true);
  }

  /** @test */
  /*public function runTask_method_test()
  {
    $this->init();

    $this->manager_mock->shouldReceive('start')
      ->once()
      ->with('123')
      ->andReturnTrue();

    // Create the log file
    $this->createDir($log_dir = '2021/09/29/logs');
    $log_file = $this->createFile(
      'log.txt', $log_file_content = json_encode(['old_content_1' => 'content_value']), $log_dir
    );

    // Create the log json file
    $json_file = $this->createFile(
      '2021-09-29.json', json_encode([$old_json_file_content = ['a' => 'b']]), '2021/09'
    );

    // Create the month json file
    $month_file = $this->createFile(
      '2021-09.json',
      json_encode($old_month_file_content = [
          'total' => 20,
          'duration' => 5,
          'content' => 2,
          'duration_content' => 2,
          'first' => '2021-09-25 00:00:00',
          'last' => '2021-09-25 00:00:00',
          'dates' => [
            date('d', strtotime('2021-09-25 00:00:00'))
          ]
        ]),
      '2021'
    );

    // Create the year json file
    $year_file = $this->createFile(
      '2021.json',
      json_encode($old_year_file_content = [
        'total' => 10,
        'duration' => 15,
        'content' => 12,
        'duration_content' => 12,
        'first' => '2021-01-01 00:00:00',
        'last' => '2021-08-25 00:00:00',
        'month' => [
          date('m', strtotime('2021-01-01 00:00:00')),
          date('m', strtotime('2021-08-25 00:00:00')),
        ]
      ]),
      ''
    );

    $this->timer_mock->shouldReceive('start')
      ->once()
      ->with($file = 'path/to/file')
      ->andReturnTrue();

    $this->controller_mock->shouldReceive('reroute')
      ->once()
      ->with($file);

    $this->controller_mock->shouldReceive('process')
      ->once();

    $this->timer_mock->shouldReceive('stop')
      ->once()
      ->with($file)
      ->andReturn($duration = 1.4);

    $this->runner->runTask([
      'type' => 'cron',
      'id' => '123',
      'file' => $file,
      'log_file' => $log_file
    ]);

    // Log file should not be deleted as it has content
    $this->assertFileExists($log_file);
    $this->assertSame($log_file_content, file_get_contents($log_file));

    // json file should have the content from the old one
    $this->assertFileExists($json_file);

    $this->assertSame(
      $old_json_file_content,
      ($new_json_content = json_decode(file_get_contents($json_file), true))[0]
    );
    $this->assertSame([
      'start' => $start = date('Y-m-d H:i:s'),
      'file' => $file,
      'pid' => getmypid(),
      'duration' => $duration,
      'content' => "$log_dir/log.txt",
      'end' => date('Y-m-d H:i:s')
    ], $new_json_content[1]);

    // Month file should consider content from the old one
    $this->assertFileExists($month_file);
    $this->assertSame([
      'total' => $old_month_file_content['total'] + 1,
      'duration' => $duration + $old_month_file_content['duration'],
      'content' => $old_month_file_content['content'] + 1,
      'duration_content' => $duration + $old_month_file_content['duration_content'],
      'first' => $old_month_file_content['first'],
      'last' => $start,
      'dates' => array_merge($old_month_file_content['dates'], [date('d')])
    ], json_decode(file_get_contents($month_file), true));

    // Year file should consider content from the old one
    $this->assertFileExists($year_file);
    $this->assertSame([
      'total' => $old_year_file_content['total'] + 1,
      'duration' => $duration + $old_year_file_content['duration'],
      'content' => $old_year_file_content['content'] + 1,
      'duration_content' => $duration + $old_year_file_content['duration_content'],
      'first' => $old_year_file_content['first'],
      'last' => $start,
      'month' => array_merge($old_year_file_content['month'], [date('m')])
    ], json_decode(file_get_contents($year_file), true));
  }*/

  /** @test */
  /*public function runTask_method_test_when_none_of_the_json_files_exist_and_log_file_is_empty()
  {
    $this->init();

    $this->manager_mock->shouldReceive('start')
      ->once()
      ->with('123')
      ->andReturnTrue();

    // Create the empty log file
    $this->createDir($log_dir = '2021/09/29/logs');
    $log_file = $this->createFile('log.txt', '', $log_dir);

    $this->timer_mock->shouldReceive('start')
      ->once()
      ->with($file = 'path/to/file')
      ->andReturnTrue();

    $this->controller_mock->shouldReceive('reroute')
      ->once()
      ->with($file);

    $this->controller_mock->shouldReceive('process')
      ->once();

    $this->timer_mock->shouldReceive('stop')
      ->once()
      ->with($file)
      ->andReturn($duration = 1.4);

    $this->runner->runTask([
      'type' => 'cron',
      'id' => '123',
      'file' => $file,
      'log_file' => $log_file
    ]);

    // Log file should be deleted as it's empty
    $this->assertFileDoesNotExist($log_file);

    // json file should be created
    $this->assertFileExists(
      $json_file = $this->getTestingDirName() . '2021/09/2021-09-29.json'
    );
    $this->assertSame([[
      'start' => $start = date('Y-m-d H:i:s'),
      'file' => $file,
      'pid' => getmypid(),
      'duration' => $duration,
      'content' => false,
      'end' => date('Y-m-d H:i:s')
    ]], json_decode(file_get_contents($json_file), true));

    // Month file should be created
    $this->assertFileExists(
      $month_file = $this->getTestingDirName() . '2021/2021-09.json'
    );
    $this->assertSame([
      'total' => 1,
      'content' => 0,
      'first' => $start,
      'last' => $start,
      'dates' => [
        date('d')
      ],
      'duration' => $duration,
      'duration_content' => 0
    ], json_decode(file_get_contents($month_file), true));

    // Year file should be created
    $this->assertFileExists(
      $year_file = $this->getTestingDirName() . '2021.json'
    );
    $this->assertSame([
      'total' => 1,
      'content' => 0,
      'first' => $start,
      'last' => $start,
      'month' => [
        date('m')
      ],
      'duration' => $duration,
      'duration_content' => 0
    ], json_decode(file_get_contents($year_file), true));
  }*/

  /** @test */
  /*public function runTask_method_when_the_log_and_json_files_exists_but_none_of_them_are_json()
  {
    $this->init();

    $this->manager_mock->shouldReceive('start')
      ->once()
      ->with('123')
      ->andReturnTrue();

    // Create the file
    $this->createDir($log_dir = '2021/09/29/logs');
    $log_file = $this->createFile('log.txt', $log_file_content = 'foo|bar', $log_dir);

    // Create the log json file
    $json_file = $this->createFile(
      '2021-09-29.json', 'foo2|bar2', '2021/09'
    );

    // Create the month json file
    $month_file = $this->createFile(
      '2021-09.json', $old_month_file_content = 'foo3|bar3', '2021'
    );

    // Create the year json file
    $year_file = $this->createFile(
      '2021.json', $old_year_file_content = 'foo4|bar4', ''
    );

    $this->timer_mock->shouldReceive('start')
      ->once()
      ->with($file = 'path/to/file')
      ->andReturnTrue();

    $this->controller_mock->shouldReceive('reroute')
      ->once()
      ->with($file);

    $this->controller_mock->shouldReceive('process')
      ->once();

    $this->timer_mock->shouldReceive('stop')
      ->once()
      ->with($file)
      ->andReturn($duration = 1.4);

    $this->runner->runTask([
      'type' => 'cron',
      'id' => '123',
      'file' => $file,
      'log_file' => $log_file
    ]);

    // Log file should not be deleted as it's not empty
    $this->assertFileExists($log_file);
    $this->assertSame($log_file_content, file_get_contents($log_file));

    // json file should be created
    $this->assertFileExists($json_file);
    $this->assertSame([[
      'start' => $start = date('Y-m-d H:i:s'),
      'file' => $file,
      'pid' => getmypid(),
      'duration' => $duration,
      'content' => '2021/09/29/logs/log.txt',
      'end' => date('Y-m-d H:i:s')
    ]], json_decode(file_get_contents($json_file), true));

    // Month file expectations
    $this->assertFileExists($month_file);
    $this->assertSame([
      'total' => 1,
      'content' => 1,
      'first' => $start,
      'last' => $start,
      'dates' => [
        date('d')
      ],
      'duration' => $duration,
      'duration_content' => $duration
    ], json_decode(file_get_contents($month_file), true));

    // Year file expectations
    $this->assertFileExists($year_file);
    $this->assertSame([
      'total' => 1,
      'content' => 1,
      'first' => $start,
      'last' => $start,
      'month' => [
        date('m')
      ],
      'duration' => $duration,
      'duration_content' => $duration
    ], json_decode(file_get_contents($year_file), true));
  }*/

  /** @test */
  /*public function run_method_test_when_type_is_cron_and_id_does_not_exist()
  {
    if (!defined('BBN_PID')) {
      define('BBN_PID', '12345');
    }

    $this->mockRunnerClass();

    $this->runner->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isActive')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isCronActive')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('runTaskSystem')
      ->once();

    $this->runner->run();

    $this->assertFileExists(
      $this->getTestingDirName() . "{$this->plugin_path}pid/.cron"
    );
  }*/

  /** @test */
  /*public function run_method_test_when_type_is_cron_and_id_exists()
  {
    if (!defined('BBN_PID')) {
      define('BBN_PID', '12345');
    }

    $this->mockRunnerClass($cfg = [
      'type' => 'cron',
      'id' => '54321'
    ]);

    $this->runner->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isActive')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isCronActive')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('runTask')
      ->with($cfg)
      ->once();

    $this->runner->run();

    $this->assertFileExists(
      $this->getTestingDirName() . "{$this->plugin_path}pid/.54321"
    );
  }*/

  /** @test */
  /*public function run_method_test_when_type_is_poll()
  {
    if (!defined('BBN_PID')) {
      define('BBN_PID', '12345');
    }

    $this->mockRunnerClass([
      'type' => 'poll'
    ]);

    $this->runner->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isActive')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isPollActive')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('poll')
      ->once();

    $this->runner->run();

    $this->assertFileExists(
      $this->getTestingDirName() . "{$this->plugin_path}pid/.poll"
    );
  }*/

  /** @test */
  /*public function run_method_test_when_the_pid_file_exists()
  {
    if (!defined('BBN_PID')) {
      define('BBN_PID', '12345');
    }

    $this->mockRunnerClass();

    $this->runner->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isActive')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isCronActive')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('runTaskSystem')
      ->once();

    // Create the pid file
    $this->createDir($dir = "{$this->plugin_path}pid");
    $pid_file = $this->createFile('.cron', $old_content = 'old_pid|old_time', $dir);

    $this->expectOutputString(<<<OUTPUT
  "Dead process": "old_pid",

OUTPUT
);

    $this->runner->run();

    $this->assertFileExists($pid_file);
    // Content should be changed
    $this->assertNotSame($old_content, file_get_contents($pid_file));
  }*/

  /** @test */
  public function run_method_throws_an_exception_when_active_file_does_not_exist()
  {
    $this->expectException(\Exception::class);
    $this->mockRunnerClass();

    $this->runner->shouldReveive('check')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isActive')
      ->once()
      ->andReturnFalse();

    $this->runner->run();
  }

  /** @test */
  public function run_method_throws_an_exception_when_type_is_cron_and_active_cron_file_does_not_exist()
  {
    $this->expectException(\Exception::class);
    $this->mockRunnerClass();

    $this->runner->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isActive')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isCronActive')
      ->once()
      ->andReturnFalse();

    $this->runner->run();
  }

  /** @test */
  public function run_method_throws_an_exception_when_type_is_poll_and_active_poll_file_does_not_exist()
  {
    $this->expectException(\Exception::class);
    $this->mockRunnerClass([
      'type' => 'poll'
    ]);

    $this->runner->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isActive')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldReceive('isPollActive')
      ->once()
      ->andReturnFalse();

    $this->runner->run();
  }

  /** @test */
  /*public function run_method_does_not_process_when_type_is_not_defined()
  {
    $this->mockRunnerClass();

    $this->setNonPublicPropertyValue('data', ['id' => '123']);

    $this->runner->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->runner->shouldNotReceive('getPidPath');
    $this->runner->shouldNotReceive('isActive');

    $this->runner->run();

    $this->assertTrue(true);
  }*/

  /** @test */
  /*public function run_method_does_not_process_when_check_method_returns_false()
  {
    $this->mockRunnerClass();

    $this->runner->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->runner->shouldNotReceive('getPidPath');
    $this->runner->shouldNotReceive('isActive');

    $this->runner->run();

    $this->assertTrue(true);
  }*/
}