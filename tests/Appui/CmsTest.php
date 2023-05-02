<?php

namespace Appui;

use bbn\Appui\Cms;
use bbn\Appui\Event;
use bbn\Appui\Note;
use bbn\Appui\Option;
use bbn\Db;
use PHPUnit\Framework\TestCase;
use tests\Reflectable;

class CmsTest extends TestCase
{
  use Reflectable;

  protected Cms $cms;

  protected $db_mock;

  protected $option_mock;

  protected $events_mock;

  protected $notes_mock;

  protected $id_event = 'c4c2c70aaaaa2aaa47652540000aaff';

  protected $id_note = '634a2c70aaaaa2aaa47652540000bbcf';

  protected $id_type = '312a2c70aaaaa2aaa47652540000ffcc';

  protected function init()
  {
    $this->db_mock     = \Mockery::mock(Db::class);
    $this->option_mock = \Mockery::mock(Option::class);
    $this->events_mock = \Mockery::mock(Event::class);
    $this->notes_mock  = \Mockery::mock(Note::class);

    $this->setNonPublicPropertyValue('_id_event', null, Cms::class);

    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('publication', 'event', 'appui')
      ->andReturn($this->id_event);

    $this->setNonPublicPropertyValue('retriever_instance', $this->option_mock, Option::class);

    $this->cms = new Cms($this->db_mock, $this->notes_mock);
  }

  protected function setUp(): void
  {
    $this->init();
  }

  protected function partiallyMockCmsInstance()
  {
    $cfg = $this->getClassCgf();

    $this->cms = \Mockery::mock(Cms::class)->makePartial();

    $this->setNonPublicPropertyValue('db', $this->db_mock);
    $this->setNonPublicPropertyValue('_notes', $this->notes_mock);
    $this->setNonPublicPropertyValue('_events', $this->events_mock);
    $this->setNonPublicPropertyValue('_options', $this->option_mock);
    $this->setNonPublicPropertyValue('class_cfg', $cfg);
  }

  protected function tearDown(): void
  {
    \Mockery::close();
  }

  public function getInstance()
  {
    return $this->cms;
  }

  protected function getClassCgf()
  {
    return $this->getNonPublicProperty('class_cfg');
  }

  /** @test */
  public function constructor_test()
  {
    $this->assertInstanceOf(Event::class, $this->getNonPublicProperty('event'));
    $this->assertInstanceOf(Option::class, $this->getNonPublicProperty('opt'));
    $this->assertInstanceOf(Note::class, $this->getNonPublicProperty('note'));
    $this->assertSame($this->id_event, $this->getNonPublicProperty('_id_event'));
    $this->assertSame($this->getNonPublicProperty('default_class_cfg'), $this->getClassCgf());
  }

  /** @test */
  public function check_date_method_checks_if_the_provided_end_date_is_after_the_start_date()
  {
    $method = $this->getNonPublicMethod('_check_date');

    $this->assertTrue(
      $method->invoke($this->cms, '2021-07-01', '2021-07-03')
    );

    $this->assertFalse(
      $method->invoke($this->cms, '2021-07-09', '2021-07-09')
    );

    $this->assertFalse(
      $method->invoke($this->cms, '2021-07-09', '2021-07-03')
    );

    $this->assertFalse(
      $method->invoke($this->cms, '2021-07-09', '')
    );

    $this->assertFalse(
      $method->invoke($this->cms, '', '2021-07-03')
    );

    $this->assertFalse(
      $method->invoke($this->cms, '2021-07-09', 'foo')
    );

    $this->assertFalse(
      $method->invoke($this->cms, 'foo', '2021-07-09')
    );
  }

  /** @test */
  public function get_method_returns_the_note_with_its_url_start_and_end_date_of_publication()
  {
    $this->partiallyMockCmsInstance();

    $this->notes_mock->shouldReceive('urlToId')
      ->once()
      ->with('foo.bar')
      ->andReturn($this->id_note);

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn($expected_note = [
        'id'      => $this->id_note,
        'title'   => 'note_title',
        'content' => json_encode(['foo' => 'bar']),
        'private' => 0,
        'locked'  => 0,
        'medias'  => []
      ]);

    $this->notes_mock->shouldReceive('getUrl')
      ->once()
      ->with($this->id_note)
      ->andReturn('foo.bar');

    $this->cms->shouldReceive('getStart')
      ->once()
      ->with($this->id_note)
      ->andReturn('2021-01-01');

    $this->cms->shouldReceive('getEnd')
      ->once()
      ->with($this->id_note)
      ->andReturn('2021-01-10');

    $expected = array_merge($expected_note, [
      'url'   => 'foo.bar',
      'start' => '2021-01-01',
      'end'   => '2021-01-10'
    ]);

    $this->assertSame($expected, $this->cms->get('foo.bar'));
  }

  /** @test */
  public function get_method_returns_empty_array_when_no_not_id_found_for_the_given_url()
  {
    $this->notes_mock->shouldReceive('urlToId')
      ->once()
      ->with('foo.bar')
      ->andReturnNull();

    $this->assertSame([], $this->cms->get('foo.bar'));
  }

  /** @test */
  public function get_method_returns_empty_array_when_note_does_not_exist()
  {
    $this->notes_mock->shouldReceive('urlToId')
      ->once()
      ->with('foo.bar')
      ->andReturn($this->id_note);

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->assertSame([], $this->cms->get('foo.bar'));
  }

  /** @test */
  public function getAll_method_returns_all_notes_of_type_pages()
  {
    $this->partiallyMockCmsInstance();

    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('pages', 'types', 'note', 'appui')
      ->andReturn($this->id_type);

    $this->notes_mock->shouldReceive('getByType')
      ->once()
      ->with($this->id_type, false, 20, 5)
      ->andReturn($notes = [
        [
          'id_note' => $this->id_note,
          'version' => 2,
          'title'   => 'note_title',
          'content' => json_encode(['foo' => 'bar']),
          'id_user'  => '123',
          'creation' => '2021-07-02'
        ]
      ]);

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue($this->id_note);

    $this->notes_mock->shouldReceive('hasUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->notes_mock->shouldReceive('getUrl')
      ->once()
      ->with($this->id_note)
      ->andReturn('foo.bar');


    $this->cms->shouldReceive('getStart')
      ->once()
      ->with($this->id_note)
      ->andReturn('2021-08-10');

    $this->cms->shouldReceive('getEnd')
      ->once()
      ->with($this->id_note)
      ->andReturn('2021-08-15');

    $this->notes_mock->shouldReceive('getMedias')
      ->once()
      ->with($this->id_note)
      ->andReturn($files = [
        ['file' => 'path/to/file/1']
      ]);

    $expected = array_merge($notes[0], [
      'is_published'  => true,
      'url'           => 'foo.bar',
      'type'          => 'pages',
      'start'         => '2021-08-10',
      'end'           => '2021-08-15',
      'files'         => $files
    ]);

    $this->assertSame([$expected], $this->cms->getAll(20, 5));
  }

  /** @test */
  public function getByUrl_method_returns_note_id_for_the_given_url_if_published()
  {
    $this->partiallyMockCmsInstance();

    $this->notes_mock->shouldReceive('urlToId')
      ->once()
      ->with('foo.bar')
      ->andReturn($this->id_note);

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->assertSame($this->id_note, $this->cms->getByUrl('foo.bar'));
  }

  /** @test */
  public function getByUrl_method_returns_null_if_no_note_found_for_the_given_url()
  {
    $this->notes_mock->shouldReceive('urlToId')
      ->once()
      ->with('foo.bar')
      ->andReturnNull();

    $this->assertNull($this->cms->getByUrl('foo.bar'));
  }

  /** @test */
  public function getByUrl_method_returns_null_if_the_fetched_note_is_not_published()
  {
    $this->partiallyMockCmsInstance();

    $this->notes_mock->shouldReceive('urlToId')
      ->once()
      ->with('foo.bar')
      ->andReturn($this->id_note);

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->assertNull($this->cms->getByUrl('foo.bar'));
  }

  /** @test */
  public function delete_method_deletes_the_given_note_and_un_publish_it_if_published()
  {
    $this->partiallyMockCmsInstance();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'      => $this->id_note,
        'title'   => 'note_title',
        'content' => json_encode(['foo' => 'bar']),
        'private' => 0,
        'locked'  => 0,
        'medias'  => []
      ]);

    $this->notes_mock->shouldReceive('getUrl')
      ->once()
      ->with($this->id_note)
      ->andReturn('foo.bar');

    $this->cms->shouldReceive('removeUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->notes_mock->shouldReceive('remove')
      ->once()
      ->with($this->id_note)
      ->andReturn(1);

    $this->assertTrue($this->cms->delete($this->id_note));
  }

  /** @test */
  public function delete_method_returns_false_if_fails_to_delete_the_note()
  {
    $this->partiallyMockCmsInstance();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'      => $this->id_note,
        'title'   => 'note_title',
        'content' => json_encode(['foo' => 'bar']),
        'private' => 0,
        'locked'  => 0,
        'medias'  => []
      ]);

    $this->notes_mock->shouldReceive('getUrl')
      ->once()
      ->with($this->id_note)
      ->andReturn('foo.bar');

    $this->cms->shouldReceive('removeUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->notes_mock->shouldReceive('remove')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->assertFalse($this->cms->delete($this->id_note));
  }

  /** @test */
  public function delete_method_returns_false_if_the_given_note_does_not_exist()
  {
    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->assertFalse($this->cms->delete($this->id_note));
  }
  
  /** @test */
  public function setUrl_method_inserts_the_url_for_the_note_if_not_exists_otherwise_update_it_if_the_given_url_does_not_exist_to_a_published_note()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getFullPublished')
      ->once()
      ->withNoArgs()
      ->andReturn([
        ['url' => 'foo1.bar', 'end' => '2021-12-01'],
        ['url' => 'foo2.bar', 'end' => '2021-12-01'],
        ['url' => 'foo3.bar', 'end' => '2021-12-01'],
      ]);

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->notes_mock->shouldReceive('insertOrUpdateUrl')
      ->once()
      ->with($this->id_note, 'foo.bar')
      ->andReturn(1);

    $this->assertTrue($this->cms->setUrl($this->id_note, 'foo.bar'));
  }

  /** @test */
  public function setUrl_method_throws_an_exception_when_the_given_url_belongs_to_a_published_note()
  {
    $this->expectException(\Exception::class);

    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getFullPublished')
      ->once()
      ->withNoArgs()
      ->andReturn([
        ['url' => 'foo1.bar', 'end' => '2021-12-01'],
        ['url' => 'foo2.bar', 'end' => '2021-12-01'],
        ['url' => 'foo3.bar', 'end' => '2021-12-01'],
      ]);

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->cms->setUrl($this->id_note, 'foo1.bar');
  }

  /** @test */
  public function setUrl_method_returns_false_the_the_given_note_does_not_exist()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getFullPublished')
      ->once()
      ->withNoArgs()
      ->andReturn([
        ['url' => 'foo1.bar', 'end' => '2021-12-01'],
        ['url' => 'foo2.bar', 'end' => '2021-12-01'],
        ['url' => 'foo3.bar', 'end' => '2021-12-01'],
      ]);

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->assertFalse($this->cms->setUrl($this->id_note, 'foo.bar'));
  }

  /** @test */
  public function removeUrl_method_removes_the_url_corresponding_to_the_given_note_id_from_bbn_notes_url_table_and_un_publish_the_url_if_published()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->cms->shouldReceive('unpublish')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->notes_mock->shouldReceive('deleteUrl')
      ->once()
      ->with($this->id_note)
      ->andReturn(1);

    $this->assertTrue($this->cms->removeUrl($this->id_note));
  }

  /** @test */
  public function removeUrl_method_removes_the_url_corresponding_to_the_given_note_id_from_bbn_notes_url_table_and_does_not_un_publish_the_url_if_note_published()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->notes_mock->shouldReceive('deleteUrl')
      ->once()
      ->with($this->id_note)
      ->andReturn(1);

    $this->assertTrue($this->cms->removeUrl($this->id_note));
  }

  /** @test */
  public function removeUrl_returns_false_when_fails_to_find_the_given_note_id()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->assertFalse($this->cms->removeUrl($this->id_note));
  }

  /** @test */
  public function removeUrl_returns_false_when_fails_to_remove_the_url()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->notes_mock->shouldReceive('deleteUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->assertFalse($this->cms->removeUrl($this->id_note));
  }

  /** @test */
  public function getEvent_method_returns_the_object_event_of_the_given_note()
  {
    $this->notes_mock->shouldReceive('getEventIdFromNote')
      ->once()
      ->with($this->id_note)
      ->andReturn($this->id_event);

    $cf     = $this->getClassCgf();
    $fields = $this->getNonPublicProperty('fields');

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with([
        'table' => $cf['table'],
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => $fields['id'],
            'value' => $this->id_event
          ]]],
      ])
      ->andReturn($event = [
        $cf['arch']['events']['id']    => $this->id_event,
        $cf['arch']['events']['start'] => '2021-07-06',
        $cf['arch']['events']['end']   => '2021-07-12'
      ]);

    $this->notes_mock->shouldReceive('_insert_notes_events')
      ->once()
      ->with($this->id_note, $this->id_event)
      ->andReturnTrue();

    $this->assertSame(
      array_merge($event, ['id_note' => $this->id_note]),
      $this->cms->getEvent($this->id_note)
    );
  }

  /** @test */
  public function getEvent_method_return_null_when_the_event_id_for_the_given_note_does_not_exist()
  {
    $this->notes_mock->shouldReceive('getEventIdFromNote')
      ->once()
      ->with($this->id_note)
      ->andReturn($this->id_event);

    $cf     = $this->getClassCgf();
    $fields = $this->getNonPublicProperty('fields');

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with([
        'table' => $cf['table'],
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => $fields['id'],
            'value' => $this->id_event
          ]]],
      ])
      ->andReturnNull();

    $this->assertNull(
      $this->cms->getEvent($this->id_note)
    );
  }

  /** @test */
  public function getEvent_method_return_null_when_the_note_id_does_not_have_event()
  {
    $this->notes_mock->shouldReceive('getEventIdFromNote')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->assertNull(
      $this->cms->getEvent($this->id_note)
    );
  }

  /** @test */
  public function updateEvent_method_updates_the_event_relative_to_the_given_note_when_start_and_end_dates_dont_match()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'    => $this->id_event,
        'start' => '2021-07-06',
        'end'   => '2021-07-11',
      ]);

    $this->events_mock->shouldReceive('edit')
      ->once()
      ->with(
        $this->id_event,
        array_merge(
          $cfg = ['start' => '2021-07-07', 'end' => '2021-07-10'],
          ['id_type' => $this->getNonPublicProperty('_id_event', Cms::class)]
        )
      )
      ->andReturn(1);

    $this->assertTrue($this->cms->updateEvent($this->id_note, $cfg));
  }

  /** @test */
  public function updateEvent_method_updates_the_event_relative_to_the_given_note_with_default_id_type_if_not_provided()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'    => $this->id_event,
        'start' => '2021-07-06',
        'end'   => '2021-07-11',
      ]);

    $this->events_mock->shouldReceive('edit')
      ->once()
      ->with(
        $this->id_event,
        array_merge(
          ['start' => '2021-07-07', 'end' => '2021-07-10'],
          ['id_type' => $this->getNonPublicProperty('_id_event', Cms::class)]
        )
      )
      ->andReturn(1);

    $this->assertTrue($this->cms->updateEvent($this->id_note, ['start' => '2021-07-07', 'end' => '2021-07-10']));
  }

  /** @test */
  public function updateEvent_method_does_not_update_the_event_if_both_start_and_end_dates_match_but_still_returns_true()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'    => $this->id_event,
        'start' => '2021-07-06',
        'end'   => '2021-07-10',
      ]);

    $this->assertTrue(
      $this->cms->updateEvent($this->id_note, ['start' => '2021-07-06', 'end' => '2021-07-10'])
    );
  }

  /** @test */
  public function updateEvent_method_updates_the_event_if_start_dates_matches_but_end_dates_dont_match()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'    => $this->id_event,
        'start' => '2021-07-06',
        'end'   => '2021-07-11',
      ]);

    $this->events_mock->shouldReceive('edit')
      ->once()
      ->with(
        $this->id_event,
        array_merge(
          $cfg = ['start' => '2021-07-06', 'end' => '2021-07-10'],
          ['id_type' => $this->getNonPublicProperty('_id_event', Cms::class)]
        )
      )
      ->andReturn(1);

    $this->assertTrue($this->cms->updateEvent($this->id_note, $cfg));
  }

  /** @test */
  public function updateEvent_method_updates_the_event_if_end_dates_matches_but_start_dates_dont_match()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'    => $this->id_event,
        'start' => '2021-07-06',
        'end'   => '2021-07-11',
      ]);

    $this->events_mock->shouldReceive('edit')
      ->once()
      ->with(
        $this->id_event,
        array_merge(
          $cfg = ['start' => '2021-07-08', 'end' => '2021-07-11'],
          ['id_type' => $this->getNonPublicProperty('_id_event', Cms::class)]
        )
      )
      ->andReturn(1);

    $this->assertTrue($this->cms->updateEvent($this->id_note, $cfg));
  }

  /** @test */
  public function updateEvent_method_updates_the_event_relative_to_the_given_note_when_the_given_start_and_end_dates_are_null()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'    => $this->id_event,
        'start' => '2021-07-06',
        'end'   => '2021-07-11',
      ]);

    $this->events_mock->shouldReceive('edit')
      ->once()
      ->with(
        $this->id_event,
        array_merge(
          $cfg = ['start' => null, 'end' => null],
          ['id_type' => $this->getNonPublicProperty('_id_event', Cms::class)]
        )
      )
      ->andReturn(1);

    $this->assertTrue($this->cms->updateEvent($this->id_note, $cfg));
  }

  /** @test */
  public function updateEvent_method_updates_the_event_relative_to_the_given_note_when_the_given_end_date_is_null()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'    => $this->id_event,
        'start' => '2021-07-06',
        'end'   => '2021-07-11',
      ]);

    $this->events_mock->shouldReceive('edit')
      ->once()
      ->with(
        $this->id_event,
        array_merge(
          $cfg = ['start' => '2021-07-06', 'end' => null],
          ['id_type' => $this->getNonPublicProperty('_id_event', Cms::class)]
        )
      )
      ->andReturn(1);

    $this->assertTrue($this->cms->updateEvent($this->id_note, $cfg));
  }

  /** @test */
  public function updateEvent_method_updates_the_event_relative_to_the_given_note_when_the_given_start_date_is_null()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'    => $this->id_event,
        'start' => '2021-07-06',
        'end'   => '2021-07-11',
      ]);

    $this->events_mock->shouldReceive('edit')
      ->once()
      ->with(
        $this->id_event,
        array_merge(
          $cfg = ['end' => '2021-07-06', 'start' => null],
          ['id_type' => $this->getNonPublicProperty('_id_event', Cms::class)]
        )
      )
      ->andReturn(1);

    $this->assertTrue($this->cms->updateEvent($this->id_note, $cfg));
  }

  /** @test */
  public function updateEvent_method_returns_false_when_start_or_end_dates_are_missing()
  {
    $this->assertFalse(
      $this->cms->updateEvent($this->id_note)
    );

    $this->assertFalse(
      $this->cms->updateEvent($this->id_note, ['start' => '2021-07-07'])
    );

    $this->assertFalse(
      $this->cms->updateEvent($this->id_note, ['end' => '2021-07-07'])
    );
  }

  /** @test */
  public function updateEvent_method_returns_false_when_the_given_dates_are_not_valid()
  {
    $this->assertFalse(
      $this->cms->updateEvent($this->id_note, ['end' => '2021-07-07', 'start' => 'foo'])
    );

    $this->assertFalse(
      $this->cms->updateEvent($this->id_note, ['start' => '2021-07-07', 'end' => 'foo'])
    );

    $this->assertFalse(
      $this->cms->updateEvent($this->id_note, ['start' => '', 'end' => '2021-07-07'])
    );

    $this->assertFalse(
      $this->cms->updateEvent($this->id_note, ['end' => '', 'start' => '2021-07-07'])
    );
  }

  /** @test */
  public function getStart_method_returns_start_date_for_the_given_note_when_a_linked_event_exists()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn(['start' => '2021-07-07']);

    $this->assertSame('2021-07-07', $this->cms->getStart($this->id_note));
  }

  /** @test */
  public function getStart_method_returns_null_when_start_date_does_not_exist()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn(['end' => '02-02-2020']);

    $this->assertNull(
      $this->cms->getStart($this->id_note)
    );
  }

  /** @test */
  public function getStart_method_returns_null_when_the_given_note_has_no_linked_event()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->assertNull(
      $this->cms->getStart($this->id_note)
    );
  }

  /** @test */
  public function getEnd_method_returns_end_date_for_the_given_note_when_a_linked_event_exists()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn(['end' => '2021-07-10']);

    $this->assertSame('2021-07-10', $this->cms->getEnd($this->id_note));
  }

  /** @test */
  public function getEnd_method_returns_null_when_end_date_does_not_exist()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn(['start' => '02-02-2020']);

    $this->assertNull(
      $this->cms->getEnd($this->id_note)
    );
  }

  /** @test */
  public function getEnd_method_returns_null_when_the_given_note_has_no_linked_event()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->assertNull(
      $this->cms->getEnd($this->id_note)
    );
  }

  /** @test */
  public function setEvent_method_creates_an_event_for_the_given_note_id_if_does_not_have_an_event()
  {
    $this->partiallyMockCmsInstance();
    $cf = $this->getClassCgf();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note, 'title' => 'note_title']);

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->events_mock->shouldReceive('insert')
      ->once()
      ->with($cfg = [
        $cf['arch']['events']['name']    => 'note_title',
        $cf['arch']['events']['id_type'] => $this->id_type,
        $cf['arch']['events']['start']   => '2021-03-01',
        $cf['arch']['events']['end']     => '2021-04-08',
      ])
      ->andReturn($this->id_event);

    $this->notes_mock->shouldReceive('_insert_notes_events')
      ->once()
      ->with($this->id_note, $this->id_event)
      ->andReturnTrue();


    $this->assertTrue(
      $this->cms->setEvent($this->id_note, $cfg)
    );
  }

  /** @test */
  public function setEvent_method_creates_an_event_for_the_given_note_id_with_default_values_when_dont_exist()
  {
    $this->partiallyMockCmsInstance();
    $cf = $this->getClassCgf();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->events_mock->shouldReceive('insert')
      ->once()
      ->with([
        $cf['arch']['events']['name']    => '',
        $cf['arch']['events']['id_type'] => $this->getNonPublicProperty('_id_event', Cms::class),
        $cf['arch']['events']['start']   => '2021-07-11',
        $cf['arch']['events']['end']     => null,
      ])
      ->andReturn($this->id_event);

    $this->notes_mock->shouldReceive('_insert_notes_events')
      ->once()
      ->with($this->id_note, $this->id_event)
      ->andReturnTrue();


    $this->assertTrue(
      $this->cms->setEvent($this->id_note, [
        'start' => '2021-07-11'
      ])
    );
  }

  /** @test */
  public function setEvent_method_does_not_insert_in_bbn_notes_events_table_if_failed_to_create_the_event()
  {
    $this->partiallyMockCmsInstance();
    $cf = $this->getClassCgf();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->events_mock->shouldReceive('insert')
      ->once()
      ->with([
        $cf['arch']['events']['name']    => '',
        $cf['arch']['events']['id_type'] => $this->getNonPublicProperty('_id_event', Cms::class),
        $cf['arch']['events']['start']   => '2021-07-11',
        $cf['arch']['events']['end']     => null,
      ])
      ->andReturnNull();

    $this->assertNull(
      $this->cms->setEvent($this->id_note, ['start' => '2021-07-11'])
    );
  }

  /** @test */
  public function setEvent_method_updates_the_event_for_the_given_note_id_when_it_has_an_event()
  {
    $this->partiallyMockCmsInstance();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id' => $this->id_event
      ]);

    $this->cms->shouldReceive('updateEvent')
      ->once()
      ->with($this->id_note, ['start' => '2021-08-01'])
      ->andReturnTrue();

    $this->assertTrue(
      $this->cms->setEvent($this->id_note, ['start' => '2021-08-01'])
    );
  }

  /** @test */
  public function setEvent_method_return_null_when_start_date_does_not_provided()
  {
    $this->assertNull(
      $this->cms->setEvent($this->id_note, ['end' => '2021-08-08'])
    );
  }

  /** @test */
  public function setEvent_return_null_when_the_given_note_does_not_exist()
  {
    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->assertNull(
      $this->cms->setEvent($this->id_note, ['start' => '2021-08-01'])
    );
  }

  /** @test */
  public function setEvent_return_null_when_the_provided_end_date_is_equal_the_start_data()
  {
    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->assertNull(
      $this->cms->setEvent($this->id_note, ['start' => '2021-08-01', 'end' => '2021-08-01'])
    );
  }

  /** @test */
  public function setEvent_return_null_when_the_provided_end_date_is_before_the_start_data()
  {
    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->assertNull(
      $this->cms->setEvent($this->id_note, ['start' => '2021-08-01', 'end' => '2021-07-31'])
    );
  }

  /** @test */
  public function getFullPublished_method_returns_an_array_containing_all_published_notes()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getFull')
      ->once()
      ->withNoArgs()
      ->andReturn([
        $note1 = ['start' => '2021-01-01', 'end' => '2021-01-02'],
        $note2 = ['start' => '2021-01-10', 'end' => '2021-01-20'],
        ['start' => null, 'end' => null]
      ]);

    $this->assertSame(
      [$note1, $note2],
      $this->cms->getFullPublished()
    );
  }

  /** @test */
  public function getFullPublished_method_returns_empty_array_when_no_results_found()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getFull')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $this->assertSame(
      [],
      $this->cms->getFullPublished()
    );
  }

  /** @test */
  public function isPublished_method_return_true_when_the_given_note_is_published()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'    => $this->id_event,
        'start' => '2021-07-06',
        'end'   => date('Y-m-d H:i:s', strtotime('+1 Day')),
      ]);

    $this->notes_mock->shouldReceive('hasUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->assertTrue(
      $this->cms->isPublished($this->id_note)
    );
  }

  /** @test */
  public function isPublished_method_return_false_when_start_date_is_null()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'    => $this->id_event,
        'start' => null,
        'end'   => date('Y-m-d H:i:s', strtotime('+1 Day')),
      ]);

    $this->assertFalse(
      $this->cms->isPublished($this->id_note)
    );
  }

  /** @test */
  public function isPublished_method_return_false_when_end_date_is_before_the_current_date()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'    => $this->id_event,
        'start' => '2021-07-06',
        'end'   => date('Y-m-d H:i:s', strtotime('-1 Day')),
      ]);

    $this->assertFalse(
      $this->cms->isPublished($this->id_note)
    );
  }

  /** @test */
  public function isPublished_method_returns_false_when_the_note_has_not_url()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn([
        'id'    => $this->id_event,
        'start' => '2021-07-06',
        'end'   => date('Y-m-d H:i:s', strtotime('+1 Day')),
      ]);

    $this->notes_mock->shouldReceive('hasUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->assertFalse(
      $this->cms->isPublished($this->id_note)
    );
  }

  /** @test */
  public function isPublished_method_returns_false_when_the_given_note_does_not_exist()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->assertFalse(
      $this->cms->isPublished($this->id_note)
    );
  }

  /** @test */
  public function publish_method_publishes_a_note_by_inserting_a_new_one_if_not_exists()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->notes_mock->shouldReceive('hasUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->cms->shouldReceive('SetEvent')
      ->once()
      ->with(
        $this->id_note, [
          'start'   => '2021-08-14',
          'end'     => '2021-08-18',
          'id_type' => $this->id_type
        ]
      )
      ->andReturnTrue();

    $this->assertTrue(
      $this->cms->publish($this->id_note, [
        'start'   => '2021-08-14',
        'end'     => '2021-08-18',
        'id_type' => $this->id_type
      ])
    );
  }

  /** @test */
  public function publish_method_publishes_a_note_by_inserting_a_new_one_with_using_default_values_when_not_provided()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->notes_mock->shouldReceive('hasUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->cms->shouldReceive('SetEvent')
      ->once()
      ->with(
        $this->id_note, [
          'start'   => date('Y-m-d H:i:s'),
          'end'     => null,
          'id_type' => $this->getNonPublicProperty('_id_event', Cms::class)
        ]
      )
      ->andReturnTrue();

    $this->assertTrue(
      $this->cms->publish($this->id_note, [])
    );
  }

  /** @test */
  public function publish_method_publishes_a_note_by_updating_existing_one_if_exists()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->notes_mock->shouldReceive('hasUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_event]);

    $this->cms->shouldReceive('updateEvent')
      ->once()
      ->with(
        $this->id_note, [
          'start'   => '2021-08-14',
          'end'     => '2021-08-18',
          'id_type' => $this->id_type
        ]
      )
      ->andReturnTrue();

    $this->assertTrue(
      $this->cms->publish($this->id_note, [
        'start'   => '2021-08-14',
        'end'     => '2021-08-18',
        'id_type' => $this->id_type
      ])
    );
  }

  /** @test */
  public function publish_method_publishes_a_note_by_updating_existing_one_with_using_default_values_when_not_provided()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->notes_mock->shouldReceive('hasUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_event]);

    $this->cms->shouldReceive('updateEvent')
      ->once()
      ->with(
        $this->id_note, [
          'start'   => date('Y-m-d H:i:s'),
          'end'     => null,
          'id_type' => $this->getNonPublicProperty('_id_event', Cms::class)
        ]
      )
      ->andReturnTrue();

    $this->assertTrue(
      $this->cms->publish($this->id_note, [])
    );
  }

  /** @test */
  public function publish_method_returns_false_when_the_given_note_has_an_url()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->notes_mock->shouldReceive('hasUrl')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->assertFalse(
      $this->cms->publish($this->id_note, [])
    );
  }

  /** @test */
  public function publish_method_returns_an_array_of_error_when_the_url_is_provided_and_failed_to_save_it()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->cms->shouldReceive('setUrl')
      ->once()
      ->with($this->id_note, 'foo.bar')
      ->andThrows(\Exception::class);

    $result = $this->cms->publish($this->id_note, ['url' => 'foo.bar']);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('error',$result);
  }

  /** @test */
  public function publish_method_returns_false_when_the_given_note_is_published()
  {
    $this->partiallyMockCmsInstance();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_note]);

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->assertFalse(
      $this->cms->publish($this->id_note, [])
    );
  }

  /** @test */
  public function publish_method_returns_false_when_the_given_note_does_not_exist()
  {
    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->assertFalse(
      $this->cms->publish($this->id_note, [])
    );
  }

  /** @test */
  public function unpublish_method_unpunlish_the_given_note_when_it_is_published()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_event]);

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->cms->shouldReceive('updateEvent')
      ->once()
      ->with($this->id_note, ['start' => null, 'end' => null])
      ->andReturnTrue();

    $this->notes_mock->shouldReceive('_remove_note_events')
      ->once()
      ->with($this->id_note, $this->id_event)
      ->andReturnTrue();

    $this->assertTrue(
      $this->cms->unpublish($this->id_note)
    );
  }

  /** @test */
  public function unpublish_method_retuns_false_when_fails_to_update_the_related_event()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_event]);

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnTrue();

    $this->cms->shouldReceive('updateEvent')
      ->once()
      ->with($this->id_note, ['start' => null, 'end' => null])
      ->andReturnFalse();

    $this->assertFalse(
      $this->cms->unpublish($this->id_note)
    );
  }

  /** @test */
  public function unpublish_method_retuns_false_when_the_given_note_is_not_published()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn(['id' => $this->id_event]);

    $this->cms->shouldReceive('isPublished')
      ->once()
      ->with($this->id_note)
      ->andReturnFalse();

    $this->assertFalse(
      $this->cms->unpublish($this->id_note)
    );
  }

  /** @test */
  public function unpublish_method_returns_false_when_the_given_note_has_no_linked_event()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->assertFalse(
      $this->cms->unpublish($this->id_note)
    );
  }

  /** @test */
  public function getFull_method_returns_all_notes_that_has_a_link_with_bbn_events_table()
  {
    $cfg = $this->getClassCgf();
    $this->partiallyMockCmsInstance();

    $this->db_mock->shouldReceive('cfn')
      ->twice()
      ->with($cfg['arch']['events']['end'], $cfg['table'])
      ->andReturn($end_date_field = "{$cfg['table']}.{$cfg['arch']['events']['end']}");

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'table' => $cfg['table'],
        'fields' => [],
        'where'  => [
          'conditions' => [
            [
              'logic' => 'OR',
              'conditions' => [
                [
                  'field'     => $end_date_field,
                  'operator'  => 'isnull',
                ],
                [
                  'field'     =>  $end_date_field,
                  'operator'  => '>',
                  'value'     => strtotime(date('Y-m-d H:i:s'))
                ],
              ]
            ]
          ]
        ]
      ])
      ->andReturn([
        $event1 = [
          'id'    => 1,
          'start' => date('Y-m-d H:i:s'),
          'end'   => date('Y-m-d H:i:s', strtotime('+1 Days'))
        ],
        $event2 = [
          'id'    => 2,
          'start' => date('Y-m-d H:i:s'),
          'end'   => null
        ],
        [
          'id'    => 3,
          'start' => date('Y-m-d H:i:s'),
          'end'   => date('Y-m-d H:i:s', strtotime('-1 Days'))
        ],
      ]);

    $expected = [];

    foreach ([$event1, $event2] as $event) {
      $this->notes_mock->shouldReceive('getNoteIdFromEvent')
        ->once()
        ->with($event['id'])
        ->andReturn($id_note = "note_{$event['id']}");

      $this->notes_mock->shouldReceive('hasUrl')
        ->once()
        ->with("note_{$event['id']}")
        ->andReturnTrue();

      $this->notes_mock->shouldReceive('get')
        ->once()
        ->with($id_note)
        ->andReturn($note = [
          'id' => $id_note,
        ]);

      $this->notes_mock->shouldReceive('getUrl')
        ->once()
        ->with($id_note)
        ->andReturn($url = "foo.{$event['id']}");

      $expected[] = array_merge($note, [
        'url'   => $url,
        'start' => $event[$cfg['arch']['events']['start']],
        'end'   => $event[$cfg['arch']['events']['end']]
      ]);
    }

    $this->assertSame($expected, $this->cms->getFull());
  }

  /** @test */
  public function getFull_method_returns_empty_array_if_no_events_found()
  {
    $cfg = $this->getClassCgf();

    $this->db_mock->shouldReceive('cfn')
      ->twice()
      ->andReturn("{$cfg['table']}.{$cfg['arch']['events']['end']}");

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->andReturnNull();

    $this->assertSame([], $this->cms->getFull());
  }
}