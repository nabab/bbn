<?php

namespace Appui;

use bbn\Appui\Cms;
use bbn\Appui\Medias;
use bbn\Appui\Note;
use bbn\Appui\Option;
use bbn\Db;
use bbn\User;
use PHPUnit\Framework\TestCase;
use bbn\tests\Files;
use bbn\tests\Reflectable;

class NoteTest extends TestCase
{
  use Reflectable, Files;

  protected Note $note;

  protected $db_mock;

  protected $option_mock;

  protected $type = '634a2c70aaaaa2aaa47652540000cfaa';

  protected $id_note = '634a2c70aaaaa2aaa47652540000bbcf';

  protected $id_note_2 = '555a2c70aaaaa2aaa47652540000ffaa';

  protected $id_media = '222a2c70aaaaa2aaa476525400002222';

  protected $id_user = 'aaaa2c70aaaaa2aaa47652540000aaaa';

  protected $id_event = 'c4c2c70aaaaa2aaa47652540000aaff';

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
    return $this->note;
  }

  protected function init()
  {
    $this->db_mock     = \Mockery::mock(Db::class);
    $this->option_mock = \Mockery::mock(Option::class);

    $this->setNonPublicPropertyValue('option_appui_id', null, Note::class);

    $this->setNonPublicPropertyValue('retriever_instance', $this->option_mock, Option::class);
    $this->setNonPublicPropertyValue('retriever_exists', true, Option::class);

    $this->setNonPublicPropertyValue('retriever_instance', null, User::class);
    $this->setNonPublicPropertyValue('retriever_exists', true, User::class);

    if (!\defined("BBN_APPUI")) {
      $this->option_mock->shouldReceive('fromCode')
        ->andReturn(1);
    }

    if (!\defined("BBN_APPUI_ROOT")) {
      $this->option_mock->shouldReceive('fromRootCode')
        ->once()
        ->with('appui')
        ->andReturn(11);
    }

    $this->note = new Note($this->db_mock);
  }

  protected function getClassCfg()
  {
    return $this->getNonPublicProperty('class_cfg');
  }

  protected function initAndMockUserClass()
  {
    $user_mock = \Mockery::mock(User::class);
    $this->setNonPublicPropertyValue('retriever_instance', $user_mock, User::class);

    return $user_mock;
  }

  protected function partiallyMockNoteClass()
  {
    $cfg = $this->getClassCfg();
    $this->note = \Mockery::mock(Note::class)->makePartial();

    $this->setNonPublicPropertyValue('db', $this->db_mock);
    $this->setNonPublicPropertyValue('class_cfg', $cfg);
  }

  protected function mockAndReplaceMediaInstance()
  {
    $this->setNonPublicPropertyValue('medias', $mock = \Mockery::mock(Medias::class));

    return $mock;
  }

  /**
   * Helper function to set expectations for queries used in getByType() method
   *
   * @param false $id_user
   * @param int $limit
   * @param int $start
   * @return array
   */
  protected function getByTypeMethodQueryArguments($id_user = false, $limit = 0, $start = 0)
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('cfn')
      ->once()
      ->with($cf['arch']['notes']['id_type'], $cf['table'])
      ->andReturn($id_type_field = "{$cf['table']}.{$cf['arch']['notes']['id_type']}");

    $this->db_mock->shouldReceive('cfn')
      ->once()
      ->with($cf['arch']['notes']['active'], $cf['table'])
      ->andReturn($active_field = "{$cf['table']}.{$cf['arch']['notes']['active']}");

    $this->db_mock->shouldReceive('cfn')
      ->times(3)
      ->with($cf['arch']['notes']['id'], $cf['table'])
      ->andReturn($id_field = "{$cf['table']}.{$cf['arch']['notes']['id']}");

    $where = [[
      'field' => $id_type_field,
      'value' => $this->type,
    ], [
      'field' => $active_field,
      'value' => 1,
    ], [
      'field' => 'versions2.'.$cf['arch']['versions']['version'],
      'operator' => 'isnull',
    ]];

    if ($id_user) {
      $this->db_mock->shouldReceive('cfn')
        ->once()
        ->with($cf['arch']['notes']['creator'], $cf['table'])
        ->andReturn($creator_field = "{$cf['table']}.{$cf['arch']['notes']['creator']}");

      $where[] = [
        'field' => $creator_field,
        'value' => $id_user,
      ];
    }

    return [
      'table' => $cf['table'],
      'fields' => [
        'versions1.'.$cf['arch']['versions']['id_note'],
        'versions1.'.$cf['arch']['versions']['version'],
        'versions1.'.$cf['arch']['versions']['title'],
        'versions1.'.$cf['arch']['versions']['content'],
        'versions1.'.$cf['arch']['versions']['id_user'],
        'versions1.'.$cf['arch']['versions']['creation'],
      ],
      'join' => [[
        'table' => $cf['tables']['versions'],
        'type' => 'left',
        'alias' => 'versions1',
        'on' => [
          'conditions' => [[
            'field' => $id_field,
            'exp' => 'versions1.'.$cf['arch']['versions']['id_note'],
          ]],
        ],
      ], [
        'table' => $cf['tables']['versions'],
        'type' => 'left',
        'alias' => 'versions2',
        'on' => [
          'conditions' => [[
            'field' => $id_field,
            'exp' => 'versions2.'.$cf['arch']['versions']['id_note'],
          ], [
            'field' => 'versions1.'.$cf['arch']['versions']['version'],
            'operator' => '<',
            'exp' => 'versions2.'.$cf['arch']['versions']['version'],
          ]],
        ],
      ]],
      'where' => [
        'conditions' => $where,
      ],
      'group_by' => $id_field,
      'order' => [[
        'field' => 'versions1.'.$cf['arch']['versions']['version'],
        'dir' => 'DESC',
      ], [
        'field' => 'versions1.'.$cf['arch']['versions']['creation'],
        'dir' => 'DESC',
      ]],
      'limit' => $limit,
      'start' => $start,
    ];
  }

  /** @test */
  public function constructor_test()
  {
    $this->assertSame(
      $this->getNonPublicProperty('default_class_cfg'),
      $this->getNonPublicProperty('class_cfg')
    );

    $this->assertSame('1', $this->getNonPublicProperty('option_appui_id'));
    $this->assertSame('1', BBN_APPUI);
    $this->assertSame('11', BBN_APPUI_ROOT);
  }

  /** @test */
  public function getMediaInstance_method_initialize_and_returns_media_instance_if_not_already_initialized()
  {
    $this->option_mock->shouldReceive('fromRootCode')
      ->once()
      ->with('media', 'note', 'appui')
      ->andReturn(2);

   $this->assertInstanceOf(Medias::class, $this->note->getMediaInstance());
  }

  /** @test */
  public function getMediaInstance_method_returns_media_instance_when_already_initialized()
  {
    $this->mockAndReplaceMediaInstance();

    $this->assertInstanceOf(Medias::class, $this->note->getMediaInstance());
  }

  /** @test */
  public function getExcerpt_method_returns_excerpt_from_the_given_title_and_content()
  {
    $expected = <<<OUTPUT
foo

bar
baz
OUTPUT;
    $this->assertSame($expected, $this->note->getExcerpt('foo', 'bar<br>baz'));

    $expected = <<<OUTPUT
foobar
OUTPUT;
    $this->assertSame($expected, $this->note->getExcerpt('', 'foobar'));

    $expected = <<<OUTPUT
foo


OUTPUT;
    $this->assertSame($expected, $this->note->getExcerpt('foo', ''));

    $this->assertSame('', $this->note->getExcerpt('', ''));

    $expected = <<<OUTPUT
foo

a

c

d


OUTPUT;


    $this->assertSame($expected, $this->note->getExcerpt('foo', json_encode(['a', 'b' => ['c', 'd']])));
  }

  /** @test */
  public function insert_method_inserts_the_given_data_in_to_db_when_only_title_and_content_provided()
  {
    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->andReturn(1);

    $user_mock = \Mockery::mock(User::class);
    $this->setNonPublicPropertyValue('retriever_instance', $user_mock, User::class);

    $user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn('123');

    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['table'],
        [
          $cf['arch']['notes']['id_parent'] => null,
          $cf['arch']['notes']['id_alias'] => null,
          $cf['arch']['notes']['id_type'] => 1,
          $cf['arch']['notes']['excerpt'] => $this->note->getExcerpt('title', 'content'),
          $cf['arch']['notes']['private'] => 0,
          $cf['arch']['notes']['locked'] => 0,
          $cf['arch']['notes']['creator'] => '123',
          $cf['arch']['notes']['mime'] => '',
          $cf['arch']['notes']['lang'] => ''
        ]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('lastId')
      ->once()
      ->withNoArgs()
      ->andReturn('123');

    // Partially mock the note class to mock the insertVersion method
    // Since it uses a lot of db calls and it will be tested in isolation
    $this->partiallyMockNoteClass();


    $this->note->shouldReceive('insertVersion')
      ->once()
      ->with('123', 'title', 'content')
      ->andReturn(1);

    $result = $this->note->insert('title', 'content');

    $this->assertSame('123', $result);
  }

  /** @test */
  public function insert_method_inserts_the_given_data_in_to_db_when_arguments_are_given_as_an_array()
  {
    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->andReturn(1);

    $user_mock = \Mockery::mock(User::class);
    $this->setNonPublicPropertyValue('retriever_instance', $user_mock, User::class);

    $user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn('123');

    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['table'],
        [
          $cf['arch']['notes']['id_parent'] => null,
          $cf['arch']['notes']['id_alias'] => null,
          $cf['arch']['notes']['id_type'] => 1,
          $cf['arch']['notes']['excerpt'] => $this->note->getExcerpt('title', 'content'),
          $cf['arch']['notes']['private'] => 0,
          $cf['arch']['notes']['locked'] => 0,
          $cf['arch']['notes']['creator'] => '123',
          $cf['arch']['notes']['mime'] => '',
          $cf['arch']['notes']['lang'] => ''
        ]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('lastId')
      ->once()
      ->withNoArgs()
      ->andReturn('123');

    // Partially mock the note class to mock the insertVersion method
    // Since it uses a lot of db calls and it will be tested in isolation
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('insertVersion')
      ->once()
      ->with('123', 'title', 'content')
      ->andReturn(1);

    $result = $this->note->insert([
      'title'  => 'title',
      'content' => 'content'
    ]);

    $this->assertSame('123', $result);
  }

  /** @test */
  public function insert_method_returns_null_when_content_is_not_provided()
  {
    $this->assertNull(
      $this->note->insert('title', '')
    );

    $this->assertNull(
      $this->note->insert(['title' => 'title'])
    );

    $this->assertNull(
      $this->note->insert(['title' => 'title', 'content' => ''])
    );
  }

  /** @test */
  public function insert_method_returns_false_when_failed_to_insert_in_db()
  {
    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->andReturn(1);

    $user_mock = $this->initAndMockUserClass();

    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['table'],
        [
          $cf['arch']['notes']['id_parent'] => null,
          $cf['arch']['notes']['id_alias'] => null,
          $cf['arch']['notes']['id_type'] => 1,
          $cf['arch']['notes']['excerpt'] => $this->note->getExcerpt('title', 'content'),
          $cf['arch']['notes']['private'] => 0,
          $cf['arch']['notes']['locked'] => 0,
          $cf['arch']['notes']['creator'] => '123',
          $cf['arch']['notes']['mime'] => '',
          $cf['arch']['notes']['lang'] => ''
        ]
      )
      ->andReturnNull();

    $user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn('123');


    $result = $this->note->insert('title', 'content');

    $this->assertFalse($result);
  }

  /** @test */
  public function insert_method_returns_false_when_user_instance_cannot_be_retrieved()
  {
    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->andReturn(1);

    $result = $this->note->insert('title', 'content');

    $this->assertFalse($result);
  }

  /** @test */
  public function insert_method_returns_false_when_last_id_cannot_be_retrieved_from_db()
  {
    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->andReturn(1);

    $user_mock = $this->initAndMockUserClass();

    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['table'],
        [
          $cf['arch']['notes']['id_parent'] => null,
          $cf['arch']['notes']['id_alias'] => null,
          $cf['arch']['notes']['id_type'] => 1,
          $cf['arch']['notes']['excerpt'] => $this->note->getExcerpt('title', 'content'),
          $cf['arch']['notes']['private'] => 0,
          $cf['arch']['notes']['locked'] => 0,
          $cf['arch']['notes']['creator'] => '123',
          $cf['arch']['notes']['mime'] => '',
          $cf['arch']['notes']['lang'] => ''
        ]
      )
      ->andReturn(1);

    $user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn('123');

    $this->db_mock->shouldReceive('lastId')
      ->once()
      ->andReturnFalse();


    $result = $this->note->insert('title', 'content');

    $this->assertFalse($result);
  }
  
  /** @test */
  public function insertVersion_method_adds_a_new_version_to_the_given_note_if_content_is_different()
  {
    $user_mock = $this->initAndMockUserClass();
    $cf        = $this->getClassCfg();

    // Partially mock the note class to mock the get method
    // Since it uses a lot of db calls and it will be tested in isolation
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('get')
      ->once()
      ->with('123')
      ->andReturn([
        'version' => $version = 2,
        'content' => 'old_content',
        'title'   => 'title'
      ]);

    $user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($id_user = '111');

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['tables']['versions'],
        [
          $cf['arch']['versions']['id_note'] => '123',
          $cf['arch']['versions']['version'] => $version + 1,
          $cf['arch']['versions']['title'] => 'title',
          $cf['arch']['versions']['content'] => 'new_content',
          $cf['arch']['versions']['id_user'] => $id_user,
          $cf['arch']['versions']['creation'] => date('Y-m-d H:i:s')
        ]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['excerpt'] => $this->note->getExcerpt('title', 'new_content')],
        [$cf['arch']['notes']['id'] => '123']
      )
      ->andReturn(1);

    $result = $this->note->insertVersion('123', 'title', 'new_content');

    $this->assertSame($version + 1, $result);
  }

  /** @test */
  public function insertVersion_method_adds_a_new_version_to_the_given_note_if_title_is_different()
  {
    $user_mock = $this->initAndMockUserClass();
    $cf        = $this->getClassCfg();

    // Partially mock the note class to mock the get method
    // Since it uses a lot of db calls and it will be tested in isolation
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('get')
      ->once()
      ->with('123')
      ->andReturn([
        'version' => $version = 4,
        'content' => 'content',
        'title'   => 'old_title'
      ]);

    $user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($id_user = '111');

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['tables']['versions'],
        [
          $cf['arch']['versions']['id_note'] => '123',
          $cf['arch']['versions']['version'] => $version + 1,
          $cf['arch']['versions']['title'] => 'new_title',
          $cf['arch']['versions']['content'] => 'content',
          $cf['arch']['versions']['id_user'] => $id_user,
          $cf['arch']['versions']['creation'] => date('Y-m-d H:i:s')
        ]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['excerpt'] => $this->note->getExcerpt('new_title', 'content')],
        [$cf['arch']['notes']['id'] => '123']
      )
      ->andReturn(1);

    $result = $this->note->insertVersion('123', 'new_title', 'content');

    $this->assertSame($version + 1, $result);
  }

  /** @test */
  public function insertVersion_method_adds_a_new_version_to_the_given_note_if_version_not_found()
  {
    $user_mock = $this->initAndMockUserClass();
    $cf        = $this->getClassCfg();

    // Partially mock the note class to mock the get method
    // Since it uses a lot of db calls and it will be tested in isolation
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('get')
      ->once()
      ->with('123')
      ->andReturn([
        'content' => 'content',
        'title'   => 'title'
      ]);

    $user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($id_user = '111');

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['tables']['versions'],
        [
          $cf['arch']['versions']['id_note'] => '123',
          $cf['arch']['versions']['version'] => 1,
          $cf['arch']['versions']['title'] => 'title',
          $cf['arch']['versions']['content'] => 'content',
          $cf['arch']['versions']['id_user'] => $id_user,
          $cf['arch']['versions']['creation'] => date('Y-m-d H:i:s')
        ]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['excerpt'] => $this->note->getExcerpt('title', 'content')],
        [$cf['arch']['notes']['id'] => '123']
      )
      ->andReturn(1);

    $result = $this->note->insertVersion('123', 'title', 'content');

    $this->assertSame(1, $result);
  }

  /** @test */
  public function insertVersion_method_does_not_add_a_new_version_to_the_given_note_if_there_is_a_version_with_same_title_and_content()
  {
    $this->initAndMockUserClass();

    // Partially mock the note class to mock the get method
    // Since it uses a lot of db calls and it will be tested in isolation
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('get')
      ->once()
      ->with('123')
      ->andReturn([
        'content' => 'content',
        'title'   => 'title',
        'version' => $version = 1
      ]);


    $result = $this->note->insertVersion('123', 'title', 'content');

    $this->assertSame($version, $result);
  }

  /** @test */
  public function insertVersion_method_does_not_add_a_new_version_to_the_given_note_if_insert_new_version_failed()
  {
    $user_mock = $this->initAndMockUserClass();
    $cf        = $this->getClassCfg();

    // Partially mock the note class to mock the get method
    // Since it uses a lot of db calls and it will be tested in isolation
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('get')
      ->once()
      ->with('123')
      ->andReturn([
        'content' => 'content',
        'title'   => 'title',
        'version' => $version = 8
      ]);

    $user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($id_user = '111');

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['tables']['versions'],
        [
          $cf['arch']['versions']['id_note'] => '123',
          $cf['arch']['versions']['version'] => $version + 1,
          $cf['arch']['versions']['title'] => 'new_title',
          $cf['arch']['versions']['content'] => 'content',
          $cf['arch']['versions']['id_user'] => $id_user,
          $cf['arch']['versions']['creation'] => date('Y-m-d H:i:s')
        ]
      )
      ->andReturnNull();


    $result = $this->note->insertVersion('123', 'new_title', 'content');

    $this->assertSame($version, $result);
  }

  /** @test */
  public function insertVersion_method_returns_null_when_there_is_an_error()
  {
    $this->setNonPublicPropertyValue('error', true);

    $this->assertNull(
      $this->note->insertVersion('123', 'title', 'content')
    );
  }

  /** @test */
  public function insertVersion_method_returns_null_when_user_instance_cannot_be_retrieved()
  {
    $this->assertNull(
      $this->note->insertVersion('123', 'title', 'content')
    );
  }

  /** @test */
  public function insertVersion_method_returns_null_when_note_does_not_exist()
  {
    $this->initAndMockUserClass();
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('get')
      ->once()
      ->with('123')
      ->andReturnNull();

    $this->assertNull(
      $this->note->insertVersion('123', 'title', 'content')
    );
  }

  /** @test */
  public function update_method_updates_private_and_lock_fields_in_db_if_changed_and_calls_the_insertVersion_method_if_old_version_exists()
  {
    $this->partiallyMockNoteClass();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with('bbn_notes', [], ['id' => '123'])
      ->andReturn([
        'private' => true,
        'locked' => true
      ]);

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        'bbn_notes',
        ['private' => false, 'locked' => false],
        ['id' => '123']
      )
      ->andReturn(1);

    $this->note->shouldReceive('get')
      ->once()
      ->with('123')
      ->andReturn([
        'title' => 'old_title',
        'content' => 'old_content'
      ]);

    $this->note->shouldReceive('insertVersion')
      ->once()
      ->with('123', 'new_title', 'new_content')
      ->andReturn($version = 33);

    $result = $this->note->update('123', 'new_title', 'new_content', false, false);

    $this->assertSame($version, $result);
  }

  /** @test */
  public function update_method_updates_private_and_lock_fields_in_db_if_changed_and_does_not_call_the_insertVersion_method_if_no_old_version_exists()
  {
    $this->partiallyMockNoteClass();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with('bbn_notes', [], ['id' => '123'])
      ->andReturn([
        'private' => true,
        'locked' => true
      ]);

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        'bbn_notes',
        ['private' => false, 'locked' => false],
        ['id' => '123']
      )
      ->andReturn($updated = 1);

    $this->note->shouldReceive('get')
      ->once()
      ->with('123')
      ->andReturnNull();

    $result = $this->note->update('123', 'new_title', 'new_content', false, false);

    $this->assertSame($updated, $result);
  }

  /** @test */
  public function update_method_only_updates_the_lock_field_in_db_when_different_and_the_private_field_did_not_change()
  {
    $this->partiallyMockNoteClass();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with('bbn_notes', [], ['id' => '123'])
      ->andReturn([
        'private' => true,
        'locked' => true
      ]);

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        'bbn_notes',
        ['locked' => false],
        ['id' => '123']
      )
      ->andReturn($updated = 1);

    $this->note->shouldReceive('get')
      ->once()
      ->with('123')
      ->andReturnNull();

    $result = $this->note->update('123', 'new_title', 'new_content', true, false);

    $this->assertSame($updated, $result);
  }

  /** @test */
  public function update_method_only_updates_the_private_field_in_db_when_different_and_the_lock_field_did_not_change()
  {
    $this->partiallyMockNoteClass();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with('bbn_notes', [], ['id' => '123'])
      ->andReturn([
        'private' => true,
        'locked' => true
      ]);

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        'bbn_notes',
        ['private' => false],
        ['id' => '123']
      )
      ->andReturn($updated = 1);

    $this->note->shouldReceive('get')
      ->once()
      ->with('123')
      ->andReturnNull();

    $result = $this->note->update('123', 'new_title', 'new_content', false, true);

    $this->assertSame($updated, $result);
  }

  /** @test */
  public function update_method_does_not_update_the_bbn_notes_table_when_no_private_and_lock_argument_is_provided()
  {
    $this->partiallyMockNoteClass();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with('bbn_notes', [], ['id' => '123'])
      ->andReturn([
        'private' => true,
        'locked' => true
      ]);

    $this->db_mock->shouldNotReceive('update');

    $this->note->shouldReceive('get')
      ->once()
      ->with('123')
      ->andReturnNull();

    $result = $this->note->update('123', 'new_title', 'new_content');

    $this->assertSame(0, $result);
  }

  /** @test */
  public function update_method_does_not_call_the_insertVersion_method_when_title_and_content_not_changed()
  {
    $this->partiallyMockNoteClass();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with('bbn_notes', [], ['id' => '123'])
      ->andReturn([
        'private' => true,
        'locked' => true
      ]);

    $this->note->shouldReceive('get')
      ->once()
      ->with('123')
      ->andReturn([
        'title'   => 'title',
        'content' => 'content'
      ]);

    $result = $this->note->update('123', 'title', 'content');

    $this->assertSame(0, $result);
  }

  /** @test */
  public function update_method_returns_null_if_id_does_not_exists_in_bbn_notes()
  {
    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with('bbn_notes', [], ['id' => '123'])
      ->andReturnNull();

    $result = $this->note->update('123', 'title', 'content');

    $this->assertNull($result);
  }

  /** @test */
  public function latest_method_returns_the_latest_version_record_from_the_given_id()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['versions'],
        'MAX('.$cf['arch']['versions']['version'].')',
        [$cf['arch']['versions']['id_note'] => '123']
      )
      ->andReturn($version = 5);

    $this->assertSame($version, $this->note->latest('123'));
  }

  /** @test */
  public function get_method_returns_note_and_medias_for_the_given_id_and_version_from_db()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['tables']['notes'],
        [],
        [$cf['arch']['notes']['id'] => '123']
      )
      ->andReturn($expected1 = [
        $cf['arch']['notes']['private']   => true,
        $cf['arch']['notes']['locked']    => true,
        $cf['arch']['notes']['id_parent'] => '222'
      ]);

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['tables']['versions'],
        [],
        [
          $cf['arch']['versions']['id_note'] => '123',
          $cf['arch']['versions']['version'] => 3
        ]
      )
      ->andReturn($expected2 = [
        $cf['arch']['versions']['content']  => 'content',
        $cf['arch']['versions']['title']    => 'title'
      ]);

    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->with(
        $cf['tables']['nmedias'], $cf['arch']['nmedias']['id_media'], [
          $cf['arch']['nmedias']['id_note'] => '123',
          $cf['arch']['nmedias']['version'] => 3,
        ]
      )
      ->andReturn([1,2]);

    // Mock the media class
    $media_mock = $this->mockAndReplaceMediaInstance();
    $media_mock->shouldReceive('getMedia')
      ->twice()
      ->andReturn($expected_media1 = ['file' => 'path/to/file/1'], $expected_media2 = ['file' => 'path/to/file/2']);

    $result   = $this->note->get('123', 3);

    $this->assertSame(
      array_merge($expected1, $expected2, ['medias' => [$expected_media1, $expected_media2]]),
      $result
    );
  }

  /** @test */
  public function get_method_returns_note_and_not_medias_for_the_given_id_and_version_from_db_when_simple_is_true()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['tables']['notes'],
        [],
        [$cf['arch']['notes']['id'] => '123']
      )
      ->andReturn($expected1 = [
        $cf['arch']['notes']['private']   => true,
        $cf['arch']['notes']['locked']    => true,
        $cf['arch']['notes']['id_parent'] => '222'
      ]);

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['tables']['versions'],
        [],
        [
          $cf['arch']['versions']['id_note'] => '123',
          $cf['arch']['versions']['version'] => 3
        ]
      )
      ->andReturn($expected2 = [
        $cf['arch']['versions']['content']  => 'content',
        $cf['arch']['versions']['title']    => 'title'
      ]);

    $result   = $this->note->get('123', 3, true);
    $expected = array_merge($expected1, $expected2);

    unset($expected[$cf['arch']['versions']['content']]);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function get_method_gets_the_latest_version_when_no_version_is_provided()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['tables']['notes'],
        [],
        [$cf['arch']['notes']['id'] => '123']
      )
      ->andReturn($expected1 = [
        $cf['arch']['notes']['private']   => true,
        $cf['arch']['notes']['locked']    => true,
        $cf['arch']['notes']['id_parent'] => '222'
      ]);

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['tables']['versions'],
        [],
        [
          $cf['arch']['versions']['id_note'] => '123',
          $cf['arch']['versions']['version'] => 44
        ]
      )
      ->andReturn($expected2 = [
        $cf['arch']['versions']['content']  => 'content',
        $cf['arch']['versions']['title']    => 'title'
      ]);

    // Partially mock the note class to set expectations for the latest method
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('latest')
      ->once()
      ->with('123')
      ->andReturn(44);

    $result   = $this->note->get('123', null, true);
    $expected = array_merge($expected1, $expected2);

    unset($expected[$cf['arch']['versions']['content']]);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function get_method_sets_version_to_one_if_latest_version_returns_null_when_no_version_is_provided()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['tables']['notes'],
        [],
        [$cf['arch']['notes']['id'] => '123']
      )
      ->andReturn($expected1 = [
        $cf['arch']['notes']['private']   => true,
        $cf['arch']['notes']['locked']    => true,
        $cf['arch']['notes']['id_parent'] => '222'
      ]);

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['tables']['versions'],
        [],
        [
          $cf['arch']['versions']['id_note'] => '123',
          $cf['arch']['versions']['version'] => 1
        ]
      )
      ->andReturn($expected2 = [
        $cf['arch']['versions']['content']  => 'content',
        $cf['arch']['versions']['title']    => 'title'
      ]);

    // Partially mock the note class to set expectations for the latest method
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('latest')
      ->once()
      ->with('123')
      ->andReturnNull();

    $result   = $this->note->get('123', null, true);
    $expected = array_merge($expected1, $expected2);

    unset($expected[$cf['arch']['versions']['content']]);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function get_method_returns_null_when_note_does_not_exist()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['tables']['notes'],
        [],
        [$cf['arch']['notes']['id'] => '123']
      )
      ->andReturnNull();

    $this->assertNull(
      $this->note->get('123', 1)
    );
  }

  /** @test */
  public function getFull_method_returns_note_and_medias_from_the_provided_id_and_version()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        [
          'table' => $cf['table'],
          'fields' => [
            $cf['arch']['notes']['id'],
            $cf['arch']['notes']['id_parent'],
            $cf['arch']['notes']['id_alias'],
            $cf['arch']['notes']['excerpt'],
            $cf['arch']['notes']['id_type'],
            $cf['arch']['notes']['private'],
            $cf['arch']['notes']['locked'],
            $cf['arch']['notes']['pinned'],
            $cf['arch']['versions']['version'],
            $cf['arch']['versions']['title'],
            $cf['arch']['versions']['content'],
            $cf['arch']['versions']['id_user'],
            $cf['arch']['versions']['creation'],
          ],
          'join' => [[
            'table' => $cf['tables']['versions'],
            'on' => [
              'conditions' => [[
                'field' => $cf['arch']['versions']['id_note'],
                'exp' => $cf['arch']['notes']['id'],
              ], [
                'field' => $cf['arch']['versions']['version'],
                'value' => 2,
              ]],
            ],
          ]],
          'where' => [
            'conditions' => [[
              'field' => $cf['arch']['notes']['id'],
              'value' => '123',
            ]],
          ],
        ]
      )
      ->andReturn($expected_note = [
        $cf['arch']['notes']['private']   => true,
        $cf['arch']['notes']['locked']    => true,
        $cf['arch']['notes']['id_parent'] => '222'
      ]);

    // Partially mock the note instance to mock the getMedias
    // Since it will be tested in isolation
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('getMedias')
      ->once()
      ->with('123', 2)
      ->andReturn($expected_medias = [
        ['file' => 'path/to/file/1'],
        ['file' => 'path/to/file/2'],
      ]);

    $result = $this->note->getFull('123', 2);

    $this->assertSame(array_merge($expected_note, ['medias' => $expected_medias]), $result);
  }

  /** @test */
  public function getFull_method_will_get_the_latest_version_when_no_version_is_provided()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        [
          'table' => $cf['table'],
          'fields' => [
            $cf['arch']['notes']['id'],
            $cf['arch']['notes']['id_parent'],
            $cf['arch']['notes']['id_alias'],
            $cf['arch']['notes']['excerpt'],
            $cf['arch']['notes']['id_type'],
            $cf['arch']['notes']['private'],
            $cf['arch']['notes']['locked'],
            $cf['arch']['notes']['pinned'],
            $cf['arch']['versions']['version'],
            $cf['arch']['versions']['title'],
            $cf['arch']['versions']['content'],
            $cf['arch']['versions']['id_user'],
            $cf['arch']['versions']['creation'],
          ],
          'join' => [[
            'table' => $cf['tables']['versions'],
            'on' => [
              'conditions' => [[
                'field' => $cf['arch']['versions']['id_note'],
                'exp' => $cf['arch']['notes']['id'],
              ], [
                'field' => $cf['arch']['versions']['version'],
                'value' => 5,
              ]],
            ],
          ]],
          'where' => [
            'conditions' => [[
              'field' => $cf['arch']['notes']['id'],
              'value' => '123',
            ]],
          ],
        ]
      )
      ->andReturn($expected_note = [
        $cf['arch']['notes']['private']   => true,
        $cf['arch']['notes']['locked']    => true,
        $cf['arch']['notes']['id_parent'] => '222'
      ]);

    // Partially mock the note instance to mock the getMedias and latest methods
    // Since they are tested in isolation
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('getMedias')
      ->once()
      ->with('123', 5)
      ->andReturn($expected_medias = [
        ['file' => 'path/to/file/1'],
        ['file' => 'path/to/file/2'],
      ]);

    $this->note->shouldReceive('latest')
      ->once()
      ->with('123')
      ->andReturn(5);

    $result = $this->note->getFull('123');

    $this->assertSame(array_merge($expected_note, ['medias' => $expected_medias]), $result);
  }

  /** @test */
  public function getFull_method_returns_null_when_note_does_not_exist()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        [
          'table' => $cf['table'],
          'fields' => [
            $cf['arch']['notes']['id'],
            $cf['arch']['notes']['id_parent'],
            $cf['arch']['notes']['id_alias'],
            $cf['arch']['notes']['excerpt'],
            $cf['arch']['notes']['id_type'],
            $cf['arch']['notes']['private'],
            $cf['arch']['notes']['locked'],
            $cf['arch']['notes']['pinned'],
            $cf['arch']['versions']['version'],
            $cf['arch']['versions']['title'],
            $cf['arch']['versions']['content'],
            $cf['arch']['versions']['id_user'],
            $cf['arch']['versions']['creation'],
          ],
          'join' => [[
            'table' => $cf['tables']['versions'],
            'on' => [
              'conditions' => [[
                'field' => $cf['arch']['versions']['id_note'],
                'exp' => $cf['arch']['notes']['id'],
              ], [
                'field' => $cf['arch']['versions']['version'],
                'value' => 2,
              ]],
            ],
          ]],
          'where' => [
            'conditions' => [[
              'field' => $cf['arch']['notes']['id'],
              'value' => '123',
            ]],
          ],
        ]
      )
      ->andReturnNull();

    $this->assertNull(
      $this->note->getFull('123', 2)
    );
  }

  /** @test */
  public function urlExists_method_checks_if_the_given_url_exists()
  {
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('urlToId')
      ->with('foo/bar')
      ->once()
      ->andReturn('123');

    $this->assertTrue(
      $this->note->urlExists('foo/bar')
    );
  }

  /** @test */
  public function urlToId_method_returns_id_from_the_given_url_from_db()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['url'],
        $cf['arch']['url']['id_note'],
        [$cf['arch']['url']['url'] => '/foo/bar']
      )
      ->andReturn('123');

    $this->assertSame('123', $this->note->urlToId('foo/bar'));
  }

  /** @test */
  public function urlToId_returns_null_when_db_look_up_returns_false()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['url'],
        $cf['arch']['url']['id_note'],
        [$cf['arch']['url']['url'] => '/foo/bar']
      )
      ->andReturnFalse();

    $this->assertNull(
      $this->note->urlToId('/foo/bar')
    );
  }

  /** @test */
  public function urlToId_returns_null_when_url_is_empty()
  {
    $this->assertNull(
      $this->note->urlToId('')
    );
  }

  /** @test */
  public function urlToNote_return_note_from_the_given_url()
  {
    $cf = $this->getClassCfg();

    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('urlToId')
      ->once()
      ->with('foo/bar')
      ->andReturn('123');

    $this->note->shouldReceive('get')
      ->once()
      ->with('123')
      ->andReturn($expected_note = [
        $cf['arch']['notes']['private']   => true,
        $cf['arch']['notes']['locked']    => true,
        $cf['arch']['notes']['id_parent'] => '222'
      ]);

    $this->assertSame($expected_note, $this->note->urlToNote('foo/bar'));
  }

  /** @test */
  public function urlToNote_return_full_note_details_from_the_given_url()
  {
    $cf = $this->getClassCfg();

    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('urlToId')
      ->once()
      ->with('foo/bar')
      ->andReturn('123');

    $this->note->shouldReceive('getFull')
      ->once()
      ->with('123')
      ->andReturn($expected_note = [
        $cf['arch']['notes']['private']   => true,
        $cf['arch']['notes']['locked']    => true,
        $cf['arch']['notes']['id_parent'] => '222'
      ]);

    $this->assertSame($expected_note, $this->note->urlToNote('foo/bar', true));
  }

  /** @test */
  public function urlToNote_method_returns_null_when_note_does_not_exists()
  {
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('urlToId')
      ->once()
      ->with('foo/bar')
      ->andReturnNull();

    $this->assertNull(
      $this->note->urlToNote('foo/bar')
    );
  }

  /** @test */
  public function hasUrl_method_returns_true_if_the_given_note_is_linked_to_an_url()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['url'],
        $cf['arch']['url']['url'],
        [
          $cf['arch']['url']['id_note'] => $this->id_note
        ]
      )
      ->andReturn('foo.bar');

    $this->assertTrue($this->note->hasUrl($this->id_note));
  }

  /** @test */
  public function getUrl_method_returns_the_url_of_the_given_note_if_exists()
  {
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('hasUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with([
        'table' => $cf['tables']['url'],
        'fields' => [$cf['arch']['url']['url']],
        'where'  => [
          'conditions'=>[[
            'field' => $cf['arch']['url']['id_note'],
            'value' => $this->id_note
          ]]
        ]
      ])
      ->andReturn('foo.bar');

    $this->assertSame('foo.bar', $this->note->getUrl($this->id_note));
  }

  /** @test */
  public function getUrl_method_returns_null_when_the_given_id_does_not_has_url()
  {
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('hasUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->assertNull($this->note->getUrl($this->id_note));
  }

  /** @test */
  public function insertOrUpdateUrl_method_inserts_the_given_url_to_the_given_note_if_it_has_no_url()
  {
    $this->partiallyMockNoteClass();
    $cf = $this->getClassCfg();

    $this->note->shouldReceive('hasUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['tables']['url'],
        [
          $cf['arch']['url']['url']     => 'foo.bar',
          $cf['arch']['url']['id_note'] => $this->id_note
        ]
      )
      ->andReturn(1);

    $this->assertSame(1, $this->note->insertOrUpdateUrl($this->id_note, 'foo.bar'));
  }

  /** @test */
  public function insertOrUpdateUrl_method_updates_the_given_url_to_the_given_note_if_it_has_a_url()
  {
    $this->partiallyMockNoteClass();
    $cf = $this->getClassCfg();

    $this->note->shouldReceive('hasUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $cf['tables']['url'],
        [$cf['arch']['url']['url'] => 'foo.bar'],
        [
          $cf['arch']['url']['id_note'] => $this->id_note
        ]
      )
      ->andReturn(1);

    $this->assertSame(1, $this->note->insertOrUpdateUrl($this->id_note, 'foo.bar'));
  }

  /** @test */
  public function deleteUrl_method_deletes_url_for_the_given_note()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with([
        'table' => $cf['tables']['url'],
        'where' => [
          'conditions' => [[
            'field' => $cf['arch']['url']['id_note'],
            'value' => $this->id_note
          ]]
        ]])
      ->andReturn(1);

    $this->assertSame(1, $this->note->deleteUrl($this->id_note));
  }

  /** @test */
  public function getByType_method_returns_notes_from_the_given_type()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with($this->getByTypeMethodQueryArguments())
      ->andReturn([
        $note = [
          $cf['arch']['versions']['id_note'] => $this->id_note,
          $cf['arch']['versions']['version'] => 2,
          $cf['arch']['versions']['title']   => 'note_title',
          $cf['arch']['versions']['content'] => 'note_content',
          $cf['arch']['versions']['id_user'] => '123',
          $cf['arch']['versions']['creation'] => '2021-07-02'
        ]
      ]);

    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->with(
        $cf['tables']['nmedias'], $cf['arch']['nmedias']['id_media'],
        [
          $cf['arch']['nmedias']['id_note'] => $note[$cf['arch']['versions']['id_note']],
          $cf['arch']['nmedias']['version'] => $note[$cf['arch']['versions']['version']],
        ]
      )
      ->andReturn([$media = $this->id_media]);

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with($cf['tables']['medias'], [], [$cf['arch']['medias']['id'] => $media])
      ->andReturn($media = [
        $cf['arch']['medias']['content'] => json_encode(['foo' => 'bar']),
        $cf['arch']['medias']['title']   => 'title_content',
        $cf['arch']['medias']['name']    => 'title_name',
      ]);

    $result = $this->note->getByType($this->type);

    $this->assertIsObject($media_content = $result[0]['medias'][0]['content']);
    $this->assertObjectHasAttribute('foo', $media_content);
    $this->assertSame('bar', $media_content->foo);

    unset($result[0]['medias'][0]['content']);
    unset($media['content']);

    $expected = [
      array_merge($note, ['medias' => [$media]])
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getByType_method_returns_notes_when_the_given_type_is_null_and_id_user_is_provided()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with($this->getByTypeMethodQueryArguments($this->id_user, 1, 1))
      ->andReturn([
        $note = [
          $cf['arch']['versions']['id_note'] => $this->id_note,
          $cf['arch']['versions']['version'] => 2,
          $cf['arch']['versions']['title']   => 'note_title',
          $cf['arch']['versions']['content'] => 'note_content',
          $cf['arch']['versions']['id_user'] => '123',
          $cf['arch']['versions']['creation'] => '2021-07-02'
        ]
      ]);

    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('personal', 'types', $this->getNonPublicProperty('option_root_id'))
      ->andReturn($this->type);

    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->with(
        $cf['tables']['nmedias'], $cf['arch']['nmedias']['id_media'],
        [
          $cf['arch']['nmedias']['id_note'] => $note[$cf['arch']['versions']['id_note']],
          $cf['arch']['nmedias']['version'] => $note[$cf['arch']['versions']['version']],
        ]
      )
      ->andReturn([$media = $this->id_media]);

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with($cf['tables']['medias'], [], [$cf['arch']['medias']['id'] => $media])
      ->andReturn($media = [
        $cf['arch']['medias']['content'] => json_encode(['foo' => 'bar']),
        $cf['arch']['medias']['title']   => 'title_content',
        $cf['arch']['medias']['name']    => 'title_name',
      ]);

    $result = $this->note->getByType(null, $this->id_user, 1, 1);

    $this->assertIsObject($media_content = $result[0]['medias'][0]['content']);
    $this->assertObjectHasAttribute('foo', $media_content);
    $this->assertSame('bar', $media_content->foo);

    unset($result[0]['medias'][0]['content']);
    unset($media['content']);

    $expected = [
      array_merge($note, ['medias' => [$media]])
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getByType_method_returns_notes_when_the_given_type_is_not_valid()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with($this->getByTypeMethodQueryArguments())
      ->andReturn([
        $note = [
          $cf['arch']['versions']['id_note'] => $this->id_note,
          $cf['arch']['versions']['version'] => 2,
          $cf['arch']['versions']['title']   => 'note_title',
          $cf['arch']['versions']['content'] => 'note_content',
          $cf['arch']['versions']['id_user'] => '123',
          $cf['arch']['versions']['creation'] => '2021-07-02'
        ]
      ]);

    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('123456', 'types', $this->getNonPublicProperty('option_root_id'))
      ->andReturn($this->type);

    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->with(
        $cf['tables']['nmedias'], $cf['arch']['nmedias']['id_media'],
        [
          $cf['arch']['nmedias']['id_note'] => $note[$cf['arch']['versions']['id_note']],
          $cf['arch']['nmedias']['version'] => $note[$cf['arch']['versions']['version']],
        ]
      )
      ->andReturn([$media = $this->id_media]);

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with($cf['tables']['medias'], [], [$cf['arch']['medias']['id'] => $media])
      ->andReturn($media = [
        $cf['arch']['medias']['content'] => json_encode(['foo' => 'bar']),
        $cf['arch']['medias']['title']   => 'title_content',
        $cf['arch']['medias']['name']    => 'title_name',
      ]);

    $result = $this->note->getByType('123456');

    $this->assertIsObject($media_content = $result[0]['medias'][0]['content']);
    $this->assertObjectHasAttribute('foo', $media_content);
    $this->assertSame('bar', $media_content->foo);

    unset($result[0]['medias'][0]['content']);
    unset($media['content']);

    $expected = [
      array_merge($note, ['medias' => [$media]])
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getByType_method_returns_notes_with_no_medias_when_not_found()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with($this->getByTypeMethodQueryArguments())
      ->andReturn([
        $note = [
          $cf['arch']['versions']['id_note'] => $this->id_note,
          $cf['arch']['versions']['version'] => 2,
          $cf['arch']['versions']['title']   => 'note_title',
          $cf['arch']['versions']['content'] => 'note_content',
          $cf['arch']['versions']['id_user'] => '123',
          $cf['arch']['versions']['creation'] => '2021-07-02'
        ]
      ]);

    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->with(
        $cf['tables']['nmedias'], $cf['arch']['nmedias']['id_media'],
        [
          $cf['arch']['nmedias']['id_note'] => $note[$cf['arch']['versions']['id_note']],
          $cf['arch']['nmedias']['version'] => $note[$cf['arch']['versions']['version']],
        ]
      )
      ->andReturnNull();

    $result   = $this->note->getByType($this->type);
    $expected = [$note];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getByType_method_returns_notes_with_empty_medias_when_no_corresponding_media_found()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with($this->getByTypeMethodQueryArguments())
      ->andReturn([
        $note = [
          $cf['arch']['versions']['id_note'] => $this->id_note,
          $cf['arch']['versions']['version'] => 2,
          $cf['arch']['versions']['title']   => 'note_title',
          $cf['arch']['versions']['content'] => 'note_content',
          $cf['arch']['versions']['id_user'] => '123',
          $cf['arch']['versions']['creation'] => '2021-07-02'
        ]
      ]);

    $this->db_mock->shouldReceive('getColumnValues')
    ->once()
    ->with(
      $cf['tables']['nmedias'], $cf['arch']['nmedias']['id_media'],
      [
        $cf['arch']['nmedias']['id_note'] => $note[$cf['arch']['versions']['id_note']],
        $cf['arch']['nmedias']['version'] => $note[$cf['arch']['versions']['version']],
      ]
    )
    ->andReturn([$media = $this->id_media]);

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with($cf['tables']['medias'], [], [$cf['arch']['medias']['id'] => $media])
      ->andReturnNull();

    $result   = $this->note->getByType($this->type);
    $expected = [
      array_merge($note, ['medias' => []])
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getByType_method_returns_empty_array_when_no_notes_found()
  {
    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with($this->getByTypeMethodQueryArguments())
      ->andReturn([]);

    $this->assertIsArray($result = $this->note->getByType($this->type));
    $this->assertEmpty($result);
  }

  /** @test */
  public function getByType_method_returns_false_when_the_retrieved_type_is_not_valid_uid()
  {
    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('12345', 'types', $this->getNonPublicProperty('option_root_id'))
      ->andReturn('123');

    $this->assertFalse($this->note->getByType('12345'));
  }

  /** @test */
  public function getVersions_method_returns_versions_from_the_given_note_id()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'table' => $cf['tables']['versions'],
        'fields' => [
          $cf['arch']['versions']['version'],
          $cf['arch']['versions']['id_user'],
          $cf['arch']['versions']['creation'],
        ],
        'where' => [
          'conditions' => [[
            'field' => $cf['arch']['versions']['id_note'],
            'value' => $this->id_note,
          ]],
        ],
        'order' => [[
          'field' => $cf['arch']['versions']['version'],
          'dir' => 'DESC',
        ]],
      ])
      ->andReturn($expected = [
        [
          $cf['arch']['versions']['version']  => 2,
          $cf['arch']['versions']['id_user']  => $this->id_user,
          $cf['arch']['versions']['creation'] => date('Y-m-d'),
        ]
      ]);

    $result = $this->note->getVersions($this->id_note);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getVersions_method_returns_null_if_the_given_note_id_is_not_valid_uid()
  {
    $this->assertNull(
      $this->note->getVersions('12345')
    );
  }

  /** @test */
  public function countByType_method_returns_the_count_of_notes_for_the_given_type_and_user_id()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with([
        'table' => $cf['table'],
        'fields' => ['COUNT(DISTINCT '.$cf['arch']['notes']['id'].')'],
        'where' => [
          'conditions' => [[
            'field' => $cf['arch']['notes']['active'],
            'value' => 1,
          ], [
            'field' => $cf['arch']['notes']['id_type'],
            'value' => $this->type,
          ], [
              'field' => $cf['arch']['notes']['creator'],
              'value' => $this->id_user,
            ]],
        ],
      ])
      ->andReturn($expected = 22);

    $result = $this->note->countByType($this->type, $this->id_user);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function countByType_method_returns_the_count_of_notes_when_type_and_user_id_are_not_provided()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with([
        'table' => $cf['table'],
        'fields' => ['COUNT(DISTINCT '.$cf['arch']['notes']['id'].')'],
        'where' => [
          'conditions' => [[
            'field' => $cf['arch']['notes']['active'],
            'value' => 1,
          ], [
            'field' => $cf['arch']['notes']['id_type'],
            'value' => $this->type,
          ]],
        ],
      ])
      ->andReturn($expected = ['COUNT(DISTINCT '.$cf['arch']['notes']['id'].')' => 22]);

    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('personal', 'types', $this->getNonPublicProperty('option_root_id'))
      ->andReturn($this->type);

    $result = $this->note->countByType();

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function countByType_method_returns_the_count_of_notes_when_the_given_type_is_not_valid_uid()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with([
        'table' => $cf['table'],
        'fields' => ['COUNT(DISTINCT '.$cf['arch']['notes']['id'].')'],
        'where' => [
          'conditions' => [[
            'field' => $cf['arch']['notes']['active'],
            'value' => 1,
          ], [
            'field' => $cf['arch']['notes']['id_type'],
            'value' => $this->type,
          ]],
        ],
      ])
      ->andReturn($expected = ['COUNT(DISTINCT '.$cf['arch']['notes']['id'].')' => 22]);

    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('12345', 'types', $this->getNonPublicProperty('option_root_id'))
      ->andReturn($this->type);

    $result = $this->note->countByType('12345');

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function countByType_method_returns_false_when_the_retrieved_type_is_not_valid_uid()
  {
    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('personal', 'types', $this->getNonPublicProperty('option_root_id'))
      ->andReturn('12345');

    $this->assertFalse($this->note->countByType());
  }

  /** @test */
  public function addMedia_method_adds_media_to_db_and_returns_the_id_from_the_given_note_id_and_version_and_media_contents()
  {
    $user_mock  = $this->initAndMockUserClass();
    $cf         = $this->getClassCfg();
    $media_mock = $this->mockAndReplaceMediaInstance();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);

    $media_mock->shouldReceive('insert')
      ->once()
      ->with('media_name',  ['foo' => 'bar'], 'media_title', 'file', true)
      ->andReturn($this->id_media);

    $user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_user);

    $this->db_mock->shouldReceive('insert')
      ->with(
        $cf['tables']['nmedias'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 5,
          $cf['arch']['nmedias']['id_media'] => $this->id_media,
          $cf['arch']['nmedias']['id_user'] => $this->id_user,
          $cf['arch']['nmedias']['creation'] => date('Y-m-d H:i:s'),
        ]
      )
      ->once()
      ->andReturn(1);


    $result = $this->note->addMedia(
      [$this->id_note, 5],
      'media_name',
      ['foo' => 'bar'],
      'media_title',
      'file',
      true
    );

    $this->assertSame($this->id_media, $result);
  }

  /** @test */
  public function addMedia_method_adds_media_to_db_and_returns_the_id_from_the_given_note_id_and_media_contents()
  {
    $user_mock  = $this->initAndMockUserClass();
    $cf         = $this->getClassCfg();
    $media_mock = $this->mockAndReplaceMediaInstance();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['versions'],
        'MAX('.$cf['arch']['versions']['version'].')',
        [$cf['arch']['versions']['id_note'] => $this->id_note]
      )
      ->andReturn($version = 4);

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);

    $media_mock->shouldReceive('insert')
      ->once()
      ->with('media_name',  ['foo' => 'bar'], 'media_title', 'file', true)
      ->andReturn($this->id_media);

    $user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_user);

    $this->db_mock->shouldReceive('insert')
      ->with(
        $cf['tables']['nmedias'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => $version,
          $cf['arch']['nmedias']['id_media'] => $this->id_media,
          $cf['arch']['nmedias']['id_user'] => $this->id_user,
          $cf['arch']['nmedias']['creation'] => date('Y-m-d H:i:s'),
        ]
      )
      ->once()
      ->andReturn(1);


    $result = $this->note->addMedia(
      $this->id_note,
      'media_name',
      ['foo' => 'bar'],
      'media_title',
      'file',
      true
    );

    $this->assertSame($this->id_media, $result);
  }

  /** @test */
  public function addMedia_method_sets_the_version_to_one_when_not_provided_and_not_latest_version_found()
  {
    $user_mock  = $this->initAndMockUserClass();
    $cf         = $this->getClassCfg();
    $media_mock = $this->mockAndReplaceMediaInstance();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['versions'],
        'MAX('.$cf['arch']['versions']['version'].')',
        [$cf['arch']['versions']['id_note'] => $this->id_note]
      )
      ->andReturn(0);

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);

    $media_mock->shouldReceive('insert')
      ->once()
      ->with('media_name',  null, '', 'file', false)
      ->andReturn($this->id_media);

    $user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_user);

    $this->db_mock->shouldReceive('insert')
      ->with(
        $cf['tables']['nmedias'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 1,
          $cf['arch']['nmedias']['id_media'] => $this->id_media,
          $cf['arch']['nmedias']['id_user'] => $this->id_user,
          $cf['arch']['nmedias']['creation'] => date('Y-m-d H:i:s'),
        ]
      )
      ->once()
      ->andReturn(1);


    $result = $this->note->addMedia($this->id_note, 'media_name');

    $this->assertSame($this->id_media, $result);
  }

  /** @test */
  public function addMedia_method_returns_null_when_note_id_does_not_exist()
  {
    $this->mockAndReplaceMediaInstance();

    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(0);

    $this->assertNull(
      $this->note->addMedia([$this->id_note, 12], 'media_name')
    );
  }

  /** @test */
  public function addMedia_method_returns_null_when_failed_to_insert_media()
  {
    $this->mockAndReplaceMediaInstance();

    $cf         = $this->getClassCfg();
    $media_mock = $this->mockAndReplaceMediaInstance();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);

    $media_mock->shouldReceive('insert')
      ->once()
      ->with('media_name',  null, '', 'file', false)
      ->andReturnNull();

    $this->assertNull(
      $this->note->addMedia([$this->id_note, 12], 'media_name')
    );
  }

  /** @test */
  public function addMedia_method_returns_null_when_failed_to_add_media_to_the_note()
  {
    $this->mockAndReplaceMediaInstance();

    $cf         = $this->getClassCfg();
    $media_mock = $this->mockAndReplaceMediaInstance();
    $user_mock  = $this->initAndMockUserClass();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);

    $media_mock->shouldReceive('insert')
      ->once()
      ->with('media_name',  null, '', 'file', false)
      ->andReturn($this->id_media);

    $user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_user);

    $this->db_mock->shouldReceive('insert')
      ->with(
        $cf['tables']['nmedias'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 12,
          $cf['arch']['nmedias']['id_media'] => $this->id_media,
          $cf['arch']['nmedias']['id_user'] => $this->id_user,
          $cf['arch']['nmedias']['creation'] => date('Y-m-d H:i:s'),
        ]
      )
      ->once()
      ->andReturnNull();

    $this->assertNull(
      $this->note->addMedia([$this->id_note, 12], 'media_name')
    );
  }

  /** @test */
  public function addMediaToNote_method_adds_nmedias_record_for_the_given_note_and_media_ids_with_the_given_version()
  {
    $user_mock = $this->initAndMockUserClass();
    $cf        = $this->getClassCfg();

    $user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_user);

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['tables']['nmedias'],
        [
          $cf['arch']['nmedias']['id_note']  => $this->id_note,
          $cf['arch']['nmedias']['version']  => 3,
          $cf['arch']['nmedias']['id_media'] => $this->id_media,
          $cf['arch']['nmedias']['id_user']  => $this->id_user,
          $cf['arch']['nmedias']['creation'] => date('Y-m-d H:i:s')
          ]
      )
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->note->addMediaToNote($this->id_media, $this->id_note, 3)
    );
  }

  /** @test */
  public function addMediaToNote_method_returns_null_when_user_instance_is_null()
  {
    $this->assertNull(
      $this->note->addMediaToNote($this->id_media, $this->id_note, 3)
    );
  }

  /** @test */
  public function removeMedia_method_removes_record_for_the_given_note_and_media_ids_with_the_given_version()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['medias'],
        $cf['arch']['medias']['id'],
        [$cf['arch']['medias']['id'] => $this->id_media]
      )
      ->andReturn($this->id_media);

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(3);

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(
        $cf['tables']['nmedias'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 4,
          $cf['arch']['nmedias']['id_media'] => $this->id_media,
        ]
      )
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->note->removeMedia($this->id_media, $this->id_note, 4)
    );
  }

  /** @test */
  public function removeMedia_method_removes_record_for_the_given_note_and_media_ids_with_the_given_version_is_false()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['medias'],
        $cf['arch']['medias']['id'],
        [$cf['arch']['medias']['id'] => $this->id_media]
      )
      ->andReturn($this->id_media);

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(3);

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(
        $cf['tables']['nmedias'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 8,
          $cf['arch']['nmedias']['id_media'] => $this->id_media,
        ]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['versions'], 'MAX('.$cf['arch']['versions']['version'].')',
        [$cf['arch']['versions']['id_note'] => $this->id_note]
      )
      ->andReturn(8);

    $this->assertSame(
      1,
      $this->note->removeMedia($this->id_media, $this->id_note, false)
    );
  }

  /** @test */
  public function removeMedia_method_removes_record_for_the_given_note_and_media_ids_with_the_given_version_is_true()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['medias'],
        $cf['arch']['medias']['id'],
        [$cf['arch']['medias']['id'] => $this->id_media]
      )
      ->andReturn($this->id_media);

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(3);

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(
        $cf['tables']['nmedias'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['id_media'] => $this->id_media,
        ]
      )
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->note->removeMedia($this->id_media, $this->id_note, true)
    );
  }

  /** @test */
  public function removeMedia_method_returns_null_when_the_provided_media_id_does_not_exist()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['medias'],
        $cf['arch']['medias']['id'], [$cf['arch']['medias']['id'] => $this->id_media]
      )
      ->andReturnFalse();

    $this->assertNull(
      $this->note->removeMedia($this->id_media, $this->id_note)
    );
  }

  /** @test */
  public function removeMedia_method_returns_null_when_the_provided_note_id_does_not_exist()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['medias'],
        $cf['arch']['medias']['id'], [$cf['arch']['medias']['id'] => $this->id_media]
      )
      ->andReturn($this->id_media);

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(0);

    $this->assertNull(
      $this->note->removeMedia($this->id_media, $this->id_note)
    );
  }


  /** @test */
  public function getMedias_method_returns_medias_from_the_given_note_id_and_version()
  {
    $media_mock = $this->mockAndReplaceMediaInstance();
    $cf         = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);


    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->with(
        $cf['tables']['nmedias'],
        $cf['arch']['nmedias']['id_media'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 12
        ]
      )
      ->andReturn([$this->id_media]);

    $media_mock->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturn($expected = ['file' => 'path/to/media']);

    $result = $this->note->getMedias($this->id_note, 12);

    $this->assertSame([$expected], $result);
  }

  /** @test */
  public function getMedias_method_returns_medias_from_the_given_note_id_and_version_is_true()
  {
    $media_mock = $this->mockAndReplaceMediaInstance();
    $cf         = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);


    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->with(
        $cf['tables']['nmedias'],
        $cf['arch']['nmedias']['id_media'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note
        ]
      )
      ->andReturn([$this->id_media]);

    $media_mock->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturn($expected = ['file' => 'path/to/media']);

    $result = $this->note->getMedias($this->id_note, true);

    $this->assertSame([$expected], $result);
  }

  /** @test */
  public function getMedias_method_returns_medias_from_the_given_note_id_and_version_is_false()
  {
    $media_mock = $this->mockAndReplaceMediaInstance();
    $cf         = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);


    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->with(
        $cf['tables']['nmedias'],
        $cf['arch']['nmedias']['id_media'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 10
        ]
      )
      ->andReturn([$this->id_media]);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['versions'], 'MAX('.$cf['arch']['versions']['version'].')',
        [$cf['arch']['versions']['id_note'] => $this->id_note]
      )
      ->andReturn(10);

    $media_mock->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturn($expected = ['file' => 'path/to/media']);

    $result = $this->note->getMedias($this->id_note, false);

    $this->assertSame([$expected], $result);
  }

  /** @test */
  public function getMedias_method_returns_empty_array_when_no_medias_found()
  {
    $this->mockAndReplaceMediaInstance();

    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);


    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->with(
        $cf['tables']['nmedias'],
        $cf['arch']['nmedias']['id_media'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 12
        ]
      )
      ->andReturn([]);

    $result = $this->note->getMedias($this->id_note, 12);

    $this->assertSame([], $result);
  }

  /** @test */
  public function getMedias_method_returns_empty_array_when_the_provided_note_does_not_exist()
  {
    $this->mockAndReplaceMediaInstance();

    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(0);

    $result = $this->note->getMedias($this->id_note, 12);

    $this->assertSame([], $result);
  }

  /** @test */
  public function hasMedias_method_checks_whether_the_provided_note_and_media_ids_have_medias_with_the_given_version()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('count')
      ->with(
        $cf['tables']['nmedias'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 12,
          $cf['arch']['nmedias']['id_media'] => $this->id_media
        ]
      )
      ->andReturn(1);

    $this->assertTrue($this->note->hasMedias($this->id_note, 12, $this->id_media));
  }

  /** @test */
  public function hasMedias_method_checks_whether_the_provided_note_id_has_medias_with_the_given_version()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('count')
      ->with(
        $cf['tables']['nmedias'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 12
        ]
      )
      ->andReturn(1);

    $this->assertTrue($this->note->hasMedias($this->id_note, 12));
  }

  /** @test */
  public function hasMedias_method_checks_whether_the_provided_note_id_has_medias_with_the_given_version_when_the_provided_media_id_is_not_valid()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('count')
      ->with(
        $cf['tables']['nmedias'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 12
        ]
      )
      ->andReturn(1);

    $this->assertTrue($this->note->hasMedias($this->id_note, 12, '1234aff'));
  }

  /** @test */
  public function hasMedias_method_checks_whether_the_provided_note_id_has_medias_when_the_given_version_is_false()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['versions'], 'MAX('.$cf['arch']['versions']['version'].')',
        [$cf['arch']['versions']['id_note'] => $this->id_note]
      )
      ->andReturn(7);

    $this->db_mock->shouldReceive('count')
      ->with(
        $cf['tables']['nmedias'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 7
        ]
      )
      ->andReturn(1);

    $this->assertTrue($this->note->hasMedias($this->id_note, false));
  }

  /** @test */
  public function hasMedias_method_returns_null_when_the_provided_note_id_does_not_exists()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(0);

    $this->assertNull($this->note->hasMedias($this->id_note, 1));
  }

  /** @test */
  public function count_method_returns_count_of_all_notes_for_the_user()
  {
    $user_mock = $this->initAndMockUserClass();
    $cf        = $this->getClassCfg();

    $user_mock->shouldReceive('getId')
      ->twice()
      ->withNoArgs()
      ->andReturn($this->id_user);

    $this->db_mock->shouldReceive('cfn')
      ->twice()
      ->with($cf['arch']['notes']['id'], $cf['tables']['notes'], 1)
      ->andReturn($id_note_field_notes_table = "{$cf['tables']['notes']}.{$cf['arch']['notes']['id']}");

    $this->db_mock->shouldReceive('tsn')
      ->once()
      ->with($cf['tables']['notes'], 1)
      ->andReturn($notes_table = $cf['tables']['notes']);

    $this->db_mock->shouldReceive('tsn')
      ->once()
      ->with($cf['tables']['versions'], 1)
      ->andReturn($versions_table = $cf['tables']['versions']);

    $this->db_mock->shouldReceive('cfn')
      ->once()
      ->with($cf['arch']['versions']['id_note'], $cf['tables']['versions'], 1)
      ->andReturn(
        $id_note_field_versions_table = "{$cf['tables']['versions']}.{$cf['arch']['versions']['id_note']}"
      );

    $this->db_mock->shouldReceive('cfn')
      ->once()
      ->with($cf['arch']['notes']['creator'], $cf['tables']['notes'], 1)
      ->andReturn(
        $creator_field = "{$cf['tables']['notes']}.{$cf['arch']['notes']['creator']}"
      );

    $this->db_mock->shouldReceive('cfn')
      ->once()
      ->with($cf['arch']['versions']['id_user'], $cf['tables']['versions'], 1)
      ->andReturn(
        $id_user_version_table = "{$cf['tables']['versions']}.{$cf['arch']['versions']['id_user']}"
      );

    $expected_sql = "
      SELECT COUNT(DISTINCT $id_note_field_notes_table)
      FROM $notes_table
        JOIN $versions_table
          ON $id_note_field_notes_table = $id_note_field_versions_table
      WHERE $creator_field = ?
      OR $id_user_version_table = ?";

    $this->db_mock->shouldReceive('getOne')
      ->once()
      ->with($expected_sql, $this->id_user, $this->id_user)
      ->andReturn(12);

    $this->assertSame(12, $this->note->count());
  }

  /** @test */
  public function count_method_returns_null_when_the_user_instance_is_null()
  {
    $this->assertNull($this->note->count());
  }

  /** @test */
  public function remove_method_removes_note_row_from_the_given_id_with_related_versions_and_media_when_keep_argument_is_false()
  {
    $cf = $this->getClassCfg();

    // Partially mock the note class to set expectations
    // For getMedias() and removeMedia()
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('getMedias')
      ->once()
      ->with($this->id_note, true)
      ->andReturn([
        ['id' => $this->id_media, 'file' => 'path/to/file']
      ]);

    $this->note->shouldReceive('removeMedia')
      ->once()
      ->with($this->id_media, $this->id_note, true)
      ->andReturn(1);

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(
        $cf['tables']['versions'],
        [$cf['arch']['versions']['id_note'] => $this->id_note]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);

    $this->assertSame(1, $this->note->remove($this->id_note, false));
  }

  /** @test */
  public function remove_method_updates_the_active_field_to_zero_for_the_given_note_id_when_keep_is_true()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $cf['table'],
        [$cf['arch']['notes']['active'] => 0], [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn(1);

    $this->assertSame(1, $this->note->remove($this->id_note, true));
  }

  /** @test */
  public function remove_method_returns_false_when_the_provided_note_id_is_not_valid()
  {
    $this->assertFalse(
      $this->note->remove('123acc')
    );
  }

  /** @test */
  public function copy_method_inserts_a_copy_from_the_given_note_id_and_version_and_returns_the_new_note_id()
  {
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('getFull')
      ->once()
      ->with($this->id_note, 2)
      ->andReturn($note = [
        'title'   => 'note_title',
        'content' => 'note_content',
        'type'    => 'note_type',
        'private' => true,
        'version' => 2,
        'medias'  => [
          ['id' => $this->id_media]
        ]
      ]);

    $this->note->shouldReceive('insert')
      ->once()
      ->with($note['title'], $note['content'], $note['type'], $note['private'])
      ->andReturn($this->id_note_2);

    $this->note->shouldReceive('addMediaToNote')
      ->once()
      ->with($this->id_media, $this->id_note, 2)
      ->andReturn(1);

    $this->assertSame(
      $this->id_note_2,
      $this->note->copy($this->id_note, 2)
    );
  }

  /** @test */
  public function copy_method_inserts_a_copy_from_the_given_note_id_and_version_and_private_and_returns_the_new_note_id()
  {
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('getFull')
      ->once()
      ->with($this->id_note, 2)
      ->andReturn($note = [
        'title'   => 'note_title',
        'content' => 'note_content',
        'type'    => 'note_type',
        'private' => true,
        'version' => 2,
        'medias'  => [
          ['id' => $this->id_media]
        ]
      ]);

    $this->note->shouldReceive('insert')
      ->once()
      ->with($note['title'], $note['content'], $note['type'], false)
      ->andReturn($this->id_note_2);

    $this->note->shouldReceive('addMediaToNote')
      ->once()
      ->with($this->id_media, $this->id_note, 2)
      ->andReturn(1);

    $this->assertSame(
      $this->id_note_2,
      $this->note->copy($this->id_note, 2, false)
    );
  }

  /** @test */
  public function copy_method_returns_null_when_the_provided_note_id_does_not_exist()
  {
    $this->partiallyMockNoteClass();

    $this->note->shouldReceive('getFull')
      ->once()
      ->with($this->id_note, 3)
      ->andReturnNull();

    $this->assertNull(
      $this->note->copy($this->id_note, 3)
    );
  }

  /** @test */
  public function getMediasNotes_method_selects_from_db_all_medias_that_have_the_property_content_not_null()
  {
    $cf         = $this->getClassCfg();
    $media_mock = $this->mockAndReplaceMediaInstance();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'table' => $cf['tables']['medias'],
        'fields' => $cf['arch']['medias'],
        'where' => [
          'conditions' => [[
            'field' => $cf['arch']['medias']['private'],
            'value' => 0,
          ], [
            'field' => $cf['arch']['medias']['content'],
            'operator' => 'isnotnull',
          ]],
        ],
        'start' => 0,
        'limit' => 5,
      ])
      ->andreturn([
        $expected_media = [
          'content' => json_encode(['path' => 'path/to/media']),
          'id'      => $this->id_media,
          'name'    => 'media.jpg'
        ]
      ]);

    if (!$this->getNonPublicProperty('_id_event', Cms::class)) {
      $this->option_mock->shouldReceive('fromCode')
        ->once()
        ->with('publication', 'event', 'appui')
        ->andReturn('123aaf');
    }

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with(
        $cf['tables']['nmedias'],
        [
          $cf['arch']['nmedias']['id_note'],
          $cf['arch']['nmedias']['version'],
        ],
        [$cf['arch']['nmedias']['id_media'] => $this->id_media]
      )
      ->andReturn([
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 2
        ]
      ]);

    // Called in the latest() method
    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->andReturn(2);

    // Called in the get() method
    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['tables']['notes'], [],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn($expected_note = [
        $cf['arch']['versions']['content'] => 'media_content'
      ]);

    // Called in the get() method
    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['tables']['versions'], [],
        [
          $cf['arch']['versions']['id_note'] => $this->id_note,
          $cf['arch']['versions']['version'] => 2,
        ]
      )
      ->andReturnNull();

    // Called in the get() method
    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->andReturnNull();

    $this->cleanTestingDir();
    $this->createDir($dir = "plugins/appui-note/media/path/to/media/$this->id_media");
    $image_path = $this->createFile('media.jpg', '', $dir);

    $media_mock->shouldReceive('isImage')
      ->once()
      ->with($image_path = str_replace('./', '', $image_path))
      ->andReturnTrue();

    $media_mock->shouldReceive('getThumbs')
      ->once()
      ->with($image_path)
      ->andReturn('path/to/thumb');

    // Called in Cms::getEvent()
    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->andReturn('123');

    // Called in Cms::getEvent()
    $this->db_mock->shouldReceive('rselect')
      ->with([
        'table' => 'bbn_events',
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => 'id',
            'value' => '123'
          ]]],
      ])
      ->once()
      ->andReturnNull();

    $expected_note['is_published'] = false;

    $expected = array_merge(
      $expected_media, ['notes' => [$expected_note]], ['is_image' => true]
    );

    $this->assertSame([$expected], $this->note->getMediasNotes(0, 5));

    $this->cleanTestingDir();
  }

  /** @test */
  public function getMediasNotes_method_does_not_return_the_notes_if_media_file_does_not_exist()
  {
    $cf = $this->getClassCfg();

   $this->mockAndReplaceMediaInstance();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'table' => $cf['tables']['medias'],
        'fields' => $cf['arch']['medias'],
        'where' => [
          'conditions' => [[
            'field' => $cf['arch']['medias']['private'],
            'value' => 0,
          ], [
            'field' => $cf['arch']['medias']['content'],
            'operator' => 'isnotnull',
          ]],
        ],
        'start' => 0,
        'limit' => 5,
      ])
      ->andreturn([
       [
          'content' => json_encode(['path' => 'path/to/media']),
          'id'      => $this->id_media,
          'name'    => 'media.jpg'
        ]
      ]);

    $this->assertSame([], $this->note->getMediasNotes(0, 5));
  }

  /** @test */
  public function getMediasNotes_method_does_not_return_the_notes_if_media_content_is_not_json()
  {
    $cf = $this->getClassCfg();

    $this->mockAndReplaceMediaInstance();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'table' => $cf['tables']['medias'],
        'fields' => $cf['arch']['medias'],
        'where' => [
          'conditions' => [[
            'field' => $cf['arch']['medias']['private'],
            'value' => 0,
          ], [
            'field' => $cf['arch']['medias']['content'],
            'operator' => 'isnotnull',
          ]],
        ],
        'start' => 0,
        'limit' => 5,
      ])
      ->andreturn([
        [
          'content' => 'foo',
          'id'      => $this->id_media,
          'name'    => 'media.jpg'
        ]
      ]);

    $this->cleanTestingDir();
    $this->createDir($dir = "plugins/appui-note/media/path/to/media/$this->id_media");
    $this->createFile('media.jpg', '', $dir);

    $this->assertSame([], $this->note->getMediasNotes(0, 5));
    $this->cleanTestingDir();
  }

  /** @test */
  public function getMediasNotes_method_returns_empty_array_when_no_media_found()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'table' => $cf['tables']['medias'],
        'fields' => $cf['arch']['medias'],
        'where' => [
          'conditions' => [[
            'field' => $cf['arch']['medias']['private'],
            'value' => 0,
          ], [
            'field' => $cf['arch']['medias']['content'],
            'operator' => 'isnotnull',
          ]],
        ],
        'start' => 0,
        'limit' => 5,
      ])
      ->andreturnNull();

    $this->assertSame([], $this->note->getMediasNotes(0, 5));
  }

  /** @test */
  public function getMediaNotes_method_returns_all_notes_linked_to_the_provided_media_id()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with(
        $cf['tables']['nmedias'], [
        $cf['arch']['nmedias']['id_note'],
        $cf['arch']['nmedias']['version'],
      ],
        [
          $cf['arch']['nmedias']['id_media'] => $this->id_media,
        ]
      )
      ->andReturn([
        ['id_note' => $this->id_note]
      ]);

    // Called in latest() method
    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['versions'], 'MAX('.$cf['arch']['versions']['version'].')',
        [$cf['arch']['versions']['id_note'] => $this->id_note]
      )
      ->andReturn(3);

    // Called in get() method
    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['tables']['notes'], [],
        [$cf['arch']['notes']['id'] => $this->id_note]
      )
      ->andReturn($expected_note = [
        $cf['arch']['versions']['content'] => 'media_content'
      ]);

    // Called in get() method
    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['tables']['versions'], [], [
          $cf['arch']['versions']['id_note'] => $this->id_note,
          $cf['arch']['versions']['version'] => 3,
        ]
      )
      ->andReturnNull();

    // Called in get() method
    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->with(
        $cf['tables']['nmedias'], $cf['arch']['nmedias']['id_media'],
        [
          $cf['arch']['nmedias']['id_note'] => $this->id_note,
          $cf['arch']['nmedias']['version'] => 3,
        ]
      )
      ->andReturnNull();

    // Called in Cms::getEvent()
    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->andReturnNull();

    $this->assertSame(
      [array_merge($expected_note, ['is_published' => false])],
      $this->note->getMediaNotes($this->id_media)
    );
  }

  /** @test */
  public function getMediaNotes_method_returns_empty_array_when_the_provided_media_id_does_not_exist()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with(
        $cf['tables']['nmedias'], [
        $cf['arch']['nmedias']['id_note'],
        $cf['arch']['nmedias']['version'],
      ],
        [
          $cf['arch']['nmedias']['id_media'] => $this->id_media,
        ]
      )
      ->andReturn([]);

    $this->assertSame([], $this->note->getMediaNotes($this->id_media));
  }

  /** @test */
  public function remove_note_events_method_removes_the_row_corresponding_to_the_given_arguments_in_notes_events_table()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(
        $cf['tables']['events'],
        [
          $cf['arch']['events']['id_event'] => $this->id_event,
          $cf['arch']['events']['id_note'] => $this->id_note,
        ]
      )
      ->andReturn(1);

    $this->assertTrue(
      $this->note->_remove_note_events($this->id_note, $this->id_event)
    );
  }

  /** @test */
  public function insert_notes_events_method_inserts_row_in_notes_events_table_if_the_provided_arguments_does_not_exist()
  {
    $cf     = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['tables']['events'],
        [
          $cf['arch']['events']['id_note'] => $this->id_note,
          $cf['arch']['events']['id_event'] => $this->id_event
        ]
      )
      ->andReturn(0);

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $cf['tables']['events'],
        [
          $cf['arch']['events']['id_note'] => $this->id_note,
          $cf['arch']['events']['id_event'] => $this->id_event
        ]
      )
      ->andReturn(1);

    $this->assertTrue(
      $this->note->_insert_notes_events($this->id_note, $this->id_event)
    );
  }

  /** @test */
  public function insert_notes_events_method_does_not_insert_row_when_the_given_arguments_exists()
  {
    $cf     = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['tables']['events'],
        [
          $cf['arch']['events']['id_note'] => $this->id_note,
          $cf['arch']['events']['id_event'] => $this->id_event
        ]
      )
      ->andReturn(1);

    $this->assertFalse(
      $this->note->_insert_notes_events($this->id_note, $this->id_event)
    );
  }

  /** @test */
  public function getEventIdFromNote_method_returns_event_id_for_the_given_note()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['events'], $cf['arch']['events']['id_event'],
        [$cf['arch']['events']['id_note'] => $this->id_note]
      )
      ->andReturn($this->id_event);

    $this->assertSame($this->id_event, $this->note->getEventIdFromNote($this->id_note));
  }

  /** @test */
  public function getNoteIdFromEvent_method_returns_note_id_for_the_given_event()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $cf['tables']['events'],
        $cf['arch']['events']['id_note'],
        [$cf['arch']['events']['id_event'] => $this->id_event]
      )
      ->andReturn($this->id_note);

    $this->assertSame($this->id_note, $this->note->getNoteIdFromEvent($this->id_event));
  }

  /** @test */
  public function check_date_method_checks_if_the_provided_end_date_is_after_the_start_date()
  {
    $method = $this->getNonPublicMethod('_check_date');

    $this->assertTrue(
      $method->invoke($this->note, '2021-07-01', '2021-07-03')
    );

    $this->assertFalse(
      $method->invoke($this->note, '2021-07-09', '2021-07-03')
    );

    $this->assertFalse(
      $method->invoke($this->note, '2021-07-09', '')
    );

    $this->assertFalse(
      $method->invoke($this->note, '', '2021-07-03')
    );

    $this->assertFalse(
      $method->invoke($this->note, '2021-07-09', 'foo')
    );

    $this->assertFalse(
      $method->invoke($this->note, 'foo', '2021-07-09')
    );
  }
}