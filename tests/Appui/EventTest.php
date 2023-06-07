<?php

namespace bbn\tests\Appui;

use bbn\Appui\Event;
use bbn\Appui\Option;
use bbn\Db;
use bbn\User;
use PHPUnit\Framework\TestCase;
use bbn\tests\Reflectable;
use When\When;

class EventTest extends TestCase
{
  use Reflectable;

  protected Event $event;

  protected $db_mock;

  protected $option_mock;

  protected $user_mock;

  protected $id_event = '312a2c70aaaaa2aaa47652540000ffdd';

  protected $id_type = '312a2c70aaaaa2aaa47652540000ffcc';


  protected function init()
  {
    $this->db_mock      = \Mockery::mock(Db::class);
    $this->option_mock  = \Mockery::mock(Option::class);
    $this->user_mock    = \Mockery::mock(User::class);

    $this->setNonPublicPropertyValue('retriever_instance', $this->option_mock, Option::class);
    $this->setNonPublicPropertyValue('retriever_instance', $this->user_mock, User::class);

    $this->event = new Event($this->db_mock);
  }

  protected function setUp(): void
  {
    $this->init();
  }


  protected function tearDown(): void
  {
    \Mockery::close();
  }


  public function getInstance()
  {
    return $this->event;
  }

  protected function getClassCfg()
  {
    return $this->getNonPublicProperty('class_cfg');
  }

  protected function partiallyMockEventClass()
  {
    $cfg    = $this->getClassCfg();
    $fields = $this->getNonPublicProperty('fields');

    $this->event = \Mockery::mock(Event::class)
      ->makePartial()
      ->shouldAllowMockingProtectedMethods();

    $this->setNonPublicPropertyValue('db', $this->db_mock);
    $this->setNonPublicPropertyValue('class_cfg', $cfg);
    $this->setNonPublicPropertyValue('fields', $fields);
    $this->setNonPublicPropertyValue('class_table', $cfg['table']);
  }

  /** @test */
  public function constructor_test()
  {
    $this->assertInstanceOf(Option::class, $this->getNonPublicProperty('opt'));
    $this->assertInstanceOf(User::class, $this->getNonPublicProperty('usr'));
    $this->assertSame(
      $this->getNonPublicProperty('default_class_cfg'),
      $this->getClassCfg()
    );
  }
  
  /** @test */
  public function filterRecurrencesByMonthWeek_method_filters_the_recurrences_of_an_event_by_months_week()
  {
    $method = $this->getNonPublicMethod('filterRecurrencesByMonthWeek');
    $cf     = $this->getClassCfg();

    $data = [
      [
        $cf['arch']['events']['start'] => '2021-07-11',
        $cf['arch']['recurring']['wd'] => 5
      ],
      [
        $cf['arch']['events']['start'] => '2021-07-09',
        $cf['arch']['recurring']['wd'] => 1
      ],
      [
        $cf['arch']['events']['start'] => '2021-07-15',
        $cf['arch']['recurring']['wd'] => 1
      ],
    ];

    $expected = [
      0 => [
        $cf['arch']['events']['start'] => '2021-07-11',
        $cf['arch']['recurring']['wd'] => 5
      ],
      2 => [
        $cf['arch']['events']['start'] => '2021-07-15',
        $cf['arch']['recurring']['wd'] => 1
      ],
    ];

    $this->assertSame($expected, $method->invoke($this->event, $data, 2));

    $data2 = [
      [
        $cf['arch']['events']['start'] => '2021-07-11',
        $cf['arch']['recurring']['wd'] => 5
      ],
      [
        $cf['arch']['events']['start'] => '2021-07-09',
        $cf['arch']['recurring']['wd'] => 1
      ],
      [
        $cf['arch']['events']['start'] => '2021-07-15',
        $cf['arch']['recurring']['wd'] => 1
      ],
    ];

    $expected2 = [
      2 => [
        $cf['arch']['events']['start'] => '2021-07-15',
        $cf['arch']['recurring']['wd'] => 1
      ],
    ];

    $this->assertSame($expected2, $method->invoke($this->event, $data2, -3));
  }

  /** @test */
  public function insert_method_inserts_the_given_data_into_events_table()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['table'],
        [
          $cf['arch']['events']['id_parent']  => '123ab',
          $cf['arch']['events']['id_type']    => $this->id_type,
          $cf['arch']['events']['start']      => '2021-07-01 00:00:00',
          $cf['arch']['events']['end']        => '2021-07-10 00:00:00',
          $cf['arch']['events']['name']       => 'event_name',
          $cf['arch']['events']['recurring']  => 0,
          $cf['arch']['events']['cfg']        => json_encode(['foo' => 'bar']),
        ]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('lastId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_event);

    $data = [
      $cf['arch']['events']['id_parent'] => '123ab',
      $cf['arch']['events']['id_type']   => $this->id_type,
      $cf['arch']['events']['start']     => '2021-07-01 00:00:00',
      $cf['arch']['events']['cfg']       => ['foo' => 'bar'],
      $cf['arch']['events']['end']       => '2021-07-10 00:00:00',
      $cf['arch']['events']['name']      => 'event_name'
    ];

    $this->assertSame($this->id_event, $this->event->insert($data));
  }

  /** @test */
  public function insert_method_inserts_the_given_data_into_events_table_when_recurring_is_provided_and_true()
  {
    $this->partiallyMockEventClass();
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['table'],
        [
          $cf['arch']['events']['id_parent']  => '123ab',
          $cf['arch']['events']['id_type']    => $this->id_type,
          $cf['arch']['events']['start']      => '2021-07-02 00:00:00',
          $cf['arch']['events']['end']        => '2021-07-12 00:00:00',
          $cf['arch']['events']['name']       => 'event_name',
          $cf['arch']['events']['recurring']  => 1,
          $cf['arch']['events']['cfg']        => json_encode(['foo' => 'bar']),
        ]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('lastId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_event);

    $data = [
      $cf['arch']['events']['id_parent'] => '123ab',
      $cf['arch']['events']['id_type']   => $this->id_type,
      $cf['arch']['events']['start']     => '2021-07-01 00:00:00',
      $cf['arch']['events']['cfg']       => ['foo' => 'bar'],
      $cf['arch']['events']['end']       => '2021-07-11 00:00:00',
      $cf['arch']['events']['name']      => 'event_name',
      $cf['arch']['events']['recurring'] => 1,
      $cf['arch']['recurring']['type']   => 'weekly',
    ];

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['tables']['recurring'],
        [
          $cf['arch']['recurring']['id_event']     => $this->id_event,
          $cf['arch']['recurring']['type']         => 'weekly',
          $cf['arch']['recurring']['interval']     => null,
          $cf['arch']['recurring']['occurrences']  => null,
          $cf['arch']['recurring']['until']        => null,
          $cf['arch']['recurring']['wd']           => null,
          $cf['arch']['recurring']['mw']           => null,
          $cf['arch']['recurring']['md']           => null,
          $cf['arch']['recurring']['ym']           => null,

        ]
      )
      ->andReturn();

    $this->event->shouldReceive('getFirstRecurrence')
      ->once()
      ->andReturn('2021-07-02 00:00:00');

    $this->assertSame($this->id_event, $this->event->insert($data));
  }

  /** @test */
  public function insert_method_returns_null_when_inserting_to_events_tables_fails()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['table'],
        [
          $cf['arch']['events']['id_parent']  => '123ab',
          $cf['arch']['events']['id_type']    => $this->id_type,
          $cf['arch']['events']['start']      => '2021-07-01 00:00:00',
          $cf['arch']['events']['end']        => '2021-07-10 00:00:00',
          $cf['arch']['events']['name']       => 'event_name',
          $cf['arch']['events']['recurring']  => 0,
          $cf['arch']['events']['cfg']        => json_encode(['foo' => 'bar']),
        ]
      )
      ->andReturn(0);

    $data = [
      $cf['arch']['events']['id_parent'] => '123ab',
      $cf['arch']['events']['id_type']   => $this->id_type,
      $cf['arch']['events']['start']     => '2021-07-01 00:00:00',
      $cf['arch']['events']['cfg']       => ['foo' => 'bar'],
      $cf['arch']['events']['end']       => '2021-07-10 00:00:00',
      $cf['arch']['events']['name']      => 'event_name'
    ];

    $this->assertNull($this->event->insert($data));
  }

  /** @test */
  public function insert_method_returns_null_when_retrieving_last_inserted_id_fails()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['table'],
        [
          $cf['arch']['events']['id_parent']  => '123ab',
          $cf['arch']['events']['id_type']    => $this->id_type,
          $cf['arch']['events']['start']      => '2021-07-01 00:00:00',
          $cf['arch']['events']['end']        => '2021-07-10 00:00:00',
          $cf['arch']['events']['name']       => 'event_name',
          $cf['arch']['events']['recurring']  => 0,
          $cf['arch']['events']['cfg']        => json_encode(['foo' => 'bar']),
        ]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('lastId')
      ->once()
      ->withNoArgs()
      ->andReturnFalse();

    $data = [
      $cf['arch']['events']['id_parent'] => '123ab',
      $cf['arch']['events']['id_type']   => $this->id_type,
      $cf['arch']['events']['start']     => '2021-07-01 00:00:00',
      $cf['arch']['events']['cfg']       => ['foo' => 'bar'],
      $cf['arch']['events']['end']       => '2021-07-10 00:00:00',
      $cf['arch']['events']['name']      => 'event_name'
    ];

    $this->assertNull($this->event->insert($data));
  }

  /** @test */
  public function insert_method_returns_null_when_the_given_start_field_is_missing()
  {
    $cf = $this->getClassCfg();

    $data = [
      $cf['arch']['events']['id_parent'] => '123ab',
      $cf['arch']['events']['id_type']   => $this->id_type,
      $cf['arch']['events']['cfg']       => ['foo' => 'bar'],
      $cf['arch']['events']['end']       => '2021-07-10 00:00:00',
      $cf['arch']['events']['name']      => 'event_name'
    ];

    $this->assertNull(
      $this->event->insert($data)
    );
  }

  /** @test */
  public function insert_method_returns_null_when_the_given_id_type_is_missing()
  {
    $cf = $this->getClassCfg();

    $data = [
      $cf['arch']['events']['id_parent'] => '123ab',
      $cf['arch']['events']['start']     => '2021-07-01 00:00:00',
      $cf['arch']['events']['cfg']       => ['foo' => 'bar'],
      $cf['arch']['events']['end']       => '2021-07-10 00:00:00',
      $cf['arch']['events']['name']      => 'event_name'
    ];

    $this->assertNull(
      $this->event->insert($data)
    );
  }

  /** @test */
  public function insert_method_returns_null_when_the_given_id_type_is_empty()
  {
    $cf = $this->getClassCfg();

    $data = [
      $cf['arch']['events']['id_parent'] => '123ab',
      $cf['arch']['events']['id_type']   => '',
      $cf['arch']['events']['start']     => '2021-07-01 00:00:00',
      $cf['arch']['events']['cfg']       => ['foo' => 'bar'],
      $cf['arch']['events']['end']       => '2021-07-10 00:00:00',
      $cf['arch']['events']['name']      => 'event_name'
    ];

    $this->assertNull(
      $this->event->insert($data)
    );
  }

  /** @test */
  public function edit_method_updates_an_event_from_the_given_data()
  {
    $this->partiallyMockEventClass();
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $cf['table'],
        [
          $cf['arch']['events']['id_type']    => $this->id_type,
          $cf['arch']['events']['start']      => '2021-07-01 00:00:00',
          $cf['arch']['events']['end']        => '2021-07-10 00:00:00',
          $cf['arch']['events']['name']       => 'event_name',
          $cf['arch']['events']['recurring']  => null,
          $cf['arch']['events']['cfg']        => json_encode(['foo' => 'bar']),
        ],
        [$cf['arch']['events']['id'] => $this->id_event]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['table'],
        $cf['arch']['events']['recurring'],
        [$cf['arch']['events']['id'] => $this->id_event]
      )
      ->andReturn(1);

    $this->event->shouldReceive('deleteRecurrences')
      ->once()
      ->with($this->id_event)
      ->andReturn(1);

    $data = [
      $cf['arch']['events']['id']         => $this->id_event,
      $cf['arch']['events']['cfg']        => ['foo' => 'bar'],
      $cf['arch']['events']['id_type']    => $this->id_type,
      $cf['arch']['events']['start']      => '2021-07-01 00:00:00',
      $cf['arch']['events']['end']        => '2021-07-10 00:00:00',
      $cf['arch']['events']['name']       => 'event_name',
    ];

    $this->assertSame(1, $this->event->edit($this->id_event, $data));
  }

  /** @test */
  public function edit_method_updates_the_given_event_when_recurring_is_provided_and_true()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $cf['table'],
        [
          $cf['arch']['events']['id_type']    => $this->id_type,
          $cf['arch']['events']['start']      => '2021-07-01 00:00:00',
          $cf['arch']['events']['end']        => '2021-07-10 00:00:00',
          $cf['arch']['events']['name']       => 'event_name',
          $cf['arch']['events']['recurring']  => 1,
          $cf['arch']['events']['cfg']        => json_encode(['foo' => 'bar']),
        ],
        [$cf['arch']['events']['id'] => $this->id_event]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['table'],
        $cf['arch']['events']['recurring'],
        [$cf['arch']['events']['id'] => $this->id_event]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('insertUpdate')
      ->once()
      ->with(
        $cf['tables']['recurring'],
        [
          $cf['arch']['recurring']['id_event']     => $this->id_event,
          $cf['arch']['recurring']['type']         => 'weekly',
          $cf['arch']['recurring']['interval']     => null,
          $cf['arch']['recurring']['occurrences']  => null,
          $cf['arch']['recurring']['until']        => null,
          $cf['arch']['recurring']['wd']           => null,
          $cf['arch']['recurring']['mw']           => null,
          $cf['arch']['recurring']['md']           => null,
          $cf['arch']['recurring']['ym']           => null,
        ]
      )
      ->andReturn();

    $data = [
      $cf['arch']['events']['cfg']        => ['foo' => 'bar'],
      $cf['arch']['events']['id_type']    => $this->id_type,
      $cf['arch']['events']['start']      => '2021-07-01 00:00:00',
      $cf['arch']['events']['end']        => '2021-07-10 00:00:00',
      $cf['arch']['events']['name']       => 'event_name',
      $cf['arch']['events']['recurring']  => 1,
      $cf['arch']['recurring']['type']   => 'weekly',
    ];

    $this->assertSame(1, $this->event->edit($this->id_event, $data));
  }

  /** @test */
  public function edit_method_returns_null_when_the_given_event_id_is_not_valid()
  {
    $this->assertNull(
      $this->event->edit('1234aff', [])
    );
  }

  /** @test */
  public function delete_method_deletes_the_given_event()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['events']['id'] => $this->id_event]
      )
      ->andReturn(1);

    $this->assertTrue(
      $this->event->delete($this->id_event)
    );
  }

  /** @test */
  public function delete_method_returns_false_when_the_given_id_is_not_valid()
  {
    $this->assertFalse(
      $this->event->delete('123aff')
    );
  }

  /** @test */
  public function get_method_fetches_the_event_from_the_given_id()
  {
    $cf     = $this->getClassCfg();
    $fields = $this->getNonPublicProperty('fields');

    $query_fields = [];

    foreach ($fields as $field) {
      $this->db_mock->shouldReceive('colFullName')
        ->once()
        ->with($field, $cf['table'])
        ->andReturn($f = "{$cf['table']}.$field");
      $query_fields[$field] = $f;
    }

    $this->db_mock->shouldReceive('colFullName')
      ->once()
      ->with(
        $id_option_col = $cf['arch']['options']['id'],
        $options_table = $cf['tables']['options']
      )
      ->andReturn($id_option_full_name = "$options_table.$id_option_col");

    $this->db_mock->shouldReceive('colFullName')
      ->once()
      ->with(
        $id_events_col = $cf['arch']['events']['id'],
        $events_table = $cf['tables']['events']
      )
      ->andREturn($id_events_full_name = "$events_table.$id_events_col");

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with([
        'table'   => $cf['table'],
        'fields'  => $query_fields,
        'join'    => [[
          'table'  => $cf['tables']['options'],
          'on'     => [
            'conditions' => [[
              'field' => $id_option_full_name,
              'exp'   => $fields['id_type']
            ]]
          ]
        ]],
        'where' => [
          $id_events_full_name => $this->id_event
        ]
      ])
      ->andReturn($expected = [
        $cf['arch']['events']['id'] => $this->id_event
      ]);

    $this->assertSame($expected, $this->event->get($this->id_event));
  }

  /** @test */
  public function get_method_returns_null_when_the_given_id_is_not_valid()
  {
    $this->assertNull(
      $this->event->get('1123aff')
    );
  }

  /** @test */
  public function getFull_method_get_an_event_with_the_recurring_details()
  {
    $cf = $this->getClassCfg();

    $events_arch    = $cf['arch']['events'];
    $recurring_arch = $cf['arch']['recurring'];

    $this->db_mock->shouldReceive('colFullName')
      ->times(3)
      ->with(
        $events_arch['id'],
        $cf['table']
      )
      ->andReturn(
        $id_event_full_name = "{$cf['table']}.{$events_arch['id']}"
      );

    $this->db_mock->shouldReceive('colFullName')
      ->once()
      ->with(
        $events_arch['id_parent'],
        $cf['table']
      )
      ->andReturn(
        $id_parent_full_name = "{$cf['table']}.{$events_arch['id_parent']}"
      );

    $this->db_mock->shouldReceive('colFullName')
      ->once()
      ->with(
        $recurring_arch['id_event'],
        $recurring_table = $cf['tables']['recurring']
      )
      ->andReturn(
        $id_event_recurring_full_name = "$recurring_table.{$recurring_arch['id_event']}"
      );

    $this->db_mock->shouldReceive('colFullName')
      ->once()
      ->with(
        $cf['arch']['options']['id'],
        $options_table = $cf['tables']['options']
      )
      ->andReturn(
        $id_option_full_name = "{$cf['arch']['options']['id']}.{$recurring_arch['id_event']}"
      );

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with([
        'table' => $cf['table'],
        'fields' => [
          $id_event_full_name,
          $id_parent_full_name,
          $events_arch['id_type'],
          $events_arch['start'],
          $events_arch['end'],
          $events_arch['name'],
          $events_arch['recurring'],
          $events_arch['cfg'],
          $recurring_arch['type'],
          $recurring_arch['interval'],
          $recurring_arch['occurrences'],
          $recurring_arch['until'],
          $recurring_arch['wd'],
          $recurring_arch['mw'],
          $recurring_arch['md'],
          $recurring_arch['ym']
        ],
        'join' => [[
          'table' => $recurring_table,
          'type' => 'left',
          'on' => [
            'conditions' => [[
              'field' => $id_event_full_name,
              'exp' => $id_event_recurring_full_name,
            ]]
          ]
        ], [
          'table' => $options_table,
          'on' => [
            'conditions' => [[
              'field' => $events_arch['id_type'],
              'exp' => $id_option_full_name,
            ]]
          ]
        ]],
        'where' => [
          $id_event_full_name => $this->id_event
        ]
      ])
      ->andReturn($expected = [
        $cf['arch']['events']['id'] => $this->id_event
      ]);

    $this->assertSame($expected, $this->event->getFull($this->id_event));
  }

  /** @test */
  public function getFull_method_returns_null_when_the_given_id_is_not_valid()
  {
    $this->assertNull(
      $this->event->getFull('1234aaff')
    );
  }
  
  /** @test */
  public function getRecurrences_method_returns_an_array_of_all_events_recurrences_in_a_period()
  {
    $this->partiallyMockEventClass();

    $cf = $this->getClassCfg();

    $event = [
      $cf['arch']['events']['start'] => '2021-07-11',
      $cf['arch']['recurring']['wd'] => 5
    ];

    $start = '2021-07-01';
    $end   = '2021-07-10';

    $this->event->shouldReceive('getWhenObject')
      ->once()
      ->with($event)
      ->andReturn(
        $when_mock = \Mockery::mock(When::class)
      );

    $when_mock->shouldReceive('getOccurrencesBetween')
      ->once()
      ->andReturn([
        'start' => '2021-07-02',
        'end'   => '2021-07-11'
      ]);

    $this->event->shouldReceive('makeRecurrencesFields')
      ->once()
      ->with($event, [
        'start' => '2021-07-02',
        'end'   => '2021-07-11'
      ])
      ->andREturn([
        'start' => '2021-07-03',
        'end'   => '2021-07-12'
      ]);

    $this->event->shouldReceive('filterRecurrencesByExceptions')
      ->once()
      ->with([
        'start' => '2021-07-03',
        'end'   => '2021-07-12'
      ])
      ->andREturn($expected = [
        'start' => '2021-07-04',
        'end'   => '2021-07-13'
      ]);

    $this->assertSame(
      $expected,
      $this->event->getRecurrences($start, $end, $event)
    );
  }

  /** @test */
  public function getRecurrences_method_returns_an_array_of_all_events_recurrences_in_a_period_when_mw_is_provided()
  {
    $this->partiallyMockEventClass();

    $cf = $this->getClassCfg();

    $event = [
      $cf['arch']['events']['start'] => '2021-07-11',
      $cf['arch']['recurring']['mw'] => 5,
    ];

    $start = '2021-07-01';
    $end   = '2021-07-10';

    $this->event->shouldReceive('getWhenObject')
      ->once()
      ->with($event)
      ->andReturn(
        $when_mock = \Mockery::mock(When::class)
      );

    $when_mock->shouldReceive('getOccurrencesBetween')
      ->once()
      ->andReturn([
        'start' => '2021-07-02',
        'end'   => '2021-07-11'
      ]);

    $this->event->shouldReceive('makeRecurrencesFields')
      ->once()
      ->with($event, [
        'start' => '2021-07-02',
        'end'   => '2021-07-11'
      ])
      ->andREturn([
        'start' => '2021-07-03',
        'end'   => '2021-07-12'
      ]);

    $this->event->shouldReceive('filterRecurrencesByMonthWeek')
      ->once()
      ->with([
        'start' => '2021-07-03',
        'end'   => '2021-07-12'
      ], 5)
      ->andReturn([
        'start' => '2021-07-04',
        'end'   => '2021-07-13'
      ]);

    $this->event->shouldReceive('filterRecurrencesByExceptions')
      ->once()
      ->with([
        'start' => '2021-07-04',
        'end'   => '2021-07-13'
      ])
      ->andREturn($expected = [
        'start' => '2021-07-05',
        'end'   => '2021-07-14'
      ]);

    $this->assertSame(
      $expected,
      $this->event->getRecurrences($start, $end, $event)
    );
  }

  /** @test */
  public function makeRecurrencesFields_method_makes_fields_structure_on_the_given_event_recurrences()
  {
    $cf = $this->getClassCfg();

    $start_field = $cf['arch']['events']['start'];
    $end_field   = $cf['arch']['events']['end'];

    $event = [
      $start_field => '2021-07-13 10:00:00',
      $end_field   => '2021-07-20 10:00:00',
    ];

    $data = [
      '2021-07-16 10:00:00',
      '2021-07-09 10:00:00',
      new \DateTime('2021-07-16 10:00:00'),
      '2021-07-13 10:00:00'
    ];

    $expected = [
      [
        $start_field => '2021-07-16 10:00:00',
        $end_field   => '2021-07-23 10:00:00',
        'recurrence' => 1
      ],
      [
        $start_field => '2021-07-09 10:00:00',
        $end_field   => '2021-07-16 10:00:00',
        'recurrence' => 1
      ],
      [
        $start_field => '2021-07-16 10:00:00',
        $end_field   => '2021-07-23 10:00:00',
        'recurrence' => 1
      ],
      [
        $start_field => '2021-07-13 10:00:00',
        $end_field   => '2021-07-20 10:00:00',
        'recurrence' => 0
      ],
    ];

    $this->assertSame($expected, $this->event->makeRecurrencesFields($event, $data));

    $event2 = [
      $start_field => '2021-07-13 10:00:00'
      ];

    $data2 = [
      '2021-07-16 10:00:00',
      '2021-07-09 10:00:00',
      new \DateTime('2021-07-16 10:00:00'),
      '2021-07-13 10:00:00'
    ];

    $expected2 = [
      [
        $start_field => '2021-07-16 10:00:00',
        $end_field   => null,
        'recurrence' => 1
      ],
      [
        $start_field => '2021-07-09 10:00:00',
        $end_field   => null,
        'recurrence' => 1
      ],
      [
        $start_field => '2021-07-16 10:00:00',
        $end_field   => null,
        'recurrence' => 1
      ],
      [
        $start_field => '2021-07-13 10:00:00',
        $end_field   => null,
        'recurrence' => 0
      ],
    ];

    $this->assertSame($expected2, $this->event->makeRecurrencesFields($event2, $data2));
  }
  
  /** @test */
  public function getFirstRecurrence_method_returns_the_date_for_the_first_recurrence_of_a_recurring_date()
  {
    $cf = $this->getClassCfg();
    $this->partiallyMockEventClass();

    $this->event->shouldReceive('getExceptions')
      ->once()
      ->with($this->id_event)
      ->andReturn([
        [
          $cf['arch']['exceptions']['day']   => '2021-07-11',
          $cf['arch']['exceptions']['start'] => '07:00:00',
        ],
        [
          $cf['arch']['exceptions']['day']   => '2021-07-09',
          $cf['arch']['exceptions']['start'] => '04:00:00',
        ]
      ]);

    $event = [
      $cf['arch']['events']['id']    => $this->id_event,
      $cf['arch']['events']['start'] => '2021-07-12 09:00:00',
      $cf['arch']['events']['end']   => '2021-07-19 07:00:00'
    ];

    $this->event->shouldReceive('getWhenObject')
      ->once()
      ->with(array_merge($event, [
        $cf['extra']['exceptions'] => [
          '2021-07-11 07:00:00', '2021-07-09 04:00:00'
        ]
      ]))
      ->andReturn($when_mock = \Mockery::mock(When::class));

    $when_mock->startDate = '2021-07-10 11:00:00';

    $when_mock->shouldReceive('getNextOccurrence')
      ->once()
      ->with($when_mock->startDate, true)
      ->andReturn($datetime = new \DateTime('2021-07-20 01:00:00'));

    $this->assertSame(
      $datetime->format('Y-m-d H:i:s'),
      $this->event->getFirstRecurrence($event, true, true)
    );
  }

  /** @test */
  public function getFirstRecurrence_method_returns_the_date_for_the_first_recurrence_of_a_recurring_date_when_getExceptions_returns_null()
  {
    $cf = $this->getClassCfg();
    $this->partiallyMockEventClass();

    $event = [
      $cf['arch']['events']['id']    => $this->id_event,
      $cf['arch']['events']['start'] => '2021-07-12 09:00:00',
      $cf['arch']['events']['end']   => '2021-07-19 07:00:00'
    ];

    $this->event->shouldReceive('getExceptions')
      ->once()
      ->with($this->id_event)
      ->andReturnNull();

    $this->event->shouldReceive('getWhenObject')
      ->once()
      ->with(array_merge($event))
      ->andReturn($when_mock = \Mockery::mock(When::class));

    $when_mock->startDate = '2021-07-10 11:00:00';

    $when_mock->shouldReceive('getNextOccurrence')
      ->once()
      ->with($when_mock->startDate, true)
      ->andReturn($datetime = new \DateTime('2021-07-20 01:00:00'));

    $this->assertSame(
      $datetime->format('Y-m-d H:i:s'),
      $this->event->getFirstRecurrence($event, true, true)
    );
  }

  /** @test */
  public function getFirstRecurrence_method_returns_the_date_for_the_first_recurrence_of_a_recurring_date_when_exceptions_is_false()
  {
    $cf = $this->getClassCfg();
    $this->partiallyMockEventClass();

    $event = [
      $cf['arch']['events']['id']    => $this->id_event,
      $cf['arch']['events']['start'] => '2021-07-12 09:00:00',
      $cf['arch']['events']['end']   => '2021-07-19 07:00:00'
    ];

    $this->event->shouldReceive('getWhenObject')
      ->once()
      ->with(array_merge($event))
      ->andReturn($when_mock = \Mockery::mock(When::class));

    $when_mock->startDate = '2021-07-10 11:00:00';

    $when_mock->shouldReceive('getNextOccurrence')
      ->once()
      ->with($when_mock->startDate, true)
      ->andReturn($datetime = new \DateTime('2021-07-20 01:00:00'));

    $this->assertSame(
      $datetime->format('Y-m-d H:i:s'),
      $this->event->getFirstRecurrence($event, true, false)
    );
  }

  /** @test */
  public function getFirstRecurrence_method_returns_null_when_getNextOccurrence_retunrs_false()
  {
    $cf = $this->getClassCfg();
    $this->partiallyMockEventClass();

    $event = [
      $cf['arch']['events']['id']    => $this->id_event,
      $cf['arch']['events']['start'] => '2021-07-12 09:00:00',
      $cf['arch']['events']['end']   => '2021-07-19 07:00:00'
    ];

    $this->event->shouldReceive('getWhenObject')
      ->once()
      ->with(array_merge($event))
      ->andReturn($when_mock = \Mockery::mock(When::class));

    $when_mock->startDate = '2021-07-10 11:00:00';

    $when_mock->shouldReceive('getNextOccurrence')
      ->once()
      ->with($when_mock->startDate, true)
      ->andReturnFalse();

    $this->assertNull(
      $this->event->getFirstRecurrence($event)
    );
  }

  /** @test */
  public function deleteRecurrences_method_deletes_the_recurrences_of_the_given_event_and_returns_true_when_count_equals_the_deleted()
  {
    $cf = $this->getClassCfg();

    $query_args = [
      $cf['tables']['recurring'],
      [$cf['arch']['recurring']['id_event'] => $this->id_event]
    ];

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(...$query_args)
      ->andReturn(1);


    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(...$query_args)
      ->andReturn(1);

    $this->assertTrue(
      $this->event->deleteRecurrences($this->id_event)
    );
  }

  /** @test */
  public function deleteRecurrences_method_deletes_the_recurrences_of_the_given_event_and_returns_false_when_count_not_equal_the_deleted()
  {
    $cf = $this->getClassCfg();

    $query_args = [
      $cf['tables']['recurring'],
      [$cf['arch']['recurring']['id_event'] => $this->id_event]
    ];

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(...$query_args)
      ->andReturn(3);

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(...$query_args)
      ->andReturn(1);

    $this->assertFalse(
      $this->event->deleteRecurrences($this->id_event)
    );
  }

  /** @test */
  public function deleteRecurrences_method_returns_false_when_the_given_id_event_is_not_valid()
  {
    $this->assertFalse(
      $this->event->deleteRecurrences('123aff')
    );
  }
}