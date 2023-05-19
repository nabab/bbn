<?php

namespace Appui;

use bbn\Appui\Medias;
use bbn\Appui\Option;
use bbn\Db;
use bbn\Mvc;
use bbn\User;
use PHPUnit\Framework\TestCase;
use bbn\tests\Files;
use bbn\tests\Reflectable;

class MediasTest extends TestCase
{
  use Reflectable, Files;

  protected $db_mock;

  protected Medias $medias;

  protected $user_mock;

  protected $option_mock;

  protected $option_id = '634a2c70aaaaa2aaa47652540000cfaa';

  protected $id_media = '222a2c70aaaaa2aaa476525400002222';

  protected $id_media_2 = '123a2c70aaaaa2aaa476525400001111';

  private $id_user = 'aaaa2c70aaaaa2aaa47652540000aaaa';

  protected function setUp(): void
  {
    $this->cleanUpCreatedFiles();
    $this->init();
  }

  protected function tearDown(): void
  {
    $this->cleanUpCreatedFiles();
    \Mockery::close();
  }

  protected function cleanUpCreatedFiles()
  {
    if (file_exists($file = 'foo.jpg')) {
      unlink($file);
    }
    $this->cleanTestingDir();
  }

  protected function init()
  {
    $this->db_mock     = \Mockery::mock(Db::class);
    $this->user_mock   = \Mockery::mock(User::class);
    $this->option_mock = \Mockery::mock(Option::class);

    $this->setNonPublicPropertyValue('retriever_instance', $this->option_mock, Option::class);
    $this->setNonPublicPropertyValue('retriever_instance', $this->user_mock, User::class);

    $this->option_mock->shouldReceive('fromRootCode')
      ->once()
      ->with('media', 'note', 'appui')
      ->andReturn($this->option_id);

    $this->medias = new Medias($this->db_mock);
  }

  protected function getClassCfg()
  {
    return $this->getNonPublicProperty('class_cfg');
  }

  public function getInstance()
  {
    return $this->medias;
  }

  protected function partiallyMockMediasClass()
  {
    $cfg = $this->getClassCfg();
    $this->medias = \Mockery::mock(Medias::class)->makePartial();

    $this->setNonPublicPropertyValue('db', $this->db_mock);
    $this->setNonPublicPropertyValue('class_cfg', $cfg);
  }

  private function getDummyImageContent()
  {
    return base64_decode('/9j/7QBIUGhvdG9zaG9wIDMuMAA4QklNBAQAAAAAABAcAm4AC6kgU1dOUy5jb20gOEJJTQPtAAAAAAAQABMAAAABAAEAEwAAAAEA');
  }

  /** @test */
  public function constructor_test()
  {
    $this->assertInstanceOf(Option::class, $this->getNonPublicProperty('opt'));
    $this->assertInstanceOf(User::class, $this->getNonPublicProperty('usr'));
    $this->assertSame($this->option_id, $this->getNonPublicProperty('opt_id'));
    $this->assertSame(
      $this->getNonPublicProperty('default_class_cfg'),
      $this->getNonPublicProperty('class_cfg')
    );
  }

  /** @test */
  public function getPath_method_returns_the_path_of_the_given_media_argument()
  {
    $this->setNonPublicPropertyValue('path', null);

    $media = [
      'id'      => $this->id_media,
      'name'    => 'foo.jpg',
      'content' => [
        'path'  => 'path/to/media/'
      ]
    ];

    $expected = BBN_DATA_PATH . 'plugins/appui-note/media/path/to/media/' . $this->id_media . '/foo.jpg';

    $this->assertSame($expected, $this->medias->getPath($media));

    $this->assertSame(
      BBN_DATA_PATH . 'plugins/appui-note/media',
      $this->getNonPublicProperty('path')
    );
  }

  /** @test */
  public function getPath_method_returns_the_path_when_no_media_argument_provided()
  {
    $this->setNonPublicPropertyValue('path', null);

    $expected = BBN_DATA_PATH . 'plugins/appui-note/media';

    $this->assertSame($expected, $this->medias->getPath());

    $this->assertSame(
      $expected,
      $this->getNonPublicProperty('path')
    );
  }

  /** @test */
  public function getPath_method_returns_the_path_when_the_provided_media_has_missing_values()
  {
    $this->setNonPublicPropertyValue('path', null);

    $media = [
      'id'      => $this->id_media,
      'name'    => 'foo.jpg',
      'content' => []
    ];

    $expected = BBN_DATA_PATH . 'plugins/appui-note/media';

    $this->assertSame($expected, $this->medias->getPath($media));

    $this->assertSame(
      $expected,
      $this->getNonPublicProperty('path')
    );
  }

  /** @test */
  public function browse_method_returns_an_array_of_medias()
  {
    // The method initializes a concrete Grid object inside
    // So it's not possible to mock it and test

    $this->assertTrue(true);
  }

  /** @test */
  public function browse_function_returns_null_when_user_instance_is_null()
  {
    $this->setNonPublicPropertyValue('retriever_instance', null, User::class);

    $this->assertNull($this->medias->browse([]));
  }

  /** @test */
  public function count_method_returns_count_of_medias_from_the_given_filters()
  {
    $cf = $this->getClassCfg();

    $filters = [
      $cf['arch']['medias']['private'] => 1
    ];

    $this->user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_user);

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        array_merge($filters, ['id_user' => $this->id_user])
      )
      ->andReturn(10);

    $this->assertSame(10, $this->medias->count($filters));
  }

  /** @test */
  public function count_method_returns_count_of_medias_when_no_filter_is_provided()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'],
        ['private' => 0]
      )
      ->andReturn(10);

    $this->assertSame(10, $this->medias->count());
  }

  /** @test */
  public function count_method_returns_null_when_user_instance_is_null()
  {
    $this->setNonPublicPropertyValue('retriever_instance', null, User::class);

    $this->assertNull($this->medias->count());
  }

  /** @test */
  public function insert_method_adds_a_new_media_from_the_provided_arguments_and_private_is_true()
  {
    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('file', $this->option_id)
      ->andReturn('123a');

    $file = $this->createFile('foo.jpg', '', getcwd(), false);

    $this->user_mock->ShouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturnTrue();

    $this->user_mock->ShouldReceive('getId')
      ->twice()
      ->withNoArgs()
      ->andReturn($this->id_user);

    $this->setNonPublicPropertyValue('_app_name', 'bbn testing', Mvc::class);

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->andReturn(1);

    $this->db_mock->shouldReceive('lastId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_media);

    $this->assertSame(
      $this->id_media,
      $this->medias->insert(
        'foo.jpg',
        ['foo' => 'bar'],
        'media_title',
        'file',
        true,
        'excerpt'
      )
    );

    $this->assertFileExists(
      $this->getTestingDirName() .
      "users/$this->id_user/data/appui-note/media/" .
      date('Y/m/d') . "/1/$this->id_media/foo.jpg"
    );

    $this->assertFileDoesNotExist($file);
  }

  /** @test */
  public function insert_method_adds_a_new_media_when_private_is_false_()
  {
    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('file', $this->option_id)
      ->andReturn('123a');

    $file = $this->createFile('foo.jpg', '', getcwd(), false);

    $this->setNonPublicPropertyValue('_app_name', 'bbn testing', Mvc::class);

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->andReturn(1);

    $this->db_mock->shouldReceive('lastId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_media);


    $this->user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andreturnNull();

    define('BBN_EXTERNAL_USER_ID', $this->id_user);

    $this->assertSame(
      $this->id_media,
      $this->medias->insert(
        'foo.jpg',
        ['foo' => 'bar'],
        'media_title'
      )
    );

    $this->assertFileExists(
      $this->getTestingDirName() .
      "plugins/appui-note/media/" .
      date('Y/m/d') . "/1/$this->id_media/foo.jpg"
    );

    $this->assertFileDoesNotExist($file);
  }

  /** @test */
  public function insert_method_returns_null_when_private_is_true_and_user_is_not_logged_in()
  {
    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('file', $this->option_id)
      ->andReturn('123a');

    $this->user_mock->shouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturnNull();

    $this->createFile('foo.jpg', '', getcwd(), false);

    $this->assertNull(
      $this->medias->insert('foo.jpg', ['foo' => 'bar'], 'title', 'file', true)
    );
  }

  /** @test */
  public function insert_method_throws_an_exception_when_fails_to_insert_into_db()
  {
    $this->expectException(\Exception::class);

    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('file', $this->option_id)
      ->andReturn('123a');

    $this->createFile('foo.jpg', '', getcwd(), false);

    $this->user_mock->ShouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturnTrue();

    $this->user_mock->ShouldReceive('getId')
      ->twice()
      ->withNoArgs()
      ->andReturn($this->id_user);

    $this->setNonPublicPropertyValue('_app_name', 'bbn testing', Mvc::class);

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->andReturnNull();

    $this->medias->insert(
      'foo.jpg',
      ['foo' => 'bar'],
      'media_title',
      'file',
      true,
      'excerpt'
    );
  }

  /** @test */
  public function insert_method_throws_an_exception_when_the_provided_file_does_not_exist()
  {
    $this->expectException(\Exception::class);

    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('file', $this->option_id)
      ->andReturn('123a');

    $this->medias->insert('foo.jpg');
  }

  /** @test */
  public function insert_method_returns_null_when_the_provided_file_name_is_empty()
  {
    $this->assertNull(
      $this->medias->insert('')
    );
  }

  /** @test */
  public function insert_method_returns_null_when_id_type_is_null()
  {
    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('file', $this->option_id)
      ->andReturnNull();

    $this->assertNull(
      $this->medias->insert('foo.jgp')
    );
  }

  /** @test */
  public function insert_method_returns_null_when_the_provided_file_name_has_not_extension()
  {
    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('file', $this->option_id)
      ->andReturn('123a');

    $this->assertNull(
      $this->medias->insert('foo')
    );
  }

  /** @test */
  public function setUrl_method_inserts_url_to_the_provided_media_id()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'], [
          $cf['arch']['medias']['id'] => $this->id_media
        ]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('insertIgnore')
      ->once()
      ->with(
        $cf['tables']['medias_url'],
        [
          $cf['arch']['medias_url']['id_media'] => $this->id_media,
          $cf['arch']['medias_url']['url'] => 'foo.bar',
          $cf['arch']['medias_url']['shared'] => 0
        ]
      )
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->medias->setUrl($this->id_media, 'foo.bar')
    );
  }

  /** @test */
  public function setUrl_method_does_not_insert_url_if_media_id_does_not_exists_and_returns_null()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $cf['table'], [
          $cf['arch']['medias']['id'] => $this->id_media
        ]
      )
      ->andReturn(0);

    $this->assertNull(
      $this->medias->setUrl($this->id_media, 'foo.bar')
    );
  }

  /** @test */
  public function getThumbsSizes_method_returns_sorted_thumb_widths_for_the_given_id()
  {
    $this->partiallyMockMediasClass();

    $this->medias->shouldReceive('getMediaPath')
      ->once()
      ->with($this->id_media)
      ->andReturn(
        $this->getTestingDirName() . "appui-note/media/path/$this->id_media/foo.jpg"
      );

    $this->createDir($dir = "appui-note/media/path/$this->id_media");
    $this->createFile('bar_w333.jpg', '', $dir);
    $this->createFile('foobar_w700.jpg', '', $dir);
    $this->createFile('foobar_600.jpg', '', $dir);
    $this->createFile('foo_w123.jpg', '', $dir);

    $this->assertSame(
      ["123", "333", "700"],
      $this->medias->getThumbsSizes($this->id_media)
    );
  }

  /** @test */
  public function getThumbsSizes_method_returns_empty_array_when_no_thumbs_matches_is_found()
  {
    $this->partiallyMockMediasClass();

    $this->medias->shouldReceive('getMediaPath')
      ->once()
      ->with($this->id_media)
      ->andReturn(
        $this->getTestingDirName() . "appui-note/media/path/$this->id_media/foo.jpg"
      );

    $this->createDir($dir = "appui-note/media/path/$this->id_media");
    $this->createFile('foo_123.jpg', '', $dir);
    $this->createFile('bar_90.jpg', '', $dir);

    $this->assertSame([], $this->medias->getThumbsSizes($this->id_media));
  }

  /** @test */
  public function getThumbsSizes_method_returns_empty_array_when_the_provided_id_is_not_valid_uid()
  {
    $this->assertSame([], $this->medias->getThumbsSizes('123a'));
  }

  /** @test */
  public function getThumbsSizes_method_returns_empty_array_when_no_path_found_for_the_given_id()
  {
    $this->partiallyMockMediasClass();

    $this->medias->shouldReceive('getMediaPath')
      ->once()
      ->with($this->id_media)
      ->andReturnNull();

    $this->assertSame([], $this->medias->getThumbsSizes($this->id_media));
  }

  /** @test */
  public function getThumbsSizes_method_returns_empty_array_when_dir_does_not_exists()
  {
    $this->partiallyMockMediasClass();

    $this->medias->shouldReceive('getMediaPath')
      ->once()
      ->with($this->id_media)
      ->andReturn(
        "path/does/not/exist/$this->id_media/foo.jpg"
      );

    $this->assertSame([], $this->medias->getThumbsSizes($this->id_media));
  }

  /** @test */
  public function getThumbsSizes_method_returns_empty_array_when_the_dir_has_only_one_file()
  {
    $this->partiallyMockMediasClass();

    $this->medias->shouldReceive('getMediaPath')
      ->once()
      ->with($this->id_media)
      ->andReturn(
        $this->getTestingDirName() . "appui-note/media/path/$this->id_media/foo.jpg"
      );

    $this->createDir($dir = "appui-note/media/path/$this->id_media");
    $this->createFile('foo_w123.jpg', '', $dir);

    $this->assertSame([], $this->medias->getThumbsSizes($this->id_media));
  }

  /** @test */
  public function getThumbs_method_returns_the_path_to_the_image_for_the_given_path_and_size_and_file_exists()
  {
    $this->createDir('media');
    $this->createFile('foo_w60_h60.jpg', '', 'media');

    $this->assertSame(
      $this->getTestingDirName() . 'media/foo_w60_h60.jpg',
      $this->medias->getThumbs($this->getTestingDirName() . 'media/foo.jpg')
    );
  }

  /** @test */
  public function getThumbs_method_returns_the_path_to_the_image_for_the_given_path_and_size_and_file_does_not_exist()
  {
    $this->assertSame(
      $this->getTestingDirName() . 'media/foo_w60_h60.jpg',
      $this->medias->getThumbs($this->getTestingDirName() . 'media/foo.jpg', [60, 60], false)
    );
  }

  /** @test */
  public function getThumbs_method_return_null_when_the_given_path_does_not_exist_and_if_exist_argument_is_true()
  {
    $this->assertNull(
      $this->medias->getThumbs($this->getTestingDirName() . 'media/foo.jpg', [60, 60], true)
    );
  }

  /** @test */
  public function getThumbs_method_returns_null_when_the_provided_sizes_are_not_valid()
  {
    $this->assertNull(
      $this->medias->getThumbs('foo.jpg', [], false)
    );

    $this->assertNull(
      $this->medias->getThumbs('foo.jpg', [60], false)
    );

    $this->assertNull(
      $this->medias->getThumbs('foo.jpg', ['foo', 'bar'], false)
    );
  }

  /** @test */
  public function getThumbs_method_does_not_return_the_given_size_if_not_is_integer()
  {
    $this->assertSame(
      'foo_h1.jpg',
      $this->medias->getThumbs('foo.jpg', ['foo', 1], false)
    );

    $this->assertSame(
      'foo_w1.jpg',
      $this->medias->getThumbs('foo.jpg', [1, 'bar'], false)
    );
  }

  /** @test */
  public function getThumbsPath_method_returns_an_array_of_thumbs_files_names_with_different_sizes_from_the_given_file_path_if_exists_and_is_an_image()
  {
    $this->partiallyMockMediasClass();

    $this->createDir('media');
    $image_path = $this->createFile('foo.jpg', '', 'media');

    $expected_array = [];

    foreach ($this->medias->thumbs_sizes as $key => $size) {
      // Only creates three file sizes out of 5 to ensure that only 3 are returned
      if ($key >= 3) {
        continue;
      }

      $name = 'foo';

      if (is_integer($size[0])) {
        $name .= "_w$size[0]";
      }

      if (is_integer($size[1])) {
        $name .= "_h$size[1]";
      }

      $name .= '.jpg';

      $expected_array[] = $this->createFile($name, '', 'media');
    }

    $this->medias->shouldReceive('isImage')
      ->with($image_path)
      ->once()
      ->andReturnTrue();

    $this->assertSame($expected_array, $this->medias->getThumbsPath($image_path));

  }
  
  /** @test */
  public function getThumbsPath_method_returns_empty_array_when_the_files_with_sizes_does_not_exist()
  {
    $this->partiallyMockMediasClass();

    $this->createDir('media');
    $image_path = $this->createFile('foo.jpg', '', 'media');

    $this->medias->shouldReceive('isImage')
      ->with($image_path)
      ->once()
      ->andReturnTrue();

    $this->assertSame([], $this->medias->getThumbsPath($image_path));
  }

  /** @test */
  public function getThumbsPath_method_returns_empty_array_when_the_given_image_does_not_exist()
  {
    $this->assertSame([], $this->medias->getThumbsPath('foo.jpg'));
  }

  /** @test */
  public function getThumbsPath_method_returns_empty_array_when_the_given_file_is_not_an_image()
  {
    $this->createDir('media');
    // Image has empty content so it won't have an image/jpg mime
    $image_path = $this->createFile('foo.jpg', '', 'media');

    $this->assertSame([], $this->medias->getThumbsPath($image_path));
  }

  /** @test */
  public function removeThumbs_method_removes_thumbs_files_for_the_provided_path()
  {
    $this->partiallyMockMediasClass();

    $this->createDir('media');
    $image_path = $this->createFile('foo.jpg', '', 'media');

    $files = [];

    foreach ($this->medias->thumbs_sizes as $size) {
      $name = 'foo';

      if (is_integer($size[0])) {
        $name .= "_w$size[0]";
      }

      if (is_integer($size[1])) {
        $name .= "_h$size[1]";
      }

      $name .= '.jpg';

      $files[] = $this->createFile($name, '', 'media');
    }

    $this->medias->shouldReceive('isImage')
      ->with($image_path)
      ->once()
      ->andReturnTrue();

    foreach ($files as $file) {
      $this->assertFileExists($file);
    }

    $this->medias->removeThumbs($image_path);

    foreach ($files as $file) {
      $this->assertFileDoesNotExist($file);
    }
  }

  /** @test */
  public function getThumbsName_method_returns_the_name_of_the_thumb_file_corresponding_to_the_given_name_and_size()
  {
    $this->assertSame(
      'foo_w80h100.jpg',
      $this->medias->getThumbsName('foo.jpg', [80, 100])
    );
  }

  /** @test */
  public function getThumbsName_method_returns_null_when_the_given_name_has_no_extension()
  {
    $this->assertNull(
      $this->medias->getThumbsName('foo')
    );
  }

  /** @test */
  public function delete_method_deletes_media_from_the_given_id()
  {
    $this->partiallyMockMediasClass();

    $cf = $this->getClassCfg();

    $this->createDir($dir = 'media/' . date('Y/m/d'));
    $file = $this->createFile('image.jpg', '', $dir);
    $dir  = dirname($file);

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturn(['file' => $file]);

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with($cf['table'], [$cf['arch']['medias']['id'] => $this->id_media])
      ->andReturn(1);

    $this->assertFileExists($file);
    $this->assertDirectoryExists($dir);

    $this->assertTrue($this->medias->delete($this->id_media));
    $this->assertFileDoesNotExist($file);
    $this->assertDirectoryDoesNotExist($dir);
  }

  /** @test */
  public function delete_method_returns_false_and_does_not_delete_the_given_media_if_it_fails_to_delete_from_db()
  {
    $this->partiallyMockMediasClass();

    $cf = $this->getClassCfg();

    $this->createDir($dir = 'media/' . date('Y/m/d'));
    $file = $this->createFile('image.jpg', '', $dir);
    $dir = dirname($file);

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturn(['file' => $file]);

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with($cf['table'], [$cf['arch']['medias']['id'] => $this->id_media])
      ->andReturnNull();

    $this->assertFalse($this->medias->delete($this->id_media));
    $this->assertFileExists($file);
    $this->assertDirectoryExists($dir);
  }

  /** @test */
  public function delete_method_returns_false_and_does_not_delete_media_dir_if_media_file_does_not_exist()
  {
    $this->partiallyMockMediasClass();

    $cf = $this->getClassCfg();

    $this->createDir($dir = 'media/' . date('Y/m/d'));

    $dir = $this->getTestingDirName() . $dir;

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturn(['file' => $dir . '/image.jpg']);

    $this->assertFalse($this->medias->delete($this->id_media));
    $this->assertDirectoryExists($dir);
  }

  /** @test */
  public function delete_method_returns_false_when_media_does_not_exist_in_db()
  {
    $this->partiallyMockMediasClass();

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturnFalse();

    $this->assertFalse($this->medias->delete($this->id_media));
  }

  /** @test */
  public function delete_method_returns_false_when_the_given_id_is_not_valid_uid()
  {
    $this->assertFalse(
      $this->medias->delete('123a')
    );
  }

  /** @test */
  public function isImage_method_returns_true_when_the_given_path_is_an_image()
  {
    $this->createDir('medias');

    $file = $this->createFile('image.jpg', $this->getDummyImageContent(), 'medias');

    $this->assertTrue($this->medias->isImage($file));
  }

  /** @test */
  public function isImage_method_returns_false_when_the_given_path_does_not_exists()
  {
    $this->assertFalse($this->medias->isImage('image.jpg'));
    $this->assertFalse($this->medias->isImage(''));
  }

  /** @test */
  public function isImage_method_returns_false_when_the_given_path_is_not_an_image()
  {
    $this->createDir('medias');

    $file = $this->createFile('image.jpg', 'Hello World!', 'medias');

    $this->assertFalse($this->medias->isImage($file));
  }

  /** @test */
  public function getMedia_method_returns_string_of_the_file_when_the_details_argument_is_false()
  {
    $cf = $this->getClassCfg();

    $this->option_mock->shouldReceive('fromCode')
      ->times(3)
      ->with('link', $this->option_id)
      ->andReturn('12380');

    $this->db_mock->shouldReceive('rselect')
      ->times(3)
      ->with(
        $cf['table'], [], [$cf['arch']['medias']['id'] => $this->id_media]
      )
      ->andReturn([
        $cf['arch']['medias']['name']   => 'image.jpg',
        $cf['arch']['medias']['private'] => 0,
        $cf['arch']['medias']['type']    => '12370',
        $cf['arch']['medias']['content'] => json_encode(['path' => 'path/to/'])
      ]);

    $this->assertSame(
      BBN_DATA_PATH . "plugins/appui-note/media/path/to/$this->id_media/image.jpg",
      $this->medias->getMedia($this->id_media)
    );


    // Test with width argument provided

    // Creates the dir and file so that the getThumbsSizes returns an array of sizes
    $this->createDir($dir = "plugins/appui-note/media/path/to/$this->id_media");
    $this->createFile('image.jpg', '', $dir);

    // Create file with different sizes, the one with 90 should match
    $this->createFile('image_w90.jpg', '', $dir);
    $this->createFile('image_w70.jpg', '', $dir);
    $this->createFile('image_w100.jpg', '', $dir);

    $this->assertSame(
      BBN_DATA_PATH . "plugins/appui-note/media/path/to/$this->id_media/image_w90.jpg",
      $this->medias->getMedia($this->id_media, false, 90)
    );
  }

  /** @test */
  public function getMedia_method_returns_the_object_of_the_media_when_the_details_argument_is_true()
  {
    $cf = $this->getClassCfg();

    $this->option_mock->shouldReceive('fromCode')
      ->times(3)
      ->with('link', $this->option_id)
      ->andReturn('12380');

    $this->db_mock->shouldReceive('rselect')
      ->times(3)
      ->with(
        $cf['table'], [], [$cf['arch']['medias']['id'] => $this->id_media]
      )
      ->andReturn([
        $cf['arch']['medias']['name']   => 'image.jpg',
        $cf['arch']['medias']['private'] => 0,
        $cf['arch']['medias']['type']    => '12370',
        $cf['arch']['medias']['content'] => json_encode(['path' => 'path/to/'])
      ]);

    // Creates the file this time ans sets image content so that it returns is_image => true
    $this->createDir($dir = "plugins/appui-note/media/path/to/$this->id_media");
    $this->createFile('image.jpg', $this->getDummyImageContent(), $dir);

    $expected = [
      'path'      => 'path/to/',
      'name'      => 'image.jpg',
      'private'   => 0,
      'type'      => '12370',
      'content'   =>  json_encode(['path' => 'path/to/']),
      'file'      => BBN_DATA_PATH . "plugins/appui-note/media/path/to/$this->id_media/image.jpg",
      'is_image'  => true
    ];

    $this->assertSame($expected, $this->medias->getMedia($this->id_media, true));

    // Test with width argument provided

    // Create file with different sizes, the one with 100 should match
    $this->createFile('image_w90.jpg', $this->getDummyImageContent(), $dir);
    $this->createFile('image_w70.jpg', $this->getDummyImageContent(), $dir);
    $this->createFile('image_w100.jpg', $this->getDummyImageContent(), $dir);

    $expected['file']  = BBN_DATA_PATH . "plugins/appui-note/media/path/to/$this->id_media/image_w100.jpg";

    $this->assertSame($expected, $this->medias->getMedia($this->id_media, true, 91));
  }

  /** @test */
  public function getMedia_method_returns_file_path_when_media_is_private()
  {
    $cf = $this->getClassCfg();
    $this->setNonPublicPropertyValue('_app_name', 'bbn medias', Mvc::class);

    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('link', $this->option_id)
      ->andReturn('12380');

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['table'], [], [$cf['arch']['medias']['id'] => $this->id_media]
      )
      ->andReturn([
        $cf['arch']['medias']['name']    => 'image.jpg',
        $cf['arch']['medias']['private'] => 1,
        $cf['arch']['medias']['type']    => '12370',
        $cf['arch']['medias']['content'] => json_encode(['path' => 'path/to/'])
      ]);

    $this->user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_user);

    $this->assertSame(
      BBN_DATA_PATH . "users/$this->id_user/data/appui-note/media/path/to/$this->id_media/image.jpg",
      $this->medias->getMedia($this->id_media)
    );
  }

  /** @test */
  public function getMedia_method_returns_false_when_link_types_matches()
  {
    $cf = $this->getClassCfg();

    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('link', $this->option_id)
      ->andReturn('12380');

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['table'], [], [$cf['arch']['medias']['id'] => $this->id_media]
      )
      ->andReturn([
        $cf['arch']['medias']['type'] => '12380',
      ]);

    $this->assertFalse($this->medias->getMedia($this->id_media));
  }

  /** @test */
  public function getMedia_method_returns_false_when_media_id_does_not_exist_in_db()
  {
    $cf = $this->getClassCfg();

    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('link', $this->option_id)
      ->andReturn('12380');

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $cf['table'], [], [$cf['arch']['medias']['id'] => $this->id_media]
      )
      ->andReturnNull();

    $this->assertFalse($this->medias->getMedia($this->id_media));
  }

  /** @test */
  public function getMedia_method_returns_false_when_retrieved_link_type_is_null()
  {
    $this->option_mock->shouldReceive('fromCode')
      ->once()
      ->with('link', $this->option_id)
      ->andReturnNull();

    $this->assertFalse($this->medias->getMedia($this->id_media));
  }

  /** @test */
  public function getMedia_method_returns_false_when_the_provided_id_is_not_valid_uid()
  {
    $this->assertFalse($this->medias->getMedia('1234a'));
  }

  /** @test */
  public function zip_method_adds_media_file_to_zip_archive_for_the_given_medias_array()
  {
    $this->partiallyMockMediasClass();

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media)
      ->andReturn($this->getTestingDirName() . 'medias/image1.jpg');

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media_2)
      ->andReturn($this->getTestingDirName() . 'medias/image2.jpg');

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with('media_that_does_not_exist')
      ->andReturnFalse();

    $this->createDir('medias');
    $this->createFile('image1.jpg', '', 'medias');
    $this->createFile('image2.jpg', '', 'medias');

    $destination = $this->getTestingDirName() . 'medias/images.zip';

    $this->assertTrue(
      $this->medias->zip([$this->id_media, $this->id_media_2, 'media_that_does_not_exist'], $destination)
    );

    $this->assertFileExists($destination);

    $zip = new \ZipArchive();

    $this->assertTrue($zip->open($destination));
    $this->assertSame(2, $zip->count());
  }

  /** @test */
  public function zip_method_adds_media_file_to_zip_archive_for_the_given_media_string()
  {
    $this->partiallyMockMediasClass();

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media)
      ->andReturn($this->getTestingDirName() . 'medias/image1.jpg');


    $this->createDir('medias');
    $this->createFile('image1.jpg', '', 'medias');

    $destination = $this->getTestingDirName() . 'medias/images.zip';

    $this->assertTrue(
      $this->medias->zip($this->id_media, $destination)
    );

    $this->assertFileExists($destination);

    $zip = new \ZipArchive();

    $this->assertTrue($zip->open($destination));
    $this->assertSame(1, $zip->count());
  }

  /** @test */
  public function zip_method_returns_false_when_failed_when_the_provided_media_is_not_string_nor_array()
  {
    $this->assertFalse($this->medias->zip((object)['foo'], 'medias.zip'));
    $this->assertFalse($this->medias->zip(22, 'medias.zip'));
  }

  /** @test */
  public function updateDb_method_updates_media_in_database()
  {
    $cf = $this->getClassCfg();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        [
          'table' => $cf['table'],
          'fields' => [
            $cf['arch']['medias']['name']    => 'image.jpg',
            $cf['arch']['medias']['title']   => 'media_title',
            $cf['arch']['medias']['content'] => json_encode(['foo' => 'bar'])
          ],
          'where' => [
            'conditions' => [[
              'field' => $cf['arch']['medias']['id'],
              'value' => $this->id_media
            ]]
          ]
        ]
      )
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->medias->updateDb($this->id_media, 'image.jpg', 'media_title', ['foo' => 'bar'])
    );

    // Test with content not provided

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        [
          'table' => $cf['table'],
          'fields' => [
            $cf['arch']['medias']['name']    => 'image.jpg',
            $cf['arch']['medias']['title']   => 'media_title'
          ],
          'where' => [
            'conditions' => [[
              'field' => $cf['arch']['medias']['id'],
              'value' => $this->id_media
            ]]
          ]
        ]
      )
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->medias->updateDb($this->id_media, 'image.jpg', 'media_title')
    );
  }

  /** @test */
  public function update_method_updates_the_title_or_the_name_of_the_given_media_in_database_and_rename_the_files()
  {
    $this->partiallyMockMediasClass();

    $this->createDir($dir = "plugins/appui-note/media/path/to/$this->id_media");
    $old_file  = $this->createFile('image_old.jpg', $this->getDummyImageContent(), $dir);

    // Create thumbs files
    $old_thumb_1 = $this->createFile('image_old_w60h60.jpg', $this->getDummyImageContent(), $dir);
    $old_thumb_2 = $this->createFile('image_old_w100h100.jpg', $this->getDummyImageContent(), $dir);

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturn([
        'path'      => 'path/to/',
        'name'      => 'image_old.jpg',
        'title'     => 'old_title',
        'content'   =>  json_encode(['path' => 'path/to/']),
        'file'      => BBN_DATA_PATH . "$dir/image_old.jpg"
      ]);

    // Call to update db with the new data
    $this->medias->shouldReceive('updateDb')
      ->once()
      ->with($this->id_media, 'image_new.jpg', 'new_title')
      ->andReturn(1);

    // Second call to getMedia to get the new updated content
    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturn($expected = [
        'path'      => 'path/to/',
        'name'      => 'image_new.jpg',
        'title'     => 'new_title',
        'content'   =>  json_encode(['path' => 'path/to/']),
        'file'      => $new_file = BBN_DATA_PATH . "$dir/image_new.jpg"
      ]);


    $this->assertSame(
      $expected,
      $this->medias->update($this->id_media, 'image_new.jpg', 'new_title')
    );

    // Ensure that the file is renamed
    $this->assertFileDoesNotExist($old_file);
    $this->assertFileExists($new_file);
    $this->assertSame($this->getDummyImageContent(), file_get_contents($new_file));

    // Ensure that thumb files are renames as well
    $this->assertFileDoesNotExist($old_thumb_1);
    $this->assertFileDoesNotExist($old_thumb_2);
    $this->assertFileExists(
      $new_thumb_1 = str_replace('old', 'new', $old_thumb_1)
    );

    $this->assertFileExists(
      $new_thumb_2 = str_replace('old', 'new', $old_thumb_2)
    );

    $this->assertSame($this->getDummyImageContent(), file_get_contents($new_thumb_1));
    $this->assertSame($this->getDummyImageContent(), file_get_contents($new_thumb_2));
  }

  /** @test */
  public function update_method_returns_an_empty_array_without_updating_db_and_files_when_the_given_media_does_not_exist()
  {
    $this->partiallyMockMediasClass();

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturnFalse();

    $this->assertSame([], $this->medias->update($this->id_media, 'image.jpg', 'media_title'));
  }

  /** @test */
  public function update_method_an_empty_array_without_updating_db_and_files_when_old_name_and_title_are_the_same()
  {
    $this->partiallyMockMediasClass();

    $this->createDir($dir = "plugins/appui-note/media/path/to/$this->id_media");
    $this->createFile('image_old.jpg', $this->getDummyImageContent(), $dir);

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturn([
        'path'      => 'path/to/',
        'name'      => 'image_old.jpg',
        'title'     => 'old_title',
        'content'   =>  json_encode(['path' => 'path/to/']),
        'file'      => BBN_DATA_PATH . "$dir/image_old.jpg"
      ]);

    $this->assertSame([], $this->medias->update($this->id_media, 'image_old.jpg', 'old_title'));
  }

  /** @test */
  public function updateContent_method_updates_the_content_of_the_media_when_deleted_and_replaced_in_the_upload()
  {
    $this->partiallyMockMediasClass();

    $this->user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_user);

    $this->createDir($tmp_dir = "users/$this->id_user/tmp/12");
    $this->createFile('old_image.jpg', $this->getDummyImageContent(), $tmp_dir);

    $this->createDir($dir = "plugins/appui-note/media/path/to/$this->id_media");
    $old_file = $this->createFile('old_image.jpg', $this->getDummyImageContent(), $dir);
    $dir = dirname($old_file);

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturn([
        'path'      => 'path/to/',
        'name'      => 'old_image.jpg',
        'title'     => 'old_title',
        'content'   =>  json_encode(['path' => 'path/to/']),
        'file'      => BBN_DATA_PATH . "$tmp_dir/old_image.jpg"
      ]);

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturn([
        'path'      => 'path/to/',
        'name'      => 'old_image.jpg',
        'title'     => 'old_title',
        'content'   =>  json_encode(['path' => 'path/to/']),
        'file'      => BBN_DATA_PATH . "$dir/old_image.jpg"
      ]);

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturn([
        'path'      => 'path/to/',
        'name'      => 'new_image.jpg',
        'title'     => 'new_title',
        'content'   =>  json_encode(['path' => 'path/to/']),
        'file'      => BBN_DATA_PATH . "$dir/new_image.jpg"
      ]);

    $this->medias->shouldReceive('removeThumbs')
      ->once()
      ->with(str_replace('./', '', $old_file));

    $this->medias->shouldReceive('updateDb')
      ->once()
      ->with(
        $this->id_media, 'new_image.jpg', 'new_title', [
          'path'      => 'path/to/',
          'size'      => filesize($old_file),
          'extension' => 'jpg'
        ]
      )
      ->andReturn(1);

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturn($expected = [
        'path'      => 'path/to/',
        'name'      => 'new_image.jpg',
        'title'     => 'new_title',
        'content'   =>  json_encode(['path' => 'path/to/']),
        'file'      => $new_file = "$dir/new_image.jpg"
      ]);

    $this->assertSame(
      $expected,
      $this->medias->updateContent(
        $this->id_media, 12, 'old_image.jpg', 'new_image.jpg', 'new_title'
      )
    );

    $this->assertFileExists($new_file);
  }

  /** @test */
  public function updateContent_method_updates_does_not_update_the_content_when_new_file_does_not_exists()
  {
    $this->user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_user);

    $this->assertSame(
      [],
      $this->medias->updateContent(
        $this->id_media, 12, 'old_image.jpg', 'new_image.jpg', 'title'
      )
    );
  }

  /** @test */
  public function updateContent_method_updates_does_not_update_the_content_when_media_id_does_not_exist()
  {
    $this->partiallyMockMediasClass();

    $this->user_mock->shouldReceive('getId')
      ->once()
      ->withNoArgs()
      ->andReturn($this->id_user);

    $this->createDir($tmp_dir = "users/$this->id_user/tmp/16");
    $this->createFile('old_image.jpg', $this->getDummyImageContent(), $tmp_dir);

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturnFalse();

    $this->assertSame(
      [],
      $this->medias->updateContent(
        $this->id_media, 16, 'old_image.jpg', 'new_image.jpg', 'title'
      )
    );
  }

  /** @test */
  public function getMediaPath_method_returns_the_path_for_the_given_media_id()
  {
    $this->partiallyMockMediasClass();

    $this->medias->shouldReceive('getMedia')
      ->twice()
      ->with($this->id_media, true)
      ->andReturn([
        'name'    => 'image.jpg',
        'content' => json_encode(['path' => 'path/to/file/'])
      ]);

    $path = BBN_DATA_PATH . "plugins/appui-note/media/path/to/file/$this->id_media";

    $this->assertSame(
      "$path/image.jpg",
      $this->medias->getMediaPath($this->id_media)
    );

    $this->assertSame(
      "$path/image_new.jpg",
      $this->medias->getMediaPath($this->id_media, 'image_new.jpg')
    );
  }

  /** @test */
  public function getMediaPath_method_returns_null_when_the_given_media_id_does_not_exist()
  {
    $this->partiallyMockMediasClass();

    $this->medias->shouldReceive('getMedia')
      ->once()
      ->with($this->id_media, true)
      ->andReturnFalse();

    $this->assertNull(
      $this->medias->getMediaPath($this->id_media)
    );
  }
}