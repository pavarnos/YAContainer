<?php

declare(strict_types=1);

namespace LSS\YAContainer;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;

use function interface_exists;

/**
 * Limitations:
 * - we have to use string for $id because a generic container can use any string as an index. Our container is only
 *   for classes, but the PSR interface is looser than that
 * - we have to return mixed for the same reason. Our container will usually only hold objects, but the generic PSR
 *   interface is wider and allows anything
 */
class Container implements ContainerInterface
{
    /**
     * All classes are shared by default
     * @var array<string,mixed> class name => class instance
     */
    private array $shared = [];

    /**
     * eg MyFooInterface::class => MyFooImplementation::class
     * @var array<class-string,class-string> alias name => real name
     */
    private array $alias = [];

    /**
     * scalar / builtin parameter values
     * @var array<string,int|string|float|bool|Closure> name => value
     */
    private array $scalar = [];

    /**
     * a callable that can build this class
     * @var array<class-string,Closure> class or interface name => callable
     */
    private array $factory = [];

    /**
     * @var array<string,int> a stack of class name => depth for error reporting and to prevent circular dependencies
     */
    private array $building = [];

    /**
     * @var callable(string): bool $shouldShare return true if get() should return a shared instance of a class,
     * false to built it fresh each time
     */
    private $shouldShare;

    /**
     * @param array<string,int|string|float|bool|Closure> $scalar see addScalar()
     * @param array<class-string,class-string>            $alias  see addAlias()
     */
    public function __construct(array $scalar = [], array $alias = [])
    {
        $this->alias       = $alias;
        $this->scalar      = $scalar;
        $this->shouldShare = fn(string $name): bool => true;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     * @return mixed
     * @throws ContainerException
     */
    public function get(string $id): mixed
    {
        if (isset($this->alias[$id])) {
            // Aliases must resolve directly to a concrete class. Recursive resolution does not work
            $id = $this->alias[$id];
        }
        if (isset($this->shared[$id])) {
            return $this->shared[$id];
        }

        // circular dependency check
        if (isset($this->building[$id])) {
            throw new ContainerException($this->building, 'Circular dependency while building ' . $id);
        }
        $this->building[$id] = count($this->building);

        try {
            if (isset($this->factory[$id])) {
                $result = $this->runFactory($this->factory[$id]);
            } else {
                $result = $this->makeClass($id);
            }
        } catch (ReflectionException $exception) {
            throw new ContainerException(
                $this->building, 'Reflection Exception: Can not build ' . $id, 0,
                $exception
            );
        }

        if (($this->shouldShare)($id)) {
            $this->shared[$id] = $result;
        }

        unset($this->building[$id]);
        return $result;
    }

    /**
     * inject a pre-built class into the container, or replace an existing value (eg for tests)
     * @param class-string $name
     * @param mixed        $classInstance
     */
    public function set(string $name, mixed $classInstance): void
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
     * @param string $id Identifier of the entry to look for.
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->shared[$id])
            || class_exists($id)
            || interface_exists($id)
            || isset($this->alias[$id])
            || isset($this->scalar[$id])
            || isset($this->factory[$id]);
    }

    /**
     * Delete any shared instances of $className.
     * get($className) will return a freshly constructed instance of $className next time you call it.
     * @param class-string $name
     * @return self
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
     * @param class-string $alias
     * @param class-string $realName
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
     * @param Closure|float|bool|int|string $valueOrCallable
     * @return self
     */
    public function addScalar(string $name, Closure|float|bool|int|string $valueOrCallable): self
    {
        // assert disabled for phpstan. in php 8 we can have better type declarations so it isn't needed
        // assert(is_scalar($valueOrCallable) || is_callable($valueOrCallable));
        $this->scalar[$name] = $valueOrCallable;
        return $this;
    }

    /**
     * if a class is complicated to build, use a factory method to build it and return the class instance.
     * Injections performed by inject() will still occur: no need to add these to your factory
     * @param class-string $name
     * @param Closure      $callable must return a class instance
     * @return self
     */
    public function addFactory(string $name, Closure $callable): self
    {
        $this->factory[$name] = $callable;
        return $this;
    }

    /**
     * @param callable(string): bool $shouldShare
     * @return $this
     */
    public function setShouldShare(callable $shouldShare): self
    {
        $this->shouldShare = $shouldShare;
        return $this;
    }

    /**
     * @param string $name
     * @return mixed the built class
     * @throws ReflectionException|ContainerException
     */
    private function makeClass(string $name): mixed
    {
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
    }

    /**
     * resolve all the arguments for the method and return them
     * @param \ReflectionParameter[] $parameters
     * @return array
     * @throws ContainerException|ReflectionException
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
            if (!($typeInfo instanceof \ReflectionNamedType)) {
                throw new ContainerException(
                    $this->building,
                    'Type is missing or a Union type for parameter ' . $parameterInfo->getName(
                    )
                );
            }
            if ($typeInfo->isBuiltin()) {
                // if it is a builtin / scalar type, see if there is a scalar matching that name
                $result[] = $this->getScalarValue($parameterInfo->getName());
                continue;
            }
            $result[] = $this->get($typeInfo->getName());
        }
        return $result;
    }

    /**
     * @param Closure $function
     * @return object
     * @throws ContainerException|ReflectionException
     */
    private function runFactory(Closure $function): object
    {
        $functionInfo = new ReflectionFunction($function);
        $arguments    = $this->collectArguments($functionInfo->getParameters());
        return $function(...$arguments);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws ContainerException|ReflectionException
     */
    private function getScalarValue(string $name): mixed
    {
        if (!isset($this->scalar[$name])) {
            throw new ContainerException($this->building, 'Scalar value not found: ' . $name);
        }
        $value = $this->scalar[$name];
        if ($value instanceof Closure) {
            $functionInfo        = new ReflectionFunction($value);
            $arguments           = $this->collectArguments($functionInfo->getParameters());
            $value               = $value(...$arguments);
            $this->scalar[$name] = $value;
        }
        return $value;
    }
}