<?php

namespace Appui;

use bbn\Appui\Medias;
use bbn\Appui\Note;
use bbn\Appui\Option;
use bbn\Db;
use bbn\User;
use PHPUnit\Framework\TestCase;
use tests\Reflectable;

class NoteTest extends TestCase
{
  use Reflectable;

  protected Note $note;

  protected $db_mock;

  protected $option_mock;

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
        ->andReturn(1);
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
}