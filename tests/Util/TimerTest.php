<?php

namespace bbn\tests\Util;

use bbn\Util\Timer;
use PHPUnit\Framework\TestCase;
use bbn\tests\Reflectable;

class TimerTest extends TestCase
{
  use Reflectable;

  protected Timer $timer;

  protected function setUp(): void
  {
    $this->timer = new Timer();
  }

  public function getInstance()
  {
    return $this->timer;
  }

  /** @test */
  public function start_method_starts_a_timer_for_the_given_key()
  {
    $this->assertTrue(
      $this->timer->start()
    );

    $measures = $this->getNonPublicProperty('_measures');

    $this->assertArrayHasKey('default', $measures);
    $this->assertIsFloat(($measure = $measures['default'])['start']);
    $this->assertSame(0, $measure['num']);
    $this->assertSame(0, $measure['sum']);

    $this->assertTrue(
      $this->timer->start('default', $start_default = strtotime('-1 DAY'))
    );

    $measures = $this->getNonPublicProperty('_measures');

    $this->assertArrayHasKey('default', $measures);
    $this->assertSame($start_default, ($measure = $measures['default'])['start']);
    $this->assertSame(0, $measure['num']);
    $this->assertSame(0, $measure['sum']);

    $this->assertTrue(
      $this->timer->start('timeout', $start_timeout = strtotime('-2 DAYS'))
    );

    $measures = $this->getNonPublicProperty('_measures');

    $this->assertArrayHasKey('default', $measures);
    $this->assertArrayHasKey('timeout', $measures);

    $this->assertSame($start_default, ($measure = $measures['default'])['start']);
    $this->assertSame(0, $measure['num']);
    $this->assertSame(0, $measure['sum']);

    $this->assertSame($start_timeout, ($measure = $measures['timeout'])['start']);
    $this->assertSame(0, $measure['num']);
    $this->assertSame(0, $measure['sum']);
  }

  /** @test */
  public function hasStarted_method_returns_true_if_the_timer_has_started_for_the_given_key()
  {
    $this->setNonPublicPropertyValue('_measures', [
      'default' => [
        'start' => 0
      ],
      'timeout' => [
        'start' => time()
      ],
      'users' => [
        'num' => 1
      ]
    ]);

    $this->assertFalse($this->timer->hasStarted());
    $this->assertTrue($this->timer->hasStarted('timeout'));
    $this->assertFalse($this->timer->hasStarted('foo'));
    $this->assertFalse($this->timer->hasStarted('users'));
  }

  /** @test */
  public function reset_method_resets_the_timer_for_the_given_key_if_already_started()
  {
    $this->setNonPublicPropertyValue('_measures', [
      'default' => [
        'num' => 11,
        'sum' => 10,
        'start' => time()
      ],
      'timeout' => [
        'num' => 5,
        'sum' => 4,
        'start' => 0 // not started
      ]
    ]);

    $this->timer->reset();
    $this->timer->reset('users');

    $this->assertSame([
      'default' => [
        'num' => 0,
        'sum' => 0
      ],
      'timeout' => [
        'num' => 5,
        'sum' => 4,
        'start' => 0
      ]
    ], $this->getNonPublicProperty('_measures'));
  }

  /** @test */
  public function stop_method_stops_the_timer_for_the_given_key()
  {
    $this->setNonPublicPropertyValue('_measures', [
      'default' => [
        'num' => 1,
        'sum' => 1,
        'start' => $default_start = strtotime('-1 HOUR')
      ],
      'users' => [
        'num' => 0,
        'sum' => 0,
        'start' => $users_start = strtotime('+1 DAY')
      ]
    ]);

    $this->timer->stop();
    $this->timer->stop('users');

    $measures = $this->getNonPublicProperty('_measures');

    $this->assertSame(2, $measures['default']['num']);
    $this->assertNotSame($default_start, $measures['default']['sum']);
    $this->assertTrue($measures['default']['sum'] > 0);
    $this->assertArrayNotHasKey('start', $measures['default']);

    $this->assertSame(1, $measures['users']['num']);
    $this->assertNotSame($users_start, $measures['users']['sum']);
    $this->assertTrue($measures['users']['sum'] < 0);
    $this->assertArrayNotHasKey('start', $measures['users']);
  }

  /** @test */
  public function stop_method_throws_an_exception_when_the_given_timer_has_zero_start_time()
  {
    $this->expectException(\Exception::class);

    $this->setNonPublicPropertyValue('_measures', [
      'timeout' => [
        'num' => 0,
        'sum' => 0,
        'start' => 0
      ]
    ]);

    $this->timer->stop('timeout');
  }

  /** @test */
  public function stop_method_throws_an_exception_when_the_given_timer_does_not_has_a_start_time()
  {
    $this->expectException(\Exception::class);

    $this->setNonPublicPropertyValue('_measures', [
      'default' => [
        'num' => 11,
        'sum' => 10
      ]
    ]);

    $this->timer->stop();
  }

  /** @test */
  public function stop_method_throws_an_exception_when_the_given_timer_does_not_exist()
  {
    $this->expectException(\Exception::class);

    $this->setNonPublicPropertyValue('_measures', [
      'default' => [
        'num' => 1,
        'sum' => 2,
        'start' => time()
      ]
    ]);

    $this->timer->stop('timeout');
  }

  /** @test */
  public function measure_method_measures_the_difference_between_current_time_and_given_key()
  {
    $this->setNonPublicPropertyValue('_measures', [
      'default' => [
        'num' => 1,
        'sum' => 1,
        'start' => $default_start = strtotime('-1 DAY')
      ],
      'timeout' => [
        'num' => 1,
        'sum' => 1,
        'start' => $timeout_start = strtotime('+1 DAY')
      ],
      'users' => [
        'num' => 0,
        'sum' => 0,
        'start' => 0
      ],
      'cron' => [
        'num' => 5,
        'sum' => 3444
      ]
    ]);

    $default_measure = $this->timer->measure();
    $timeout_measure = $this->timer->measure('timeout');

    $this->assertNotSame($default_start, $default_measure);
    $this->assertTrue($default_measure > 0);

    $this->assertNotSame($timeout_start, $timeout_measure);
    $this->assertTrue($timeout_measure < 0);

    $this->assertNull($this->timer->measure('users'));
    $this->assertNull($this->timer->measure('cron'));
    $this->assertNull($this->timer->measure('foo'));
  }

  /** @test */
  public function current_method_adds_the_measure_differences_to_the_given_timer_and_return_it_as_a_new_array()
  {
    $this->setNonPublicPropertyValue('_measures', [
      'default' => [
        'num' => 1,
        'sum' => 1,
        'start' => $default_start = strtotime('-1 DAY')
      ],
      'timeout' => [
        'num' => 0,
        'sum' => 0,
        'start' => 0
      ],
      'users' => [
        'num' => 12,
        'sum' => 1234
      ]
    ]);

    $default_result = $this->timer->current();

    $this->assertArrayHasKey('current', $default_result);
    $this->assertNotSame($default_start, $default_result['current']);
    $this->assertTrue($default_result['current'] > 0);
    $this->assertSame(1, $default_result['sum']);
    $this->assertSame(1, $default_result['num']);
    $this->assertSame($default_start, $default_result['start']);

    $this->assertSame([
      'current' => 0,
      'num' => 0,
      'sum' => 0,
      'start' => 0,
    ], $this->timer->current('timeout'));

    $this->assertSame([
      'current' => 0,
      'num' => 12,
      'sum' => 1234
    ], $this->timer->current('users'));

    $this->assertSame([], $this->timer->current('foo'));
  }

  /** @test */
  public function currents_method_adds_measure_differences_to_all_existing_timers()
  {
    $this->setNonPublicPropertyValue('_measures', [
      'default' => [
        'num' => 1,
        'sum' => 1,
        'start' => $default_start = strtotime('+1 DAY')
      ],
      'timeout' => [
        'num' => 2,
        'sum' => 2,
        'start' => $timeout_start = strtotime('-1 DAY')
      ],
      'users' => [
        'num' => 0,
        'sum' => 0
      ],
      'cron' => [
        'num' => 22,
        'sum' => 123324
      ]
    ]);

    $result = $this->timer->currents();

    $this->assertArrayHasKey('current', $result['default']);
    $this->assertNotSame($default_start, $result['default']['current']);
    $this->assertTrue($result['default']['current'] < 0);
    $this->assertSame(1, $result['default']['num']);
    $this->assertSame(1, $result['default']['sum']);

    $this->assertArrayHasKey('current', $result['timeout']);
    $this->assertNotSame($timeout_start, $result['timeout']['current']);
    $this->assertTrue($result['timeout']['current'] > 0);
    $this->assertSame(2, $result['timeout']['num']);
    $this->assertSame(2, $result['timeout']['sum']);

    $this->assertSame([
      'current' => 0,
      'num' => 0,
      'sum' => 0
    ], $result['users']);

    $this->assertSame([
      'current' => 0,
      'num' => 22,
      'sum' => 123324
    ], $result['cron']);
  }

  /** @test */
  public function result_method_returns_statistics_for_the_given_timer()
  {
    $this->setNonPublicPropertyValue('_measures', [
      'default' => [
        'num' => 1,
        'sum' => 1,
        'start' => strtotime('+1 DAY')
      ],
      'timeout' => [
        'num' => 2,
        'sum' => 2,
        'start' => strtotime('-1 DAY')
      ],
      'users' => [
        'num' => 0,
        'sum' => 0
      ],
      'cron' => [
        'num' => 22,
        'sum' => 123324
      ]
    ]);

    $result = $this->timer->result();

    $this->assertSame(2, $result['num']);
    $this->assertTrue($result['total'] < 0);
    $this->assertTrue($result['average'] < 0);

    $result = $this->timer->result('timeout');

    $this->assertSame(3, $result['num']);
    $this->assertTrue($result['total'] > 0);
    $this->assertTrue($result['average'] > 0);

    $result = $this->timer->result('users');

    $this->assertSame(0, $result['num']);
    $this->assertSame('0.0000000000', $result['total']);
    $this->assertSame('0.0000000000', $result['average']);

    $result = $this->timer->result('cron');

    $this->assertSame(22, $result['num']);
    $this->assertSame('123324.0000000000', $result['total']);
    $this->assertSame(
      number_format(123324 / 22, 10, '.', ''),
      $result['average']
    );
  }

  /** @test */
  public function result_method_returns_statistics_for_every_existing_timer()
  {
    $this->setNonPublicPropertyValue('_measures', [
      'default' => [
        'num' => 1,
        'sum' => 1,
        'start' => strtotime('+1 DAY')
      ],
      'timeout' => [
        'num' => 2,
        'sum' => 2,
        'start' => strtotime('-1 DAY')
      ],
      'users' => [
        'num' => 0,
        'sum' => 0
      ],
      'cron' => [
        'num' => 22,
        'sum' => 123324
      ]
    ]);

    $result = $this->timer->results();

    $this->assertSame(2, $result['default']['num']);
    $this->assertTrue($result['default']['total'] < 0);
    $this->assertTrue($result['default']['average'] < 0);

    $this->assertSame(3, $result['timeout']['num']);
    $this->assertTrue($result['timeout']['total'] > 0);
    $this->assertTrue($result['timeout']['average'] > 0);

    $this->assertSame(0, $result['users']['num']);
    $this->assertSame('0.0000000000', $result['users']['total']);
    $this->assertSame('0.0000000000', $result['users']['average']);

    $this->assertSame(22, $result['cron']['num']);
    $this->assertSame('123324.0000000000', $result['cron']['total']);
    $this->assertSame(
      number_format(123324 / 22, 10, '.', ''),
      $result['cron']['average']
    );
  }

  /** @test */
  public function remove_method_removes_the_given_timer()
  {
    $this->setNonPublicPropertyValue('_measures', $measures = [
      'default' => [
        'num' => 1,
        'sum' => 1,
        'start' => 1234
      ],
      'timeout' => [
        'num' => 0,
        'sum' => 0,
        'start' => 0
      ],
      'users' => [
        'num' => 22,
        'sum' => 444
      ]
    ]);

    $this->assertTrue($this->timer->remove());

    unset($measures['default']);
    $this->assertSame($measures, $this->getNonPublicProperty('_measures'));

    $this->assertTrue($this->timer->remove('timeout'));

    unset($measures['timeout']);
    $this->assertSame($measures, $this->getNonPublicProperty('_measures'));

    $this->assertTrue($this->timer->remove('users'));

    unset($measures['users']);
    $this->assertSame($measures, $this->getNonPublicProperty('_measures'));

    $this->assertFalse($this->timer->remove('foo'));
    $this->assertSame($measures, $this->getNonPublicProperty('_measures'));
  }
}