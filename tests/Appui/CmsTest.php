<?php

namespace bbn\tests\Appui;

use bbn\Appui\Cms;
use bbn\Appui\Event;
use bbn\Appui\Note;
use bbn\Appui\Option;
use bbn\Appui\Url;
use bbn\Db;
use PHPUnit\Framework\TestCase;
use bbn\tests\Reflectable;
use bbn\tests\ReflectionHelpers;

class CmsTest extends TestCase
{
  use Reflectable;

  protected Cms $cms;

  protected $db_mock;

  protected $option_mock;

  protected $events_mock;

  protected $notes_mock;

  protected $url_mock;

  protected $id_event = 'c4c2c70aaaaa2aaa47652540000aaff';

  protected $id_note = '634a2c70aaaaa2aaa47652540000bbcf';

  protected $id_type = '312a2c70aaaaa2aaa47652540000ffcc';

  protected function init()
  {
    $this->db_mock     = \Mockery::mock(Db::class);
    $this->option_mock = \Mockery::mock(Option::class);
    $this->events_mock = \Mockery::mock(Event::class);
    $this->notes_mock  = \Mockery::mock(Note::class);
    $this->url_mock    = \Mockery::mock(Url::class);

    $this->setNonPublicPropertyValue('_id_event', null, Cms::class);

    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('publication', 'types','event', 'appui')
      ->andReturn($this->id_event);
    
    $this->option_mock->shouldReceive('fromRootCode')
      ->once()
      ->with('media', 'note', 'appui')
      ->andReturn($this->id_type);
    
    $this->notes_mock->shouldReceive('getClassCfg')
      ->once()
      ->andReturn($this->getNonPublicProperty('default_class_cfg', Note::class));

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
    $this->cms->cacheInit();

    $this->setNonPublicPropertyValue('db', $this->db_mock);
    $this->setNonPublicPropertyValue('note', $this->notes_mock);
    $this->setNonPublicPropertyValue('event', $this->events_mock);
    $this->setNonPublicPropertyValue('opt', $this->option_mock);
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
  public function testConstructorTest()
  {
    $this->assertInstanceOf(Event::class, $this->getNonPublicProperty('event'));
    $this->assertInstanceOf(Option::class, $this->getNonPublicProperty('opt'));
    $this->assertInstanceOf(Note::class, $this->getNonPublicProperty('note'));
    $this->assertSame($this->id_event, $this->getNonPublicProperty('_id_event'));
    $this->assertSame($this->getNonPublicProperty('class_cfg'), $this->getClassCgf());
  }

  /** @test */
  public function testCheckDateMethodChecksIfTheProvidedEndDateIsAfterTheStartDate()
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
  public function testGetMethodReturnsTheNoteWithItsUrlStartAndEndDateOfPublication()
  {
    $this->partiallyMockCmsInstance();

    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with('foo.bar')
      ->andReturn($expected_note = [
        'id'      => $this->id_note,
        'title'   => 'note_title',
        'content' => json_encode(['foo' => 'bar']),
        'private' => 0,
        'locked'  => 0,
        'medias'  => []
      ]);

    $this->notes_mock->shouldReceive('urlToId')
      ->once()
      ->with('foo.bar')
      ->andReturn($this->id_note);

    $this->notes_mock->shouldReceive('getUrl')
      ->once()
      ->with('foo.bar')
      ->andReturn('foo.bar');

      $this->notes_mock->shouldReceive('getTags')
      ->once()
      ->with('foo.bar')
      ->andReturn([]);

    $this->cms->shouldReceive('getStart')
      ->once()
      ->with('foo.bar')
      ->andReturn('2021-01-01');

    $this->cms->shouldReceive('getEnd')
      ->once()
      ->with('foo.bar')
      ->andReturn('2021-01-10');

    $expected = array_merge($expected_note, [
      'url'   => 'foo.bar',
      'start' => '2021-01-01',
      'end'   => '2021-01-10'
    ]);

    $this->assertSame($expected, $this->cms->get('foo.bar'));
  }

  /** @test */
  public function testGetMethodReturnsEmptyArrayWhenNoNotIdFoundForTheGivenUrl()
  {
    $this->notes_mock->shouldReceive('urlToId')
      ->once()
      ->with('foo.bar')
      ->andReturnNull();

    $this->assertSame([], $this->cms->get('foo.bar'));
  }

  /** @test */
  public function testGetMethodReturnsEmptyArrayWhenNoteDoesNotExist()
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
  public function testGetallMethodReturnsAllNotesOfTypePages()
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
  public function testGetbyurlMethodReturnsNoteIdForTheGivenUrlIfPublished()
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
  public function testGetbyurlMethodReturnsNullIfNoNoteFoundForTheGivenUrl()
  {
    $this->notes_mock->shouldReceive('urlToId')
      ->once()
      ->with('foo.bar')
      ->andReturnNull();

    $this->assertNull($this->cms->getByUrl('foo.bar'));
  }

  /** @test */
  public function testGetbyurlMethodReturnsNullIfTheFetchedNoteIsNotPublished()
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
  public function testDeleteMethodDeletesTheGivenNoteAndUnPublishItIfPublished()
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
  public function testDeleteMethodReturnsFalseIfFailsToDeleteTheNote()
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
  public function testDeleteMethodReturnsFalseIfTheGivenNoteDoesNotExist()
  {
    $this->notes_mock->shouldReceive('get')
      ->once()
      ->with($this->id_note)
      ->andReturnNull();

    $this->assertFalse($this->cms->delete($this->id_note));
  }
  
  /** @test */
  public function testSeturlMethodInsertsTheUrlForTheNoteIfNotExistsOtherwiseUpdateItIfTheGivenUrlDoesNotExistToAPublishedNote()
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
  public function testSeturlMethodThrowsAnExceptionWhenTheGivenUrlBelongsToAPublishedNote()
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
  public function testSeturlMethodReturnsFalseTheTheGivenNoteDoesNotExist()
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
  public function testRemoveurlMethodRemovesTheUrlCorrespondingToTheGivenNoteIdFromBbnNotesUrlTableAndUnPublishTheUrlIfPublished()
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
  public function testRemoveurlMethodRemovesTheUrlCorrespondingToTheGivenNoteIdFromBbnNotesUrlTableAndDoesNotUnPublishTheUrlIfNotePublished()
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
  public function testRemoveurlReturnsFalseWhenFailsToFindTheGivenNoteId()
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
  public function testRemoveurlReturnsFalseWhenFailsToRemoveTheUrl()
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
  public function testGeteventMethodReturnsTheObjectEventOfTheGivenNote()
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
  public function testGeteventMethodReturnNullWhenTheEventIdForTheGivenNoteDoesNotExist()
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
  public function testGeteventMethodReturnNullWhenTheNoteIdDoesNotHaveEvent()
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
  public function testUpdateeventMethodUpdatesTheEventRelativeToTheGivenNoteWhenStartAndEndDatesDontMatch()
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
  public function testUpdateeventMethodUpdatesTheEventRelativeToTheGivenNoteWithDefaultIdTypeIfNotProvided()
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
  public function testUpdateeventMethodDoesNotUpdateTheEventIfBothStartAndEndDatesMatchButStillReturnsTrue()
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
  public function testUpdateeventMethodUpdatesTheEventIfStartDatesMatchesButEndDatesDontMatch()
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
  public function testUpdateeventMethodUpdatesTheEventIfEndDatesMatchesButStartDatesDontMatch()
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
  public function testUpdateeventMethodUpdatesTheEventRelativeToTheGivenNoteWhenTheGivenStartAndEndDatesAreNull()
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
  public function testUpdateeventMethodUpdatesTheEventRelativeToTheGivenNoteWhenTheGivenEndDateIsNull()
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
  public function testUpdateeventMethodUpdatesTheEventRelativeToTheGivenNoteWhenTheGivenStartDateIsNull()
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
  public function testUpdateeventMethodReturnsFalseWhenStartOrEndDatesAreMissing()
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
  public function testUpdateeventMethodReturnsFalseWhenTheGivenDatesAreNotValid()
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
  public function testGetstartMethodReturnsStartDateForTheGivenNoteWhenALinkedEventExists()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn(['start' => '2021-07-07']);

    $this->assertSame('2021-07-07', $this->cms->getStart($this->id_note));
  }

  /** @test */
  public function testGetstartMethodReturnsNullWhenStartDateDoesNotExist()
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
  public function testGetstartMethodReturnsNullWhenTheGivenNoteHasNoLinkedEvent()
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
  public function testGetendMethodReturnsEndDateForTheGivenNoteWhenALinkedEventExists()
  {
    $this->partiallyMockCmsInstance();

    $this->cms->shouldReceive('getEvent')
      ->once()
      ->with($this->id_note)
      ->andReturn(['end' => '2021-07-10']);

    $this->assertSame('2021-07-10', $this->cms->getEnd($this->id_note));
  }

  /** @test */
  public function testGetendMethodReturnsNullWhenEndDateDoesNotExist()
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
  public function testGetendMethodReturnsNullWhenTheGivenNoteHasNoLinkedEvent()
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
  public function testSeteventMethodCreatesAnEventForTheGivenNoteIdIfDoesNotHaveAnEvent()
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
  public function testSeteventMethodCreatesAnEventForTheGivenNoteIdWithDefaultValuesWhenDontExist()
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
  public function testSeteventMethodDoesNotInsertInBbnNotesEventsTableIfFailedToCreateTheEvent()
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
  public function testSeteventMethodUpdatesTheEventForTheGivenNoteIdWhenItHasAnEvent()
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
  public function testSeteventMethodReturnNullWhenStartDateDoesNotProvided()
  {
    $this->assertNull(
      $this->cms->setEvent($this->id_note, ['end' => '2021-08-08'])
    );
  }

  /** @test */
  public function testSeteventReturnNullWhenTheGivenNoteDoesNotExist()
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
  public function testSeteventReturnNullWhenTheProvidedEndDateIsEqualTheStartData()
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
  public function testSeteventReturnNullWhenTheProvidedEndDateIsBeforeTheStartData()
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
  public function testGetfullpublishedMethodReturnsAnArrayContainingAllPublishedNotes()
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
  public function testGetfullpublishedMethodReturnsEmptyArrayWhenNoResultsFound()
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
  public function testIspublishedMethodReturnTrueWhenTheGivenNoteIsPublished()
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
  public function testIspublishedMethodReturnFalseWhenStartDateIsNull()
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
  public function testIspublishedMethodReturnFalseWhenEndDateIsBeforeTheCurrentDate()
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
  public function testIspublishedMethodReturnsFalseWhenTheNoteHasNotUrl()
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
  public function testIspublishedMethodReturnsFalseWhenTheGivenNoteDoesNotExist()
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
  public function testPublishMethodPublishesANoteByInsertingANewOneIfNotExists()
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
  public function testPublishMethodPublishesANoteByInsertingANewOneWithUsingDefaultValuesWhenNotProvided()
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
  public function testPublishMethodPublishesANoteByUpdatingExistingOneIfExists()
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
  public function testPublishMethodPublishesANoteByUpdatingExistingOneWithUsingDefaultValuesWhenNotProvided()
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
  public function testPublishMethodReturnsFalseWhenTheGivenNoteHasAnUrl()
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
  public function testPublishMethodReturnsAnArrayOfErrorWhenTheUrlIsProvidedAndFailedToSaveIt()
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
  public function testPublishMethodReturnsFalseWhenTheGivenNoteIsPublished()
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
  public function testPublishMethodReturnsFalseWhenTheGivenNoteDoesNotExist()
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
  public function testUnpublishMethodUnpunlishTheGivenNoteWhenItIsPublished()
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
  public function testUnpublishMethodRetunsFalseWhenFailsToUpdateTheRelatedEvent()
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
  public function testUnpublishMethodRetunsFalseWhenTheGivenNoteIsNotPublished()
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
  public function testUnpublishMethodReturnsFalseWhenTheGivenNoteHasNoLinkedEvent()
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
  public function testGetfullMethodReturnsAllNotesThatHasALinkWithBbnEventsTable()
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
  public function testGetfullMethodReturnsEmptyArrayIfNoEventsFound()
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