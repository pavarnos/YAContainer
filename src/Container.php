<?php

declare(strict_types=1);

namespace LSS\YAContainer;

use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;

use function interface_exists;
use function is_callable;

class Container implements ContainerInterface
{
    /**
     * All classes are shared by default
     * @var array<string,mixed> class name => class instance
     */
    private $shared = [];

    /**
     * eg MyFooInterface::class => MyFooImplementation::class
     * @var array<string,string> alias name => real name
     */
    private $alias = [];

    /**
     * scalar / builtin parameter values
     * @var array<string,int|string|float|bool|callable> name => value
     */
    private $scalar = [];

    /**
     * a callable that can build this class
     * @var array<string,callable> class or interface name => callable
     */
    private $factory = [];

    /**
     * @var array<string,int> a stack of class name => depth for error reporting and to prevent circular dependencies
     */
    private $building = [];

    /**
     * @var Closure function(string): bool return true if get() should return a shared instance of a class,
     * false to built it fresh each time
     */
    private $shouldShare;

    /**
     * @param array<string,mixed>  $scalar see addScalar()
     * @param array<string,string> $alias  see addAlias()
     */
    public function __construct(array $scalar = [], array $alias = [])
    {
        $this->alias       = $alias;
        $this->scalar      = $scalar;
        $this->shouldShare = function (string $name): bool {
            return true;
        };
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $name Identifier of the entry to look for.
     *
     * @return mixed Entry.
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface
     */
    public function get($name)
    {
        if (isset($this->alias[$name])) {
            // resolve aliases. Aliases must resolve directly to a concrete class. Recursive resolution does not work
            $name = $this->alias[$name];
        }
        if (isset($this->shared[$name])) {
            return $this->shared[$name];
        }

        // circular dependency check
        if (isset($this->building[$name])) {
            throw new ContainerException($this->building, 'Circular dependency while building ' . $name);
        }
        $this->building[$name] = count($this->building);

        if (isset($this->factory[$name])) {
            $result = $this->runFactory($this->factory[$name]);
        } else {
            $result = $this->makeClass($name);
        }

        if (($this->shouldShare)($name)) {
            $this->shared[$name] = $result;
        }

        unset($this->building[$name]);
        return $result;
    }

    /**
     * inject a pre-built class into the container, or replace an existing value (eg for tests)
     * @param string $name
     * @param mixed  $classInstance
     */
    public function set(string $name, $classInstance): void
    {
        $this->shared[$name] = $classInstance;
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $name Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($name)
    {
        return isset($this->shared[$name])
            || class_exists($name)
            || interface_exists($name)
            || isset($this->alias[$name])
            || isset($this->scalar[$name])
            || isset($this->factory[$name]);
    }

    /**
     * Delete any shared instances of $className.
     * get($className) will return a freshly constructed instance of $className next time you call it.
     * @param string $name
     * @return $this
     */
    public function forget(string $name): self
    {
        unset($this->shared[$name]);
        return $this;
    }

    /**
     * Alias must resolve to a real instantiable class in one step. An alias cannot refer to another alias.
     * Aliases only work for class names: they are not used for scalars. If you want to alias a scalar, add it again
     * with the aliased name using addScalar()
     * @param string $alias
     * @param string $realName
     * @return self
     */
    public function addAlias(string $alias, string $realName): self
    {
        assert(!isset($this->alias[$realName]));
        $this->alias[$alias] = $realName;
        return $this;
    }

    /**
     * Scalars values are used when the constructor / function parameter has no class type hint and is a scalar value.
     * If a callable, it will be called only once then replaced with its return value.
     * @param string                         $name
     * @param int|string|float|bool|callable $valueOrCallable
     * @return self
     */
    public function addScalar(string $name, $valueOrCallable): self
    {
        // assert disabled for phpstan. in php 8 we can have better type declarations so it isn't needed
        // assert(is_scalar($valueOrCallable) || is_callable($valueOrCallable));
        $this->scalar[$name] = $valueOrCallable;
        return $this;
    }

    /**
     * if a class is complicated to build, use a factory method to build it and return the class instance.
     * Injections performed by inject() will still occur: no need to add these to your factory
     * @param string   $name
     * @param callable $callable must return a class instance
     * @return \LSS\YAContainer\Container
     */
    public function addFactory(string $name, callable $callable): self
    {
        $this->factory[$name] = $callable;
        return $this;
    }

    public function setShouldShare(Closure $shouldShare): self
    {
        $this->shouldShare = $shouldShare;
        return $this;
    }

    /**
     * @param string $name
     * @return mixed the built class
     * @throws ContainerException
     */
    private function makeClass(string $name)
    {
        try {
            $classInfo = new ReflectionClass($name);
            if (!$classInfo->isInstantiable()) {
                // is it abstract, an interface, has a private constructor etc
                throw new ContainerException($this->building, 'Class is not instantiable ' . $name);
            }

            $constructorInfo = $classInfo->getConstructor();
            if (empty($constructorInfo)) {
                // no constructor specified
                return new $name();
            }

            $arguments = $this->collectArguments($constructorInfo->getParameters());
            // It is faster to call it directly than to use reflection http://stackoverflow.com/a/24648651/117647
            return new $name(...$arguments);
        } catch (ReflectionException $exception) {
            throw new ContainerException(
                $this->building, 'Reflection Exception: Can not build ' . $name, 0,
                $exception
            );
        }
    }

    /**
     * resolve all the arguments for the method and return them
     * @param \ReflectionParameter[] $parameters
     * @return array
     * @throws ContainerException
     */
    private function collectArguments(array $parameters): array
    {
        $result = [];
        foreach ($parameters as $parameterInfo) {
            // resolve the argument to a value
            if ($parameterInfo->isOptional()) {
                // accept default values for all arguments because all subsequent arguments must also have a default value
                break;
            }
            $typeInfo = $parameterInfo->getType();
            if (empty($typeInfo) || $typeInfo->isBuiltin()) {
                $result[] = $this->getScalarValue($parameterInfo->getName());
                continue;
            }
            // union types will cause some problems here
            $result[] = $this->get($typeInfo->getName());
        }
        return $result;
    }

    /**
     * @param callable $function
     * @return object
     */
    private function runFactory(callable $function)
    {
        $functionInfo = new ReflectionFunction($function);
        $arguments    = $this->collectArguments($functionInfo->getParameters());
        return $function(...$arguments);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws ContainerException
     */
    private function getScalarValue(string $name)
    {
        if (!isset($this->scalar[$name])) {
            throw new ContainerException($this->building, 'Scalar value not found: ' . $name);
        }
        $value = $this->scalar[$name];
        if (is_callable(($value))) {
            $functionInfo        = new ReflectionFunction($value);
            $arguments           = $this->collectArguments($functionInfo->getParameters());
            $value               = $value(...$arguments);
            $this->scalar[$name] = $value;
        }
        return $value;
    }
}