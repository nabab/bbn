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
      $this->cache = Cache::getEngine();
  }


    /**
     * @return void
     */
  protected function tearDown(): void
  {
      $this->cache->clear();
  }


    /** @test */
  public function testItMakesAUniqueHashOfStrings()
  {
      $hash = Cache::makeHash('foo');

      $this->assertSame(md5('foo'), $hash);
  }


    /** @test */
  public function testItMakesAUniqueHashOfArrays()
  {
      $hash   = Cache::makeHash(
        $data = ['foo' => 'bar']
      );

      $this->assertSame(md5(serialize($data)), $hash);
  }


    /** @test */
  public function testItMakesAUniqueHashOfObjects()
  {
      $hash   = Cache::makeHash(
        $data = (object)['foo' => 'bar']
      );

      $this->assertSame(md5(serialize($data)), $hash);
  }


    /** @test */
  public function testItReturnsTheTtlAsIsIfParameterIsInteger()
  {
      $ttl = Cache::ttl(12);

      $this->assertSame(12, $ttl);
  }


    /** @test */
  public function testItReturnsTheTtlInSecondsIfParameterIsString()
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
  public function testItThrowsExceptionWhenTtlIsNotValid()
  {
      $this->expectException(\Exception::class);

      Cache::ttl('test');
  }


    /** @test */
  public function testItReturnsTheTypeOfCacheEngine()
  {
      $this->assertSame('files', Cache::getType());
  }


    /** @test */
  public function testItCannotBeCreatedWithTheNewKeywordIfAnInstanceWasAlreadyCreated()
  {
      $this->expectException(Exception::class);

      Cache::getEngine();
      new Cache();
  }


    /** @test */
  public function testItReturnsTheTimestampOfTheGivenItem()
  {
      $this->cache->set('foo', 'bar', 30);
      $file = $this->invokeGetRawMethod('foo');

      $this->assertSame((int)$file['timestamp'], $this->cache->timestamp('foo'));
  }


    /** @test */
  public function testItReturnsTheHashOfTheGivenItem()
  {
      $this->cache->set('foo', 'bar');
      $file = $this->invokeGetRawMethod('foo');

      $this->assertSame($file['hash'], $this->cache->hash('foo'));
  }


    /** @test */
  public function testItChecksWhetherOrNotTheGivenItemIsMoreRecentThanTheGivenTimestamp()
  {
      $this->cache->set('foo', 'bar');
      $file = $this->invokeGetRawMethod('foo');

      $this->assertTrue(
        $this->cache->isAfter('foo', $file['timestamp'] - 10)
      );

      $this->assertFalse(
        $this->cache->isAfter('foo', $file['timestamp'] + 10)
      );
  }


    /** @test */
  public function testItChecksIfTheValueOfTheItemCorrespondsToTheGivenHash()
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
