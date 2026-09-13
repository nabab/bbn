<?php
namespace bbn;

use Exception;
use RuntimeException;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Dependency injector and class container
 *
 * @category  Dependency Injection
 * @package bbn
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version Release: <package_version>
 * @link https://bbn.io/bbn-php/doc/class/di
 * @since Aug 14, 2026, 01:58:55 +0200
 */
class Di
{
	/** @var array<string, string> Binds an abstract class or interface to a concrete class */
	private array $bindings = [];

	
	private array $shared = [];

	private array $instances = [];

	private array $resolving = [];

	public function bind(string $abstract, string $concrete): void
	{
		$this->bindings[$abstract] = $concrete;
	}

	/**
	 * 
	 *
	 * @param string $abstract
	 * @param string $concrete
	 * @return void
	 */
	public function singleton(string $abstract, string $concrete): void
	{
		$this->bindings[$abstract] = $concrete;
		$this->shared[$abstract]   = true;
	}

	/**
	 * Creates or retrieves an instance for the given class name
	 *
	 * @param string $className
	 * @return object
	 */
	public function get(string $className): object
	{
		if (isset($this->instances[$className])) {
			return $this->instances[$className];
		}

		if (isset($this->resolving[$className])) {
			$chain = implode(' -> ', array_keys($this->resolving));
			throw new RuntimeException(X::_("Circular dependency detected: $chain -> $className"));
		}

		$this->resolving[$className] = true;

		try {
			$requestedClass = $className;

			if (isset($this->bindings[$className])) {
				$className = $this->bindings[$className];
			}

			$reflection = new ReflectionClass($className);

			if (! $reflection->isInstantiable()) {
				throw new RuntimeException(X::_("%s is not instantiable", $className));
			}

			$constructor = $reflection->getConstructor();

			if ($constructor === null) {
				$instance = new $className();
			}
			else {
				$dependencies = [];

				foreach ($constructor->getParameters() as $parameter) {
					$type = $parameter->getType();

					if (!$type instanceof ReflectionNamedType
						|| $type->isBuiltin()
					) {
						throw new RuntimeException(X::_("Cannot resolve \$%s in %s", $parameter->getName(), $className));
					}

					$dependencies[] = $this->get($type->getName());
				}

				$instance = $reflection->newInstanceArgs($dependencies);
			}

			if (isset($this->shared[$requestedClass])) {
				$this->instances[$requestedClass] = $instance;
			}

			return $instance;
		}
		finally {
			unset($this->resolving[$requestedClass]);
		}
	}
}
