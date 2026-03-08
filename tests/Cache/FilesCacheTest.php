<?php

use bbn\Cache;
use PHPUnit\Framework\TestCase;

class FilesCacheTest extends TestCase
{
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


    protected $cache;

    protected $cache_uid = [
        'foo' => 'bar'
    ];


    /**
     * @param string $prefix
     * @param string $method
     *
     * @return string
     */
    protected function generateCacheName(string $prefix, string $method = 'test'): string
    {
        return $prefix . md5(serialize($this->cache_uid)) . $method;
    }


    /** @test */
    public function testItReturnsTheGivenKeyIfStored()
    {
        $this->cache->set('foo', 'bar');

        $this->assertSame('bar', $this->cache->get('foo'));
    }


    /** @test */
    public function testItReturnsFalseIfTheGivenKeyNotStored()
    {
        $this->assertFalse($this->cache->get('foobar'));
    }


    /** @test */
    public function testItReturnsFalseIfTheGivenKeyIsStoredButExpired()
    {
        $this->cache->set('foo', 'bar', 15);

        $this->assertFalse($this->cache->get('foo', 20));
    }


    /** @test */
    public function testItDeletesTheGivenKey()
    {
        $this->cache->set('foo', 'bar');
        $this->cache->delete('foo');

        $this->cache->set($cache_name = $this->generateCacheName('Foo\Bar'), 'baz');
        $this->cache->delete($cache_name);

        $this->assertFalse($this->cache->get('foo'));
        $this->assertFalse($this->cache->get($cache_name));
    }


    /** @test */
    public function testItSetsTheGivenKeyWithTheNewValueIfStoredButExpired()
    {
        $this->cache->set('foo', 'bar', 15);

        $result = $this->cache->getSet(
          function () {
            return 'baz';
          }, 'foo', 20
        );

        $this->assertSame($result, 'baz');
    }


    /** @test */
    public function testItDoesNotSetTheGivenKeyWithTheNewValueIfStoredAndValid()
    {
        $this->cache->set('foo', 'bar', 15);

        $result = $this->cache->getSet(
          function () {
            return 'baz';
          }, 'foo', 10
        );

        $this->assertSame($result, 'bar');
    }


    /** @test */
    public function testItClearsAllCacheIfNoPathIsProvided()
    {
        $this->cache->set('foo', 'bar');
        $this->cache->set(
          $cache_name = $this->generateCacheName('foobar/'),
          'baz'
        );

        $this->assertSame($this->cache->get('foo'), 'bar');
        $this->assertSame($this->cache->get($cache_name), 'baz');

        $this->cache->clear();

        $this->assertFalse($this->cache->get('foo'));
        $this->assertFalse($this->cache->get($cache_name));
    }


    /** @test */
    public function testItDeletesCacheOnlyForTheProvidedPath()
    {
        $cache_name1 = $this->generateCacheName('Foo/Bar/');
        $cache_name2 = $this->generateCacheName('Foo/Baz/');

        $this->cache->set($cache_name1, 'foo');
        $this->cache->set($cache_name2, 'bar');

        $this->cache->deleteAll($cache_name1);

        $this->assertFalse($this->cache->get($cache_name1));
        $this->assertSame('bar', $this->cache->get($cache_name2));
    }


    /** @test */
    public function testItReturnsTrueIfCacheKeyExistsAndValid()
    {
        $this->cache->set('foo', 'bar', 20);
        $this->assertTrue($this->cache->has('foo'));
    }


    /** @test */
    public function testItReturnsFalseIfCacheKeyDoesNotExit()
    {
        $this->cache->set('foo', 'bar', 20);
        $this->assertFalse($this->cache->has('baz'));
    }


    /** @test */
    public function testItReturnsFalseIfCacheKeyExitsButNotValid()
    {
        $this->cache->set('foo', 'bar', 20);
        $this->assertFalse($this->cache->has('foo', 21));
    }


    /** @test */
    public function testItReturnsItemsInCache()
    {
        $this->cache->set('foo', 'bar');
        $this->cache->set('foobar', 'baz');

        $this->assertSame(['foo', 'foobar'], $this->cache->items());

    }


    /** @test */
    public function testItReturnsInfoOfTheFile()
    {
        $this->cache->set('foo', 'bar');
        $this->cache->set('foobar', 'baz');

        $class_reflection = new ReflectionClass(Cache::class);
        $path             = $class_reflection->getProperty('path');

        $path->setAccessible(true);
        $path = $path->getValue($this->cache);

        $this->assertSame(
          ["{$path}foo.bbn.cache", "{$path}foobar.bbn.cache"],
          $this->cache->info()
        );
    }


}
