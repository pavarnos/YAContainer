<?php
declare(strict_types=1);

namespace LSS\YAContainer;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use function interface_exists;
use function is_callable;
use function is_scalar;

class Container implements \Psr\Container\ContainerInterface
{
    /**
     * All classes are shared by default
     * @var array class name => class instance
     */
    private $shared = [];

    /**
     * eg MyFooInterface::class => MyFooImplementation::class
     * @var array alias name => real name
     */
    private $alias = [];

    /**
     * scalar / builtin parameter values
     * @var array name => value
     */
    private $scalar = [];

    /**
     * call the interface method if the class implements it
     * @var array interface name => method name or callable
     */
    private $inject = [];

    /**
     * a callable that can build this class
     * @var array class or interface name => callable
     */
    private $factory = [];

    /**
     * @var array a stack of class name => depth for error reporting and to prevent circular dependencies
     */
    private $building = [];

    /**
     * Container constructor.
     * @param array $scalar see addScalar()
     * @param array $alias  see addAlias()
     */
    public function __construct(array $scalar = [], array $alias = [])
    {
        $this->alias  = $alias;
        $this->scalar = $scalar;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $name Identifier of the entry to look for.
     *
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @return mixed Entry.
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
            throw new ContainerException($this->building,
                'Circular dependency while building ' . $name . ': ' . join(' - ', array_keys($this->building)));
        }
        $this->building[$name] = count($this->building);

        if (isset($this->factory[$name])) {
            $result = $this->runFactory($this->factory[$name]);
        }
        else {
            $result = $this->makeClass($name);
        }

        $this->shared[$name] = $result;
        $this->injectSetters($result);

        unset($this->building[$name]);
        return $result;
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
        return class_exists($name) || interface_exists($name) || isset($this->alias[$name]) || isset($this->scalar[$name]) || isset($this->factory[$name]);
    }

    /**
     * Alias must resolve to a real instantiable class in one step. An alias cannot refer to another alias.
     * Aliases only work for class names: they are not used for scalars. If you want to alias a scalar, add it again
     * with the aliased name
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
        assert(is_scalar($valueOrCallable) || is_callable($valueOrCallable));
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

    /**
     * after a class is built, it it implements $interfaceName, call $methodName.
     * Type hinted and scalar method parameters will be resolved before the call.
     * If a callable is used, the newly created object will be the first parameter
     * @param string            $interfaceName
     * @param string | callable $methodNameOrCallable
     * @return self
     */
    public function inject(string $interfaceName, $methodNameOrCallable): self
    {
        $this->inject[$interfaceName] = $methodNameOrCallable;
        return $this;
    }

    /**
     * @param string $name
     * @return mixed the built class
     * @throws \LSS\YAContainer\ContainerException
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

            $arguments = $this->collectArguments($constructorInfo);
            // It is faster to call it directly than to use reflection http://stackoverflow.com/a/24648651/117647
            return new $name(...$arguments);

        } catch (ReflectionException $exception) {
            throw new ContainerException($this->building, 'Reflection Exception: Can not build ' . $name, 0,
                $exception);
        }
    }

    /**
     * resolve all the arguments for the method and return them
     * @param \ReflectionFunctionAbstract $functionInfo
     * @return array
     * @throws \LSS\YAContainer\ContainerException
     */
    private function collectArguments(ReflectionFunctionAbstract $functionInfo): array
    {
        $result = [];
        foreach ($functionInfo->getParameters() as $parameterInfo) {
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
            $typeHint = $parameterInfo->getClass();
            $result[] = $this->get($typeHint->getName());
        }
        return $result;
    }

    /**
     * setter injection by interface to call method name or callable
     * @param object $result
     * @throws \LSS\YAContainer\ContainerException
     */
    private function injectSetters($result)
    {
        if (empty($this->inject)) {
            return;
        }

        // reflectionClass may be instantiated twice for each object, which is a bit ugly...
        $classInfo = new ReflectionClass($result);
        foreach ($this->inject as $interfaceName => $methodNameOrCallable) {
            if (!$classInfo->implementsInterface($interfaceName)) {
                continue;
            }
            if (is_string($methodNameOrCallable)) {
                $methodInfo = $classInfo->getMethod($methodNameOrCallable);
                $arguments  = $this->collectArguments($methodInfo);
                $result->$methodNameOrCallable(...$arguments);
                continue;
            }
            if (is_callable($methodNameOrCallable)) {
                $functionInfo = new ReflectionFunction($methodNameOrCallable);
                $arguments    = $this->collectArguments($functionInfo);
                $result       = $methodNameOrCallable(...$arguments);
                continue;
            }
            throw new ContainerException($this->building,
                'Expected interface method name or callable when injecting ' . $classInfo->getName());
        }
    }

    /**
     * @param callable $function
     * @return object
     */
    private function runFactory(callable $function)
    {
        $functionInfo = new ReflectionFunction($function);
        $arguments    = $this->collectArguments($functionInfo);
        return $function(...$arguments);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws \LSS\YAContainer\ContainerException
     */
    private function getScalarValue($name)
    {
        if (!isset($this->scalar[$name])) {
            throw new ContainerException($this->building, 'Scalar value not found: ' . $name);
        }
        $value = $this->scalar[$name];
        if (is_callable(($value))) {
            $functionInfo        = new ReflectionFunction($value);
            $arguments           = $this->collectArguments($functionInfo);
            $value               = $value(...$arguments);
            $this->scalar[$name] = $value;
        }
        return $value;
    }
}