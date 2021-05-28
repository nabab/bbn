<?php

use bbn\Cache;
use PHPUnit\Framework\TestCase;

class CacheUnitTest extends TestCase
{

    protected $cache;


    /**
     * @return void
     */
  protected function setUp(): void
  {
      $this->cache = Cache::getEngine('files');
  }


    /**
     * @return void
     */
  protected function tearDown(): void
  {
      $this->cache->clear();
  }


    /** @test */
  public function it_makes_a_unique_hash_of_strings()
  {
      $hash = Cache::makeHash('foo');

      $this->assertSame(md5('foo'), $hash);
  }


    /** @test */
  public function it_makes_a_unique_hash_of_arrays()
  {
      $hash   = Cache::makeHash(
        $data = ['foo' => 'bar']
      );

      $this->assertSame(md5(serialize($data)), $hash);
  }


    /** @test */
  public function it_makes_a_unique_hash_of_objects()
  {
      $hash   = Cache::makeHash(
        $data = (object)['foo' => 'bar']
      );

      $this->assertSame(md5(serialize($data)), $hash);
  }


    /** @test */
  public function it_returns_the_ttl_as_is_if_parameter_is_integer()
  {
      $ttl = Cache::ttl(12);

      $this->assertSame(12, $ttl);
  }


    /** @test */
  public function it_returns_the_ttl_in_seconds_if_parameter_is_string()
  {
      $options = [
        'xxs' => 30,
        'xs'  => 60,
        's'   => 300,
        'm'   => 3600,
        'l'   => 3600 * 24,
        'xl'  => 3600 * 24 * 7,
        'xxl' => 3600 * 24 * 30
      ];

      foreach ($options as $param => $value) {
          $ttl = Cache::ttl($param);

          $this->assertSame($value, $ttl);
      }
  }


    /** @test */
  public function it_throws_exception_when_ttl_is_not_valid()
  {
      $this->expectException(\Exception::class);

      Cache::ttl('test');
  }


    /** @test */
  public function it_returns_the_type_of_cache_engine()
  {
      $this->assertSame('files', Cache::getType());
  }


    /** @test */
  public function it_cannot_be_created_with_the_new_keyword_if_an_instance_was_already_created()
  {
      $this->expectException(Exception::class);

      Cache::getEngine('files');
      new Cache();
  }


    /** @test */
  public function it_returns_the_timestamp_of_the_given_item()
  {
      $this->cache->set('foo', 'bar', 30);
      $file = $this->invokeGetRawMethod('foo');

      $this->assertSame((int)$file['timestamp'], $this->cache->timestamp('foo'));
  }


    /** @test */
  public function it_returns_the_hash_of_the_given_item()
  {
      $this->cache->set('foo', 'bar');
      $file = $this->invokeGetRawMethod('foo');

      $this->assertSame($file['hash'], $this->cache->hash('foo'));
  }


    /** @test */
  public function it_checks_whether_or_not_the_given_item_is_more_recent_than_the_given_timestamp()
  {
      $this->cache->set('foo', 'bar');
      $file = $this->invokeGetRawMethod('foo');

      $this->assertTrue(
        $this->cache->isNew('foo', $file['timestamp'] - 10)
      );

      $this->assertFalse(
        $this->cache->isNew('foo', $file['timestamp'] + 10)
      );
  }


    /** @test */
  public function it_checks_if_the_value_of_the_item_corresponds_to_the_given_hash()
  {
      $this->cache->set('foo', 'bar');

      $this->assertFalse(
        $this->cache->isChanged('foo', $this->cache->hash('foo'))
      );

      $this->assertTrue(
        $this->cache->isChanged('foo', md5('dummy'))
      );
  }


    /**
     * @param string $args
     *
     * @return mixed
     * @throws ReflectionException
     */
  protected function invokeGetRawMethod(string $args)
  {
      $class_reflection = new ReflectionClass(Cache::class);
      $method           = $class_reflection->getMethod('getRaw');
      $method->setAccessible(true);

      return $method->invoke(Cache::getEngine(), $args);
  }


}
