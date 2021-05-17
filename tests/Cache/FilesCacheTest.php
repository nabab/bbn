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
      $this->cache = Cache::getEngine('files');
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
    public function it_returns_the_given_key_if_stored()
    {
        $this->cache->set('foo', 'bar');

        $this->assertSame('bar', $this->cache->get('foo'));
    }


    /** @test */
    public function it_returns_false_if_the_given_key_not_stored()
    {
        $this->assertFalse($this->cache->get('foobar'));
    }


    /** @test */
    public function it_returns_false_if_the_given_key_is_stored_but_expired()
    {
        $this->cache->set('foo', 'bar', 15);

        $this->assertFalse($this->cache->get('foo', 20));
    }


    /** @test */
    public function it_deletes_the_given_key()
    {
        $this->cache->set('foo', 'bar');
        $this->cache->delete('foo');

        $this->cache->set($cache_name = $this->generateCacheName('Foo\Bar'), 'baz');
        $this->cache->delete($cache_name);

        $this->assertFalse($this->cache->get('foo'));
        $this->assertFalse($this->cache->get($cache_name));
    }


    /** @test */
    public function it_sets_the_given_key_with_the_new_value_if_stored_but_expired()
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
    public function it_does_not_set_the_given_key_with_the_new_value_if_stored_and_valid()
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
    public function it_clears_all_cache_if_no_path_is_provided()
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
    public function it_deletes_cache_only_for_the_provided_path()
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
    public function it_returns_true_if_cache_key_exists_and_valid()
    {
        $this->cache->set('foo', 'bar', 20);
        $this->assertTrue($this->cache->has('foo'));
    }


    /** @test */
    public function it_returns_false_if_cache_key_does_not_exit()
    {
        $this->cache->set('foo', 'bar', 20);
        $this->assertFalse($this->cache->has('baz'));
    }


    /** @test */
    public function it_returns_false_if_cache_key_exits_but_not_valid()
    {
        $this->cache->set('foo', 'bar', 20);
        $this->assertFalse($this->cache->has('foo', 21));
    }


    /** @test */
    public function it_returns_items_in_cache()
    {
        $this->cache->set('foo', 'bar');
        $this->cache->set('foobar', 'baz');

        $this->assertSame(['foo', 'foobar'], $this->cache->items());

    }


    /** @test */
    public function it_returns_info_of_the_file()
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
